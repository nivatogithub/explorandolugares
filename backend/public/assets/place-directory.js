(() => {
  'use strict';
  const form = document.querySelector('[data-place-filters]');
  if (!form) return;
  const search = form.elements.namedItem('q');
  const department = form.elements.namedItem('department');
  const status = document.querySelector('.place-directory-results');
  const key = 'place-directory:' + location.pathname;
  let timer;
  let composing = false;
  try {
    const saved = JSON.parse(sessionStorage.getItem(key));
    sessionStorage.removeItem(key);
    if(saved&&Number.isFinite(saved.scroll))window.addEventListener('load',()=>requestAnimationFrame(()=>scrollTo({top:saved.scroll,behavior:'instant'})),{once:true});
    const field = saved && form.elements.namedItem(saved.focus);
    if (field) {
      field.focus({preventScroll: true});
      if (field === search && Number.isInteger(saved.cursor)) field.setSelectionRange(saved.cursor, saved.cursor);
    }
  } catch (_) {}
  const navigate = async () => {
    clearTimeout(timer);
    if (composing) return;
    const url = new URL(form.action, location.href);
    for (const [name, raw] of new FormData(form)) {
      const value = String(raw).trim();
      if (value) url.searchParams.set(name, value);
    }
    const current = new URL(location.href);
    current.searchParams.sort();
    const target = new URL(url);
    target.searchParams.sort();
    if (current.href === target.href) return;
    if(url.searchParams.get('guardados')==='1'){
      try{
        const response=await fetch('/api/account-session.php',{credentials:'same-origin',cache:'no-store'});
        const state=response.ok?await response.json():null;
        if(!state?.authenticated){
          document.dispatchEvent(new CustomEvent('el:request-login',{detail:{
            trigger:form.elements.namedItem('guardados'),url:url.href,
            onSuccess:()=>navigate(),onCancel:()=>{form.elements.namedItem('guardados').checked=false;},
          }}));return;
        }
      }catch(_){form.elements.namedItem('guardados').checked=false;return;}
    }
    try { sessionStorage.setItem(key, JSON.stringify({focus: document.activeElement?.name, cursor: search.selectionStart,scroll:scrollY})); } catch (_) {}
    if (status) { status.setAttribute('aria-busy', 'true'); status.textContent = form.dataset.updating; }
    location.assign(url.href);
  };
  form.addEventListener('submit', event => { event.preventDefault(); navigate(); });
  form.addEventListener('change', event => {
    if (event.target === search) return;
    if (['region','sector'].includes(event.target.name)) department.value = '';
    navigate();
  });
  let savedRefresh=0;
  document.addEventListener('el:saved-place-changed',async event=>{
    if(event.detail?.saved!==false||!form.elements.namedItem('guardados')?.checked)return;
    const grid=document.querySelector('#place-directory-grid');
    const card=event.detail.form.closest('.destination-card');
    if(!grid||!card||!grid.contains(card))return;
    const index=Array.from(grid.children).indexOf(card);card.remove();
    const version=++savedRefresh;
    try{
      // The existing PHP catalogue owns cards, empty state, totals and pagination.
      const response=await fetch(location.href,{credentials:'same-origin',cache:'no-store'});
      if(!response.ok||new URL(response.url).origin!==location.origin)throw new Error('Catalogue unavailable');
      const page=new DOMParser().parseFromString(await response.text(),'text/html');
      const freshGrid=page.querySelector('#place-directory-grid');
      const freshStatus=page.querySelector('.place-directory-results');
      if(!freshGrid||!freshStatus)throw new Error('Catalogue not returned');
      if(version!==savedRefresh)return;
      grid.replaceChildren(...Array.from(freshGrid.childNodes));
      status.textContent=freshStatus.textContent;
      const pagination=document.querySelector('.place-directory-pagination');
      const freshPagination=page.querySelector('.place-directory-pagination');
      if(pagination){if(freshPagination)pagination.replaceWith(freshPagination);else pagination.remove();}
      else if(freshPagination)grid.after(freshPagination);
      const controls=grid.querySelectorAll('.saved-place-action');
      if(controls.length)controls[Math.min(index,controls.length-1)].focus({preventScroll:true});
      else {status.tabIndex=-1;status.focus({preventScroll:true});}
    }catch(_){if(version===savedRefresh)status.textContent=status.dataset.removedMessage;}
  });
  const schedule = () => { clearTimeout(timer); if (!composing) timer = setTimeout(navigate, 650); };
  search.addEventListener('input', schedule);
  search.addEventListener('compositionstart', () => { composing = true; clearTimeout(timer); });
  search.addEventListener('compositionend', () => { composing = false; schedule(); });
  form.querySelector('[data-clear-filters]').addEventListener('click', () => clearTimeout(timer));
  window.addEventListener('pagehide', () => clearTimeout(timer));
  window.addEventListener('pageshow', event => { if (event.persisted) { form.reset(); location.reload(); } });
})();

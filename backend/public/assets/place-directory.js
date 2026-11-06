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
  const schedule = () => { clearTimeout(timer); if (!composing) timer = setTimeout(navigate, 650); };
  search.addEventListener('input', schedule);
  search.addEventListener('compositionstart', () => { composing = true; clearTimeout(timer); });
  search.addEventListener('compositionend', () => { composing = false; schedule(); });
  form.querySelector('[data-clear-filters]').addEventListener('click', () => clearTimeout(timer));
  window.addEventListener('pagehide', () => clearTimeout(timer));
  window.addEventListener('pageshow', event => { if (event.persisted) { form.reset(); location.reload(); } });
})();

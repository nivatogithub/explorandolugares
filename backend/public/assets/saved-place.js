(() => {
  'use strict';
  // Progressive enhancement of a state-changing form. PHP owns initial markup and authoritative state.
  const applyState = (form, result) => {
    const button=form.querySelector('.saved-place-action');
    button.setAttribute('aria-pressed', String(result.saved));
    button.setAttribute('aria-label', result.label);button.title=result.label;
    button.querySelector('svg')?.setAttribute('fill',result.saved?'currentColor':'none');
    form.elements.namedItem('action').value=result.saved?'remove_place':'save_place';
    if(result.csrf){form.elements.namedItem('csrf').value=result.csrf;form.dataset.authenticated='1';}
  };
  const refreshState = async form => {
    const url=new URL(form.dataset.authUrl,location.href);url.searchParams.set('state','1');
    const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
    const result=await response.json();
    if(response.ok&&result.ok&&typeof result.saved==='boolean')applyState(form,result);
    else form.querySelector('[data-saved-place-feedback]').textContent=form.dataset.saveError;
  };
  const authenticate = async form => {
    try {
      const response=await fetch('/api/account-session.php',{credentials:'same-origin',cache:'no-store'});
      const account=response.ok?await response.json():null;
      if(account?.authenticated){
        await refreshState(form);
        if(form.dataset.authenticated==='1'&&form.elements.namedItem('action').value==='save_place')form.requestSubmit();
        return;
      }
    } catch (_) { /* The dialog owns connection feedback if the session check cannot complete. */ }
    document.dispatchEvent(new CustomEvent('el:request-login',{detail:{
      trigger:form.querySelector('.saved-place-action'),url:form.dataset.authUrl,
      onSuccess:async()=>{await refreshState(form);if(form.dataset.authenticated==='1'&&form.elements.namedItem('action').value==='save_place')form.requestSubmit();},
    }}));
  };
  document.addEventListener('el:authenticated',()=>{
    for(const form of document.querySelectorAll('.saved-place-control'))refreshState(form).catch(()=>{});
  });
  document.addEventListener('click',event=>{
    const link=event.target.closest('[data-saved-place-auth]');if(!link)return;
    event.preventDefault();authenticate(link.closest('.saved-place-control'));
  });
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('.saved-place-control')) return;
    event.preventDefault();
    if(form.dataset.authenticated==='0'){await authenticate(form);return;}
    const button = form.querySelector('.saved-place-action');
    const action = form.elements.namedItem('action');
    const feedback = form.querySelector('[data-saved-place-feedback]');
    const authLink = form.querySelector('[data-saved-place-auth]');
    if (!button || !action || button.disabled) return;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    if (feedback) feedback.textContent = '';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(form.getAttribute('action'), {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: {'Accept': 'application/json'}, body: new FormData(form), signal: controller.signal,
      });
      const result = await response.json();
      if (response.status === 401) {
        if (feedback) feedback.textContent = form.dataset.authRequired;
        if (authLink) authLink.hidden = false;
        return;
      }
      if (!response.ok || result.ok !== true || typeof result.saved !== 'boolean' || typeof result.label !== 'string') {
        throw new Error('Bookmark request was not confirmed');
      }
      if (authLink) authLink.hidden = true;
      applyState(form,result);
      document.dispatchEvent(new CustomEvent('el:saved-place-changed',{detail:{form,saved:result.saved}}));
    } catch (_) {
      if (feedback) feedback.textContent = form.dataset.saveError || 'No se pudo guardar. Inténtalo de nuevo.';
    } finally {
      clearTimeout(timeout);
      button.disabled = false;
      button.removeAttribute('aria-busy');
    }
  });
})();

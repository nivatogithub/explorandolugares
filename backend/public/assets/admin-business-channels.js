'use strict';
(() => {
  const root = document.querySelector('.crm-page');
  if (!root || typeof HTMLDialogElement === 'undefined') return;
  const originalOverflow = document.body.style.overflow;
  let dialog = null;
  let request = null;
  let sequence = 0;
  const scrollKey = 'el.crm.channel-dialog.scroll';

  function showDetail(section, opener) {
    if (dialog) dialog.close();
    const modal = document.createElement('dialog');
    modal.className = 'crm-channel-dialog';
    modal.setAttribute('aria-labelledby', 'crm-detail-title');
    const bar = document.createElement('div');
    bar.className = 'crm-channel-dialog-bar';
    const label = document.createElement('span');
    label.textContent = 'Canales y responsable';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'crm-dialog-close';
    close.textContent = 'Cerrar ✕';
    close.setAttribute('aria-label', 'Cerrar canales del negocio');
    close.autofocus = true;
    bar.append(label, close);
    modal.append(bar, section);
    root.append(modal);
    document.body.style.overflow = 'hidden';
    close.addEventListener('click', () => modal.close());
    modal.addEventListener('click', event => {
      if (event.target.closest('[data-close-channels]')) { event.preventDefault(); modal.close(); }
      if (event.target === modal) {
        const rect = modal.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) modal.close();
      }
    });
    modal.addEventListener('submit', () => {
      try { sessionStorage.setItem(scrollKey, JSON.stringify({y: window.scrollY, business: section.dataset.businessId})); } catch (_) { /* Storage is optional. */ }
    });
    modal.addEventListener('close', () => {
      modal.remove();
      if (dialog === modal) {
        document.body.style.overflow = originalOverflow;
        dialog = null;
        if (opener?.isConnected) opener.focus({preventScroll: true});
      }
    }, {once: true});
    modal.addEventListener('click', async event => {
      const copy = event.target.closest('[data-copy-channel]');
      if (!copy) return;
      try {
        await navigator.clipboard.writeText(copy.dataset.copyChannel);
        copy.textContent = 'Copiado';
      } catch (_) { copy.textContent = 'Selecciona el texto para copiar'; }
    });
    dialog = modal;
    modal.showModal();
  }

  document.addEventListener('click', async event => {
    const opener = event.target.closest('a[data-open-channels]');
    if (!opener || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    if (request) request.abort();
    request = new AbortController();
    const current = ++sequence;
    const originalText = opener.textContent;
    opener.textContent = 'Abriendo…';
    opener.setAttribute('aria-busy', 'true');
    let feedback = root.querySelector('[data-channel-load-status]');
    if (!feedback) { feedback = document.createElement('p'); feedback.dataset.channelLoadStatus = ''; feedback.setAttribute('role', 'status'); root.querySelector('.crm-result-heading')?.after(feedback); }
    feedback.textContent = '';
    try {
      const response = await fetch(opener.href, {credentials: 'same-origin', signal: request.signal});
      if (!response.ok) throw new Error(response.status === 403 ? 'La sesión expiró. Inicia sesión de nuevo.' : 'No se pudo abrir este negocio. Inténtalo de nuevo.');
      const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
      const section = parsed.querySelector('#contactos-del-negocio');
      if (!section) throw new Error('No se encontró el detalle del negocio. Recarga la página.');
      if (current === sequence) showDetail(section, opener);
    } catch (error) {
      if (error.name !== 'AbortError' && current === sequence) feedback.textContent = error.message || 'No se pudo abrir el detalle.';
    } finally {
      opener.textContent = originalText;
      opener.removeAttribute('aria-busy');
    }
  });

  const existing = root.querySelector('#contactos-del-negocio');
  if (existing) {
    const opener = root.querySelector(`[data-crm-business-id="${existing.dataset.businessId}"] [data-open-channels]`);
    try {
      const saved = JSON.parse(sessionStorage.getItem(scrollKey) || 'null');
      sessionStorage.removeItem(scrollKey);
      if (saved && saved.business === existing.dataset.businessId && Number.isFinite(saved.y)) window.scrollTo(0, saved.y);
    } catch (_) { /* The detail works without browser storage. */ }
    showDetail(existing, opener);
  }
})();

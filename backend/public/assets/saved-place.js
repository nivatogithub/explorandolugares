(() => {
  'use strict';
  // Progressive enhancement of a state-changing form. PHP owns initial markup and authoritative state.
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('.saved-place-control')) return;
    event.preventDefault();
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
      button.setAttribute('aria-pressed', String(result.saved));
      button.setAttribute('aria-label', result.label);
      button.title = result.label;
      button.querySelector('svg')?.setAttribute('fill', result.saved ? 'currentColor' : 'none');
      action.value = result.saved ? 'remove_place' : 'save_place';
    } catch (_) {
      if (feedback) feedback.textContent = form.dataset.saveError || 'No se pudo guardar. Inténtalo de nuevo.';
    } finally {
      clearTimeout(timeout);
      button.disabled = false;
      button.removeAttribute('aria-busy');
    }
  });
})();

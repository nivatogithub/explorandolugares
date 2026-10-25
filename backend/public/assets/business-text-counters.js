(() => {
  const count = value => Array.from(value || '').length;
  const updates = new WeakMap();
  const bind = field => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) return;
    // maxlength is a validation constraint, not a request to display a counter.
    // Only editorial fields explicitly marked by their template get one.
    if (field.dataset.textCounter === undefined) return;
    if (updates.has(field)) { updates.get(field)(); return; }
    if (field.maxLength < 1 || ['hidden', 'password', 'file', 'checkbox', 'radio'].includes(field.type)) return;
    const label = field.closest('label') || field.parentElement;
    if (!label) return;
    let counter = label.querySelector('[data-business-text-counter]');
    if (!counter) {
      counter = document.createElement('small');
      counter.dataset.businessTextCounter = '1';
      counter.className = 'business-text-counter';
      counter.setAttribute('aria-live', 'polite');
      label.append(counter);
    }
    const update = () => {
      const length = count(field.value);
      const hint = length > field.maxLength ? (field.dataset.textCounterLimitMessage || ' · Superas el límite; resume el texto o elige otro plan.')
        : length === field.maxLength ? ' · Límite alcanzado; resume el texto si necesitas añadir más.' : '';
      const text = `${length} / ${field.maxLength} caracteres${hint}`;
      if (counter.textContent !== text) counter.textContent = text;
      // The containing step/tab already hides its descendants. Do not freeze
      // a counter as hidden just because its field was initialized off-screen.
      if (counter.hidden !== field.hidden) counter.hidden = field.hidden;
      grow();
    };
    const grow = () => {
      if (field.dataset.autogrow === undefined || !(field instanceof HTMLTextAreaElement) || !field.getClientRects().length) return;
      field.style.height = 'auto';
      field.style.height = `${field.scrollHeight + field.offsetHeight - field.clientHeight}px`;
    };
    field.addEventListener('input', update);
    field.addEventListener('change', update);
    field.dataset.textCounterBound = '1';
    updates.set(field, update);
    update();
    if (field.dataset.autogrow !== undefined && typeof ResizeObserver !== 'undefined') {
      let lastWidth = -1;
      new ResizeObserver(entries => {
        const width = entries[0].contentRect.width;
        if (width !== lastWidth) { lastWidth = width; grow(); }
      }).observe(field);
    }
  };
  const bindAll = root => root.querySelectorAll?.('[data-text-counter][maxlength]').forEach(bind);
  const start = () => {
    bindAll(document);
    new MutationObserver(records => {
      for (const record of records) {
        if (record.type === 'childList') record.addedNodes.forEach(node => { if (node.nodeType === 1) { bind(node); bindAll(node); } });
        if (record.type === 'attributes' && record.target instanceof HTMLElement) bind(record.target);
      }
    }).observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['maxlength', 'hidden', 'disabled']});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();

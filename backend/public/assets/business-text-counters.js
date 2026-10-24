(() => {
  const count = value => Array.from(value || '').length;
  const updates = new WeakMap();
  const bind = field => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) return;
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
      const text = `${count(field.value)} / ${field.maxLength} caracteres`;
      if (counter.textContent !== text) counter.textContent = text;
      // The containing step/tab already hides its descendants. Do not freeze
      // a counter as hidden just because its field was initialized off-screen.
      if (counter.hidden !== field.hidden) counter.hidden = field.hidden;
    };
    field.addEventListener('input', update);
    field.addEventListener('change', update);
    field.dataset.textCounterBound = '1';
    updates.set(field, update);
    update();
  };
  const bindAll = root => root.querySelectorAll?.('input[maxlength],textarea[maxlength]').forEach(bind);
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

(() => {
  const count = value => Array.from(value || '').length;
  const bind = field => {
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) || field.dataset.textCounterBound === '1' || field.maxLength < 1 || ['hidden', 'password', 'file', 'checkbox', 'radio'].includes(field.type)) return;
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
      counter.textContent = `${count(field.value)} / ${field.maxLength} caracteres`;
      counter.hidden = field.hidden || field.closest('[hidden]') !== null;
    };
    field.addEventListener('input', update);
    field.addEventListener('change', update);
    field.dataset.textCounterBound = '1';
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

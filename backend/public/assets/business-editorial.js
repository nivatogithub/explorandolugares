(() => {
  'use strict';
  const editor = document.querySelector('form:has(input[name="action"][value="save"])');
  if (!editor) return;
  editor.querySelectorAll('input[maxlength],textarea[maxlength]').forEach(field => {
    if (['hidden', 'password', 'file', 'checkbox', 'radio'].includes(field.type)) return;
    const label = field.closest('label') || field.parentElement;
    if (!label) return;
    const counter = document.createElement('small');
    counter.className = 'business-text-counter';
    counter.setAttribute('aria-live', 'polite');
    const update = () => { counter.textContent = `${Array.from(field.value).length} / ${field.maxLength} caracteres`; };
    field.addEventListener('input', update); label.append(counter); update();
  });
  const markDirty = () => {
    document.querySelectorAll('form.editorial-actions button').forEach(button => { button.disabled = true; });
    const notice = document.querySelector('.editorial-save-notice');
    if (notice) notice.hidden = false;
  };
  editor.addEventListener('input', markDirty);
  editor.addEventListener('change', markDirty);
})();

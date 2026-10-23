(() => {
  'use strict';
  const editor = document.querySelector('form:has(input[name="action"][value="save"])');
  if (!editor) return;
  const showcase = Array.from(editor.querySelectorAll('fieldset')).find(fieldset => fieldset.querySelector('legend')?.textContent.includes('Productos o servicios estrella'));
  if (showcase) {
    showcase.querySelector('p').textContent = 'Elige hasta tres productos o servicios estrella. Cada uno lleva una foto y una descripción corta.';
    Array.from(showcase.querySelectorAll(':scope > details')).slice(3).forEach(slot => slot.remove());
    showcase.querySelectorAll('textarea[maxlength="400"]').forEach(field => { field.maxLength = 180; field.rows = 2; });
  }
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

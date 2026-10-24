(() => {
  'use strict';
  const editor = document.querySelector('form:has(input[name="action"][value="save"])');
  const ownerApproval = document.querySelector('button[name="action"][value="approve"]');
  if (ownerApproval) {
    ownerApproval.textContent = 'Aprobar y publicar mi negocio';
    const revision = document.querySelector('#revision');
    if (revision) revision.textContent = 'Comprueba tu tarjeta y página en las vistas previas. Al aprobarla, autorizas la publicación de esta versión exacta.';
  }
  document.querySelector('button[name="action"][value="publish"]')?.remove();
  if (!editor) return;
  const showcase = Array.from(editor.querySelectorAll('fieldset')).find(fieldset => fieldset.querySelector('legend')?.textContent.includes('Productos o servicios estrella'));
  if (showcase) {
    showcase.querySelector('p').textContent = 'Elige hasta cinco productos o servicios estrella. Cada uno lleva una foto y una descripción corta.';
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

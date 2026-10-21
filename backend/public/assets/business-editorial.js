(() => {
  'use strict';
  const editor = document.querySelector('form:has(input[name="action"][value="save"])');
  if (!editor) return;
  const markDirty = () => {
    document.querySelectorAll('form.editorial-actions button').forEach(button => { button.disabled = true; });
    const notice = document.querySelector('.editorial-save-notice');
    if (notice) notice.hidden = false;
  };
  editor.addEventListener('input', markDirty);
  editor.addEventListener('change', markDirty);
})();

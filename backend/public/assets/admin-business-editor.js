(() => {
  'use strict';
  const form = document.querySelector('[data-admin-business-editor]');
  if (!form) return;
  const images = form.querySelector('[data-product-image-panels]');
  const syncProducts = () => {
    images?.querySelectorAll(':scope > [data-admin-product-image]').forEach(panel => {
      const product = form.querySelector('[data-product-editor] [data-product-slot="' + panel.dataset.adminProductImage + '"]');
      const configured = product?.dataset.configured === '1' ? '1' : '0';
      if (panel.dataset.configured === '1' && configured === '0') {
        panel.querySelectorAll('select').forEach(select => { select.selectedIndex = 0; });
        panel.querySelectorAll('input[type="file"]').forEach(input => { input.value = ''; });
        panel.querySelectorAll('img').forEach(image => { image.hidden = true; image.removeAttribute('src'); });
      }
      panel.dataset.configured = configured;
    });
    images?.dispatchEvent(new Event('editor-panels-refresh'));
  };
  form.addEventListener('change', event => {
    syncProducts();
    const select = event.target.closest('[data-admin-prepared-image]');
    if (select) {
      const preview = select.closest('label').nextElementSibling;
      const path = select.selectedOptions[0]?.dataset.imagePath;
      preview.hidden = !path;
      if (path) preview.src = path; else preview.removeAttribute('src');
    }
  });
  form.addEventListener('click', event => {
    const clear = event.target.closest('[data-admin-clear-source]');
    if (!clear) return;
    const field = clear.closest('[data-admin-source]');
    field.querySelector('select').value = '';
    field.querySelector('input[type="file"]').value = '';
    const preview = field.querySelector('[data-admin-source-preview]');
    if (preview) preview.hidden = true;
    field.dispatchEvent(new Event('change', {bubbles: true}));
  });
  syncProducts();
})();

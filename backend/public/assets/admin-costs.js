(() => {
  'use strict';
  document.querySelectorAll('[data-cost-file]').forEach(input => {
    const output = input.closest('.cost-upload-control')?.querySelector('[data-cost-filename]');
    if (!output) return;
    const update = () => { output.textContent = input.files?.[0]?.name || 'Ningún archivo seleccionado'; };
    input.addEventListener('change', update);
    window.addEventListener('pageshow', update);
    update();
  });
})();

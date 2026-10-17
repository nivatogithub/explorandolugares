(() => {
  'use strict';
  const input = document.getElementById('db-package-file');
  const filename = document.getElementById('db-package-name');
  if (!input || !filename) return;
  const update = () => {
    filename.textContent = input.files?.[0]?.name || 'Ningún archivo seleccionado';
  };
  input.addEventListener('change', update);
  window.addEventListener('pageshow', update);
  update();
})();

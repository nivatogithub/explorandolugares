/* A Spanish, accessible label for the native file input. Upload remains server-side. */
(() => {
  const form = document.querySelector('[data-contact-upload]');
  if (!form) return;
  const input = form.querySelector('input[type="file"]');
  const name = document.getElementById('crm-file-name');
  const error = form.querySelector('[data-file-error]');
  const submit = form.querySelector('button[type="submit"]');
  if (!input || !name || !error || !submit) return;
  const unavailable = input.disabled;
  const validate = () => {
    const file = input.files?.[0];
    const message = file && !/\.json$/i.test(file.name)
      ? 'Selecciona un archivo de canales con extensión .json.'
      : file && file.size > 2 * 1024 * 1024
        ? 'El archivo supera los 2 MB. Selecciona un archivo más pequeño.'
        : file && file.size < 2 ? 'El archivo está vacío. Selecciona el JSON de contactos.' : '';
    name.textContent = file ? file.name : 'Ningún archivo seleccionado';
    error.textContent = message;
    error.hidden = !message;
    input.setCustomValidity(message);
    submit.disabled = unavailable || !file || Boolean(message);
  };
  input.addEventListener('change', validate);
  form.addEventListener('reset', () => setTimeout(validate, 0));
  validate();
})();

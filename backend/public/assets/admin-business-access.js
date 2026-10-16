document.addEventListener('DOMContentLoaded', function () {
  var mode = document.querySelector('[data-business-public-mode]');
  var confirmation = document.querySelector('[data-public-confirmation]');
  if (mode && confirmation) {
    var sync = function () {
      var opening = mode.value === '1';
      confirmation.hidden = !opening;
      var input = confirmation.querySelector('input');
      if (input) input.required = opening;
    };
    mode.addEventListener('change', sync);
    sync();
  }
  document.querySelectorAll('[data-copy-business-key]').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = document.getElementById(button.getAttribute('data-copy-business-key'));
      var status = document.getElementById('business-key-copy-status');
      if (!field || !navigator.clipboard) {
        if (status) status.textContent = 'Copia el contenido seleccionando el campo.';
        if (field) { field.focus(); field.select(); }
        return;
      }
      navigator.clipboard.writeText(field.value).then(function () {
        if (status) status.textContent = button.getAttribute('data-copy-label') || (field.id === 'generated-business-test-url' ? 'Enlace de prueba copiado.' : 'Llave copiada. Guárdala en un lugar privado.');
      }).catch(function () {
        if (status) status.textContent = 'No se pudo copiar automáticamente; selecciona el contenido y cópialo.';
        field.focus(); field.select();
      });
    });
  });
});

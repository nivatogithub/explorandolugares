'use strict';
document.querySelectorAll('[data-prepare-campaign]').forEach(form => {
  let running = false;
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (running || !form.reportValidity()) return;
    running = true;
    const button = form.querySelector('button');
    const status = form.querySelector('[data-prepare-status]');
    button.disabled = true;
    try {
      for (let step = 0; step < 305; step++) {
        const response = await fetch('campanas.php', {method: 'POST', body: new FormData(form), headers: {Accept: 'application/json'}, credentials: 'same-origin'});
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'No se pudo continuar. Revisa la campaña.');
        status.textContent = `Preparando en Brevo: ${result.cursor} de ${result.total} correos. No se está enviando la campaña.`;
        if (result.status === 'prepared') { location.assign(`campanas.php?id=${encodeURIComponent(form.elements.namedItem('id').value)}`); return; }
      }
      throw new Error('La preparación no terminó. Revisa el estado antes de continuar.');
    } catch (error) {
      status.textContent = error.message || 'La conexión se interrumpió. Recarga para comprobar el estado antes de continuar.';
      button.disabled = false;
      running = false;
    }
  });
});

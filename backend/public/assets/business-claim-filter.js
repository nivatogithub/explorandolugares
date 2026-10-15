(() => {
  const form = document.querySelector('.business-claim-filters');
  const list = document.getElementById('business-claim-results');
  if (!form || !list) return;

  form.querySelectorAll('[data-submit-on-change]').forEach((control) => {
    control.addEventListener('change', () => form.requestSubmit());
  });

  const nameField = form.querySelector('input[name="nombre"]');
  const count = document.getElementById('business-claim-result-count');
  const empty = document.getElementById('business-claim-empty');
  const submit = document.getElementById('business-claim-submit');
  let timer;
  let controller;

  nameField?.addEventListener('input', () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(async () => {
      const parameters = new URLSearchParams(new FormData(form));
      parameters.set('search', '1');
      controller?.abort();
      controller = new AbortController();
      try {
        const response = await fetch(`${window.location.pathname}?${parameters}`, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          signal: controller.signal,
        });
        if (!response.ok) throw new Error('No se pudo actualizar la búsqueda.');
        const payload = await response.json();
        const selected = list.querySelector('input[name="business_id"]:checked')?.value;
        list.replaceChildren();
        for (const item of payload.results) {
          const label = document.createElement('label');
          label.className = 'business-claim-result';
          const radio = document.createElement('input');
          radio.type = 'radio';
          radio.name = 'business_id';
          radio.value = String(item.id);
          radio.required = true;
          radio.setAttribute('form', 'business-claim-form');
          if (radio.value === selected) radio.checked = true;
          const details = document.createElement('span');
          const title = document.createElement('strong');
          title.textContent = item.display_name;
          const status = document.createElement('small');
          status.textContent = item.information_status === 'VERIFICADO'
            ? 'Información verificada'
            : 'Información pública por confirmar';
          if (item.address_text) status.textContent += ` · ${item.address_text}`;
          details.append(title, status);
          label.append(radio, details);
          list.append(label);
        }
        if (count) count.textContent = `(${payload.results.length} encontrados, máximo 500)`;
        if (empty) empty.hidden = payload.results.length > 0;
        if (submit) submit.disabled = payload.results.length === 0;
      } catch (error) {
        if (error.name !== 'AbortError' && empty) {
          empty.textContent = 'No se pudo actualizar la búsqueda. Inténtalo otra vez.';
          empty.hidden = false;
        }
      }
    }, 250);
  });
})();

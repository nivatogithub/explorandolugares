(() => {
  const form = document.getElementById('business-registration');
  if (!form) return;
  const english = document.documentElement.lang === 'en';
  const t = value => english ? (window.EL_EN_MESSAGES[value] || value) : value;
  if (english) { form.action += '?lang=en'; form.elements.lang.value = 'en'; }
  const fields = document.getElementById('registration-fields');
  const availability = document.getElementById('registration-availability');
  const result = document.getElementById('registration-result');
  const button = document.getElementById('registration-submit');
  let ready = false;
  button.disabled = true;
  async function load() {
    try {
      const response = await fetch(form.action, {credentials: 'same-origin', cache: 'no-store'});
      if (!response.ok) throw new Error('unavailable');
      const data = await response.json();
      if (!data.enabled) { availability.textContent = t(data.message); return; }
      form.elements.csrf.value = data.csrf;
      form.elements.request_key.value = data.request_key;
      ready = true;
      button.disabled = false;
      availability.textContent = t("Puedes enviar tu solicitud. Solo se guardará cuando confirmes el envío.");
      const summary = document.getElementById('privacy-summary');
      summary.textContent = english ? `Data controller: ${data.privacy.operator}. Data enquiries: ${data.privacy.contact}. Retention: ${data.privacy.retention}. Purpose: to contact you about your business and manage your enquiry. Not automatically published or used to subscribe you to advertising.` : `Responsable: ${data.privacy.operator}. Consultas sobre datos: ${data.privacy.contact}. Conservación: ${data.privacy.retention}. Finalidad: contactarte sobre tu negocio y gestionar tu solicitud. No se publica automáticamente ni se usa para suscribirte a publicidad.`;
    } catch {
      availability.textContent = t("Puedes explorar las opciones y completar el formulario. El envío aún no está disponible; no se guardará información.");
    }
  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!ready || fields.disabled || button.disabled) return;
    form.querySelectorAll('[data-error]').forEach(node => { node.textContent = ''; });
    form.querySelectorAll('[aria-invalid]').forEach(node => node.removeAttribute('aria-invalid'));
    if (!form.elements.email.value.trim() && !form.elements.phone_number.value.trim()) {
      result.textContent = t("Indica al menos un correo o teléfono para identificar tu solicitud.");
      form.elements.email.focus(); return;
    }
    if (!form.elements.consent.checked) {
      result.textContent = t("Necesitamos tu autorización para guardar la solicitud.");
      form.elements.consent.focus(); return;
    }
    if (!form.elements.understanding.checked) {
      result.textContent = t("Confirma que has leído y entendido cómo participar y el alcance de esta etapa.");
      form.elements.understanding.focus(); return;
    }
    if (!form.reportValidity()) return;
    button.disabled = true;
    result.textContent = t("Guardando tu solicitud…");
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), credentials: 'same-origin'});
      const data = await response.json();
      result.textContent = t(data.message) || t("No pudimos confirmar el envío.");
      if (data.errors) {
        let first;
        for (const [key, message] of Object.entries(data.errors)) {
          const node = form.querySelector(`[data-error="${key}"]`);
          if (node) node.textContent = message;
          const field = form.elements.namedItem(key === 'phone' ? 'phone_number' : key);
          if (field) { field.setAttribute('aria-invalid', 'true'); first ||= field; }
        }
        if (first) first.focus();
      } else result.focus();
      if (response.ok) { ready = false; fields.disabled = true; availability.textContent = t("Recibimos tus datos de contacto. Gracias por contarnos sobre tu negocio."); }
    } catch {
      result.textContent = t("No pudimos confirmar si se guardó. Conserva tus datos y vuelve a intentar; el mismo envío no se duplicará.");
      result.focus();
    } finally { button.disabled = !ready; }
  });
  load();
})();

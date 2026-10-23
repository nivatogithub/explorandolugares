(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#business-registration-form');
    if (!form) return;

    const details = document.querySelector('#registration-new-details');
    const preview = document.querySelector('#registration-live-preview');
    const continuation = document.querySelector('#registration-continuation');
    const planStage = document.querySelector('#registration-plan-choice');
    const submit = document.querySelector('#registration-submit');
    const type = document.querySelector('#registration-application-type');
    const csrf = form.querySelector('[name="csrf"]')?.value || '';
    const plan = () => form.querySelector('[name="plan_code"]:checked')?.value || '';

    document.querySelectorAll('.subscription-choice-description').forEach((copy) => {
      copy.textContent = copy.textContent.replace('hasta cuatro productos o servicios', 'hasta cinco productos o servicios');
    });
    if (continuation?.querySelector('legend')) continuation.querySelector('legend').textContent = 'Información para enviar la solicitud';
    if (submit) submit.textContent = 'Guardar solicitud';

    const setPlanField = (selector, visible, required = false) => {
      form.querySelectorAll(selector).forEach((node) => {
        node.hidden = !visible;
        node.querySelectorAll('input, textarea, select').forEach((control) => {
          control.disabled = !visible;
          if (required && control.type !== 'file') control.required = visible;
        });
      });
    };

    const fieldText = (selector) => form.querySelector(selector)?.value.trim() || '';
    const renderPreview = () => {
      if (!preview) return;
      const selected = form.querySelector('.business-registration-option input:checked');
      const title = fieldText('#registration-new-name') || selected?.closest('label')?.innerText.split('\n')[0] || 'Tu negocio';
      preview.querySelector('[data-preview-name]').textContent = title;
      preview.querySelector('[data-preview-summary]').textContent = fieldText('[data-preview-field="summary"]') || 'Tu presentación aparecerá aquí.';
      const description = preview.querySelector('[data-preview-description]');
      const longText = fieldText('[data-preview-field="description"]');
      description.textContent = longText;
      description.hidden = !longText;
      preview.querySelector('[data-preview-address]').textContent = fieldText('[data-preview-field="address"]');
    };

    const syncPlan = () => {
      const choice = plan();
      const editorial = ['featured', 'page', 'page_premium'].includes(choice);
      const premium = choice === 'page_premium';
      if (submit) submit.textContent = choice && choice !== 'free'
        ? 'Guardar y continuar al pago'
        : 'Guardar solicitud para revisión';
      setPlanField('[data-plan-field="summary"]', editorial, editorial);
      setPlanField('[data-plan-field="description"]', premium, premium);
      setPlanField('[data-plan-field="location"]', ['featured', 'page_premium'].includes(choice), choice === 'featured');
      setPlanField('[data-plan-field="contact-options"]', editorial);
      setPlanField('[data-plan-field="phone"]', editorial);
      setPlanField('[data-plan-field="cover"]', ['page', 'page_premium'].includes(choice), ['page', 'page_premium'].includes(choice));
      setPlanField('[data-plan-field="products"]', premium, false);
      const phone = form.querySelector('[name="business_phone[number]"]');
      if (phone) phone.required = choice === 'featured';
      const extendedContacts = ['email', 'website', 'instagram', 'facebook', 'linkedin'];
      extendedContacts.forEach((channel) => {
        const control = form.querySelector(`[name="channels[${channel}]"]`);
        const visible = channel === 'email' ? ['page', 'page_premium'].includes(choice) : choice === 'page_premium';
        if (control) { control.closest('label').hidden = !visible; control.disabled = !visible; }
      });
      if (preview) preview.hidden = !editorial || !!details?.hidden;
      renderPreview();
    };

    const syncFreeIdentifier = () => {
      const requiresIdentifier = ['free', 'featured'].includes(plan());
      const nit = form.querySelector('[name="business_nit"]');
      const rnt = form.querySelector('[name="business_rnt"]');
      const provided = Boolean(nit?.value.trim() || rnt?.value.trim());
      [nit, rnt].forEach((field) => field?.setCustomValidity(requiresIdentifier && !provided ? 'Para Gratis o Destacado indica el NIT o el RNT del negocio.' : ''));
      form.querySelectorAll('[data-free-identifier-note]').forEach((node) => { node.textContent = requiresIdentifier ? '(NIT o RNT obligatorio)' : '(opcional)'; });
      const help = form.querySelector('[data-free-identifier-help]');
      if (help) help.textContent = requiresIdentifier ? 'Solo números. Para Gratis o Destacado indica NIT o RNT.' : 'Solo números. Si no aplica, déjalo vacío.';
    };

    const uploadFile = async (input, kind, slot) => {
      const file = input.files?.[0];
      if (!file) return;
      const holder = input.closest('label') || input.parentElement;
      let status = holder.querySelector('[data-upload-status]');
      if (!status) {
        status = document.createElement('small');
        status.dataset.uploadStatus = '1';
        holder.append(status);
      }
      status.textContent = 'Subiendo archivo…';
      const data = new FormData();
      data.append('csrf', csrf);
      data.append('action', 'upload');
      data.append('kind', kind);
      data.append('slot', slot);
      data.append('file', file);
      try {
        const response = await fetch('/business/onboarding-upload.php', {method: 'POST', body: data, credentials: 'same-origin'});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo subir el archivo.');
        input.disabled = true;
        form.querySelector(`[data-temp-upload="${kind}-${slot}"]`)?.remove();
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = `temporary_uploads[${kind}][${slot}]`;
        hidden.value = result.upload.id;
        hidden.dataset.tempUpload = `${kind}-${slot}`;
        form.append(hidden);
        status.textContent = `Archivo listo: ${result.upload.name} `;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'text-link';
        remove.textContent = 'Quitar';
        remove.addEventListener('click', async () => {
          const removal = new FormData();
          removal.append('csrf', csrf);
          removal.append('action', 'delete');
          removal.append('upload_id', result.upload.id);
          await fetch('/business/onboarding-upload.php', {method: 'POST', body: removal, credentials: 'same-origin'});
          hidden.remove();
          remove.remove();
          input.disabled = false;
          input.value = '';
          status.textContent = 'Archivo retirado.';
        });
        status.append(remove);
        if (kind === 'editorial') {
          const image = document.createElement('img');
          image.src = `/business/onboarding-upload-preview.php?id=${encodeURIComponent(result.upload.id)}`;
          image.alt = 'Vista previa privada';
          image.width = 160;
          image.height = 100;
          image.loading = 'lazy';
          holder.append(image);
        }
      } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'No se pudo subir el archivo.';
      }
    };

    form.querySelectorAll('[data-image-slot]').forEach((input) => input.addEventListener('change', () => uploadFile(input, 'editorial', input.dataset.imageSlot || 'cover')));
    form.querySelectorAll('[data-product-card]').forEach((card, index) => {
      const panel = document.createElement('details');
      panel.className = 'registration-optional product-panel';
      panel.open = index === 0;
      const title = document.createElement('summary');
      title.textContent = `Producto o servicio ${index + 1}`;
      card.before(panel);
      panel.append(title, card);
    });

    const evidence = form.querySelector('[name="business_registration"]');
    if (evidence) evidence.addEventListener('change', () => uploadFile(evidence, 'evidence', 'business_registration'));

    const finder = Array.from(form.querySelectorAll(':scope > fieldset')).find((fieldset) => fieldset.querySelector('legend')?.textContent.trim() === '1. Busca el negocio');
    const stepTwo = [details, document.querySelector('#registration-contact-details'), document.querySelector('#registration-distinct-business'), continuation].filter(Boolean);
    let step = 1;

    const renderStep = () => {
      if (!finder || !planStage || !continuation) return;
      const first = step === 1;
      const planStep = step === 2;
      const selected = !!type?.value;
      const selectedPlan = plan();
      const showDetails = step === 3;
      form.dataset.registrationWizardStep = String(step);
      form.dataset.registrationPremiumDetails = '0';
      finder.hidden = !first;
      finder.disabled = !first;
      planStage.hidden = !planStep || !selected;
      planStage.disabled = first || !selected;
      controls.hidden = !(first || (planStep && selectedPlan));
      back.hidden = first;
      stepTwo.forEach((node) => { node.hidden = !showDetails; node.disabled = !showDetails; });
      if (showDetails) form.dispatchEvent(new Event('operator-eligibility-change'));
      if (step === 2) {
        const planLabel = {free: 'Gratis', featured: 'Destacado', page: 'Página Comercial', page_premium: 'Premium'}[selectedPlan];
        next.textContent = planLabel ? `Continuar con ${planLabel}` : 'Elige un plan para continuar';
      }
      syncPlan();
      window.scrollTo({top: form.getBoundingClientRect().top + window.scrollY - 16, behavior: 'smooth'});
    };

    let controls;
    let back;
    let next;
    if (finder && planStage && continuation) {
      controls = document.createElement('nav');
      controls.className = 'editorial-actions registration-wizard-actions';
      const warning = document.createElement('p');
      warning.className = 'notice';
      warning.hidden = true;
      next = document.createElement('button');
      next.type = 'button';
      next.className = 'pill';
      next.textContent = 'Continuar a planes';
      back = document.createElement('button');
      back.type = 'button';
      back.className = 'pill light';
      back.textContent = '← Volver a búsqueda';
      planStage.after(controls);
      controls.append(warning, next);
      continuation.before(back);
      next.addEventListener('click', () => {
        if (step === 1 && !type?.value) {
          warning.textContent = 'Primero busca el negocio y elige si lo vas a reclamar o crear.';
          warning.hidden = false;
          return;
        }
        if (step === 2 && !plan()) {
          warning.textContent = 'Elige una suscripción antes de continuar.';
          warning.hidden = false;
          return;
        }
        step = step === 2 ? 3 : 2;
        renderStep();
      });
      back.addEventListener('click', () => {
        step = step === 3 ? 2 : 1;
        renderStep();
      });
      form.addEventListener('operator-eligibility-change', () => { if (step === 1) renderStep(); });
      form.addEventListener('change', (event) => { if (step === 2 && event.target.name === 'plan_code') { syncFreeIdentifier(); renderStep(); } });
    }

    form.addEventListener('change', (event) => {
      if (event.target.name === 'plan_code') { syncPlan(); syncFreeIdentifier(); }
      renderPreview();
    });
    form.addEventListener('input', (event) => { renderPreview(); if (event.target.name === 'business_nit' || event.target.name === 'business_rnt') syncFreeIdentifier(); });
    form.addEventListener('submit', () => {
      if (!submit || submit.disabled) return;
      submit.disabled = true;
      submit.textContent = 'Procesando solicitud…';
    });
    syncPlan();
    syncFreeIdentifier();
    if (controls) renderStep();
  });
})();

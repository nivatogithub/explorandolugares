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
      copy.textContent = copy.textContent.replace('hasta cuatro productos o servicios', 'hasta tres productos o servicios');
    });
    if (continuation?.querySelector('legend')) continuation.querySelector('legend').textContent = '2. Relación, soportes y envío';
    if (submit) submit.textContent = 'Enviar para revisión';

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
      setPlanField('[data-plan-field="summary"]', editorial, editorial);
      setPlanField('[data-plan-field="description"]', premium, premium);
      setPlanField('[data-plan-field="cover"]', ['page', 'page_premium'].includes(choice), false);
      setPlanField('[data-plan-field="products"]', premium, false);
      if (preview) preview.hidden = !editorial || !!details?.hidden;
      renderPreview();
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
    const evidence = form.querySelector('[name="business_registration"]');
    if (evidence) evidence.addEventListener('change', () => uploadFile(evidence, 'evidence', 'business_registration'));

    const finder = Array.from(form.querySelectorAll(':scope > fieldset')).find((fieldset) => fieldset.querySelector('legend')?.textContent.trim() === '1. Busca el negocio');
    const residence = form.querySelector('.account-residence');
    if (residence && continuation) continuation.prepend(residence);
    const stepTwo = [details, document.querySelector('#registration-distinct-business'), continuation].filter(Boolean);
    let step = 1;

    const renderStep = () => {
      if (!finder || !planStage || !continuation) return;
      const first = step === 1;
      const selected = !!type?.value;
      form.dataset.registrationWizardStep = String(step);
      finder.hidden = !first;
      planStage.hidden = !first || !selected;
      controls.hidden = !first;
      back.hidden = first;
      stepTwo.forEach((node) => { node.hidden = first; });
      if (!first) form.dispatchEvent(new Event('operator-eligibility-change'));
      syncPlan();
      window.scrollTo({top: form.getBoundingClientRect().top + window.scrollY - 16, behavior: 'smooth'});
    };

    let controls;
    let back;
    if (finder && planStage && continuation) {
      controls = document.createElement('nav');
      controls.className = 'editorial-actions registration-wizard-actions';
      const warning = document.createElement('p');
      warning.className = 'notice';
      warning.hidden = true;
      const next = document.createElement('button');
      next.type = 'button';
      next.className = 'pill';
      next.textContent = 'Continuar con la información';
      back = document.createElement('button');
      back.type = 'button';
      back.className = 'pill light';
      back.textContent = '← Volver a búsqueda y plan';
      planStage.after(controls);
      controls.append(warning, next);
      continuation.before(back);
      next.addEventListener('click', () => {
        if (!type?.value || !plan()) {
          warning.textContent = 'Primero selecciona el negocio y la suscripción.';
          warning.hidden = false;
          return;
        }
        step = 2;
        renderStep();
      });
      back.addEventListener('click', () => {
        step = 1;
        renderStep();
      });
      form.addEventListener('operator-eligibility-change', () => { if (step === 1) renderStep(); });
      form.addEventListener('change', (event) => { if (step === 1 && event.target.name === 'plan_code') renderStep(); });
    }

    form.addEventListener('change', (event) => {
      if (event.target.name === 'plan_code') syncPlan();
      renderPreview();
    });
    form.addEventListener('input', renderPreview);
    form.addEventListener('submit', () => {
      if (!submit || submit.disabled) return;
      submit.disabled = true;
      submit.textContent = 'Procesando solicitud…';
    });
    syncPlan();
    if (controls) renderStep();
  });
})();

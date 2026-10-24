(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#business-registration-form');
    if (!form) return;

    const details = document.querySelector('#registration-new-details');
    const preview = document.querySelector('[data-registration-preview]');
    const continuation = document.querySelector('#registration-continuation');
    const planStage = document.querySelector('#registration-plan-choice');
    const submit = document.querySelector('#registration-submit');
    const type = document.querySelector('#registration-application-type');
    const csrf = form.querySelector('[name="csrf"]')?.value || '';
    const plan = () => form.querySelector('[name="plan_code"]:checked')?.value || '';
    const serverError = document.querySelector('.account-registration-card [role="alert"]');
    const recoveryKey = 'explorando-lugares:business-registration-recovery';
    const excludedFromRecovery = new Set(['csrf', 'display_name', 'email', 'phone', 'password', 'password_confirm', 'business_registration', 'identity_document', 'authority_document', 'reference_images[]']);
    const recoverable = () => Array.from(form.elements).filter((field) => field.name && !excludedFromRecovery.has(field.name) && field.type !== 'file' && field.type !== 'password');
    const restoreAfterValidationError = () => {
      if (!serverError) { sessionStorage.removeItem(recoveryKey); return; }
      try {
        const saved = JSON.parse(sessionStorage.getItem(recoveryKey) || '{}');
        for (const field of recoverable()) {
          const value = saved[field.name];
          if (value === undefined) continue;
          if (field.type === 'checkbox' || field.type === 'radio') field.checked = Array.isArray(value) ? value.includes(field.value) : value === field.value;
          else field.value = String(value);
        }
      } catch { /* A malformed browser draft must never block the form. */ }
    };
    restoreAfterValidationError();
    form.addEventListener('submit', () => {
      const values = {};
      for (const field of recoverable()) {
        if (field.type === 'checkbox' || field.type === 'radio') {
          if (!field.checked) continue;
          (values[field.name] ||= []).push(field.value);
        } else values[field.name] = field.value;
      }
      try { sessionStorage.setItem(recoveryKey, JSON.stringify(values)); } catch { /* Storage can be unavailable in private browsing. */ }
    });
    // The applicant should not have to guess what an administrator needs.
    // Review requests carry a specific reason back to the owner instead.
    form.querySelector('[name="message"]')?.closest('label')?.remove();

    // La cuenta se crea o inicia antes de este wizard. En la solicitud solo
    // mostramos la identidad que se asociará; no se puede editar aquí.
    const identityFields = ['display_name', 'email'].map((name) => form.querySelector(`[name="${name}"]`)).filter(Boolean);
    if (identityFields.length === 2) {
      const identity = document.createElement('section');
      identity.className = 'registration-account-identity wide';
      const title = document.createElement('strong');
      title.textContent = 'Cuenta que se asociará al negocio';
      const values = document.createElement('p');
      values.textContent = `${identityFields[0].value} · ${identityFields[1].value}`;
      identity.append(title, values);
      identityFields[0].closest('label')?.before(identity);
      identityFields.forEach((field) => {
        field.required = false;
        field.disabled = true;
        field.closest('label')?.remove();
      });
    }
    // El teléfono personal pertenece al perfil de la cuenta, no al soporte de
    // una solicitud. Los canales públicos se capturan en su propio paso.
    form.querySelector('#registration-continuation .contact-phone-fields')?.remove();

    document.querySelectorAll('.subscription-choice-description').forEach((copy) => {
      copy.textContent = copy.textContent.replace('hasta cuatro productos o servicios', 'hasta cinco productos o servicios');
    });
    if (continuation?.querySelector('legend')) continuation.querySelector('legend').textContent = 'Información personal y autorización para solicitar la publicación';
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

    const renderPreview = () => {
      if (!preview) return;
      const choice = plan();
      const link = preview.querySelector('[data-subscription-example]');
      preview.hidden = !['free', 'featured', 'page', 'page_premium'].includes(choice);
      if (!link) return;
      link.href = `/business/demo.php?view=${encodeURIComponent(choice)}`;
      link.textContent = ['page', 'page_premium'].includes(choice) ? 'Ver tarjeta y página de ejemplo' : 'Ver tarjeta de ejemplo';
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
      setPlanField('[data-plan-field="location"]', ['featured', 'page', 'page_premium'].includes(choice), choice !== 'free');
      // Los canales son un paso independiente del wizard. Su visibilidad y
      // habilitación dependen exclusivamente del paso 4, administrado por
      // business-registration.js; no los abras al escoger una suscripción.
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
      if (help) help.textContent = requiresIdentifier ? 'Puedes copiarlo tal como aparece en el documento. Para Gratis o Destacado indica NIT o RNT.' : 'Si no aplica, déjalo vacío.';
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
    const detailsStep = [details, document.querySelector('#registration-distinct-business')].filter(Boolean);
    const contacts = document.querySelector('#registration-contact-details');
    const contactStep = [contacts].filter(Boolean);
    const submissionStep = [continuation].filter(Boolean);
    let step = type?.value && plan() && serverError ? 3 : 1;

    const renderStep = () => {
      if (!finder || !planStage || !continuation) return;
      const first = step === 1;
      const planStep = step === 2;
      const selected = !!type?.value;
      const selectedPlan = plan();
      const showDetails = step === 3;
      const showContacts = step === 4;
      const showSubmission = step === 5;
      form.dataset.registrationWizardStep = String(step);
      form.dataset.registrationPremiumDetails = '0';
      finder.hidden = !first;
      finder.disabled = false;
      planStage.hidden = !planStep || !selected;
      planStage.disabled = !selected;
      controls.hidden = !(first || (planStep && selectedPlan) || showDetails || showContacts);
      next.hidden = showSubmission;
      back.hidden = first;
      detailsStep.forEach((node) => { node.hidden = !showDetails; });
      contactStep.forEach((node) => { node.hidden = !showContacts; });
      submissionStep.forEach((node) => { node.hidden = !showSubmission; node.disabled = !showSubmission; });
      tabs?.querySelectorAll('[data-wizard-step]').forEach((tab) => {
        const tabStep = Number(tab.dataset.wizardStep);
        tab.disabled = tabStep > step;
        tab.setAttribute('aria-current', tabStep === step ? 'step' : 'false');
        tab.classList.toggle('is-current', tabStep === step);
        tab.classList.toggle('is-complete', tabStep < step);
      });
      if (showDetails || showContacts || showSubmission) form.dispatchEvent(new Event('operator-eligibility-change'));
      if (step === 2) {
        const planLabel = {free: 'Gratis', featured: 'Destacado', page: 'Página Comercial', page_premium: 'Premium'}[selectedPlan];
        next.textContent = planLabel ? `Continuar con ${planLabel}` : 'Elige un plan para continuar';
      } else if (showDetails) {
        next.textContent = 'Continuar a canales de contacto';
      } else if (showContacts) {
        next.textContent = 'Continuar para enviar solicitud';
      }
      syncPlan();
      window.scrollTo({top: form.getBoundingClientRect().top + window.scrollY - 16, behavior: 'smooth'});
    };

    let controls;
    let back;
    let next;
    let tabs;
    if (finder && planStage && continuation) {
      tabs = document.createElement('nav');
      tabs.className = 'registration-wizard-tabs';
      tabs.setAttribute('aria-label', 'Progreso del registro del negocio');
      ['Negocio', 'Suscripción', 'Información', 'Canales', 'Autorización'].forEach((label, index) => {
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'registration-wizard-tab';
        tab.dataset.wizardStep = String(index + 1);
        tab.textContent = `${index + 1}. ${label}`;
        tab.addEventListener('click', () => {
          const target = Number(tab.dataset.wizardStep);
          if (target > step) return;
          step = target;
          renderStep();
        });
        tabs.append(tab);
      });
      finder.before(tabs);
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
      contacts.after(controls);
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
        step = step === 1 ? 2 : step === 2 ? 3 : step === 3 ? 4 : 5;
        renderStep();
      });
      back.addEventListener('click', () => {
        step = step === 5 ? 4 : step === 4 ? 3 : step === 3 ? 2 : 1;
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

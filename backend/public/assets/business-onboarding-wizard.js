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
    const planRules = JSON.parse(details?.dataset.planRules || '{}');
    const rules = () => planRules[plan()] || {};
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
    const setPlanField = (selector, visible, required = false) => {
      form.querySelectorAll(selector).forEach((node) => {
        node.hidden = !visible;
        node.querySelectorAll('input, textarea, select').forEach((control) => {
          const uploaded = control.type === 'file' && control.dataset.imageSlot && form.querySelector(`[data-temp-upload="editorial-${control.dataset.imageSlot}"]`);
          control.disabled = !visible || !!uploaded;
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
      const selectedRules = rules();
      const editorial = selectedRules.description_limit > 0;
      const premium = selectedRules.products > 0;
      if (submit) submit.textContent = choice && choice !== 'free'
        ? 'Guardar y continuar al pago'
        : 'Guardar solicitud para revisión';
      setPlanField('[data-plan-field="summary"]', editorial, editorial);
      const summary = form.querySelector('[name="summary"]');
      const limit = selectedRules.description_limit || 0;
      if (summary) {
        // Never truncate a Premium description when selecting a lower plan.
        // Keep it editable and require the person to shorten it before moving on.
        if (limit > 0 && summary.maxLength !== limit) summary.maxLength = limit;
        summary.rows = choice === 'page_premium' ? 7 : 4;
        summary.setCustomValidity(editorial && Array.from(summary.value).length > limit ? `La descripción supera los ${limit} caracteres de este plan. Acórtala o elige un plan con más espacio.` : '');
      }
      const descriptionLabel = form.querySelector('[data-description-label]');
      if (descriptionLabel) descriptionLabel.textContent = `Descripción del negocio · máximo ${limit} caracteres`;
      setPlanField('[data-plan-field="location"]', !!selectedRules.location);
      const address=form.querySelector('[name="address_text"]');if(address)address.required=!!selectedRules.location;
      // Canales y productos tienen pasos propios; renderStep controla sus
      // contenedores sin reactivar productos todavía no configurados.
      setPlanField('[data-plan-field="cover"]', !!selectedRules.cover, !!selectedRules.cover);
      const products = form.querySelector('#registration-product-details');
      if (products) products.disabled = !premium;
      const phone = form.querySelector('[name="business_phone[number]"]');
      if (phone) phone.required = !!selectedRules.location;
      const extendedContacts = ['email', 'website', 'instagram', 'facebook', 'linkedin'];
      extendedContacts.forEach((channel) => {
        const control = form.querySelector(`[name="channels[${channel}]"]`);
        const visible = selectedRules.channels?.includes(channel) || false;
        if (control) { control.closest('label').hidden = !visible; control.disabled = !visible; }
      });
      renderPreview();
    };

    const syncFreeIdentifier = () => {
      const requiresIdentifier = !!rules().identifier_required;
      const nit = form.querySelector('[name="business_nit"]');
      const rnt = form.querySelector('[name="business_rnt"]');
      const provided = Boolean(nit?.value.trim() || rnt?.value.trim());
      [nit, rnt].forEach((field) => field?.setCustomValidity(requiresIdentifier && !provided ? 'Indica el NIT o el RNT del negocio, según el documento de soporte.' : ''));
      form.querySelectorAll('[data-free-identifier-note]').forEach((node) => { node.textContent = requiresIdentifier ? '(NIT o RNT obligatorio)' : '(opcional)'; });
      const help = form.querySelector('[data-free-identifier-help]');
      if (help) help.textContent = 'Indica NIT o RNT según el documento del negocio. No necesitas ambos.';
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
    const detailsStep = [details, document.querySelector('#registration-distinct-business')].filter(Boolean);
    const contacts = document.querySelector('#registration-contact-details');
    const products = document.querySelector('#registration-product-details');
    const productStep = [products].filter(Boolean);
    const contactStep = [contacts].filter(Boolean);
    const submissionStep = [continuation].filter(Boolean);
    const activeSteps = () => [1, 2, 3, ...(rules().products ? [4] : []), ...(rules().channels?.length ? [5] : []), 6];
    let step = type?.value && plan() && serverError ? 3 : 1;

    const renderStep = () => {
      if (!finder || !planStage || !continuation) return;
      const first = step === 1;
      const planStep = step === 2;
      const selected = !!type?.value;
      const selectedPlan = plan();
      const showDetails = step === 3;
      const showProducts = step === 4 && selectedPlan === 'page_premium';
      const showContacts = step === 5;
      const showSubmission = step === 6;
      const period = form.querySelector('.subscription-choice-period');
      if(period){period.hidden = !selectedPlan || selectedPlan === 'free';period.querySelector('select').disabled = !selectedPlan || selectedPlan === 'free';}
      const distinct=document.querySelector('#registration-distinct-business');if(distinct){distinct.hidden=!(showDetails&&selectedPlan==='free'&&type.value==='new_business');distinct.disabled=selectedPlan!=='free'||type.value!=='new_business';}
      const applicableContacts=!!rules().channels?.length;
      if(contacts)contacts.disabled=!applicableContacts;
      if(details)details.disabled=!selectedPlan||!type.value;
      if(submit)submit.disabled=!showSubmission||!selectedPlan||!type.value;
      form.dataset.registrationWizardStep = String(step);
      form.dataset.registrationPremiumDetails = '0';
      finder.hidden = !first;
      finder.disabled = false;
      planStage.hidden = !planStep || !selected;
      planStage.disabled = !selected;
      controls.hidden = showSubmission;
      next.hidden = showSubmission;
      back.hidden = first;
      if(details)details.hidden=!showDetails;
      if(products)products.hidden=!showProducts;
      contactStep.forEach((node) => { node.hidden = !showContacts; });
      submissionStep.forEach((node) => { node.hidden = !showSubmission; node.disabled = !showSubmission; });
      tabs?.querySelectorAll('[data-wizard-step]').forEach((tab) => {
        const tabStep = Number(tab.dataset.wizardStep);
        const position = activeSteps().indexOf(tabStep);
        tab.hidden = position < 0;
        tab.textContent = `${position + 1}. ${tab.dataset.stepLabel}`;
        tab.disabled = tabStep > step;
        tab.setAttribute('aria-current', tabStep === step ? 'step' : 'false');
        tab.classList.toggle('is-current', tabStep === step);
        tab.classList.toggle('is-complete', tabStep < step);
      });

      if (step === 2) {
        const planLabel = {free: 'Gratis', featured: 'Destacado', page: 'Página Comercial', page_premium: 'Premium'}[selectedPlan];
        next.textContent = planLabel ? `Continuar con ${planLabel}` : 'Elige un plan para continuar';
      } else if (showDetails) {
        next.textContent = selectedPlan==='free'?'Continuar a autorización':selectedPlan==='page_premium'?'Continuar a productos':'Continuar a canales de contacto';
      } else if (showProducts) {
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
      ['Negocio', 'Suscripción', 'Información', 'Productos', 'Canales', 'Autorización'].forEach((label, index) => {
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'registration-wizard-tab';
        tab.dataset.wizardStep = String(index + 1);
        tab.dataset.stepLabel = label;
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
        const currentPanels=step===1?[finder]:step===2?[planStage]:step===3?detailsStep:step===4?productStep:contactStep;
        const invalid=currentPanels.flatMap(panel=>Array.from(panel.querySelectorAll('input,select,textarea'))).find(field=>field.willValidate&&!field.checkValidity());
        if(invalid){invalid.reportValidity();return;}
        warning.hidden=true;
        const steps = activeSteps();
        step = steps[steps.indexOf(step) + 1];
        renderStep();
      });
      back.addEventListener('click', () => {
        const steps = activeSteps();
        step = steps[Math.max(0, steps.indexOf(step) - 1)];
        renderStep();
      });
      form.addEventListener('operator-eligibility-change', () => { renderStep(); });
      form.addEventListener('change', (event) => { if (step === 2 && event.target.name === 'plan_code') { syncFreeIdentifier(); renderStep(); } });
      form.addEventListener('invalid', event=>{
        const field=event.target;
        const target=finder.contains(field)?1:planStage.contains(field)?2:detailsStep.some(panel=>panel.contains(field))?3:productStep.some(panel=>panel.contains(field))?4:contactStep.some(panel=>panel.contains(field))?5:6;
        if(step!==target){step=target;renderStep();}
      },true);
    }

    form.addEventListener('change', (event) => {
      if (event.target.name === 'plan_code') { syncPlan(); syncFreeIdentifier(); }
      renderPreview();
    });
    form.addEventListener('input', (event) => { renderPreview(); if(event.target.name==='summary')syncPlan(); if (event.target.name === 'business_nit' || event.target.name === 'business_rnt') syncFreeIdentifier(); });
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

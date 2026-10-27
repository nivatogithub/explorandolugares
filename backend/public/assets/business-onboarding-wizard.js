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
    const draft = form.querySelector('[data-registration-draft]');
    const draftStatus = form.querySelector('[data-draft-status]');
    const revision = form.querySelector('[name="draft_revision"]');
    const stepField = form.querySelector('[name="wizard_step"]');
    let pendingUploads = 0, saving = Promise.resolve(true), leaving = false;
    // Capture lives privately in the account, never in browser storage.
    try { sessionStorage.removeItem('explorando-lugares:business-registration-recovery'); } catch {}
    const restored = JSON.parse(draft?.dataset.restoredValues || '{}');
    const fieldValue = (name) => {
      const keys = name.replace(/\]/g, '').split('[').filter(Boolean);
      return keys.reduce((value, key) => value && typeof value === 'object' ? value[key] : undefined, restored);
    };
    for (const field of form.elements) {
      if (!field.name || field.type === 'file' || ['csrf','registration_draft','draft_revision','wizard_step'].includes(field.name)) continue;
      const value = fieldValue(field.name);
      if (value === undefined) continue;
      if (field.type === 'radio' || field.type === 'checkbox') field.checked = Array.isArray(value) ? value.includes(field.value) : String(value) === field.value;
      else if (typeof value !== 'object') field.value = String(value);
    }
    const saveDraft = (target) => {
      saving = saving.catch(() => false).then(async () => {
        if (!draft?.value || !type?.value) return false;
        if (pendingUploads) { draftStatus.textContent = 'Espera a que terminen de subir los archivos antes de continuar.'; return false; }
        const data = new FormData();
        for (const field of form.elements) {
          if (!field.name || ['file','password','submit','button'].includes(field.type) || ['csrf','draft_action','wizard_step','draft_revision'].includes(field.name)) continue;
          if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) continue;
          data.append(field.name, field.value);
        }
        data.set('csrf', csrf); data.set('draft_action','save'); data.set('wizard_step',String(target)); data.set('draft_revision',revision.value);
        draftStatus.textContent = 'Guardando avance en tu cuenta…';
        try {
          const response = await fetch(location.href, {method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});
          const result = await response.json();
          if (response.status === 401) { location.assign(result.login_url || '/negocios/acceso/'); return false; }
          if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo guardar el avance.');
          revision.value = String(result.revision); stepField.value = String(target);
          const url = new URL(location.href); url.searchParams.set('draft',result.id); history.replaceState(null,'',url);
          draftStatus.textContent = 'Avance guardado. Puedes continuar después desde Mis negocios.';
          return true;
        } catch (error) { draftStatus.textContent = error.message || 'No se pudo guardar. No cierres esta página; vuelve a intentarlo.'; return false; }
      });
      return saving;
    };
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

    const showUpload = (input, kind, slot, upload) => {
      const holder=input.closest('label')||input.parentElement;
      holder.querySelectorAll('[data-upload-status],[data-upload-preview]').forEach(node=>node.remove());
      const status=document.createElement('small');status.dataset.uploadStatus='1';status.textContent='Archivo listo: '+upload.name+' ';
      const remove=document.createElement('button');remove.type='button';remove.className='business-upload-remove';remove.textContent='×';
      remove.setAttribute('aria-label',kind==='editorial'?'Quitar foto':'Retirar soporte');remove.title=kind==='editorial'?'Quitar foto':'Retirar soporte';
      input.disabled=true;input.dataset.uploadReady='1';
      remove.addEventListener('click',async(event)=>{
        event.preventDefault();event.stopPropagation();
        remove.disabled=true;pendingUploads++;const data=new FormData();data.set('csrf',csrf);data.set('action','delete');data.set('upload_id',upload.id);data.set('registration_draft',draft.value);
        try{const response=await fetch('/business/onboarding-upload.php',{method:'POST',body:data,headers:{Accept:'application/json'}});const result=await response.json();if(response.status===401){location.assign(result.login_url||'/negocios/acceso/');return;}if(!response.ok)throw new Error(result.error||'No se pudo retirar el archivo.');
          form.querySelector('[data-temp-upload="'+kind+'-'+slot+'"]')?.remove();input.disabled=false;delete input.dataset.uploadReady;input.value='';status.remove();holder.querySelector('[data-upload-preview]')?.remove();input.dispatchEvent(new Event('change',{bubbles:true}));
        }catch(error){status.textContent=error.message+' ';remove.disabled=false;if(kind!=='editorial')status.append(remove);}
        finally{pendingUploads--;}
        await saveDraft(step);
      });
      holder.append(status);
      if(kind==='editorial'){
        const preview=document.createElement('span');preview.className='business-image-preview';preview.dataset.uploadPreview='1';
        const image=document.createElement('img');image.dataset.uploadThumbnail='1';image.src='/business/onboarding-upload-preview.php?id='+encodeURIComponent(upload.id)+'&draft='+encodeURIComponent(draft.value);image.alt='Fotografía privada guardada';
        preview.append(image,remove);holder.append(preview);
      }else status.append(remove);
    };
    for(const upload of JSON.parse(draft?.dataset.savedUploads||'[]')){
      const input=upload.upload_kind==='evidence'?form.querySelector('[name="business_registration"]'):form.querySelector('[data-image-slot="'+upload.slot_code+'"]');
      if(input)showUpload(input,upload.upload_kind,upload.slot_code,{id:upload.id,name:upload.original_name});
    }
    const uploadFile = async (input, kind, slot) => {
      const file = input.files?.[0];
      if (!file) return;
      input.setCustomValidity('');
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
      data.append('registration_draft',draft.value);
      pendingUploads++;
      input.disabled=true;
      const removeProduct=input.closest('[data-product-slot]')?.querySelector('[data-remove-product]');
      if(removeProduct)removeProduct.disabled=true;
      try {
        const response = await fetch('/business/onboarding-upload.php', {method: 'POST', body: data, credentials: 'same-origin',headers:{Accept:'application/json'}});
        const result = await response.json();
        if(response.status===401){location.assign(result.login_url||'/negocios/acceso/');return;}
        if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo subir el archivo.');
        input.disabled = true;
        form.querySelector(`[data-temp-upload="${kind}-${slot}"]`)?.remove();
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = `temporary_uploads[${kind}][${slot}]`;
        hidden.value = result.upload.id;
        hidden.dataset.tempUpload = `${kind}-${slot}`;
        form.append(hidden);
        showUpload(input,kind,slot,result.upload);
      } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'No se pudo subir el archivo.';
        input.disabled=false;
        input.setCustomValidity('No se pudo guardar el archivo. Vuelve a seleccionarlo antes de continuar.');
      } finally { pendingUploads--;if(removeProduct)removeProduct.disabled=false; }
      if(input.dataset.uploadReady)await saveDraft(step);
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
    let step = type?.value ? Number(draft?.dataset.resumeStep||1) : 1;
    if(![1,2,3,4,5,6].includes(step)||!activeSteps().includes(step))step=3;

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
        tab.addEventListener('click', async () => {
          const target = Number(tab.dataset.wizardStep);
          if (target > step) return;
          if(!await saveDraft(target))return;
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
      next.addEventListener('click', async () => {
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
        const target=steps[steps.indexOf(step)+1];
        next.disabled=true;const saved=await saveDraft(target);next.disabled=false;if(!saved)return;
        step = target;
        renderStep();
      });
      back.addEventListener('click', async () => {
        const steps = activeSteps();
        const target=steps[Math.max(0,steps.indexOf(step)-1)];
        if(!await saveDraft(target))return;step=target;
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
    form.addEventListener('submit', async (event) => {
      event.preventDefault();if(leaving)return;
      if (!submit || submit.disabled) return;
      if(pendingUploads){draftStatus.textContent='Espera a que terminen de subir los archivos.';return;}
      submit.disabled = true;
      submit.textContent = 'Procesando solicitud…';
      if(!await saveDraft(6)){submit.disabled=false;syncPlan();return;}
      leaving=true;form.submit();
    });
    syncPlan();
    syncFreeIdentifier();
    if (controls) renderStep();
  });
})();

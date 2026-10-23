<?php if(!(isset($service))){http_response_code(404);exit;} ?>
<link rel="stylesheet" href="/assets/business-registration-final-step.css?v=1">
<fieldset id="registration-new-details" hidden disabled>
  <legend>Información de la ficha</legend>
  <p>Completa únicamente los espacios que se verán en la tarjeta o página elegida. La información se envía una vez para revisión.</p>
  <div class="destination-form-grid">
    <label>Identificador tributario del negocio · NIT <span data-free-identifier-note>(opcional)</span>
      <input name="business_nit" inputmode="numeric" maxlength="25" value="<?=esc($_POST['business_nit']??'')?>">
      <small>Sirve para identificar el establecimiento; no es un documento personal.</small>
    </label>
    <label>Registro Nacional de Turismo · RNT <span data-free-identifier-note>(si aplica)</span>
      <input name="business_rnt" inputmode="numeric" maxlength="12" value="<?=esc($_POST['business_rnt']??'')?>">
      <small data-free-identifier-help>Solo números. Para Gratis debes indicar NIT o RNT.</small>
    </label>
    <label class="wide" data-plan-field="cover" hidden>Foto de portada
      <input type="file" name="reference_images[]" accept="image/jpeg,image/png,image/webp" data-image-slot="cover">
      <small>Una foto JPG, PNG o WebP, máximo 8 MB. Queda privada mientras se revisa.</small>
    </label>
    <label class="wide" data-plan-field="summary">Presentación breve · máximo 450 caracteres
      <textarea name="summary" maxlength="450" rows="4" data-preview-field="summary"><?=esc($_POST['summary']??'')?></textarea>
      <small>Un párrafo corto, claro y útil para quien visita el lugar.</small>
    </label>
    <label class="wide" data-plan-field="description" hidden>Descripción ampliada · máximo 1.200 caracteres
      <textarea name="description" maxlength="1200" rows="7" data-preview-field="description"><?=esc($_POST['description']??'')?></textarea>
      <small>Disponible solo para Premium. Procura no superar diez líneas de lectura.</small>
    </label>
    <label class="wide" data-plan-field="location">Dirección o referencia para llegar
      <input id="business-address" name="address_text" maxlength="300" value="<?=esc($_POST['address_text']??'')?>" data-preview-field="address">
    </label>
    <label class="wide" data-plan-field="location">Referencia adicional (opcional)
      <input id="business-location-reference" name="location_reference" maxlength="500" value="<?=esc($_POST['location_reference']??'')?>">
    </label>
    <div class="wide business-map-actions" data-plan-field="location"><button id="business-map-open" type="button" class="pill light">Elegir punto en el mapa</button><p id="location-capture-status" role="status">Elige el punto exacto. El enlace público abrirá Google Maps.</p></div>
    <?php foreach(['latitude','longitude','map_url','location_confirmed','location_changed'] as $field):$name=['map_url'=>'location_map_url','location_changed'=>'location_map_changed'][$field]??$field;?><input type="hidden" id="business-<?=str_replace('_','-',$field)?>" name="<?=esc($name)?>" value="<?=esc($_POST[$name]??'')?>"><?php endforeach ?>
  </div>
  <section data-plan-field="products" hidden aria-labelledby="products-title">
    <h3 id="products-title">Hasta cinco productos o servicios</h3>
    <p>Cada uno tiene título, descripción corta y una sola foto. No es una galería.</p>
    <?php for($i=0;$i<5;$i++):?><div class="destination-form-grid product-card" data-product-card>
      <label>Nombre<input name="products[<?=$i?>][title]" maxlength="100" data-preview-product-title></label>
      <label>Descripción corta · máximo 180 caracteres<textarea name="products[<?=$i?>][summary]" maxlength="180" rows="2" data-preview-product-summary></textarea></label>
      <label>Foto del producto<input type="file" name="reference_images[]" accept="image/jpeg,image/png,image/webp" data-image-slot="product-<?=$i?>"></label>
    </div><?php endfor ?>
  </section>
  <p class="registration-preview-action" data-registration-preview hidden><a class="pill light subscription-example" data-subscription-example href="/business/demo.php?view=free">Ver vista previa de este plan</a></p>
</fieldset>
<fieldset id="registration-contact-details" data-plan-field="contact-options" hidden disabled>
  <legend>Canales de contacto</legend>
  <p>Incluye solo los canales que quieres mostrar a las personas que visiten tu negocio.</p>
  <div class="destination-form-grid"><?php $phoneValues=$_POST['channels']??[];require __DIR__.'/phone-fields.php';foreach(['email'=>'Correo del negocio','website'=>'Sitio web','instagram'=>'Instagram','facebook'=>'Facebook','linkedin'=>'LinkedIn'] as $channel=>$label):?><label><?=esc($label)?><input name="channels[<?=esc($channel)?>]" type="<?= $channel==='email'?'email':'url' ?>" maxlength="500" value="<?=esc($_POST['channels'][$channel]??'')?>"></label><?php endforeach ?></div>
</fieldset>
<script src="/assets/business-text-counters.js?v=1" defer></script>
<script type="application/json" data-legacy-inline-script>
document.addEventListener('DOMContentLoaded',()=>{
  const form=document.querySelector('#business-registration-form');if(!form)return;
  document.querySelectorAll('.subscription-choice-description').forEach(copy=>{copy.textContent=copy.textContent.replace('hasta cuatro productos o servicios','hasta tres productos o servicios');});
  const details=document.querySelector('#registration-new-details');const preview=document.querySelector('#registration-live-preview');
  const continuation=document.querySelector('#registration-continuation');if(continuation?.querySelector('legend'))continuation.querySelector('legend').textContent='2. Relación, soportes y envío';
  const submitButton=document.querySelector('#registration-submit');if(submitButton)submitButton.textContent='Enviar para revisión';
  const plan=()=>form.querySelector('[name="plan_code"]:checked')?.value||'';
  const set=(selector,visible,required=false)=>{for(const node of form.querySelectorAll(selector)){node.hidden=!visible;for(const control of node.querySelectorAll('input,textarea,select')){control.disabled=!visible;if(required&&control.type!=='file')control.required=visible;}}};
  const sync=()=>{
    const value=plan(), editorial=['featured','page','page_premium'].includes(value), premium=value==='page_premium';
    set('[data-plan-field="summary"]',editorial,editorial);
    set('[data-plan-field="description"]',premium,premium);
    set('[data-plan-field="cover"]',['page','page_premium'].includes(value),['page','page_premium'].includes(value));
    set('[data-plan-field="products"]',premium,false);
    if(preview)preview.hidden=!editorial||details.hidden;
    render();
  };
  const text=(selector)=>form.querySelector(selector)?.value.trim()||'';
  const render=()=>{if(!preview)return;const name=text('#registration-new-name')||form.querySelector('.business-registration-option input:checked')?.closest('label')?.innerText.split('\n')[0]||'Tu negocio';preview.querySelector('[data-preview-name]').textContent=name;preview.querySelector('[data-preview-summary]').textContent=text('[data-preview-field="summary"]')||'Tu presentación aparecerá aquí.';const long=preview.querySelector('[data-preview-description]'),description=text('[data-preview-field="description"]');long.textContent=description;long.hidden=!description;preview.querySelector('[data-preview-address]').textContent=text('[data-preview-field="address"]');};
  form.addEventListener('change',event=>{if(event.target.name==='plan_code')sync();render();});form.addEventListener('input',render);sync();
  form.addEventListener('submit',()=>{const button=document.querySelector('#registration-submit');if(button&&!button.disabled){button.disabled=true;button.dataset.originalText=button.textContent;button.textContent='Procesando solicitud…';}});
  const csrf=form.querySelector('[name="csrf"]')?.value||'';
  const uploadFile=async(input,kind,slot)=>{const file=input.files?.[0];if(!file)return;const holder=input.closest('label')||input.parentElement;let status=holder.querySelector('[data-upload-status]');if(!status){status=document.createElement('small');status.dataset.uploadStatus='1';holder.append(status);}status.textContent='Subiendo archivo…';const data=new FormData();data.append('csrf',csrf);data.append('action','upload');data.append('kind',kind);data.append('slot',slot);data.append('file',file);try{const response=await fetch('/business/onboarding-upload.php',{method:'POST',body:data,credentials:'same-origin'});const result=await response.json();if(!response.ok||!result.ok)throw new Error(result.error||'No se pudo subir el archivo.');input.disabled=true;const previous=form.querySelector(`[data-temp-upload="${kind}-${slot}"]`);if(previous)previous.remove();const hidden=document.createElement('input');hidden.type='hidden';hidden.name=`temporary_uploads[${kind}][${slot}]`;hidden.value=result.upload.id;hidden.dataset.tempUpload=`${kind}-${slot}`;form.append(hidden);status.textContent=`Archivo listo: ${result.upload.name} `;const remove=document.createElement('button');remove.type='button';remove.className='text-link';remove.textContent='Quitar';remove.addEventListener('click',async()=>{const removeData=new FormData();removeData.append('csrf',csrf);removeData.append('action','delete');removeData.append('upload_id',result.upload.id);await fetch('/business/onboarding-upload.php',{method:'POST',body:removeData,credentials:'same-origin'});hidden.remove();remove.remove();input.disabled=false;input.value='';status.textContent='Archivo retirado.';});status.append(remove);if(kind==='editorial'){const preview=document.createElement('img');preview.src=`/business/onboarding-upload-preview.php?id=${encodeURIComponent(result.upload.id)}`;preview.alt='Vista previa privada';preview.width=160;preview.height=100;preview.loading='lazy';holder.append(preview);}}catch(error){status.textContent=error instanceof Error?error.message:'No se pudo subir el archivo.';}};
  form.querySelectorAll('[data-image-slot]').forEach(input=>input.addEventListener('change',()=>uploadFile(input,'editorial',input.dataset.imageSlot||'cover')));
  const evidence=form.querySelector('[name="business_registration"]');if(evidence)evidence.addEventListener('change',()=>uploadFile(evidence,'evidence','business_registration'));
  const finder=Array.from(form.querySelectorAll(':scope > fieldset')).find(fieldset=>fieldset.querySelector('legend')?.textContent.trim()==='1. Busca el negocio');
  const planStage=document.querySelector('#registration-plan-choice');const residence=form.querySelector('.account-residence');if(residence)continuation.prepend(residence);const stepTwo=[document.querySelector('#registration-new-details'),document.querySelector('#registration-distinct-business'),continuation].filter(Boolean);let step=1;
  if(finder&&planStage&&continuation){const controls=document.createElement('nav');controls.className='editorial-actions registration-wizard-actions';const next=document.createElement('button');next.type='button';next.className='pill';next.textContent='Continuar con la información';const back=document.createElement('button');back.type='button';back.className='pill light';back.textContent='← Volver a búsqueda y plan';const warning=document.createElement('p');warning.className='notice';warning.hidden=true;planStage.after(controls);controls.append(warning,next);continuation.before(back);const renderStep=()=>{const first=step===1;const chosen=!!form.querySelector('#registration-application-type')?.value;form.dataset.registrationWizardStep=String(step);finder.hidden=!first;planStage.hidden=!first||!chosen;controls.hidden=!first;back.hidden=first;for(const node of stepTwo)node.hidden=first;if(!first)form.dispatchEvent(new Event('operator-eligibility-change'));window.scrollTo({top:form.getBoundingClientRect().top+window.scrollY-16,behavior:'smooth'});};form.addEventListener('operator-eligibility-change',()=>{if(step===1)renderStep();});form.addEventListener('change',event=>{if(step===1&&event.target.name==='plan_code')renderStep();});next.addEventListener('click',()=>{const selected=form.querySelector('[name="plan_code"]:checked');if(!form.querySelector('#registration-application-type')?.value||!selected){warning.textContent='Primero selecciona el negocio y la suscripción.';warning.hidden=false;return;}step=2;renderStep();});back.addEventListener('click',()=>{step=1;renderStep();});renderStep();}
});
</script>
<script src="/assets/business-onboarding-wizard.js?v=6" defer></script>

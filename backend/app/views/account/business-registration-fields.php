<link rel="stylesheet" href="<?=esc($resourceUrls['business-registration-final-step.css'])?>">
<link rel="stylesheet" href="<?=esc($resourceUrls['business-registration-selects.css'])?>">
<input type="hidden" name="registration_draft" value="<?=esc($draftId??'')?>" data-registration-draft data-resume-step="<?=(int)($resumeStep??1)?>" data-restored-values="<?=esc(json_encode($input))?>" data-saved-uploads="<?=esc(json_encode($savedDraftUploads??[]))?>">
<input type="hidden" name="draft_revision" value="<?=(int)($draftRevision??0)?>">
<input type="hidden" name="wizard_step" value="<?=(int)($resumeStep??1)?>">
<p class="notice" data-draft-status role="status">El avance se guarda en tu cuenta al continuar cada paso. Los archivos del borrador se conservan privadamente hasta 30 días desde el último guardado.</p>
<?php foreach(($input['temporary_uploads']??[]) as $uploadKind=>$uploads):foreach(is_array($uploads)?$uploads:[] as $uploadSlot=>$uploadId):?>
  <input type="hidden" name="temporary_uploads[<?=esc((string)$uploadKind)?>][<?=esc((string)$uploadSlot)?>]" value="<?=esc((string)$uploadId)?>" data-temp-upload="<?=esc((string)$uploadKind.'-'.(string)$uploadSlot)?>">
<?php endforeach;endforeach ?>
<fieldset id="registration-new-details" data-plan-rules="<?=esc(json_encode($registrationPlanRules))?>" hidden disabled>
  <legend>Información de la ficha</legend>
  <p>Completa los espacios de tu tarjeta o página. Guardamos el avance al continuar; solo se envía para revisión cuando terminas el registro.</p>
  <div class="destination-form-grid">
    <label>Identificador tributario del negocio · NIT <span data-free-identifier-note>(NIT o RNT obligatorio)</span>
      <input name="business_nit" inputmode="numeric" maxlength="25" value="<?=esc($input['business_nit']??'')?>">
      <small>Sirve para identificar el establecimiento; no es un documento personal.</small>
    </label>
    <label>Registro Nacional de Turismo · RNT <span data-free-identifier-note>(si aplica)</span>
      <input name="business_rnt" maxlength="20" value="<?=esc($input['business_rnt']??'')?>">
      <small data-free-identifier-help>Indica NIT o RNT según el documento del negocio. No necesitas ambos.</small>
    </label>
    <label class="wide" data-plan-field="cover" hidden>Foto de portada
      <input type="file" name="reference_images[]" accept="image/jpeg,image/png,image/webp" data-image-slot="cover" required>
      <small>Una foto JPG, PNG o WebP, máximo 8 MB. Queda privada mientras se revisa.</small>
    </label>
    <label class="wide" data-plan-field="summary">
<span data-description-label>Descripción del negocio · máximo <?=$registrationDescriptionLimit?> caracteres</span>
      <textarea name="summary" maxlength="<?=$registrationDescriptionLimit?>" rows="4" data-text-counter data-preview-field="summary">
<?=esc($input['summary']??'')?>
</textarea>
      <small>Un párrafo corto, claro y útil para quien visita el lugar.</small>
    </label>
    <label class="wide" data-plan-field="location">Dirección o referencia para llegar
      <input id="business-address" name="address_text" maxlength="300" value="<?=esc($input['address_text']??'')?>" data-preview-field="address">
    </label>
    <div class="wide business-map-actions" data-plan-field="location">
<button id="business-map-open" type="button" class="pill light">Elegir punto en el mapa</button>
<p id="location-capture-status" role="status">Elige el punto exacto. El enlace público abrirá Google Maps.</p>
</div>
    <?php foreach(['latitude','longitude','map_url','location_confirmed','location_changed'] as $field):$name=['map_url'=>'location_map_url','location_changed'=>'location_map_changed'][$field]??$field;?>
<input type="hidden" id="business-<?=str_replace('_','-',$field)?>" name="<?=esc($name)?>" value="<?=esc($input[$name]??'')?>">
<?php endforeach ?>
  </div>
  <p class="registration-preview-action" data-registration-preview hidden>
<a class="pill light subscription-example" data-subscription-example href="/business/demo.php?view=free">Ver vista previa de este plan</a>
</p>
</fieldset>
<fieldset id="registration-product-details" data-plan-field="products" hidden disabled>
  <legend>Productos y servicios · Premium</legend>
  <section data-editor-panels data-product-editor aria-labelledby="products-title">
    <h3 id="products-title">Hasta cinco productos o servicios</h3>
    <p>Cada uno tiene título, descripción corta y una sola foto. No es una galería.</p>
    <div data-editor-tabs aria-label="Productos del negocio">
</div>
<p data-products-empty>Agrega los productos que quieras presentar.</p>
<button class="pill light" type="button" data-add-product>Agregar producto</button>
    <?php for($i=0;$i<5;$i++):$configured=!empty($input['products'][$i]['title'])||!empty($input['products'][$i]['summary'])||!empty($input['temporary_uploads']['editorial']['product-'.$i]);?>
<fieldset data-editor-panel data-product-slot="<?=$i?>" data-panel-label="Producto <?=$i+1?>" data-configured="<?=$configured?'1':'0'?>" <?=$configured?'':'hidden disabled'?>>
<legend>Producto o servicio <?=$i+1?>
</legend>
<div class="destination-form-grid product-card" data-product-card>
      <label class="product-title">Título<input name="products[<?=$i?>][title]" maxlength="100" required value="<?=esc($input['products'][$i]['title']??'')?>" data-preview-product-title>
</label>
      <label class="product-description">Descripción corta · máximo 180 caracteres<textarea name="products[<?=$i?>][summary]" maxlength="180" rows="6" data-text-counter data-autogrow data-preview-product-summary>
<?=esc($input['products'][$i]['summary']??'')?>
</textarea>
</label>
      <label class="product-photo">Foto del producto<input type="file" name="reference_images[]" accept="image/jpeg,image/png,image/webp" data-image-slot="product-<?=$i?>" required>
</label>
    </div>
<button type="button" class="product-remove-action" data-remove-product aria-label="Eliminar este producto completo" title="Eliminar este producto completo">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7M14 10v7"/>
</svg>
</button>
</fieldset>
<?php endfor ?>
  </section>
</fieldset>
<fieldset id="registration-contact-details" data-plan-field="contact-options" hidden disabled>
  <legend>Canales de contacto</legend>
  <p>Incluye solo los canales que quieres mostrar a las personas que visiten tu negocio.</p>
  <div class="destination-form-grid">
<?php BusinessPhonePresentation::render($phone);foreach(['email'=>'Correo del negocio','website'=>'Sitio web','instagram'=>'Instagram','facebook'=>'Facebook','linkedin'=>'LinkedIn'] as $channel=>$label):?>
<label>
<?=esc($label)?>
<input name="channels[<?=esc($channel)?>]" type="<?= $channel==='email'?'email':'url' ?>" maxlength="500" value="<?=esc($input['channels'][$channel]??'')?>">
</label>
<?php endforeach ?>
</div>
</fieldset>
<script src="<?=esc($resourceUrls['business-text-counters.js'])?>" defer>
</script>
<script src="<?=esc($resourceUrls['business-onboarding-wizard.js'])?>" defer>
</script>
<script src="<?=esc($resourceUrls['business-phone.js'])?>" defer>
</script>
<link rel="stylesheet" href="<?=esc($resourceUrls['business-editor-panels.css'])?>">
<script src="<?=esc($resourceUrls['business-editor-panels.js'])?>" defer>
</script>

<?php if(!(isset($service))){http_response_code(404);exit;} ?>
<link rel="stylesheet" href="/assets/business-registration-final-step.css?v=2">
<link rel="stylesheet" href="/assets/business-registration-selects.css?v=1">
<?php foreach(($_POST['temporary_uploads']??[]) as $uploadKind=>$uploads):foreach(is_array($uploads)?$uploads:[] as $uploadSlot=>$uploadId):?>
  <input type="hidden" name="temporary_uploads[<?=esc((string)$uploadKind)?>][<?=esc((string)$uploadSlot)?>]" value="<?=esc((string)$uploadId)?>" data-temp-upload="<?=esc((string)$uploadKind.'-'.(string)$uploadSlot)?>">
<?php endforeach;endforeach ?>
<fieldset id="registration-new-details" hidden disabled>
  <legend>Información de la ficha</legend>
  <p>Completa únicamente los espacios que se verán en la tarjeta o página elegida. La información se envía una vez para revisión.</p>
  <div class="destination-form-grid">
    <label>Identificador tributario del negocio · NIT <span data-free-identifier-note>(opcional)</span>
      <input name="business_nit" inputmode="numeric" maxlength="25" value="<?=esc($_POST['business_nit']??'')?>">
      <small>Sirve para identificar el establecimiento; no es un documento personal.</small>
    </label>
    <label>Registro Nacional de Turismo · RNT <span data-free-identifier-note>(si aplica)</span>
      <input name="business_rnt" maxlength="20" value="<?=esc($_POST['business_rnt']??'')?>">
      <small data-free-identifier-help>Puedes copiarlo tal como aparece en el documento. Para Gratis debes indicar NIT o RNT.</small>
    </label>
    <label class="wide" data-plan-field="cover" hidden>Foto de portada
      <input type="file" name="reference_images[]" accept="image/jpeg,image/png,image/webp" data-image-slot="cover" required>
      <small>Una foto JPG, PNG o WebP, máximo 8 MB. Queda privada mientras se revisa.</small>
    </label>
    <label class="wide" data-plan-field="summary">Descripción del negocio · máximo 450 caracteres
      <textarea name="summary" maxlength="450" rows="4" data-preview-field="summary"><?=esc($_POST['summary']??'')?></textarea>
      <small>Un párrafo corto, claro y útil para quien visita el lugar.</small>
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
  <p class="registration-preview-action" data-registration-preview hidden><a class="pill light subscription-example" data-subscription-example href="/business/demo.php?view=free">Ver vista previa de este plan</a></p>
</fieldset>
<fieldset id="registration-product-details" data-plan-field="products" hidden disabled>
  <legend>Productos y servicios · Premium</legend>
  <section data-editor-panels data-product-editor aria-labelledby="products-title">
    <h3 id="products-title">Hasta cinco productos o servicios</h3>
    <p>Cada uno tiene título, descripción corta y una sola foto. No es una galería.</p>
    <div data-editor-tabs aria-label="Productos del negocio"></div><p data-products-empty>Agrega los productos que quieras presentar.</p><button class="pill light" type="button" data-add-product>Agregar producto</button>
    <?php for($i=0;$i<5;$i++):$configured=!empty($_POST['products'][$i]['title'])||!empty($_POST['products'][$i]['summary'])||!empty($_POST['temporary_uploads']['editorial']['product-'.$i]);?><fieldset data-editor-panel data-product-slot="<?=$i?>" data-panel-label="Producto <?=$i+1?>" data-configured="<?=$configured?'1':'0'?>" <?=$configured?'':'hidden disabled'?>><legend>Producto o servicio <?=$i+1?></legend><div class="destination-form-grid product-card" data-product-card>
      <label>Nombre<input name="products[<?=$i?>][title]" maxlength="100" required value="<?=esc($_POST['products'][$i]['title']??'')?>" data-preview-product-title></label>
      <label>Descripción corta · máximo 180 caracteres<textarea name="products[<?=$i?>][summary]" maxlength="180" rows="2" data-preview-product-summary><?=esc($_POST['products'][$i]['summary']??'')?></textarea></label>
      <label>Foto del producto<input type="file" name="reference_images[]" accept="image/jpeg,image/png,image/webp" data-image-slot="product-<?=$i?>" required></label>
    </div><button type="button" class="text-link" data-remove-product>Quitar este producto</button></fieldset><?php endfor ?>
  </section>
</fieldset>
<fieldset id="registration-contact-details" data-plan-field="contact-options" hidden disabled>
  <legend>Canales de contacto</legend>
  <p>Incluye solo los canales que quieres mostrar a las personas que visiten tu negocio.</p>
  <div class="destination-form-grid"><?php $phoneValues=$_POST['channels']??[];require __DIR__.'/phone-fields.php';foreach(['email'=>'Correo del negocio','website'=>'Sitio web','instagram'=>'Instagram','facebook'=>'Facebook','linkedin'=>'LinkedIn'] as $channel=>$label):?><label><?=esc($label)?><input name="channels[<?=esc($channel)?>]" type="<?= $channel==='email'?'email':'url' ?>" maxlength="500" value="<?=esc($_POST['channels'][$channel]??'')?>"></label><?php endforeach ?></div>
</fieldset>
<script src="/assets/business-text-counters.js?v=1" defer></script>
<script src="/assets/business-onboarding-wizard.js?v=8" defer></script>
<script src="/assets/business-phone.js?v=2" defer></script>
<link rel="stylesheet" href="/assets/business-editor-panels.css">
<script src="/assets/business-editor-panels.js" defer></script>

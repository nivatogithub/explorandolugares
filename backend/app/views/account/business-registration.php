<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="<?=esc($context['locale'])?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Registrar o reclamar un negocio | Explorando Lugares</title>
<?=PortalPresentation::assets(true,'public','css')?>
<link rel="stylesheet" href="/assets/admin.css?v=business-representation-1">
<link rel="stylesheet" href="<?=esc($resourceUrls['subscription-choice.css'])?>">
<link rel="stylesheet" href="<?=esc($resourceUrls['leaflet.css'])?>">
<link rel="stylesheet" href="<?=esc($resourceUrls['account-registration.css'])?>">
<link rel="stylesheet" href="<?=esc($resourceUrls['contact-phone.css'])?>">
<link rel="icon" href="<?=esc('/assets/brand/explorando-lugares-logo-v2-transparent.png')?>" type="image/png">
<link rel="stylesheet" href="<?=esc($resourceUrls['portal-identity.css'])?>">
<?=PortalPresentation::assets(true,'public','js')?>
</head>
<body class="portal-account-page">
<?=PortalPresentation::header($context)?>
<main class="wrap admin-page account-portal">
<p>
<a href="<?=esc('/negocios-locales/')?>">
<?=esc($copy['back_to_businesses'])?>
</a>
</p>
<section class="account-portal-card account-registration-card">
<p class="eyebrow">Espacio para negocios</p>
<h1>Registra o reclama un negocio</h1>
<p>La cuenta es tuya como persona. Puedes usarla para viajar y administrar varios negocios en distintos lugares; no tienen que estar donde resides.</p>
<p>Busca primero el negocio para evitar duplicados. Con tu correo verificado, un documento del negocio y tu declaración, puedes crear o asociar una ficha privada. Si ya tiene otro responsable, abrimos una disputa y conservamos su acceso. La ficha no aparece en el portal hasta revisar los soportes y aprobar su publicación.</p>
<?php if($error):?>
<p class="notice" role="alert">
<?=esc($error)?>
</p>
<?php endif ?>
<form method="post" enctype="multipart/form-data" class="registration-form" id="business-registration-form" data-registration-state="<?=esc(json_encode(['category'=>$input['category_code']??'','type'=>$input['application_type']??'','extras'=>$input['category_codes']??[],'id'=>$input['business_id']??'','name'=>$input['business_name']??'']))?>" data-search-url="<?=esc('/negocios/registro/')?>">
<input type="hidden" name="account_type" value="business">
<input type="hidden" name="csrf" value="<?=esc($csrf)?>">
<fieldset>
<legend>1. Busca el negocio</legend>
<div class="destination-form-grid">
<label>Lugar<select name="destination_id" id="registration-destination" required>
<option value="">Selecciona un lugar</option>
<?php foreach($places as $item):?>
<option value="<?=esc($item['id'])?>" data-name="<?=esc($item['display_name'])?>" data-type="<?=esc($item['destination_type'])?>" data-department="<?=esc($item['department'])?>" data-country="<?=esc($item['country_code'])?>" data-lat="<?=esc($item['latitude']??'')?>" data-lon="<?=esc($item['longitude']??'')?>" <?=$selectedPlaceId===(int)$item['id']?'selected':''?>>
<?=esc($item['display_name'])?>
</option>
<?php endforeach ?>
</select>
</label>
<label>Categoría<select name="category_code" id="registration-category" required <?=$categories?'':'disabled'?>>
<option value="">Selecciona una categoría</option>
<?php foreach($categories as $item):?>
<option value="<?=esc($item['code'])?>" <?=($input['category_code']??'')===$item['code']?'selected':''?>>
<?=esc($item['label'])?>
</option>
<?php endforeach ?>
</select>
</label>
<label class="wide">Nombre del negocio<input id="registration-search" type="search" autocomplete="off" placeholder="Escribe al menos dos letras" disabled>
<small>Buscamos negocios publicados y registros internos que aún no son visibles.</small>
</label>
</div>
<div id="registration-results" class="business-registration-results" aria-live="polite">
<p>Selecciona lugar y categoría para iniciar la búsqueda.</p>
</div>
<button type="button" class="pill light" id="registration-not-found" disabled>No lo encuentro · Crear negocio</button>
<input type="hidden" name="application_type" id="registration-application-type" value="">
<input type="hidden" name="business_id" id="registration-business-id" value="">
<label class="wide" id="registration-new-name-wrap" hidden>Nombre legal o comercial del negocio que falta<input name="business_name" id="registration-new-name" maxlength="180" autocomplete="organization">
<small>Para Gratis basta nombre, categoría, documento del negocio y tu declaración; la cuenta debe verificar su correo.</small>
</label>
</fieldset>
<?php ?>
<fieldset id="registration-plan-choice" class="registration-plan-stage" hidden disabled>
<?php require __DIR__.'/business-registration-subscription.php'; ?>
</fieldset>
<?php require __DIR__.'/business-registration-fields.php'; ?>
<fieldset id="registration-distinct-business" hidden disabled>
<legend>Si existe otro establecimiento con nombre parecido</legend>
<details>
<summary>Es otro negocio o una sede diferente</summary>
<label class="admin-checkbox-field">
<input type="checkbox" name="distinct_business" value="1">
<span>Declaro que es otro establecimiento y no la ficha encontrada. No estoy reclamando el negocio de otra persona.</span>
</label>
<label>Dirección que lo diferencia<input name="distinct_address" maxlength="300" minlength="8" value="<?=esc($input['distinct_address']??'')?>">
</label>
<p>Solo se permite si la dirección es diferente de las registradas para las coincidencias. La declaración queda conservada con el documento; no se certifica automáticamente.</p>
</details>
</fieldset>
<fieldset id="registration-continuation" hidden disabled>
<legend>Autorización para solicitar la publicación</legend>
<fieldset class="operator-registration" data-operator-prices="<?=esc(json_encode($addonPrices))?>">
<legend>Visibilidad adicional</legend>
<?php require __DIR__.'/business-registration-operator.php'; ?>
<p data-operator-registration-total role="status">
</p>
</fieldset>
<fieldset>
<legend>Tu relación con el negocio y los soportes</legend>
<div class="destination-form-grid">
<section class="registration-account-identity wide">
<strong>Cuenta que se asociará al negocio</strong>
<p>
<?=esc($currentAccount['display_name'])?> · <?=esc($currentAccount['email'])?>
</p>
</section>
<label>Tu relación<select name="relationship_code" id="registration-relationship" required>
<option value="">Selecciona</option>
<option value="owner">Soy propietario/a</option>
<option value="legal_representative">Soy representante legal</option>
<option value="authorized_administrator">Soy administrador/a autorizado/a</option>
</select>
</label>
<label class="wide">Documento del negocio · RUT, matrícula o certificado equivalente · máximo 8 MB<input type="file" name="business_registration" accept="application/pdf,image/jpeg,image/png,image/webp" required>
<small>Adjunta un soporte que relacione el negocio con su titular: RUT, matrícula mercantil o certificado equivalente. Lo revisaremos antes de publicar y eliminaremos el archivo al validar la titularidad; conservaremos la constancia de revisión.</small>
</label>
<label class="admin-checkbox-field wide">
<input type="checkbox" name="responsibility_declared" value="1" required>
<span>Declaro que tengo autorización para administrar este negocio y que el documento corresponde a él. Entiendo que otra persona puede solicitar revisión de esta asociación.</span>
</label>
</div>
<p class="small-note">Los documentos se guardan en un área privada y solo los consulta el equipo autorizado. No se publican en el perfil del negocio.</p>
</fieldset>
<label class="registration-consent account-consent">
<input type="checkbox" name="consent" value="1" required>
<span>Autorizo el uso de mis datos y documentos para revisar y responder esta solicitud. Leí la <a href="<?=esc('/privacidad/')?>" target="_blank" rel="noopener">política de tratamiento de datos</a>.</span>
</label>
<button class="pill" type="submit" id="registration-submit" disabled>
<?=$currentAccount?'Guardar y asociar negocio':'Crear cuenta y continuar'?>
</button>
</fieldset>
</form>
<?php if(!$currentAccount):?>
<p>
<?=esc($copy['existing_account'])?> <a href="<?=esc('/negocios/acceso/')?>">
<?=esc($copy['sign_in'])?>
</a>
</p>
<?php endif ?>
</section>
</main>
<?=PortalPresentation::footer($context)?>
<?php require __DIR__.'/business-map-dialog.php'; ?>
<script src="<?=esc($resourceUrls['leaflet.js'])?>">
</script>
<script src="<?=esc($resourceUrls['admin-business-location.js'])?>" defer>
</script>
<script src="<?=esc($resourceUrls['contact-phone.js'])?>" defer>
</script>
<script src="/assets/business-registration.js?v=8" defer>
</script>
<script src="<?=esc($resourceUrls['account-files.js'])?>" defer>
</script>
<script src="<?=esc($resourceUrls['operator-addon-registration.js'])?>" defer>
</script>
</body>
</html>

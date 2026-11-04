<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="<?=$e($context['locale'])?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Selecciona tu negocio | Explorando Lugares</title>
<?=PortalPresentation::assets(true,'public','css')?>
<link rel="stylesheet" href="<?=$e($resourceUrls['account-registration.css'])?>">
<link rel="stylesheet" href="<?=$e($resourceUrls['subscription-simulator.css'])?>">
<link rel="stylesheet" href="<?=$e($resourceUrls['portal-identity.css'])?>">
<?=PortalPresentation::assets(true,'public','js')?>
</head><body class="portal-account-page"><?=PortalPresentation::header($context)?>
<main class="wrap account-portal"><section class="account-portal-card">
<p class="eyebrow">Tu suscripción</p><h1>¿Para cuál negocio?</h1>
<?php if($data['error']):?><p role="alert"><?=$e($data['error'])?></p><?php endif?>
<?php if($data['hasSelection']):?>
<p>Elegiste <strong><?=$e($data['planLabel'])?></strong> · <?=$e($data['periodLabel'])?><?=$data['hasOperator']?' + complemento de experiencia':''?>.</p>
<?php if($data['hasOperator']):?><p>Después de elegir el negocio, selecciona una experiencia publicada de su mismo lugar. Solo pueden contratar el complemento los negocios con la categoría Experiencias y actividades.</p><?php endif?>
<div class="checkout-businesses"><?php foreach($data['businesses'] as $business):?><a class="pill light" href="<?=$e($business['url'])?>"><?=$e($business['name'])?> · <?=$e($business['placeName'])?></a><?php endforeach?></div>
<p>¿Es un negocio nuevo o todavía no lo administras?</p><a class="pill" href="/negocios/registro/?tipo=business&desde=simulador">Crear o reclamar un negocio</a>
<p>Conservaremos la suscripción elegida. Un negocio sin responsable se asocia a tu cuenta con el correo verificado. Si ya tiene responsable, pasa a conciliación manual. Un negocio nuevo requiere revisión antes de publicarse; el pago queda disponible al tenerlo asociado.</p>
<?php endif?><p><a href="/negocios-locales/suscripciones/">Volver al simulador</a></p>
</section></main><?=PortalPresentation::footer($context)?></body></html>

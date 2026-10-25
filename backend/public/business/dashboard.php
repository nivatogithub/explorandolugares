<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();require_business_session();
require_once dirname(__DIR__,2).'/app/BusinessWorkspace.php';
require_once dirname(__DIR__,2).'/app/BusinessQrCode.php';
require_once dirname(__DIR__,2).'/app/BusinessPaymentEvidenceService.php';
require_once dirname(__DIR__).'/admin/_asset.php';
require_once __DIR__.'/_workspace-icon.php';
$portal=new BusinessAccountPortalService(db());
$account=$portal->current((int)$_SESSION['business_account_id']);
if(!$account){unset($_SESSION['business_account_id']);require_business_session();}
$workspace=new BusinessWorkspace(db());$rows=$workspace->rows((int)$account['id']);$portalHasBusinesses=(bool)$rows;
$isAdministrator=AccountAuthorization::isAdmin(db(),(int)$account['id']);
$requestedTab=(string)($_GET['tab']??(($_GET['espacio']??'')==='business'||$_SERVER['REQUEST_METHOD']==='POST'?'business':'personal'));
if(!in_array($requestedTab,['personal','business'],true)){http_response_code(400);exit('Pestaña no válida.');}
$_SESSION['account_space']=$requestedTab==='business'?'business':'explore';
if($requestedTab==='personal'){require __DIR__.'/traveler.php';exit;}
header('Cache-Control: private, no-store');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf();$action=(string)($_POST['action']??'');if(!in_array($action,['owner_publish','owner_changes'],true))throw new RuntimeException('Esta acción no está disponible. Vuelve a elegir el negocio.');
        $business=(int)($_POST['business_id']??0);$editorial=new BusinessEditorialService(db(),private_storage_path('business-image-references'));
        $editorial->authorize((int)$account['id'],$business);$version=$editorial->latest($business);
        if(!$version||(int)$version['id']!==(int)($_POST['version_id']??0))throw new RuntimeException('La propuesta cambió. Recarga y revisa la nueva versión.');
        $hash=(string)($_POST['payload_hash']??'');$note=trim((string)($_POST['note']??''));
        if($action==='owner_publish'&&$version['status']==='published'&&hash_equals($version['payload_hash'],$hash)){header('Location: /negocios/portal/?tab=business&resultado=publicado',true,303);exit;}
        if($action==='owner_changes'&&mb_strlen($note)<8)throw new RuntimeException('Explica qué deseas corregir, con al menos 8 caracteres.');
        $editorial->transition((int)$version['id'],$action==='owner_publish'?'approve':'request_changes',(int)$account['id'],'',$note,$hash);
        if($action==='owner_publish')$editorial->publishByOwner((int)$version['id'],(int)$account['id'],$hash);
        header('Location: /negocios/portal/?tab=business&resultado='.($action==='owner_publish'?'publicado':'correcciones'),true,303);exit;
    }catch(Throwable $e){$reference=PortalIncidents::technical($e,'business_workspace');$error=$reference?'No se pudo completar. Referencia: '.$reference:$e->getMessage();http_response_code($reference?503:400);}
}
if(($_GET['solicitud']??'')==='guardada'&&(int)($_GET['business']??0)>0){
    foreach($rows as $row)if($row['kind']==='business'&&(int)$row['id']===(int)$_GET['business']&&$row['state']['pay']){
        header('Location: /business/payment.php?business='.(int)$row['id'],true,303);exit;
    }
}
$payments=(new BusinessPaymentEvidenceService(db(),private_storage_path('business-payment-evidence')))->history((int)$account['id']);
require_once dirname(__DIR__).'/_account-page.php';account_page(false);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Mis negocios | Explorando Lugares</title>
<link rel="stylesheet" href="<?=esc(admin_asset('style.css'))?>">
<link rel="stylesheet" href="<?=esc(admin_asset('account-portal.css'))?>">
<link rel="stylesheet" href="<?=esc(admin_asset('business-workspace.css'))?>">
<link rel="stylesheet" href="<?=esc(admin_asset('business-workspace-grid.css'))?>">
<link rel="stylesheet" href="<?=esc(admin_asset('business-qr.css'))?>">
</head>
<body><?php require __DIR__.'/../_account-header.php';?>
<main class="wrap account-portal">
<div class="account-profile-heading"><div><p class="eyebrow">Mi cuenta</p><h1>Mis negocios</h1><p><?=esc($account['display_name'])?></p></div><?php require __DIR__.'/profile-logout.php';?></div>
<nav class="account-tabs" aria-label="Secciones de Mi cuenta"><a href="/negocios/portal/?tab=personal">Viajero</a><a href="/negocios/portal/?tab=business" aria-current="page">Mis negocios</a></nav>
<?php if($isAdministrator):?><p><a class="pill light" href="/admin/index.php">Abrir administración</a></p><?php endif?>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif?>
<?php if(($_GET['resultado']??'')==='publicado'):?><p class="notice" role="status">Tu negocio quedó publicado.</p><?php elseif(($_GET['resultado']??'')==='correcciones'):?><p class="notice" role="status">Recibimos tus comentarios. El equipo revisará las correcciones antes de enviarte otra propuesta.</p><?php elseif(($_GET['pago']??'')==='enviado'):?><p class="notice" role="status">Comprobante recibido. Consulta el estado actualizado del negocio en esta lista.</p><?php elseif(($_GET['solicitud']??'')==='guardada'):?><p class="notice" role="status">Registro guardado. Puedes seguir su estado en esta lista.</p><?php endif?>
<section class="destination-admin-list">
<div class="business-grid-heading"><h2>Tus negocios</h2><a class="pill" href="/negocios/registro/?tipo=business">Registrar o reclamar un negocio</a></div>
<p>Continúa un borrador, consulta el estado o revisa la publicación desde la fila del negocio.</p>
<?php if(!$rows):?><p>Todavía no tienes negocios ni borradores registrados.</p><?php else:?>
<p class="business-grid-scroll-help">En pantallas pequeñas, desliza la tabla para ver el estado y las acciones.</p>
<div class="destination-admin-table" tabindex="0" role="region" aria-label="Listado de tus negocios"><table class="business-workspace-grid"><caption class="visually-hidden">Negocios, borradores y acciones disponibles</caption><thead><tr><th>Negocio</th><th>Lugar</th><th>Suscripción</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($rows as $row):?>
<tr data-business-row="<?=esc($row['kind'])?>">
<td><strong class="business-row-name" title="<?=esc($row['display_name'])?>"><?=esc($row['display_name'])?></strong></td>
<td><?=esc($row['destination_name'])?></td><td><?=esc(BusinessWorkspace::PLANS[$row['plan_code']]??'Por seleccionar')?></td>
<td><span class="business-status" data-status="<?=esc($row['state']['code'])?>"><?=esc($row['state']['label'])?></span></td>
<td><div class="business-row-actions">
<?php if($row['kind']==='draft'):?><a class="business-icon-action" href="/negocios/registro/?tipo=business&amp;draft=<?=esc($row['draft_id'])?>" aria-label="Continuar registro" title="Continuar registro"><?=workspace_icon('edit')?></a>
<?php elseif($row['kind']==='business'):?>
<?php $businessQr=BusinessQrCode::publishedPage(db(),$row['public_id']);if($businessQr):?><button type="button" class="business-icon-action business-qr-trigger" data-business-qr-url="<?=esc($businessQr['url'])?>" data-business-qr-name="<?=esc($businessQr['name'])?>" data-business-qr-filename="<?=esc($businessQr['filename'])?>" aria-haspopup="dialog" aria-controls="business-qr-dialog" aria-label="Generar QR de <?=esc($businessQr['name'])?>" title="Generar QR"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 13v4h-2v4h4v-2h4v-6h-3M12 3v7M3 12h7M12 12h2M19 21h2"/></svg></button><?php endif?>
<?php if($row['state']['pay']):$paymentLabel=$row['state']['code']==='clarification'?'Corregir soporte de pago':'Continuar al pago';?><a class="business-icon-action" href="/business/payment.php?business=<?=(int)$row['id']?>" aria-label="<?=esc($paymentLabel)?>" title="<?=esc($paymentLabel)?>"><?=workspace_icon('payment')?></a><?php endif?>
<?php if($row['version']):?><a class="business-icon-action" href="/business/editorial.php?business=<?=(int)$row['id']?>&amp;version=<?=(int)$row['version']['id']?>&amp;preview=card" data-business-preview data-preview-title="Tarjeta · <?=esc($row['display_name'])?>" aria-label="Ver tarjeta" title="Ver tarjeta"><?=workspace_icon('card')?></a>
<?php if(in_array($row['plan_code'],['page','page_premium'],true)):?><a class="business-icon-action" href="/business/editorial.php?business=<?=(int)$row['id']?>&amp;version=<?=(int)$row['version']['id']?>&amp;preview=page" data-business-preview data-preview-title="Página · <?=esc($row['display_name'])?>" aria-label="Ver página" title="Ver página"><?=workspace_icon('page')?></a><?php endif?>
<?php endif?>
<?php if($row['state']['publish']):?>
<form method="post" class="business-owner-decision">
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="business_id" value="<?=(int)$row['id']?>"><input type="hidden" name="version_id" value="<?=(int)$row['version']['id']?>"><input type="hidden" name="payload_hash" value="<?=esc($row['version']['payload_hash'])?>">
<button class="business-icon-action business-icon-action--primary" name="action" value="owner_publish" aria-label="Aprobar y publicar" title="Aprobar y publicar"><?=workspace_icon('publish')?></button>
<?php if($row['version']['status']==='awaiting_owner'):?><button class="business-icon-action" type="button" data-owner-changes data-business-name="<?=esc($row['display_name'])?>" aria-label="Solicitar correcciones" title="Solicitar correcciones"><?=workspace_icon('comment')?></button><?php endif?>
</form>
<?php elseif($row['state']['code']==='published'):?><a class="business-icon-action" href="/negocios/<?=esc($row['public_id'])?>/" aria-label="Ver publicación" title="Ver publicación"><?=workspace_icon('external')?></a><?php endif?>
<?php endif?>
<?php if($row['message']):?><button class="business-icon-action" type="button" data-business-notice="<?=esc($row['message'])?>" aria-label="Detalles del estado" title="Detalles del estado"><?=workspace_icon('info')?></button><?php endif?>
</div></td></tr>
<?php endforeach?></tbody></table></div><?php endif?>
</section>
<section class="destination-admin-list" id="historial-pagos"><h2>Historial de pagos</h2>
<?php if(!$payments):?><p>Aquí aparecerán los comprobantes que envíes.</p><?php else:?>
<div class="destination-admin-table"><table><thead><tr><th>Negocio</th><th>Plan y periodo</th><th>Importe pagado</th><th>Fecha del pago</th><th>Estado</th><th>Fecha de aprobación</th></tr></thead><tbody>
<?php foreach($payments as $payment):?><tr>
<td><?=esc($payment['business_name'])?></td><td><?=esc(BusinessWorkspace::PLANS[$payment['plan_code']]??$payment['plan_name'])?> · <?=esc(BusinessWorkspace::PERIODS[$payment['billing_frequency']]??'Sin periodo')?></td>
<td><?=esc(number_format((float)$payment['paid_amount'],2,',','.').' '.$payment['currency'])?></td><td><?=esc($payment['transaction_date']?:$payment['submitted_at'])?></td>
<td><?=esc(['pending'=>'Pendiente de validación','approved'=>'Aprobado','clarification_requested'=>'Requiere aclaración','rejected'=>'Rechazado'][$payment['status']]??$payment['status'])?><?php if($payment['customer_message']):?><p class="business-row-message"><?=esc($payment['customer_message'])?></p><?php endif?></td>
<td><?=esc($payment['status']==='approved'?($payment['reviewed_at']??'Por confirmar'):'Pendiente')?></td>
</tr><?php endforeach?></tbody></table></div><?php endif?>
</section>
<p class="business-workspace-help">La presencia contratada conserva su vigencia. Si no deseas continuar después, no realices una renovación.</p>
<dialog class="business-preview-dialog" aria-labelledby="business-preview-title"><div class="business-preview-toolbar"><h2 id="business-preview-title">Vista previa de tu negocio</h2><button type="button" class="pill light" data-close-business-preview>Cerrar</button></div><iframe title="Vista previa privada del negocio"></iframe></dialog>
<dialog class="business-action-dialog" id="business-changes-dialog" aria-labelledby="business-changes-title"><h2 id="business-changes-title">Solicitar correcciones</h2><p data-changes-business></p><form method="post">
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="owner_changes"><input type="hidden" name="business_id"><input type="hidden" name="version_id"><input type="hidden" name="payload_hash">
<label>Motivo de rechazo · máximo <?=BusinessEditorialService::REVIEW_NOTE_LIMIT?> caracteres<textarea name="note" minlength="8" maxlength="<?=BusinessEditorialService::REVIEW_NOTE_LIMIT?>" rows="6" data-text-counter data-autogrow data-text-counter-limit-message=" · Superas el límite; resume el motivo." required></textarea></label><div class="business-dialog-actions"><button class="pill light" type="button" data-close-dialog>Cancelar</button><button class="pill" type="submit">Enviar comentarios</button></div></form></dialog>
<dialog class="business-action-dialog" id="business-notice-dialog" aria-labelledby="business-notice-title"><h2 id="business-notice-title">Detalles del estado</h2><p data-business-notice-text></p><button class="pill light" type="button" data-close-dialog>Cerrar</button></dialog>
</main><script src="<?=esc(admin_asset('business-workspace.js'))?>" defer></script><script src="<?=esc(admin_asset('business-text-counters.js'))?>" defer></script><?php require __DIR__.'/qr-dialog.php';?>
<script src="/assets/vendor/qrcodegen-v1.8.0.js?v=<?=substr(hash_file('sha256',dirname(__DIR__).'/assets/vendor/qrcodegen-v1.8.0.js'),0,16)?>" defer></script>
<script src="<?=esc(admin_asset('business-qr.js'))?>" defer></script></body></html>

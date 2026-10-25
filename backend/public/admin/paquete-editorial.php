<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialPackage.php';
require_once dirname(__DIR__,2).'/app/BusinessPaymentEvidenceService.php';
if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){unset($_SESSION['admin'],$_SESSION['last_seen']);header('Location: /admin/index.php',true,303);exit;}
$_SESSION['last_seen']=time();$business=filter_var($_REQUEST['business']??null,FILTER_VALIDATE_INT);$error='';$notice='';$readiness=null;$businessOptions=[];$editorialVersion=null;
try{
    $packages=new BusinessEditorialPackage(db(),private_storage_path('business-image-references'));
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();if(!$business)throw new RuntimeException('Selecciona el negocio.');$action=(string)($_POST['action']??'');
        if($action==='review_payment'){
            $evidenceId=(int)($_POST['evidence_id']??0);$check=db()->prepare('SELECT e.id FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.id=? AND s.business_id=?');$check->execute([$evidenceId,$business]);if(!$check->fetchColumn())throw new RuntimeException('El comprobante no corresponde a este negocio.');
            (new BusinessPaymentEvidenceService(db(),private_storage_path('business-payment-evidence')))->review($evidenceId,(string)($_POST['decision']??''),(string)($_POST['admin_note']??''),(string)$_SESSION['admin'],(string)($_POST['customer_message']??''));
            $notice='Se actualizó la revisión del comprobante. El negocio sigue privado hasta la prepublicación y aprobación final.';
        }elseif($action==='import_editorial'){
            $version=$packages->importPrepared((int)$business,$_FILES['editorial_zip']??[],dirname(__DIR__),(string)$_SESSION['admin']);
            $notice='La propuesta editorial quedó privada y lista para revisión. Versión '.$version.'.';
        }else throw new RuntimeException('Acción no válida.');
    }
    if(($_GET['download']??'')==='source'){
        if(!$business)throw new RuntimeException('Selecciona el negocio.');
        $file=$packages->exportSource((int)$business,(string)$_SESSION['admin']);
        header('Content-Type: application/zip');header('Content-Length: '.(string)filesize($file));
        header('Content-Disposition: attachment; filename="paquete-editorial-negocio-'.$business.'.zip"');header('Cache-Control: private, no-store, max-age=0');
        readfile($file);@unlink($file);exit;
    }
    if($business)$readiness=$packages->readiness((int)$business);
    $businessOptions=db()->query('SELECT b.id,t.display_name,d.display_name AS destination_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destination_translations d ON d.destination_id=b.destination_id AND d.locale="es" ORDER BY t.display_name,b.id')->fetchAll(PDO::FETCH_ASSOC);
    if($business){$versionQuery=db()->prepare('SELECT id,status FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC LIMIT 1');$versionQuery->execute([$business]);$editorialVersion=$versionQuery->fetch(PDO::FETCH_ASSOC)?:null;}
}catch(Throwable $exception){$error=$exception->getMessage();}
?><!doctype html>
<html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Paquete editorial | Administración</title>
<?php require __DIR__.'/_theme.php';?>
<body class="portal-admin"><main class="wrap admin-page">
<?php require __DIR__.'/_navigation.php';?>
<a href="atencion-comercios.php">← Negocios locales</a>
<h1>Gestionar negocio</h1>
<p>Este es el proceso único de publicación: valida titularidad y pago, prepara o importa el paquete editorial y abre la revisión para enviar la prepublicación.</p>
<nav class="admin-workflow-tabs" aria-label="Proceso del negocio"><a href="#requisitos">1. Requisitos</a><a href="#paquete">2. Paquete editorial</a><a href="#revision">3. Revisión y prepublicación</a></nav>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?>
<?php if($notice):?><p class="notice" role="status"><?=esc($notice)?></p><?php endif ?>
<form method="get" class="registration-form"><label>Negocio<select name="business" required><option value="">Selecciona un negocio</option><?php foreach($businessOptions as $option):?><option value="<?=esc((string)$option['id'])?>" <?=((int)$business===(int)$option['id'])?'selected':''?>><?=esc($option['display_name'])?> · <?=esc($option['destination_name'])?></option><?php endforeach ?></select></label><button class="pill">Abrir ficha administrativa</button></form>
<?php if($business&&$readiness):?><section class="account-portal-card"><h2><?=esc($readiness['business']['name'])?></h2>
<div id="requisitos">
<p>Revisa primero los soportes privados. Nunca se incluyen en el paquete editorial.</p>
<dl>
<dt>Soporte de titularidad o representación</dt><dd><?php if($readiness['title_document']):?><a href="representacion-evidencia.php?id=<?=esc((string)$readiness['title_document']['id'])?>" target="_blank" rel="noopener">Ver <?=esc($readiness['title_document']['original_name'])?> ↗</a> · <?=!empty($readiness['title_document']['verified_at'])?'Validado':'Pendiente de validación'?><?php if(empty($readiness['title_document']['verified_at'])&&$readiness['application']):?> · <a href="validar-titularidad.php?application=<?=esc((string)$readiness['application']['id'])?>">Validar soporte</a><?php endif ?><?php else:?>No se encontró un documento de registro del negocio.<?php endif ?></dd>
<dt>Pago o cobertura promocional</dt><dd><?php if($readiness['subscription']&&$readiness['subscription']['plan_code']==='free'):?>No aplica para la suscripción Gratis.<?php elseif($readiness['promotion_covered']):?>Cubierto íntegramente por cupón. Sin pago ni comprobante requerido.<?php elseif($readiness['payment']):?><a href="comprobante-pago.php?id=<?=esc((string)$readiness['payment']['id'])?>" target="_blank" rel="noopener">Ver <?=esc($readiness['payment']['original_name'])?> ↗</a> · <?=esc($readiness['payment']['status']==='approved'?'Pago confirmado':($readiness['payment']['status']==='rejected'?'Pago rechazado':'Pendiente de revisión'))?><?php else:?>Aún no hay comprobante cargado.<?php endif ?></dd>
<?php if($readiness['promotion']):$promotion=$readiness['promotion'];?><dt>Cupón aplicado</dt><dd><strong><?=esc($promotion['code'])?></strong> · <?=esc($promotion['percentage'])?> % · <?=esc(BusinessPromotions::PLANS[$promotion['plan_code']])?> · <?=esc(BusinessSubscriptionChoice::PERIODS[$promotion['billing_frequency']])?><br>Precio del plan: <?=esc(number_format((int)$promotion['list_cents']/100,2,',','.'))?> <?=esc($promotion['currency'])?> · Descuento: <?=esc(number_format((int)$promotion['discount_cents']/100,2,',','.'))?> · Saldo contratado: <?=esc(number_format((int)$promotion['due_cents']/100,2,',','.'))?>. <small>No es un ingreso recibido. Complementos no incluidos en el descuento.</small></dd><?php endif?>
</dl></div>
<?php if($readiness['payment']&&$readiness['payment']['status']==='pending'):?><form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="business" value="<?=esc((string)$business)?>"><input type="hidden" name="action" value="review_payment"><input type="hidden" name="evidence_id" value="<?=esc((string)$readiness['payment']['id'])?>"><fieldset><legend>Revisar comprobante</legend><p>Confirma el ingreso en tu banco antes de aprobar. El archivo por sí solo no prueba la recepción.</p><label>Nota interna <input name="admin_note" maxlength="1000"></label><label>Mensaje para el negocio <input name="customer_message" maxlength="1000"></label><button class="pill" name="decision" value="approved">Confirmar pago</button> <button class="pill light" name="decision" value="clarification_requested">Pedir aclaración o soporte corregido</button> <button class="pill light" name="decision" value="rejected">Rechazar definitivamente</button></fieldset></form><?php endif ?>
<?php if(!$readiness['ready']):?><p class="notice"><strong>La preparación editorial sigue bloqueada.</strong><br><?=esc(implode(' ', $readiness['blockers']))?></p>
<?php else:?><div id="paquete"><h3>2. Paquete editorial</h3><p class="notice" role="status">Soportes administrativos revisados. Ya puedes preparar el material editorial.</p>
<p><a class="pill light" href="?business=<?=$business?>&amp;download=source">Descargar fotos y ficha editorial</a></p>
<form method="post" enctype="multipart/form-data" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="business" value="<?=esc((string)$business)?>"><input type="hidden" name="action" value="import_editorial">
<label>ZIP editorial preparado<input type="file" name="editorial_zip" accept="application/zip,.zip" required><small>Incluye editorial.json, imágenes finales y textos en español, inglés y portugués.</small></label><button class="pill">Importar propuesta privada</button></form><?php endif ?>
<?php if($editorialVersion):?><div id="revision"><h3>3. Revisión y prepublicación</h3><p>La propuesta está lista para revisar antes de enviar el enlace al propietario.</p><a class="pill light" href="/business/editorial.php?business=<?=$business?>&amp;admin=1">Abrir revisión editorial</a></div><?php endif ?></div>
</section><?php endif ?>
</main></body></html>

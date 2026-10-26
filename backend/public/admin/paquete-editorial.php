<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialPackage.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialService.php';
require_once dirname(__DIR__,2).'/app/BusinessOwnershipEvidence.php';
require_once dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
require_once dirname(__DIR__,2).'/app/BusinessPaymentEvidenceService.php';
require_once dirname(__DIR__,2).'/app/AdminReturnContext.php';
require_once __DIR__.'/_asset.php';
require_admin_session();
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
$business=max(0,(int)($_GET['business']??0));
$return=AdminReturnContext::safe($_GET['return']??null,'atencion-comercios.php');
if(!$business){header('Location: /admin/'.$return,true,303);exit;}
$workflowTab=in_array($_GET['tab']??'',['requirements','package','preview','history'],true)?$_GET['tab']:'requirements';
$baseQuery=['business'=>$business,'return'=>$return];
$adminActor=(string)($_SESSION['admin_user']??$config['admin_user']??'administrador');
$error='';$notice=(string)($_SESSION['business_admin_notice']??'');unset($_SESSION['business_admin_notice']);
$readiness=null;$editorialVersion=null;$events=[];$payments=[];
try{
    $packages=new BusinessEditorialPackage(db(),private_storage_path('business-image-references'));
    $ownership=new BusinessOwnershipEvidence(db(),private_storage_path('business-representation-evidence'));
    $editorial=new BusinessEditorialService(db(),private_storage_path('business-image-references'));
    $readiness=$packages->readiness($business);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();$action=(string)($_POST['action']??'');
        if($action==='verify_title'){
            if(!$readiness['application'])throw new RuntimeException('No hay una solicitud del responsable actual para validar.');
            (new BusinessAccountPortalService(db()))->verifyApprovedBusinessRegistration((int)$readiness['application']['id'],(array)($_POST['verified_evidence']??[]),(string)($_POST['admin_note']??''),$adminActor);
            $notice='Titularidad validada. El soporte se conserva privado hasta publicar.';
        }elseif($action==='retry_retirement'){
            if($ownership->retirePublished($business))throw new RuntimeException('El negocio sigue publicado. El retiro del documento aún está pendiente; revisa el diagnóstico antes de reintentar.');
            $notice='Retiro documental completado. Se conserva la constancia de verificación.';
        }elseif($action==='review_payment'){
            $evidenceId=(int)($_POST['evidence_id']??0);
            if($evidenceId!==(int)($readiness['payment']['id']??0))throw new RuntimeException('El comprobante no corresponde a la revisión vigente de este negocio.');
            (new BusinessPaymentEvidenceService(db(),private_storage_path('business-payment-evidence')))->review($evidenceId,(string)($_POST['decision']??''),(string)($_POST['admin_note']??''),$adminActor,(string)($_POST['customer_message']??''));
            $notice='Revisión del pago guardada. Esta acción no publica el negocio.';
        }elseif($action==='import_editorial'){
            $packages->importPrepared($business,$_FILES['editorial_zip']??[],dirname(__DIR__),$adminActor);
            $notice='Nueva propuesta editorial privada importada. Revisa la vista previa antes de enviarla.';
        }elseif($action==='begin_editorial'){
            $packages->requireReady($business);$editorial->beginEditorial($business,$adminActor);
            header('Location: /business/editorial.php?'.http_build_query($baseQuery+['admin'=>'1']),true,303);exit;
        }else throw new RuntimeException('Acción no válida.');
        $_SESSION['business_admin_notice']=$notice;
        header('Location: /admin/paquete-editorial.php?'.http_build_query($baseQuery+['tab'=>$workflowTab]),true,303);exit;
    }
    if(($_GET['download']??'')==='source'){
        $file=$packages->exportSource($business,$adminActor);
        header('Content-Type: application/zip');header('Content-Length: '.(string)filesize($file));
        header('Content-Disposition: attachment; filename="paquete-editorial.zip"');
        readfile($file);@unlink($file);exit;
    }
    $editorialVersion=$editorial->latest($business);
    $publication=db()->prepare('SELECT publication_status FROM businesses WHERE id=?');$publication->execute([$business]);$published=$publication->fetchColumn()==='published';
    if($workflowTab==='history'){
        $q=db()->prepare('SELECT e.action,e.actor,e.created_at FROM business_editorial_events e JOIN business_editorial_versions v ON v.id=e.version_id WHERE v.business_id=? ORDER BY e.id DESC LIMIT 100');$q->execute([$business]);$events=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=db()->prepare('SELECT e.status,e.submitted_at,e.reviewed_at,e.reviewed_by,e.customer_message FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE s.business_id=? ORDER BY e.id DESC');$q->execute([$business]);$payments=$q->fetchAll(PDO::FETCH_ASSOC);
    }
}catch(Throwable $exception){$reference=PortalIncidents::technical($exception,'business_admin_record');$error=$reference?'No se pudo completar la operación. Referencia: '.$reference:$exception->getMessage();}
function recordHidden(int $business,string $action):void{?>
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="business" value="<?=$business?>"><input type="hidden" name="action" value="<?=esc($action)?>">
<?php }
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Ficha del negocio | Administración</title>
<?php require __DIR__.'/_theme.php';?><link rel="stylesheet" href="<?=esc(admin_asset('admin-business-workspace.css'))?>"></head>
<body class="portal-admin"><main class="wrap admin-page">
<?php require __DIR__.'/_navigation.php';?>
<h1><?=esc($readiness['business']['name']??'Ficha del negocio')?></h1>
<?php require __DIR__.'/_business-workflow.php';?>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif?>
<?php if($notice):?><p class="notice" role="status"><?=esc($notice)?></p><?php endif?>
<?php if($readiness):?>
<p><?=esc($readiness['business']['destination'])?> · <?=esc(BusinessPromotions::PLANS[$readiness['business']['plan']]??$readiness['business']['plan'])?><?php if($editorialVersion):?> · <?=esc(BusinessEditorialService::LABELS[$editorialVersion['status']])?><?php endif?></p>
<?php if($workflowTab==='requirements'):?>
<p>Titularidad y pago se verifican por separado. Ninguna de estas acciones publica el negocio. Los soportes nunca se incluyen en el ZIP editorial.</p>
<div class="admin-requirements-grid">
<section><h2>Titularidad del negocio</h2>
<?php if($document=$readiness['title_document']):?>
<?php if($document['deleted_at']):?><p>Validada. Archivo retirado; se conserva la constancia de revisión.</p>
<?php else:?><p><a href="representacion-evidencia.php?id=<?=(int)$document['id']?>" target="_blank" rel="noopener">Ver soporte privado ↗</a></p><?php endif?>
<?php if($document['verified_at']):?><p>Validada el <?=esc($document['verified_at'])?> por <?=esc($document['verified_by'])?>.</p>
<?php if(empty($document['deleted_at'])):?><p><?=!empty($published)?'Retiro documental pendiente. La publicación no se repite para reintentarlo.':'El documento se eliminará después de publicar.'?></p>
<?php if(!empty($published)):?><form method="post"><?php recordHidden($business,'retry_retirement');?><button class="pill light">Reintentar retiro del documento</button></form><?php endif?>
<?php endif?>
<?php else:?><form method="post" class="registration-form"><?php recordHidden($business,'verify_title');?>
<label class="admin-checkbox-field"><input type="checkbox" name="verified_evidence[]" value="<?=(int)$document['id']?>" required><span>Revisé que el documento acredita al responsable del negocio.</span></label>
<label>Constancia interna de la verificación<textarea name="admin_note" minlength="12" maxlength="1000" rows="3" required></textarea></label>
<button class="pill">Validar titularidad</button></form><?php endif?>
<?php else:?><p>No hay soporte del responsable actual para revisar. Una reclamación ajena no reemplaza su titularidad.</p><?php endif?>
</section>
<section><h2>Pago</h2>
<?php if($readiness['subscription']&&$readiness['subscription']['plan_code']==='free'):?><p>No aplica: suscripción Gratis.</p>
<?php elseif($readiness['promotion_covered']):?><p>Cubierto íntegramente por cupón; no requiere comprobante.</p>
<?php elseif($payment=$readiness['payment']):?>
<p><a href="comprobante-pago.php?id=<?=(int)$payment['id']?>" target="_blank" rel="noopener">Ver comprobante privado ↗</a></p>
<p><?=esc(['pending'=>'Pendiente de revisión','approved'=>'Pago confirmado','clarification_requested'=>'Aclaración solicitada','rejected'=>'Pago rechazado'][$payment['status']]??$payment['status'])?></p>
<?php if($payment['reviewed_at']):?><p>Revisado el <?=esc($payment['reviewed_at'])?> por <?=esc($payment['reviewed_by'])?>.</p><?php endif?>
<?php if($payment['customer_message']):?><p><strong>Mensaje para el negocio:</strong> <?=esc($payment['customer_message'])?></p><?php endif?>
<?php if($payment['status']==='pending'):?><form method="post" class="registration-form"><?php recordHidden($business,'review_payment');?>
<input type="hidden" name="evidence_id" value="<?=(int)$payment['id']?>">
<p>Confirma el ingreso en el banco. El comprobante por sí solo no acredita recepción.</p>
<label>Decisión<select name="decision"><option value="approved">Confirmar pago</option><option value="clarification_requested">Pedir aclaración o soporte corregido</option><option value="rejected">Rechazar pago</option></select></label>
<label>Nota interna<textarea name="admin_note" maxlength="1000" rows="3"></textarea></label>
<label>Motivo para el negocio<textarea name="customer_message" maxlength="1000" rows="3"></textarea><small>Obligatorio junto con la nota interna si solicitas aclaración o rechazas.</small></label>
<button class="pill">Guardar revisión del pago</button></form><?php endif?>
<?php else:?><p>Aún no hay comprobante cargado.</p><?php endif?>
<?php if($promotion=$readiness['promotion']):?><dl class="admin-business-facts"><dt>Cupón aplicado</dt><dd><?=esc($promotion['code'])?> · <?=esc($promotion['percentage'])?> %</dd><dt>Valor del plan</dt><dd><?=esc(SubscriptionChangeService::money((int)$promotion['list_cents']))?> <?=esc($promotion['currency'])?></dd><dt>Saldo contratado</dt><dd><?=esc(SubscriptionChangeService::money((int)$promotion['due_cents']))?> <?=esc($promotion['currency'])?></dd></dl><p>El descuento no es un ingreso recibido.</p><?php endif?>
</section></div>
<?php elseif($workflowTab==='package'):?>
<h2>Paquete editorial</h2>
<?php if(!$readiness['ready']):?><p class="notice"><?=esc(implode(' ',$readiness['blockers']))?></p>
<?php else:?><p>Descarga originales y textos, prepara las imágenes y los tres idiomas fuera del portal e importa el ZIP resultante. No incluye documentos de titularidad ni comprobantes.</p>
<p><a class="pill light" href="?<?=esc(http_build_query($baseQuery+['tab'=>'package','download'=>'source']))?>">Descargar paquete para preparación</a></p>
<form method="post" enctype="multipart/form-data" class="registration-form"><?php recordHidden($business,'import_editorial');?>
<label>ZIP preparado<input type="file" name="editorial_zip" accept="application/zip,.zip" required><small>editorial.json, imágenes finales y textos ES / EN / PT-BR.</small></label>
<button class="pill">Importar nueva propuesta privada</button></form>
<?php if($editorialVersion&&in_array($editorialVersion['status'],['draft','changes_requested','rejected','published'],true)):?><form method="post"><?php recordHidden($business,'begin_editorial');?><button class="pill light">Preparar desde el editor</button></form><?php endif?>
<?php endif?>
<?php elseif($workflowTab==='preview'):?>
<h2>Vista previa privada</h2>
<?php if(!$editorialVersion):?><p>Aún no existe una propuesta para previsualizar.</p>
<?php else:
$locale=in_array($_GET['lang']??'',['es','en','pt-BR'],true)?$_GET['lang']:'es';
$preview=($_GET['view']??'')==='page'&&in_array($editorialVersion['plan_code'],['page','page_premium'],true)?'page':'card';
?><nav class="admin-business-tabs" aria-label="Vista e idioma"><?php foreach(['card'=>'Tarjeta','page'=>'Página'] as $view=>$label):if($view==='page'&&!in_array($editorialVersion['plan_code'],['page','page_premium'],true))continue;?>
<a href="?<?=esc(http_build_query($baseQuery+['tab'=>'preview','view'=>$view,'lang'=>$locale]))?>" <?=$preview===$view?'aria-current="page"':''?>><?=esc($label)?></a><?php endforeach?>
<?php foreach(['es'=>'Español','en'=>'English','pt-BR'=>'Português'] as $lang=>$label):?><a href="?<?=esc(http_build_query($baseQuery+['tab'=>'preview','view'=>$preview,'lang'=>$lang]))?>" <?=$locale===$lang?'aria-current="page"':''?>><?=esc($label)?></a><?php endforeach?></nav>
<iframe class="admin-business-preview" title="Vista privada de la propuesta" src="<?=esc('/business/editorial.php?'.http_build_query(['business'=>$business,'version'=>$editorialVersion['id'],'preview'=>$preview,'lang'=>$locale,'admin'=>'1']))?>"></iframe>
<?php endif?>
<?php elseif($workflowTab==='history'):?>
<h2>Historial del negocio</h2><h3>Titularidad</h3>
<?php foreach($ownership->documents($business) as $document):?><p><?=esc($document['verified_at']?'Verificado el '.$document['verified_at'].' por '.$document['verified_by']:'Sin verificar')?><?=!empty($document['deleted_at'])?' · Archivo retirado el '.esc($document['deleted_at']):''?></p><?php endforeach?>
<h3>Pagos</h3><ol class="admin-history-list"><?php foreach($payments as $payment):?><li><?=esc($payment['submitted_at'])?> · <?=esc(['pending'=>'Pendiente','approved'=>'Aprobado','clarification_requested'=>'Aclaración solicitada','rejected'=>'Rechazado'][$payment['status']]??$payment['status'])?><?php if($payment['customer_message']):?><p><?=esc($payment['customer_message'])?></p><?php endif?></li><?php endforeach?></ol>
<h3>Preparación y publicación</h3><ol class="admin-history-list"><?php foreach($events as $event):?><li><?=esc($event['created_at'])?> · <?=esc(['saved'=>'Versión privada guardada','begin_editorial'=>'Preparación iniciada','quality_checked'=>'Revisión editorial completada','prepare'=>'Prepublicación preparada','approve'=>'Propietario aprobó','published'=>'Publicado','request_changes'=>'Propietario solicitó correcciones','reject'=>'Propuesta rechazada','mail_simulated'=>'Correo simulado','mail_accepted'=>'Correo aceptado por el proveedor','mail_failed'=>'Envío fallido','mail_uncertain'=>'Envío incierto'][$event['action']]??'Actualización editorial')?> · <?=str_starts_with($event['actor'],'admin:')?'Administración':'Propietario / sistema'?></li><?php endforeach?></ol>
<?php endif?>
<?php endif?>
</main></body></html>

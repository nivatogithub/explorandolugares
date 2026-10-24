<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){unset($_SESSION['admin'],$_SESSION['last_seen']);header('Location: /admin/index.php',true,303);exit;}
$_SESSION['last_seen']=time();$id=filter_var($_REQUEST['application']??null,FILTER_VALIDATE_INT);if(!$id){http_response_code(404);exit('Solicitud no encontrada.');}
$service=new BusinessAccountPortalService(db());$error='';
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();$service->verifyApprovedBusinessRegistration((int)$id,(array)($_POST['verified_evidence']??[]),(string)($_POST['admin_note']??''),(string)($_SESSION['admin_user']??'admin'));
        header('Location: paquete-editorial.php?business='.(int)($_POST['business']??0),true,303);exit;
    }
    $query=db()->prepare('SELECT a.id,a.business_id,a.business_name,a.status,t.display_name AS destination_name FROM business_account_applications a JOIN destination_translations t ON t.destination_id=a.destination_id AND t.locale="es" WHERE a.id=?');$query->execute([$id]);$application=$query->fetch(PDO::FETCH_ASSOC);if(!$application||$application['status']!=='approved'||(int)$application['business_id']<1)throw new RuntimeException('La solicitud no está disponible para esta validación.');
    $documents=array_values(array_filter($service->applicationEvidence((int)$id),static fn(array $document):bool=>$document['evidence_type']==='business_registration'));
    if(!$documents)throw new RuntimeException('No hay documento de registro del negocio para revisar.');
}catch(Throwable $exception){$error=$exception->getMessage();}
?><!doctype html>
<html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Validar titularidad | Administración</title>
<?php require __DIR__.'/_theme.php';?>
<body class="portal-admin"><main class="wrap admin-page">
<?php require __DIR__.'/_navigation.php';?>
<a href="<?=!empty($application)?'paquete-editorial.php?business='.(int)$application['business_id']:'solicitudes-cuenta.php'?>">← Volver</a>
<h1>Validar soporte de titularidad</h1>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php elseif($application):?><p><?=esc($application['business_name'])?> · <?=esc($application['destination_name'])?></p>
<p>Este documento se conserva privado y no se agregará al paquete editorial.</p>
<form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="business" value="<?=esc((string)$application['business_id'])?>">
<?php foreach($documents as $document):?><label class="admin-checkbox-field"><input type="checkbox" name="verified_evidence[]" value="<?=esc((string)$document['id'])?>" <?=!empty($document['verified_at'])?'checked disabled':''?> required><span>Revisé <a href="representacion-evidencia.php?id=<?=esc((string)$document['id'])?>" target="_blank" rel="noopener"><?=esc($document['original_name'])?> ↗</a>.</span></label><?php endforeach ?>
<label>Nota interna <small>Explica cómo verificaste que el documento respalda la titularidad o representación.</small><textarea name="admin_note" maxlength="1000" required></textarea></label>
<button class="pill">Validar soporte</button></form><?php endif ?>
</main></body></html>

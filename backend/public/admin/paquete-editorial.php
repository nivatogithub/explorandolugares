<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialPackage.php';
if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){http_response_code(403);exit('Inicia sesión desde administración.');}
$_SESSION['last_seen']=time();$business=filter_var($_REQUEST['business']??null,FILTER_VALIDATE_INT);$error='';$notice='';$readiness=null;
try{
    $packages=new BusinessEditorialPackage(db(),private_storage_path('business-image-references'));
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();if(!$business)throw new RuntimeException('Selecciona el negocio.');
        $version=$packages->importPrepared((int)$business,$_FILES['editorial_zip']??[],dirname(__DIR__),(string)$_SESSION['admin']);
        $notice='La propuesta editorial quedó privada y lista para revisión. Versión '.$version.'.';
    }
    if(($_GET['download']??'')==='source'){
        if(!$business)throw new RuntimeException('Selecciona el negocio.');
        $file=$packages->exportSource((int)$business,(string)$_SESSION['admin']);
        header('Content-Type: application/zip');header('Content-Length: '.(string)filesize($file));
        header('Content-Disposition: attachment; filename="paquete-editorial-negocio-'.$business.'.zip"');header('Cache-Control: private, no-store, max-age=0');
        readfile($file);@unlink($file);exit;
    }
    if($business)$readiness=$packages->readiness((int)$business);
}catch(Throwable $exception){$error=$exception->getMessage();}
?><!doctype html>
<html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Paquete editorial | Administración</title>
<?php require __DIR__.'/_theme.php';?>
<body class="portal-admin"><main class="wrap admin-page">
<?php require __DIR__.'/_navigation.php';?>
<a href="editorial-negocios.php">← Preparación editorial</a>
<h1>Paquete editorial</h1>
<p>Este intercambio contiene solo imágenes y contenido público. No admite documentos de identidad, RUT, matrículas, pagos, correos ni teléfonos.</p>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?>
<?php if($notice):?><p class="notice" role="status"><?=esc($notice)?></p><?php endif ?>
<form method="get" class="registration-form"><label>ID interno del negocio<input name="business" inputmode="numeric" value="<?=esc((string)($business?:''))?>" required></label><button class="pill">Abrir</button></form>
<?php if($business&&$readiness):?><section class="account-portal-card"><h2><?=esc($readiness['business']['name'])?> <small>#<?=esc((string)$business)?></small></h2>
<p>Revisa primero los soportes privados. Nunca se incluyen en el paquete editorial.</p>
<dl>
<dt>Soporte de titularidad o representación</dt><dd><?php if($readiness['title_document']):?><a href="representacion-evidencia.php?id=<?=esc((string)$readiness['title_document']['id'])?>" target="_blank" rel="noopener">Ver <?=esc($readiness['title_document']['original_name'])?> ↗</a> · <?=!empty($readiness['title_document']['verified_at'])?'Validado':'Pendiente de validación'?><?php if(empty($readiness['title_document']['verified_at'])&&$readiness['application']):?> · <a href="validar-titularidad.php?application=<?=esc((string)$readiness['application']['id'])?>">Validar soporte</a><?php endif ?><?php else:?>No se encontró un documento de registro del negocio.<?php endif ?></dd>
<dt>Comprobante de pago</dt><dd><?php if($readiness['subscription']&&$readiness['subscription']['plan_code']==='free'):?>No aplica para la suscripción Gratis.<?php elseif($readiness['payment']):?><a href="comprobante-pago.php?id=<?=esc((string)$readiness['payment']['id'])?>" target="_blank" rel="noopener">Ver <?=esc($readiness['payment']['original_name'])?> ↗</a> · <?=esc($readiness['payment']['status']==='approved'?'Pago confirmado':($readiness['payment']['status']==='rejected'?'Pago rechazado':'Pendiente de revisión'))?><?php else:?>Aún no hay comprobante cargado.<?php endif ?></dd>
</dl>
<?php if(!$readiness['ready']):?><p class="notice"><strong>La preparación editorial sigue bloqueada.</strong><br><?=esc(implode(' ', $readiness['blockers']))?></p>
<?php else:?><p class="notice" role="status">Soportes administrativos revisados. Ya puedes preparar el material editorial.</p>
<p><a class="pill light" href="?business=<?=$business?>&amp;download=source">Descargar fotos y ficha editorial</a></p>
<form method="post" enctype="multipart/form-data" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="business" value="<?=esc((string)$business)?>">
<label>ZIP editorial preparado<input type="file" name="editorial_zip" accept="application/zip,.zip" required><small>Incluye editorial.json, imágenes finales y textos en español, inglés y portugués.</small></label><button class="pill">Importar propuesta privada</button></form><?php endif ?>
</section><?php endif ?>
</main></body></html>

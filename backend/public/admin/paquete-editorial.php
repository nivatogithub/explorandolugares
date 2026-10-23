<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialPackage.php';
if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){http_response_code(403);exit('Inicia sesión desde administración.');}
$_SESSION['last_seen']=time();$business=filter_var($_REQUEST['business']??null,FILTER_VALIDATE_INT);$error='';$notice='';
try{
    $packages=new BusinessEditorialPackage(db(),private_storage_path('business-image-references'));
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();if(!$business)throw new RuntimeException('Selecciona el negocio.');
        $version=$packages->importPrepared((int)$business,$_FILES['editorial_zip']??[],dirname(__DIR__),(string)$_SESSION['admin']);
        $notice='La propuesta editorial quedó privada y lista para revisión. Versión '.$version.'.';
    }
    if(($_GET['download']??'')==='source'){
        if(!$business)throw new RuntimeException('Selecciona el negocio.');
        $file=$packages->exportSource((int)$business);
        header('Content-Type: application/zip');header('Content-Length: '.(string)filesize($file));
        header('Content-Disposition: attachment; filename="paquete-editorial-negocio-'.$business.'.zip"');header('Cache-Control: private, no-store, max-age=0');
        readfile($file);@unlink($file);exit;
    }
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
<?php if($business):?><section class="account-portal-card"><h2>Negocio #<?=esc((string)$business)?></h2>
<p><a class="pill light" href="?business=<?=$business?>&amp;download=source">Descargar fotos y ficha editorial</a></p>
<form method="post" enctype="multipart/form-data" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="business" value="<?=esc((string)$business)?>">
<label>ZIP editorial preparado<input type="file" name="editorial_zip" accept="application/zip,.zip" required><small>Incluye editorial.json, imágenes finales y textos en español, inglés y portugués.</small></label><button class="pill">Importar propuesta privada</button></form>
</section><?php endif ?>
</main></body></html>

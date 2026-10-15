<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/BusinessPublicImporter.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();$error='';$count=0;
if(($_GET['plantilla']??'')==='1'){
    $candidates=[dirname(__DIR__,2).'/app/content/plantilla-importar-negocios.json',dirname(__DIR__,3).'/content/plantilla-importar-negocios.json'];
    $path='';foreach($candidates as $candidate)if(is_file($candidate)){$path=$candidate;break;}
    if(!is_file($path)){http_response_code(404);exit('No se encontró la plantilla.');}
    header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="plantilla-importar-negocios.json"');readfile($path);exit;
}
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if(($_POST['action']??'')!=='import'||($_POST['confirm_import']??'')!=='1')throw new RuntimeException('Confirma que revisaste el paquete antes de importarlo.');
        $upload=$_FILES['package']??null;
        if(!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Selecciona un archivo JSON válido.');
        if((int)$upload['size']<2||(int)$upload['size']>2*1024*1024)throw new RuntimeException('El paquete debe pesar entre 2 bytes y 2 MB.');
        $package=json_decode((string)file_get_contents((string)$upload['tmp_name']),true,512,JSON_THROW_ON_ERROR);
        if(!is_array($package))throw new RuntimeException('El archivo no contiene un paquete JSON válido.');
        $count=BusinessPublicImporter::import(db(),$package);
        header('Location: importar-negocios.php?importados='.$count,true,303);exit;
    }
}catch(Throwable $exception){$error=$exception instanceof JsonException?'El archivo no tiene JSON válido.':$exception->getMessage();http_response_code(400);}
$imported=max(0,(int)($_GET['importados']??0));
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Importar negocios | Administración</title><link rel="stylesheet" href="../assets/style.css?v=admin-navigation-20261015"></head><body><main class="wrap admin-page destination-admin"><a class="brand" href="index.php"><span>Explorando<b>Lugares</b></span></a><p><a href="index.php">← Volver al panel</a></p><h1>Importar negocios</h1><p>Importa lotes de negocios por destino desde un JSON estructurado.</p><?php $adminPath='importar-negocios.php';require __DIR__.'/_navigation.php'; ?>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?><?php if($imported):?><p class="notice" role="status">Se importaron <?=esc($imported)?> negocios. Todos quedaron como borradores privados y sin reclamar; no se asignaron suscripciones.</p><?php endif ?>
<section class="destination-editor"><h2>Paquete JSON</h2><p>El lote se valida completo antes de guardar. Si un registro tiene errores, se cancela toda la importación. Cada negocio requiere nombre, categoría activa y por lo menos una fuente comprobable.</p><p><strong>Verificación:</strong> usa <code>PUBLICO</code> para información de fuentes aún no confirmada por el negocio; usa <code>VERIFICADO</code> cuando hayas comprobado los datos y conserves la fuente. Todos quedan sin reclamar para que su representante pueda iniciar el proceso.</p><p>Importar no publica los negocios. Para que aparezcan en el buscador de reclamaciones, revisa cada ficha, registra su suscripción Gratis y publícala desde la administración. El plan Gratis admite la ficha pública de nombre, categoría y ubicación sin publicar contacto.</p><p><a class="pill light" href="importar-negocios.php?plantilla=1">Descargar plantilla de ejemplo</a></p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import"><label>Archivo de negocios (.json)<input type="file" name="package" accept="application/json,.json" required></label><label class="admin-checkbox-field"><span>Revisé el destino, las categorías y las fuentes. Entiendo que las fichas se guardan como borradores.</span><input type="checkbox" name="confirm_import" value="1" required></label><button class="pill" type="submit">Validar e importar lote</button></form></section></main></body></html>

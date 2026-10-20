<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/DestinationPackage.php';
if (isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800) unset($_SESSION['admin']);
if (empty($_SESSION['admin'])) json_response(['message'=>'Inicia sesión desde el panel de administración.'],403);
$_SESSION['last_seen']=time();
if ($_SERVER['REQUEST_METHOD']!=='POST') json_response(['message'=>'Método no permitido.'],405);
if (!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])) json_response(['message'=>'La sesión expiró. Recarga la página.'],403);
$upload=$_FILES['package_zip']??null;
if (!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) {
    $uploadError=is_array($upload)?(int)($upload['error']??UPLOAD_ERR_NO_FILE):UPLOAD_ERR_NO_FILE;
    $message=in_array($uploadError,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)?'El servidor rechazó el ZIP por superar su límite de carga. Aumenta upload_max_filesize y post_max_size en PHP o usa un paquete más liviano.':'Selecciona un paquete ZIP válido.';
    json_response(['message'=>$message],400);
}
try {
    DestinationPackage::removeExpiredStaging();
    $base=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'el-destination-package-'.bin2hex(random_bytes(8));
    if (!mkdir($base,0700)) throw new RuntimeException('No se pudo preparar el paquete de forma temporal.');
    try { $parsed=DestinationPackage::readZip((string)$upload['tmp_name'],$base); }
    catch (Throwable $error) {if(class_exists('PortalIncidents',false))PortalIncidents::technical($error,'paquete_destino'); DestinationPackage::removeDirectory($base); throw $error; }
    foreach (($_SESSION['destination_package_staging']??[]) as $oldToken=>$oldPackage) {
        if (!is_array($oldPackage)) continue;
        DestinationPackage::removeDirectory((string)($oldPackage['directory']??''));
        unset($_SESSION['destination_package_staging'][$oldToken]);
    }
    $files=[];
    foreach ($parsed['images'] as $name=>$image) {
        $files[$name]=['path'=>$image['path'],'mime'=>$image['mime'],'name'=>$image['name']];
    }
    $token=bin2hex(random_bytes(24));
    $_SESSION['destination_package_staging'][$token]=['directory'=>$base,'files'=>$files,'expires'=>time()+1800];
    json_response(['manifest'=>$parsed['manifest'],'token'=>$token,'images'=>array_map(static fn($name)=>['path'=>$name,'url'=>'paquete-destino-imagen.php?token='.rawurlencode($token).'&file='.rawurlencode($name)],array_keys($files))]);
} catch (Throwable $error) {if(class_exists('PortalIncidents',false))PortalIncidents::technical($error,'paquete_destino'); json_response(['message'=>$error->getMessage()],400); }

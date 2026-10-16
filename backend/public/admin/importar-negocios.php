<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/BusinessPublicImporter.php';
require dirname(__DIR__,2).'/app/BusinessPackage.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$_SESSION['last_seen']=time();$error='';$count=0;
if(($_GET['plantilla']??'')==='1'){
    if(($_GET['formato']??'')==='zip'){
        $zipCandidates=[dirname(__DIR__,2).'/app/content/plantilla-paquete-negocios.zip',dirname(__DIR__,3).'/content/plantilla-paquete-negocios.zip'];$zipPath='';foreach($zipCandidates as $candidate)if(is_file($candidate)){$zipPath=$candidate;break;}
        if($zipPath===''){http_response_code(404);exit('No se encontró la plantilla ZIP.');}
        header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="plantilla-paquete-negocios.zip"');header('Content-Length: '.filesize($zipPath));readfile($zipPath);exit;
    }
    $candidates=[dirname(__DIR__,2).'/app/content/plantilla-importar-negocios.json',dirname(__DIR__,3).'/content/plantilla-importar-negocios.json'];
    $path='';foreach($candidates as $candidate)if(is_file($candidate)){$path=$candidate;break;}
    if(!is_file($path)){http_response_code(404);exit('No se encontró la plantilla.');}
    header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="plantilla-importar-negocios.json"');readfile($path);exit;
}
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if(($_POST['action']??'')!=='import')throw new RuntimeException('Acción de importación no válida.');
        $upload=$_FILES['package']??null;
        if(!is_array($upload)||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){$code=(int)($upload['error']??UPLOAD_ERR_NO_FILE);if(in_array($code,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new RuntimeException('El servidor rechazó el archivo por superar su límite de carga PHP.');throw new RuntimeException('Selecciona un archivo JSON o ZIP válido.');}
        $filename=(string)($upload['name']??'');$isZip=strtolower(pathinfo($filename,PATHINFO_EXTENSION))==='zip';$images=[];$stage='';
        try{
            if($isZip){
                if((int)$upload['size']<2||(int)$upload['size']>BusinessPackage::MAX_ARCHIVE_BYTES)throw new RuntimeException('El ZIP debe pesar entre 2 bytes y 64 MB.');
                $stage=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'el-business-package-'.bin2hex(random_bytes(8));if(!mkdir($stage,0700))throw new RuntimeException('No se pudo preparar el espacio temporal del ZIP.');
                $parsed=BusinessPackage::readZip((string)$upload['tmp_name'],$stage);$package=$parsed['manifest'];$images=$parsed['images'];
            }else{
                if((int)$upload['size']<2||(int)$upload['size']>2*1024*1024)throw new RuntimeException('El JSON debe pesar entre 2 bytes y 2 MB.');
                $package=json_decode((string)file_get_contents((string)$upload['tmp_name']),true,512,JSON_THROW_ON_ERROR);BusinessPackage::validateManifest($package);
            }
            $count=BusinessPublicImporter::import(db(),$package,$images,private_storage_path('business-image-references'));$destinationSlug=(string)$package['destination_slug'];
        }finally{if($stage!=='')BusinessPackage::removeDirectory($stage);}
        header('Location: negocios.php?tab=importar&importados='.$count.'&destino='.rawurlencode($destinationSlug),true,303);exit;
    }
}catch(Throwable $exception){$error=$exception instanceof JsonException?'El archivo no tiene JSON válido.':($exception instanceof PDOException?'No se pudo importar el lote. Revisa que las tablas de destinos y negocios estén listas en Base de datos.':$exception->getMessage());if($exception instanceof PDOException)error_log('Explorando Lugares: falló la importación de negocios.');$_SESSION['business_import_error']=$error;header('Location: negocios.php?tab=importar',true,303);exit;}
// La importación se administra desde la pestaña Negocios > Importar. Se conserva
// esta ruta para enlaces guardados y para descargar la plantilla de ejemplo.
header('Location: negocios.php?tab=importar', true, 303);
exit;

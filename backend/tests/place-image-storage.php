<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/PlaceImageStorage.php';

function storage_rejects(callable $operation,string $message):void
{
    try{$operation();}
    catch(RuntimeException $error){if($error->getMessage()!==$message)throw new LogicException('Image validation message changed.');return;}
    throw new LogicException('An untrusted upload was accepted.');
}

$file=tempnam(sys_get_temp_dir(),'place-storage-');
$root=$file.'-public';$storage=new PlaceImageStorage($root);
try{
    file_put_contents($file,'Local files must not masquerade as HTTP uploads.');
    foreach([UPLOAD_ERR_NO_FILE,UPLOAD_ERR_PARTIAL,UPLOAD_ERR_OK] as $error){
        storage_rejects(static fn()=>$storage->inspect(['error'=>$error,'tmp_name'=>$file,'size'=>filesize($file)]),'No se pudo recibir la imagen.');
    }
    storage_rejects(static fn()=>$storage->store(1,['temporary_path'=>$file,'extension'=>'php','width'=>240,'height'=>160]),'Usa una imagen JPG, PNG o WebP válida; no se admiten SVG.');
    if(file_exists($root))throw new LogicException('Rejected input created a destination directory.');
    if(!$storage->discardNewFiles()||!$storage->discardNewFiles()||!is_file($file))throw new LogicException('Cleanup touched an unowned local file.');
}finally{unlink($file);}
echo "OK: real-upload origin required, executable extensions rejected and unowned files preserved.\n";

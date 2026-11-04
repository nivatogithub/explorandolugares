<?php
declare(strict_types=1);
/** Private upload storage shared by manual payment workflows; never reads SQL or HTTP globals. */
require_once __DIR__.'/PaymentEvidenceStorage.php';
final class BusinessPaymentUpload implements PaymentEvidenceStorage
{
    public function __construct(private string $storage){}
    public function store(array $upload):array
    {
        $storage=$this->storage;
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new RuntimeException('Adjunta un comprobante válido.');
        $size=(int)($upload['size']??0);if($size<1||$size>8*1024*1024)throw new RuntimeException('El comprobante puede pesar máximo 8 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($extensions[$mime]))throw new RuntimeException('El comprobante debe ser PDF, JPG, PNG o WebP.');
        if(!is_dir($storage)&&!mkdir($storage,0700,true)&&!is_dir($storage))throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
        $name=bin2hex(random_bytes(24)).'.'.$extensions[$mime];$path=$storage.'/'.$name;
        if(!move_uploaded_file($upload['tmp_name'],$path))throw new RuntimeException('No se pudo guardar el comprobante.');chmod($path,0600);
        return ['storage_name'=>$name,'path'=>$path,'original_name'=>substr(basename((string)($upload['name']??'comprobante')),0,255),'mime_type'=>$mime,'file_size'=>$size];
    }
    public function discard(array $file):void
    {
        if(dirname($file['path'])!==$this->storage)throw new LogicException('Invalid payment storage path.');
        if(is_file($file['path'])&&!unlink($file['path']))throw new RuntimeException('No se pudo retirar el comprobante de una operación fallida.');
    }
}

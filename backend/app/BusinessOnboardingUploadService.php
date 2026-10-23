<?php
declare(strict_types=1);

/** Session-bound, short-lived private files for the registration wizard. */
final class BusinessOnboardingUploadService
{
    public function __construct(private PDO $pdo,private string $storage) {}

    public function store(string $draft,string $kind,string $slot,array $upload):array
    {
        $this->cleanupExpired();
        if(!preg_match('/^[a-f0-9]{48}$/D',$draft)||!in_array($kind,['editorial','evidence'],true)||!preg_match('/^[a-z0-9_-]{0,40}$/D',$slot))throw new RuntimeException('La carga no es válida.');
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new RuntimeException('No se recibió el archivo.');
        $limit=$kind==='evidence'?8*1024*1024:8*1024*1024;$size=(int)($upload['size']??0);if($size<1||$size>$limit)throw new RuntimeException('Cada archivo puede pesar máximo 8 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);$extension=null;$width=null;$height=null;
        if($kind==='evidence'){$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($extensions[$mime]))throw new RuntimeException('El soporte debe ser PDF, JPG, PNG o WebP.');$extension=$extensions[$mime];if($mime==='application/pdf'&&file_get_contents((string)$upload['tmp_name'],false,null,0,5)!=='%PDF-')throw new RuntimeException('El PDF no es válido.');if($mime!=='application/pdf'&&!($info=@getimagesize((string)$upload['tmp_name'])))throw new RuntimeException('La imagen del soporte no es válida.');}
        else {$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$info=@getimagesize((string)$upload['tmp_name']);if(!$info||!isset($extensions[$mime])||(int)$info[0]<240||(int)$info[1]<160||(int)$info[0]>10000||(int)$info[1]>10000)throw new RuntimeException('La imagen debe ser JPG, PNG o WebP y medir al menos 240 × 160 píxeles.');$extension=$extensions[$mime];$width=(int)$info[0];$height=(int)$info[1];}
        if(!is_dir($this->storage)&&!mkdir($this->storage,0700,true)&&!is_dir($this->storage))throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
        $id=bin2hex(random_bytes(24));$name=bin2hex(random_bytes(20)).'.'.$extension;$path=$this->storage.'/'.$name;if(!move_uploaded_file((string)$upload['tmp_name'],$path))throw new RuntimeException('No se pudo guardar el archivo.');@chmod($path,0600);
        try{$old=$this->pdo->prepare('SELECT storage_name FROM business_onboarding_uploads WHERE draft_token=? AND upload_kind=? AND slot_code=? AND consumed_at IS NULL');$old->execute([$draft,$kind,$slot]);$oldNames=$old->fetchAll(PDO::FETCH_COLUMN);$this->pdo->prepare('DELETE FROM business_onboarding_uploads WHERE draft_token=? AND upload_kind=? AND slot_code=? AND consumed_at IS NULL')->execute([$draft,$kind,$slot]);foreach($oldNames as $oldName){$oldPath=$this->storage.'/'.$oldName;if(is_file($oldPath))@unlink($oldPath);}$this->pdo->prepare('INSERT INTO business_onboarding_uploads(id,draft_token,upload_kind,slot_code,storage_name,original_name,mime_type,file_size,width,height,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$draft,$kind,$slot,$name,substr(basename((string)($upload['name']??'archivo')),0,255),$mime,$size,$width,$height,gmdate('Y-m-d H:i:s',time()+86400)]);}catch(Throwable $error){@unlink($path);throw $error;}
        return ['id'=>$id,'name'=>substr(basename((string)($upload['name']??'archivo')),0,255),'kind'=>$kind,'slot'=>$slot];
    }

    public function cleanupExpired():void
    {
        $q=$this->pdo->query('SELECT storage_name FROM business_onboarding_uploads WHERE expires_at<CURRENT_TIMESTAMP');$names=$q->fetchAll(PDO::FETCH_COLUMN);$this->pdo->exec('DELETE FROM business_onboarding_uploads WHERE expires_at<CURRENT_TIMESTAMP');foreach($names as $name)if(preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png|webp)$/D',(string)$name)){ $path=$this->storage.'/'.$name;if(is_file($path))@unlink($path); }
    }

    public function delete(string $draft,string $id):void
    {
        $q=$this->pdo->prepare('SELECT storage_name FROM business_onboarding_uploads WHERE id=? AND draft_token=? AND consumed_at IS NULL');$q->execute([$id,$draft]);$name=$q->fetchColumn();if(!$name)throw new RuntimeException('El archivo temporal no existe.');$this->pdo->prepare('DELETE FROM business_onboarding_uploads WHERE id=? AND draft_token=?')->execute([$id,$draft]);$path=$this->storage.'/'.$name;if(is_file($path))@unlink($path);
    }

    public function preview(string $draft,string $id):array
    {
        $q=$this->pdo->prepare('SELECT storage_name,mime_type,file_size FROM business_onboarding_uploads WHERE id=? AND draft_token=? AND consumed_at IS NULL AND expires_at>CURRENT_TIMESTAMP');$q->execute([$id,$draft]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row||!str_starts_with((string)$row['mime_type'],'image/'))throw new RuntimeException('La vista previa no está disponible.');$path=$this->storage.'/'.$row['storage_name'];if(!is_file($path)||(int)filesize($path)!==(int)$row['file_size'])throw new RuntimeException('El archivo temporal no está disponible.');$row['path']=$path;return $row;
    }

    /** Caller must already be in the same database transaction as the final request. */
    public function consume(string $draft,string $id,string $destination,int $hexBytes):array
    {
        $q=$this->pdo->prepare('SELECT * FROM business_onboarding_uploads WHERE id=? AND draft_token=? AND consumed_at IS NULL AND expires_at>CURRENT_TIMESTAMP');$q->execute([$id,$draft]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Uno de los archivos temporales ya no está disponible. Vuelve a cargarlo.');
        $extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$extension=$extensions[$row['mime_type']]??null;if(!$extension)throw new RuntimeException('El archivo temporal no tiene un formato válido.');
        if(!is_dir($destination)&&!mkdir($destination,0700,true)&&!is_dir($destination))throw new RuntimeException('No se pudo preparar el almacenamiento privado.');$target=bin2hex(random_bytes($hexBytes)).'.'.$extension;$from=$this->storage.'/'.$row['storage_name'];$to=rtrim($destination,'/').'/'.$target;if(!is_file($from)||!copy($from,$to))throw new RuntimeException('No se pudo asegurar el archivo antes de enviar la solicitud.');@chmod($to,0600);
        $this->pdo->prepare('UPDATE business_onboarding_uploads SET consumed_at=CURRENT_TIMESTAMP WHERE id=? AND draft_token=? AND consumed_at IS NULL')->execute([$id,$draft]);
        return ['storage_name'=>$target,'original_name'=>$row['original_name'],'mime_type'=>$row['mime_type'],'file_size'=>(int)$row['file_size'],'width'=>$row['width']===null?null:(int)$row['width'],'height'=>$row['height']===null?null:(int)$row['height'],'path'=>$to];
    }
}

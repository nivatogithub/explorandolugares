<?php
declare(strict_types=1);
final class CostEvidenceService
{
    public const TYPES=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    private array $written=[];
    public function __construct(private PDO $pdo,private string $storage){}
    public function ready():bool{try{$this->pdo->query('SELECT cost_id,sha256 FROM operating_cost_evidence LIMIT 0');return true;}catch(Throwable){return false;}}
    private function atomic(callable $operation):int{
        $this->written=[];$this->pdo->beginTransaction();
        try{$id=$operation();$this->pdo->commit();$this->written=[];return $id;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();foreach($this->written as $path)if(is_file($path))unlink($path);$this->written=[];throw $e;}
    }
    public function create(OperatingFinanceService $finance,array $data,string $actor,array $upload):int{
        return $this->atomic(function()use($finance,$data,$actor,$upload){$id=$finance->addCost($data,$actor);if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$this->persist($id,$actor,$upload);return $id;});
    }
    public function attach(int $costId,string $actor,array $upload):int{return $this->atomic(fn()=>$this->persist($costId,$actor,$upload));}
    private function persist(int $costId,string $actor,array $upload):int{
        if(!$this->ready())throw new RuntimeException('Falta aplicar la migración 032 para adjuntar soportes.');
        $q=$this->pdo->prepare('SELECT id FROM operating_costs WHERE id=?');$q->execute([$costId]);if(!$q->fetchColumn())throw new RuntimeException('El gasto no existe.');
        $tmp=(string)($upload['tmp_name']??'');if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($tmp))throw new RuntimeException('Selecciona una factura, recibo o pantallazo válido.');
        $size=filesize($tmp);if($size<1||$size>8*1024*1024)throw new RuntimeException('Cada soporte debe pesar máximo 8 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!isset(self::TYPES[$mime]))throw new RuntimeException('Solo se admiten PDF, JPG, PNG o WebP; el tipo se comprueba por contenido.');
        if($mime==='application/pdf'){if(file_get_contents($tmp,false,null,0,5)!=='%PDF-')throw new RuntimeException('PDF no válido.');}elseif(@getimagesize($tmp)===false)throw new RuntimeException('Imagen no válida.');
        if(!is_dir($this->storage)&&!mkdir($this->storage,0700,true)&&!is_dir($this->storage))throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
        $name=bin2hex(random_bytes(24)).'.'.self::TYPES[$mime];$path=$this->storage.'/'.$name;$digest=hash_file('sha256',$tmp);
        if(!move_uploaded_file($tmp,$path))throw new RuntimeException('No se pudo guardar el soporte.');$this->written[]=$path;chmod($path,0600);
        $original=mb_substr(basename(str_replace('\\','/',(string)($upload['name']??'soporte'))),0,255);
        $q=$this->pdo->prepare('INSERT INTO operating_cost_evidence(cost_id,storage_name,original_name,mime_type,file_size,sha256,created_by) VALUES(?,?,?,?,?,?,?)');$q->execute([$costId,$name,$original,$mime,$size,$digest,mb_substr($actor,0,120)]);return (int)$this->pdo->lastInsertId();
    }
}

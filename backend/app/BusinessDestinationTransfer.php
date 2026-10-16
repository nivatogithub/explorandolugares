<?php
declare(strict_types=1);
require_once __DIR__.'/DestinationAdminService.php';

/** Reviewed relocation of existing IDs; never imports or duplicates businesses. */
final class BusinessDestinationTransfer
{
    public function __construct(private PDO $pdo) {}
    private function config():array{return json_decode((string)file_get_contents(__DIR__.'/cauca-viejo-business-transfer.json'),true,32,JSON_THROW_ON_ERROR);}
    private static function normalize(string $value):string{return trim(preg_replace('/[^a-z0-9]+/',' ',strtolower((string)iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)))??'');}
    private static function json(mixed $data):string{return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    public function preview(bool $lock=false):array
    {
        $config=$this->config();$suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $q=$this->pdo->prepare('SELECT b.id,b.public_id,b.destination_id,b.slug,b.updated_at,b.publication_status,t.display_name,d.slug AS destination_slug FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id WHERE d.slug IN (?,?) ORDER BY b.id'.$suffix);$q->execute([$config['source_slug'],$config['target_slug']]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $ready=[];$omitted=[];$already=[];
        $notes=[];if($rows){$sourceIds=array_column($rows,'id');$q=$this->pdo->prepare('SELECT business_id,note FROM business_sources WHERE business_id IN ('.implode(',',array_fill(0,count($sourceIds),'?')).')');$q->execute($sourceIds);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $source)$notes[(int)$source['business_id']][]=$source['note'];}
        foreach($config['businesses'] as $evidence){
            $matches=array_values(array_filter($rows,static fn($r)=>self::normalize($r['display_name'])===self::normalize($evidence['name'])));
            if(count($matches)!==1){$omitted[]=['name'=>$evidence['name'],'reason'=>count($matches)?'Coincidencia ambigua: revisar duplicados.':'No se encontró una ficha con ese nombre exacto.'];continue;}
            $row=$matches[0];$row['evidence']=$evidence;
            $rntPattern='/\bRNT\s+'.preg_quote((string)$evidence['rnt'],'/').'\b/i';
            if(!preg_match($rntPattern,implode(' ', $notes[(int)$row['id']]??[]))){$omitted[]=['name'=>$row['display_name'],'reason'=>'No coincide la referencia RNT de las fuentes con el inventario revisado. Verificar la identidad antes de mover.'];continue;}
            if($row['destination_slug']===$config['target_slug']){$already[]=$row;continue;}
            $collision=array_filter($rows,static fn($r)=>$r['destination_slug']===$config['target_slug']&&$r['slug']===$row['slug']);
            if($collision){$omitted[]=['name'=>$row['display_name'],'reason'=>'La dirección web del negocio ya existe en Cauca Viejo. Revisar sin fusionar ni duplicar.'];continue;}
            $ready[]=$row;
        }
        return ['ready'=>$ready,'already'=>$already,'omitted'=>$omitted,'excluded'=>$config['excluded'],'hash'=>hash('sha256',self::json($ready))];
    }
    public function apply(array $ids,string $hash,bool $confirmed,string $actor,string $publicRoot):array
    {
        if(!$confirmed)throw new RuntimeException('Confirma el traslado de las fichas seleccionadas.');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
        if(!$ids||count($ids)>50||min($ids)<1)throw new RuntimeException('Selecciona al menos un negocio del lote revisado.');
        $plan=$this->preview();if(!hash_equals($plan['hash'],$hash))throw new RuntimeException('El lote cambió. Recarga y revisa los negocios antes de trasladarlos.');
        $available=array_column($plan['ready'],null,'id');if(array_diff($ids,array_keys($available)))throw new RuntimeException('El lote contiene un negocio no autorizado para este traslado.');
        $target=(new DestinationAdminService($this->pdo))->createStarterDraft('cauca-viejo',$publicRoot);
        $this->pdo->beginTransaction();
        try{
            $plan=$this->preview(true);if(!hash_equals($plan['hash'],$hash))throw new RuntimeException('El lote cambió. Recarga y vuelve a revisarlo.');
            $available=array_column($plan['ready'],null,'id');$batch=bin2hex(random_bytes(16));$moved=[];
            $audit=$this->pdo->prepare('INSERT INTO business_bulk_update_audit(batch_id,business_id,actor,before_json,after_json,changes_json) VALUES(?,?,?,?,?,?)');
            foreach($ids as $id){$before=$available[$id];$q=$this->pdo->prepare('UPDATE businesses SET destination_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND destination_id=?');$q->execute([$target,$id,$before['destination_id']]);if($q->rowCount()!==1)throw new RuntimeException('Un negocio cambió de destino. Se canceló el lote completo.');
                $after=$before;$after['destination_id']=$target;$after['destination_slug']='cauca-viejo';$stamp=$this->pdo->prepare('SELECT updated_at FROM businesses WHERE id=?');$stamp->execute([$id]);$after['updated_at']=$stamp->fetchColumn();
                $audit->execute([$batch,$id,substr($actor,0,180),self::json($before),self::json($after),self::json([['field'=>'destination_id','before'=>(int)$before['destination_id'],'after'=>$target,'reason'=>'Clasificación territorial Cauca Viejo; se conservan ID, canales, categorías, suscripciones y estado.','evidence'=>$before['evidence']]])]);$moved[]=['id'=>$id,'name'=>$before['display_name']];
            }
            $this->pdo->commit();return ['batch_id'=>$batch,'target_id'=>$target,'moved'=>$moved];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}

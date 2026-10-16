<?php
declare(strict_types=1);

final class BusinessBulkPublicationService
{
    public function __construct(private PDO $pdo) {}

    /** Publish only selected public-import Free drafts; return per-row decisions instead of hiding failures. */
    public function publish(array $businessIds): array
    {
        $ids=array_values(array_unique(array_filter(array_map(static fn($id):int=>(int)$id,$businessIds),static fn(int $id):bool=>$id>0)));
        if(!$ids||count($ids)>500)throw new RuntimeException('Selecciona entre 1 y 500 negocios.');
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->pdo->prepare('SELECT b.id,b.destination_id,b.record_origin,b.publication_status,b.claim_status,t.display_name,
                (SELECT s.plan_code FROM business_subscriptions s WHERE s.business_id=b.id ORDER BY s.id DESC LIMIT 1) AS plan_code,
                (SELECT s.status FROM business_subscriptions s WHERE s.business_id=b.id ORDER BY s.id DESC LIMIT 1) AS subscription_status
            FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            WHERE b.id IN ('.$placeholders.')');
        $q->execute($ids);$selected=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($selected)!==count($ids))throw new RuntimeException('Uno o más negocios seleccionados ya no existen. Actualiza la lista antes de continuar.');
        $all=$this->pdo->query('SELECT b.id,b.destination_id,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es"')->fetchAll(PDO::FETCH_ASSOC);
        $results=['published'=>[],'skipped'=>[]];
        $publisher=new BusinessSubscriptionAdminService($this->pdo);
        foreach($selected as $business){
            $id=(int)$business['id'];$name=(string)$business['display_name'];$reason='';
            if($business['record_origin']!=='public_import'||$business['publication_status']!=='draft'||$business['claim_status']!=='NO_RECLAMADO'||$business['plan_code']!=='free'||$business['subscription_status']!=='pending_publication')$reason='No es un borrador importado Gratis pendiente de aprobación.';
            if($reason==='')foreach($all as $other){
                if((int)$other['id']===$id||(int)$other['destination_id']!==(int)$business['destination_id'])continue;
                if(self::looksLikeDuplicate($name,(string)$other['display_name'])){$reason='Posible duplicado de «'.(string)$other['display_name'].'». Revisa ambos registros individualmente.';break;}
            }
            if($reason!==''){$results['skipped'][]=['id'=>$id,'name'=>$name,'reason'=>$reason];continue;}
            try{$publisher->publish($id);$results['published'][]=['id'=>$id,'name'=>$name];}
            catch(Throwable $error){$results['skipped'][]=['id'=>$id,'name'=>$name,'reason'=>$error->getMessage()];}
        }
        return $results;
    }

    public static function looksLikeDuplicate(string $left,string $right):bool
    {
        $a=self::normalize($left);$b=self::normalize($right);
        if($a===''||$b==='')return false;
        if($a===$b)return true;
        preg_match_all('/\d+/',$a,$numbersA);preg_match_all('/\d+/',$b,$numbersB);
        if($numbersA[0]!==$numbersB[0])return false;
        if(min(strlen($a),strlen($b))<9)return false;
        similar_text($a,$b,$percent);
        return $percent>=91.0;
    }

    public static function duplicateMap(array $rows):array
    {
        $groups=[];foreach($rows as $row){$key=(int)$row['destination_id'].'|'.self::normalize((string)$row['display_name']);if(str_ends_with($key,'|'))continue;$groups[$key][]=$row;}
        $map=[];foreach($groups as $group){if(count($group)<2)continue;foreach($group as $row){$others=array_values(array_filter($group,static fn(array $other):bool=>(int)$other['id']!==(int)$row['id']));$map[(int)$row['id']]=array_column($others,'display_name');}}
        return $map;
    }

    private static function normalize(string $value):string
    {
        $value=mb_strtolower(trim($value),'UTF-8');
        if(function_exists('iconv')){$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);if(is_string($ascii))$value=$ascii;}
        $value=str_replace(["'",'`'],'',$value);
        $value=preg_replace('/[^a-z0-9]+/',' ',$value)??'';
        return trim(preg_replace('/\s+/',' ',$value)??'');
    }
}

<?php
declare(strict_types=1);

/** Export editable JSON and apply reviewed, sparse updates to existing business IDs. */
require_once __DIR__.'/BusinessCategories.php';

final class BusinessBulkEditService
{
    private const PROFILE=['category_code'=>40,'address_text'=>300,'location_reference'=>500,'latitude'=>0,'longitude'=>0,'opening_hours'=>4000,'price_range'=>100,'capacity_count'=>0,'menu_url'=>500,'reservations_url'=>500];
    private const TEXT=['display_name'=>180,'summary'=>1000,'description'=>3500,'brand_story'=>1500,'featured_services'=>1500];
    private const CONTACT=['channel'=>24,'contact_value'=>500];
    private const SOURCE=['source_url'=>1000,'checked_at'=>19,'note'=>500];
    private const RESEARCH=['channel'=>24,'contact_value'=>500,'source_url'=>1000,'source_type'=>80,'source_field'=>80,'source_period'=>120,'consulted_at'=>10,'evidence_status'=>100,'source_note'=>4000,'legal_basis_status'=>500,'crm_stage'=>80,'potential_subscription'=>40];
    private const CHANNELS=['email','phone','phone_fixed','whatsapp','instagram','facebook','website','linkedin'];
    private ?bool $recipientReady=null;

    public function __construct(private PDO $pdo) {}

    public function export(string $destination,string $category='',string $query=''):array
    {
        $sql='SELECT b.id FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=?';$params=[$destination];
        if($category!==''){if(BusinessCategories::ready($this->pdo)){$sql.=' AND (b.category_code=? OR EXISTS (SELECT 1 FROM business_categories bc WHERE bc.business_id=b.id AND bc.category_code=?))';$params[]=$category;$params[]=$category;}else{$sql.=' AND b.category_code=?';$params[]=$category;}}
        if($query!==''){$sql.=' AND t.display_name LIKE ?';$params[]='%'.$query.'%';}
        $q=$this->pdo->prepare($sql.' ORDER BY b.id LIMIT 501');$q->execute($params);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
        if(!$ids||count($ids)>500)throw new RuntimeException('La exportación debe contener entre 1 y 500 negocios. Ajusta los filtros.');
        $businesses=[];
        foreach($ids as $id){$state=$this->snapshot((int)$id);$businesses[]=['id'=>$state['id'],'public_id'=>$state['public_id'],'version'=>self::version($state),'fields'=>$state['fields'],'category_codes'=>$state['category_codes'],'translations'=>$state['translations'],'contacts'=>$state['contacts'],'sources'=>$state['sources'],'research_contacts'=>$state['research_contacts'],'_context'=>$state['_context']];}
        return ['schema_version'=>'business-bulk-update-v1','destination_slug'=>$destination,'businesses'=>$businesses];
    }

    public function preview(array $package):array
    {
        $plan=$this->plan($package,false);$result=[];
        foreach($plan as $item)if($item['changes'])$result[]=['public_id'=>$item['before']['public_id'],'name'=>$item['before']['translations']['es']['display_name']??'','publication_status'=>$item['before']['_context']['publication_status'],'changes'=>$item['changes']];
        return ['businesses_in_file'=>count($plan),'businesses_changed'=>count($result),'changes'=>$result];
    }

    public function apply(array $package,string $actor):array
    {
        $this->pdo->beginTransaction();
        try{
            $plan=$this->plan($package,true);$batch=bin2hex(random_bytes(16));$changed=0;
            $audit=$this->pdo->prepare('INSERT INTO business_bulk_update_audit(batch_id,business_id,actor,before_json,after_json,changes_json) VALUES(?,?,?,?,?,?)');
            foreach($plan as $item){
                if(!$item['changes'])continue;
                $before=$item['before'];$after=$item['after'];$id=$before['id'];
                $updates=[];$params=[];
                foreach(self::PROFILE as $field=>$_)if($before['fields'][$field]!==$after['fields'][$field]){$updates[]=$field.'=?';$params[]=$after['fields'][$field];}
                if($updates){$params[]=$id;$this->pdo->prepare('UPDATE businesses SET '.implode(',',$updates).',updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($params);}
                if($before['category_codes']!==$after['category_codes']||$before['fields']['category_code']!==$after['fields']['category_code'])BusinessCategories::replace($this->pdo,$id,$after['fields']['category_code'],$after['category_codes']);
                foreach($after['translations'] as $locale=>$translation){
                    if(($before['translations'][$locale]??null)===$translation)continue;
                    $fields=array_keys(self::TEXT);$values=array_map(static fn($field)=>$translation[$field],$fields);
                    if(isset($before['translations'][$locale]))$this->pdo->prepare('UPDATE business_translations SET '.implode(',',array_map(static fn($field)=>$field.'=?',$fields)).' WHERE business_id=? AND locale=?')->execute([...$values,$id,$locale]);
                    else $this->pdo->prepare('INSERT INTO business_translations('.implode(',',$fields).',business_id,locale) VALUES(?,?,?,?,?,?,?)')->execute([...$values,$id,$locale]);
                }
                foreach(['contacts'=>'business_contacts','sources'=>'business_sources','research_contacts'=>'business_research_contacts'] as $list=>$table){
                    $oldById=array_column($before[$list],null,'id');
                    foreach($after[$list] as $record){
                        $recordId=$record['id']??null;$old=$recordId?($oldById[$recordId]??null):null;
                        if($old===$record)continue;
                        $values=$record;unset($values['id']);
                        if($list==='research_contacts')$values['record_hash']=self::researchHash($values);
                        $columns=array_keys($values);
                        if($recordId)$this->pdo->prepare('UPDATE '.$table.' SET '.implode(',',array_map(static fn($field)=>$field.'=?',$columns)).' WHERE id=? AND business_id=?')->execute([...array_values($values),$recordId,$id]);
                        else $this->pdo->prepare('INSERT INTO '.$table.'('.implode(',',$columns).',business_id) VALUES('.implode(',',array_fill(0,count($columns)+1,'?')).')')->execute([...array_values($values),$id]);
                    }
                }
                $this->pdo->prepare('UPDATE businesses SET updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
                $saved=$this->snapshot($id,true);
                $audit->execute([$batch,$id,self::text($actor,180),self::json($before),self::json($saved),self::json($item['changes'])]);$changed++;
            }
            $this->pdo->commit();return ['batch_id'=>$batch,'updated'=>$changed,'unchanged'=>count($plan)-$changed];
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    private function plan(array $package,bool $lock):array
    {
        self::keys($package,['schema_version','destination_slug','businesses']);
        if(($package['schema_version']??'')!=='business-bulk-update-v1')throw new RuntimeException('Usa una exportación business-bulk-update-v1. La importación de negocios nuevos es otro proceso.');
        $records=$package['businesses']??null;
        if(!is_array($records)||!array_is_list($records)||count($records)<1||count($records)>500)throw new RuntimeException('Selecciona entre 1 y 500 negocios existentes.');
        $destination=self::text($package['destination_slug']??'',100);
        $suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        // Lock names in the destination as well as the rows changed, preventing concurrent duplicate renames.
        $q=$this->pdo->prepare('SELECT b.id,b.public_id,t.display_name FROM businesses b JOIN destinations d ON d.id=b.destination_id LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? ORDER BY b.id'.$suffix);$q->execute([$destination]);$all=$q->fetchAll(PDO::FETCH_ASSOC);$available=array_column($all,null,'public_id');
        $seen=[];$plan=[];$finalNames=[];
        foreach($all as $business)$finalNames[(int)$business['id']]=(string)$business['display_name'];
        foreach($records as $record)if(!is_array($record))throw new RuntimeException('Cada negocio debe ser un objeto.');
        usort($records,static fn($a,$b)=>strcmp((string)($a['public_id']??''),(string)($b['public_id']??'')));
        foreach($records as $record){
            if(!is_array($record))throw new RuntimeException('Cada negocio debe ser un objeto.');
            self::keys($record,['id','public_id','version','fields','category_codes','translations','contacts','sources','research_contacts','_context']);
            $publicId=self::text($record['public_id']??'',64);
            if(isset($seen[$publicId]))throw new RuntimeException('El mismo ID aparece dos veces en el archivo.');$seen[$publicId]=true;
            if(!isset($available[$publicId]))throw new RuntimeException('ID inexistente en el destino: '.$publicId.'. No se crearán negocios nuevos.');
            $id=(int)$available[$publicId]['id'];
            if(isset($record['id'])&&(string)$record['id']!==(string)$id)throw new RuntimeException('El ID interno no corresponde al ID público del negocio.');
            $before=$this->snapshot($id,$lock);
            if(!is_string($record['version']??null)||!hash_equals(self::version($before),$record['version']))throw new RuntimeException('El negocio «'.$before['translations']['es']['display_name'].'» cambió desde la exportación. Exporta una versión actual antes de continuar.');
            if(isset($record['_context'])&&$record['_context']!=$before['_context'])throw new RuntimeException('No se pueden modificar estados, URL, suscripción o permisos desde _context.');
            $after=$before;
            if(isset($record['fields'])){
                if(!is_array($record['fields']))throw new RuntimeException('fields debe ser un objeto.');self::keys($record['fields'],array_keys(self::PROFILE));
                foreach($record['fields'] as $field=>$value){
                    if(self::blank($value))continue;
                    $after['fields'][$field]=$this->profileValue($field,$value);
                }
                if(($after['fields']['latitude']===null)!==($after['fields']['longitude']===null))throw new RuntimeException('La ubicación necesita latitud y longitud juntas.');
            }
            $after['category_codes']=BusinessCategories::validate($this->pdo,$record['category_codes']??array_values(array_diff($before['category_codes'],[$before['fields']['category_code']])),$after['fields']['category_code']);
            if(isset($record['translations'])){
                if(!is_array($record['translations']))throw new RuntimeException('translations debe ser un objeto.');self::keys($record['translations'],['es','en','pt-BR']);
                foreach($record['translations'] as $locale=>$fields){
                    if(!is_array($fields))throw new RuntimeException('Cada traducción debe ser un objeto.');self::keys($fields,array_keys(self::TEXT));
                    $translation=$after['translations'][$locale]??array_fill_keys(array_keys(self::TEXT),'');
                    foreach($fields as $field=>$value)if(!self::blank($value))$translation[$field]=self::text($value,self::TEXT[$field],true);
                    if($translation['display_name']==='')throw new RuntimeException('Cada idioma necesita el nombre del negocio.');
                    $after['translations'][$locale]=$translation;
                }
                ksort($after['translations']);
            }
            foreach(['contacts'=>self::CONTACT,'sources'=>self::SOURCE,'research_contacts'=>$this->researchSpec()] as $list=>$spec)if(isset($record[$list]))$after[$list]=$this->mergeRecords($list,$before[$list],$record[$list],$spec);
            $finalNames[$id]=$after['translations']['es']['display_name'];
            $changes=self::diff($before,$after);
            $plan[]=['before'=>$before,'after'=>$after,'changes'=>$changes];
        }
        $names=[];foreach($finalNames as $id=>$name)$names[self::normalizeName($name)][]=$id;
        foreach($plan as $item){$name=$item['after']['translations']['es']['display_name'];if($name!==$item['before']['translations']['es']['display_name']&&count($names[self::normalizeName($name)]??[])>1)throw new RuntimeException('El cambio de nombre produciría un duplicado: '.$name.'.');}
        return $plan;
    }

    private function snapshot(int $id,bool $lock=false):array
    {
        $suffix=$lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $q=$this->pdo->prepare('SELECT * FROM businesses WHERE id=?'.$suffix);$q->execute([$id]);$business=$q->fetch(PDO::FETCH_ASSOC);if(!$business)throw new RuntimeException('El negocio ya no existe.');
        $fields=[];foreach(self::PROFILE as $field=>$_){$value=$business[$field]??null;if(in_array($field,['latitude','longitude'],true))$value=$value===null?null:round((float)$value,7);elseif($field==='capacity_count')$value=$value===null?null:(int)$value;else $value=(string)($value??'');$fields[$field]=$value;}
        $q=$this->pdo->prepare('SELECT locale,display_name,summary,description,brand_story,featured_services FROM business_translations WHERE business_id=? ORDER BY locale'.$suffix);$q->execute([$id]);$translations=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$locale=$row['locale'];unset($row['locale']);foreach($row as &$value)$value=(string)($value??'');unset($value);$translations[$locale]=$row;}
        $collections=[];
        foreach(['contacts'=>['business_contacts',array_merge(['id'],array_keys(self::CONTACT),['is_public','display_order'])],'sources'=>['business_sources',array_merge(['id'],array_keys(self::SOURCE))],'research_contacts'=>['business_research_contacts',array_merge(['id'],array_keys($this->researchSpec()),['authorization_status','marketing_consent','campaign_allowed','suppressed'])]] as $list=>[$table,$columns]){
            if($list==='research_contacts'&&!$this->researchReady()){$collections[$list]=[];continue;}
            $q=$this->pdo->prepare('SELECT '.implode(',',$columns).' FROM '.$table.' WHERE business_id=? ORDER BY id'.$suffix);$q->execute([$id]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
            foreach($rows as &$row){$row['id']=(int)$row['id'];foreach(['is_public','display_order','marketing_consent','campaign_allowed','suppressed'] as $field)if(isset($row[$field]))$row[$field]=(int)$row[$field];}unset($row);$collections[$list]=$rows;
        }
        $q=$this->pdo->prepare('SELECT id,plan_code,status FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1'.$suffix);$q->execute([$id]);$subscription=$q->fetch(PDO::FETCH_ASSOC)?:null;if($subscription)$subscription['id']=(int)$subscription['id'];
        $codes=BusinessCategories::enrich($this->pdo,[$business])[0]['category_codes'];sort($codes);
        return ['id'=>$id,'public_id'=>$business['public_id'],'category_codes'=>$codes,'fields'=>$fields,'translations'=>$translations,...$collections,'_context'=>['slug'=>$business['slug'],'publication_status'=>$business['publication_status'],'information_status'=>$business['information_status'],'claim_status'=>$business['claim_status'],'operation_status'=>$business['operation_status'],'updated_at'=>$business['updated_at'],'subscription'=>$subscription]];
    }

    private function researchSpec():array
    {
        if($this->recipientReady===null){
            try{$this->pdo->query('SELECT recipient_label FROM business_research_contacts LIMIT 0');$this->recipientReady=true;}
            catch(PDOException $error){
                if(!in_array($error->errorInfo[0]??'',['42S02','42S22'],true)&&!str_contains($error->getMessage(),'no such table:')&&!str_contains($error->getMessage(),'no such column:'))throw $error;
                $this->recipientReady=false;
            }
        }
        return self::RESEARCH+($this->recipientReady?['recipient_label'=>180]:[]);
    }

    private function mergeRecords(string $list,array $existing,mixed $input,array $spec):array
    {
        if(!is_array($input)||!array_is_list($input)||count($input)>200)throw new RuntimeException('Revisa la lista '.$list.'; admite hasta 200 elementos por negocio.');
        if($list==='research_contacts'&&$input&&!$this->researchReady())throw new RuntimeException('Instala la estructura de contactos privados (026) antes de cargar investigación.');
        $byId=array_column($existing,null,'id');$seen=[];$new=[];
        $protected=match($list){'contacts'=>['is_public','display_order'],'research_contacts'=>['authorization_status','marketing_consent','campaign_allowed','suppressed'],default=>[]};
        foreach($input as $record){
            if(!is_array($record))throw new RuntimeException('Cada elemento de '.$list.' debe ser un objeto.');self::keys($record,['id',...array_keys($spec),...$protected]);
            $id=$record['id']??null;
            if($id!==null){
                if(!is_int($id)||$id<1||!isset($byId[$id])||isset($seen[$id]))throw new RuntimeException('ID de '.$list.' inexistente, ajeno al negocio o repetido.');
                $seen[$id]=true;$candidate=$byId[$id];
                foreach($protected as $field)if(array_key_exists($field,$record)&&$record[$field]!==$candidate[$field])throw new RuntimeException('No se pueden cambiar permisos de publicación o contacto comercial en una actualización masiva.');
            }else{
                $candidate=['id'=>null,...array_fill_keys(array_keys($spec),'')];
                if($list==='sources')$candidate['checked_at']=null;
                if($list==='contacts')$candidate+=['is_public'=>0,'display_order'=>count($existing)+count($new)];
                if($list==='research_contacts')$candidate+=['authorization_status'=>'NO_OBTENIDA','marketing_consent'=>0,'campaign_allowed'=>0,'suppressed'=>0];
                foreach($protected as $field)if(array_key_exists($field,$record)&&$record[$field]!==$candidate[$field])throw new RuntimeException('Los contactos nuevos se guardan privados y sin autorizaciones comerciales.');
            }
            foreach($spec as $field=>$max)if(array_key_exists($field,$record)&&!self::blank($record[$field]))$candidate[$field]=self::text($record[$field],$max);
            if($id===null||$candidate!==$byId[$id])$this->validateRecord($list,$candidate);
            if($id!==null)$byId[$id]=$candidate;else $new[]=$candidate;
        }
        $result=array_values($byId);$signatures=[];$baseline=[];
        foreach($existing as $record){$signature=self::recordSignature($list,$record);$baseline[$signature]=($baseline[$signature]??0)+1;}
        foreach($result as $record){$signature=self::recordSignature($list,$record);$signatures[$signature]=($signatures[$signature]??0)+1;if($signatures[$signature]>max(1,$baseline[$signature]??0))throw new RuntimeException('La actualización produciría observaciones o contactos duplicados.');}
        foreach($new as $record){$signature=self::recordSignature($list,$record);if(isset($signatures[$signature]))continue;$signatures[$signature]=true;$result[]=$record;}
        return $result;
    }

    private function validateRecord(string $list,array $record):void
    {
        if($list!=='sources'&&!in_array($record['channel'],self::CHANNELS,true))throw new RuntimeException('Canal de contacto no admitido.');
        if($list!=='sources'&&$record['contact_value']==='')throw new RuntimeException('El contacto necesita un valor.');
        if($list==='contacts'){
            $value=$record['contact_value'];$channel=$record['channel'];
            $valid=match($channel){'email'=>(bool)filter_var($value,FILTER_VALIDATE_EMAIL),'website','instagram','facebook','linkedin'=>self::validUrl($value),default=>(bool)preg_match('/^\+?[0-9() .-]{7,24}$/D',$value)};
            if(!$valid)throw new RuntimeException('El valor del contacto no tiene un formato válido.');
        }else{
            if(!self::validUrl($record['source_url']))throw new RuntimeException('La fuente debe ser una URL HTTP o HTTPS válida.');
            $date=$record[$list==='sources'?'checked_at':'consulted_at'];
            if($date!==''&&$date!==null){$format=$list==='sources'&&strlen($date)===19?'Y-m-d H:i:s':'Y-m-d';$parsed=DateTimeImmutable::createFromFormat('!'.$format,$date);if(!$parsed||$parsed->format($format)!==$date)throw new RuntimeException('La fecha de consulta debe ser válida y usar AAAA-MM-DD (con hora opcional en fuentes).');}
            if($list==='research_contacts'&&(!$date||$record['evidence_status']===''||$record['legal_basis_status']===''))throw new RuntimeException('La investigación necesita fecha, estado de evidencia y base de uso.');
        }
    }

    private function profileValue(string $field,mixed $value):mixed
    {
        if(in_array($field,['latitude','longitude'],true)){
            $limit=$field==='latitude'?90:180;if(!is_numeric($value)||!is_finite((float)$value)||abs((float)$value)>$limit)throw new RuntimeException('Coordenadas no válidas.');return round((float)$value,7);
        }
        if($field==='capacity_count'){if(filter_var($value,FILTER_VALIDATE_INT)===false||(int)$value<0||(int)$value>65535)throw new RuntimeException('Capacidad no válida.');return (int)$value;}
        $text=self::text($value,self::PROFILE[$field]);
        if($field==='category_code'){$q=$this->pdo->prepare('SELECT 1 FROM service_categories WHERE code=? AND is_active=1');$q->execute([$text]);if(!$q->fetchColumn())throw new RuntimeException('La categoría no existe o está inactiva: '.$text);}
        if(in_array($field,['menu_url','reservations_url'],true)&&!self::validUrl($text))throw new RuntimeException('El enlace debe usar HTTP o HTTPS.');
        return $text;
    }

    private function researchReady():bool
    {
        try{$this->pdo->query('SELECT id FROM business_research_contacts LIMIT 0');return true;}
        catch(PDOException $error){if(($error->errorInfo[0]??'')==='42S02'||str_contains($error->getMessage(),'no such table: business_research_contacts'))return false;throw $error;}
    }

    private static function diff(array $before,array $after):array
    {
        $changes=[];
        if($before['category_codes']!==$after['category_codes'])$changes[]=['field'=>'category_codes','before'=>$before['category_codes'],'after'=>$after['category_codes']];
        foreach(['fields','translations'] as $section){
            foreach($after[$section] as $key=>$value){
                if($section==='translations'){foreach($value as $field=>$text)if(($before[$section][$key][$field]??'')!==$text)$changes[]=['field'=>$section.'.'.$key.'.'.$field,'before'=>$before[$section][$key][$field]??'','after'=>$text];}
                elseif($before[$section][$key]!==$value)$changes[]=['field'=>$section.'.'.$key,'before'=>$before[$section][$key],'after'=>$value];
            }
        }
        foreach(['contacts','sources','research_contacts'] as $section){$old=array_column($before[$section],null,'id');foreach($after[$section] as $row){$id=$row['id'];if($id===null)$changes[]=['field'=>$section.'.nuevo','before'=>null,'after'=>$row];else foreach($row as $field=>$value)if($field!=='id'&&$old[$id][$field]!==$value)$changes[]=['field'=>$section.'.'.$id.'.'.$field,'before'=>$old[$id][$field],'after'=>$value];}}
        return $changes;
    }

    private static function recordSignature(string $list,array $record):string
    {
        return match($list){'contacts'=>self::json([$record['channel'],$record['contact_value']]),'sources'=>self::json([$record['source_url'],$record['checked_at']?:'',$record['note']]),default=>self::researchHash($record)};
    }
    private static function researchHash(array $row):string{return hash('sha256',json_encode(array_map(static fn($key)=>$row[$key],['channel','contact_value','source_url','source_type','source_field','source_period','consulted_at','evidence_status','source_note']),JSON_THROW_ON_ERROR));}
    private static function version(array $state):string{if(($state['category_codes']??[])===[$state['fields']['category_code']])unset($state['category_codes']);return hash('sha256',self::json($state));}
    private static function json(mixed $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private static function blank(mixed $value):bool{return $value===null||(is_string($value)&&trim($value)==='');}
    private static function validUrl(string $value):bool{return (bool)filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true);}
    private static function keys(array $value,array $allowed):void{if(array_diff(array_keys($value),$allowed))throw new RuntimeException('El archivo contiene campos desconocidos o no editables.');}
    private static function text(mixed $value,int $max,bool $characters=false):string
    {
        if(!is_string($value)||($characters?mb_strlen($value,'UTF-8'):strlen($value))>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value))throw new RuntimeException('Un campo tiene formato o longitud no válidos.');return trim($value);
    }
    private static function normalizeName(string $value):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',mb_strtolower($value,'UTF-8'));return preg_replace('/[^a-z0-9]+/','',strtolower(is_string($ascii)?$ascii:$value))??'';}
}

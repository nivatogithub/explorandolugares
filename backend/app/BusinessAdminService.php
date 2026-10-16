<?php
declare(strict_types=1);

require_once __DIR__.'/BusinessCategories.php';

final class BusinessAdminService
{
    private const LOCALES=['es','en','pt-BR'];
    private const PROTECTED_SUBSCRIPTION = '(bs.plan_code<>"free" OR COALESCE(bs.quoted_amount,0)>0 OR bs.reviewed_at IS NOT NULL OR bs.status IN ("paid_pending_publication") OR EXISTS (SELECT 1 FROM business_subscription_payment_evidence pe WHERE pe.subscription_id=bs.id))';

    public function canDelete(int $id): bool
    {
        $q=$this->pdo->prepare('SELECT id FROM business_subscriptions bs WHERE bs.business_id=? AND '.self::PROTECTED_SUBSCRIPTION.' LIMIT 1');
        $q->execute([$id]);
        return !$q->fetchColumn();
    }

    public function delete(int $id, bool $confirmed): void
    {
        if(!$confirmed)throw new RuntimeException('Confirma que deseas eliminar definitivamente este negocio y sus canales.');
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.$lock);$q->execute([$id]);
            if(!$q->fetchColumn())throw new RuntimeException('El negocio ya no existe.');
            // Lock historical subscriptions too: current Free status is insufficient.
            $q=$this->pdo->prepare('SELECT id FROM business_subscriptions WHERE business_id=?'.$lock);$q->execute([$id]);$q->fetchAll();
            if(!$this->canDelete($id))throw new RuntimeException('No se puede eliminar: el negocio tiene historial de suscripción paga o comprobantes de pago, aunque actualmente sea Gratis.');
            $q=$this->pdo->prepare('SELECT storage_name FROM business_image_references WHERE business_id=?');$q->execute([$id]);$files=$q->fetchAll(PDO::FETCH_COLUMN);
            $q=$this->pdo->prepare('DELETE FROM businesses WHERE id=?');$q->execute([$id]);
            $this->pdo->commit();
            foreach($files as $name){if(preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',(string)$name)){ $path=$this->referenceStorage.'/'.$name;if(is_file($path)&&!@unlink($path))error_log('Explorando Lugares: quedó pendiente retirar una imagen privada de un negocio eliminado.'); }}
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function __construct(private PDO $pdo, private string $referenceStorage)
    {
        if (!is_dir($this->referenceStorage) && !mkdir($this->referenceStorage, 0700, true) && !is_dir($this->referenceStorage)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento privado de imágenes de referencia.');
        }
    }

    public function options(): array
    {
        return [
            'destinations'=>$this->pdo->query('SELECT d.id,d.latitude,d.longitude,d.destination_type,d.department,d.country_code,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY d.display_order,t.display_name')->fetchAll(PDO::FETCH_ASSOC),
            'categories'=>$this->pdo->query('SELECT c.code,t.label FROM service_categories c JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es" WHERE c.is_active=1 ORDER BY c.display_order')->fetchAll(PDO::FETCH_ASSOC),
            'plans'=>(new BusinessSubscriptionAdminService($this->pdo))->options(),
        ];
    }

    public function all(): array
    {
        $rows=$this->pdo->query('SELECT b.id,b.public_id,b.destination_id,b.record_origin,b.information_status,b.claim_status,b.operation_status,b.publication_status,b.updated_at,
                NOT EXISTS (SELECT 1 FROM business_subscriptions bs WHERE bs.business_id=b.id AND '.self::PROTECTED_SUBSCRIPTION.') AS can_delete,
                d.slug AS destination_slug,dt.display_name AS destination_name,c.code AS category_code,ct.label AS category_label,
                bt.display_name,
                (SELECT COUNT(*) FROM business_translations tx JOIN business_translations tx_es ON tx_es.business_id=tx.business_id AND tx_es.locale="es" WHERE tx.business_id=b.id AND tx.display_name<>"" AND (tx.locale="es" OR tx.display_name<>tx_es.display_name OR tx.summary<>tx_es.summary OR tx.description<>tx_es.description OR COALESCE(tx.brand_story,"")<>COALESCE(tx_es.brand_story,"") OR COALESCE(tx.featured_services,"")<>COALESCE(tx_es.featured_services,""))) AS locale_count,
                COALESCE((SELECT bs.plan_code FROM business_subscriptions bs WHERE bs.business_id=b.id ORDER BY bs.id DESC LIMIT 1),"free") AS plan_code,
                COALESCE((SELECT bs.status FROM business_subscriptions bs WHERE bs.business_id=b.id ORDER BY bs.id DESC LIMIT 1),"sin suscripción") AS subscription_status,
                COALESCE((SELECT GROUP_CONCAT(a.email) FROM business_account_memberships m JOIN accounts a ON a.id=m.account_id WHERE m.business_id=b.id AND m.status="active"),"") AS account_email
            FROM businesses b JOIN destinations d ON d.id=b.destination_id
            JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es"
            JOIN service_categories c ON c.code=b.category_code JOIN service_category_translations ct ON ct.category_code=c.code AND ct.locale="es"
            JOIN business_translations bt ON bt.business_id=b.id AND bt.locale="es"
            ORDER BY b.updated_at DESC,b.id DESC')->fetchAll(PDO::FETCH_ASSOC);
        return BusinessCategories::enrich($this->pdo,$rows);
    }

    public function find(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT * FROM businesses WHERE id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['translations']=[];$q=$this->pdo->prepare('SELECT locale,display_name,summary,description,brand_story,featured_services FROM business_translations WHERE business_id=?');$q->execute([$id]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $t)$row['translations'][$t['locale']]=$t;
        $row['contacts']=[];$q=$this->pdo->prepare('SELECT channel,contact_value,is_public FROM business_contacts WHERE business_id=? ORDER BY display_order,id');$q->execute([$id]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $contact)$row['contacts'][$contact['channel']]=$contact;
        $row['media']=['logo'=>0,'cover'=>0,'gallery'=>[]];$q=$this->pdo->prepare('SELECT media_asset_id,role FROM business_media WHERE business_id=? ORDER BY display_order');$q->execute([$id]);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $media){$assetId=(int)$media['media_asset_id'];if($media['role']==='gallery')$row['media']['gallery'][]=$assetId;else $row['media'][$media['role']]=$assetId;}
        $row['image_assistance_requested']=(int)($row['image_assistance_requested']??0);
        $row['image_references']=[];$q=$this->pdo->prepare('SELECT id,original_name,mime_type,file_size,width,height,review_status,created_at FROM business_image_references WHERE business_id=? ORDER BY id');$q->execute([$id]);$row['image_references']=$q->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->pdo->prepare('SELECT plan_code,billing_frequency,status,starts_at,ends_at FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);$subscription=$q->fetch(PDO::FETCH_ASSOC);
        $row['subscription']=$subscription?:null;
        $row=BusinessCategories::enrich($this->pdo,[$row])[0];
        return $row;
    }

    public function save(array $input): int
    {
        $id=max(0,(int)($input['id']??0));$destinationId=filter_var($input['destination_id']??null,FILTER_VALIDATE_INT);
        if(!$destinationId)throw new RuntimeException('Selecciona un destino.');
        $category=(string)($input['category_code']??'');$valid=$this->pdo->prepare('SELECT 1 FROM service_categories WHERE code=? AND is_active=1');$valid->execute([$category]);
        if(!$valid->fetchColumn())throw new RuntimeException('Selecciona una categoría válida.');
        $existing=$id?$this->find($id):null;if($id&&!$existing)throw new RuntimeException('El negocio ya no existe.');
        $categoryCodes=BusinessCategories::validate($this->pdo,$input['category_codes']??array_values(array_diff($existing['category_codes']??[],[$existing['category_code']??''])),$category);
        $submitted=is_array($input['translations']??null)?$input['translations']:[];$translations=[];
        $locales=$id?array_values(array_unique(['es',...array_intersect(self::LOCALES,array_keys($submitted))])):['es'];
        foreach($locales as $locale){$v=is_array($submitted[$locale]??null)?$submitted[$locale]:($existing['translations'][$locale]??[]);$translations[$locale]=['display_name'=>trim((string)($v['display_name']??'')),'summary'=>trim((string)($v['summary']??'')),'description'=>trim((string)($v['description']??'')),'brand_story'=>trim((string)($v['brand_story']??'')),'featured_services'=>trim((string)($v['featured_services']??''))];}
        if(($translations['es']['display_name']??'')==='')throw new RuntimeException('Completa el nombre del negocio en español.');
        $planCode=(string)($input['plan_code']??$input['subscription']['plan_code']??$existing['subscription']['plan_code']??'');$plan=null;
        foreach($this->options()['plans'] as $candidate)if($candidate['code']===$planCode){$plan=$candidate;break;}
        if(!$plan)throw new RuntimeException('Selecciona la suscripción que corresponde al negocio.');
        $features=$plan['features'];
        foreach($locales as $locale){
            if(strlen($translations[$locale]['display_name'])>180)throw new RuntimeException('El nombre del negocio supera los 180 caracteres.');
            foreach($plan['text_limits'] as $field=>$limit){$limit=(int)$limit;if($limit>0&&self::characterLength($translations[$locale][$field])>$limit)throw new RuntimeException('El campo '.self::textFieldLabel($field).' en '.strtoupper($locale).' supera el límite de '.$limit.' caracteres de esta suscripción.');}
        }
        foreach($translations as $locale=>&$translation){$prior=$existing['translations'][$locale]??[];if(!in_array('expanded_details',$features,true))$translation['summary']=(string)($prior['summary']??'');if(!in_array('extended_description',$features,true))$translation['description']=(string)($prior['description']??'');if(!in_array('brand_presentation',$features,true))foreach(['brand_story','featured_services'] as $key)$translation[$key]=(string)($prior[$key]??'');}unset($translation);
        $location=$this->locationFields($input,$existing,$features,$planCode!=='free');
        $frequency=(string)($input['billing_frequency']??$input['subscription']['billing_frequency']??$existing['subscription']['billing_frequency']??'');if($planCode!=='free'&&$frequency==='')$frequency='monthly';
        // URL, orden, verificación, reclamación, operación, plan y publicación no son datos de alta.
        $slug=$existing['slug']??$this->availableSlug($destinationId,$this->slug($translations['es']['display_name']),0);
        if($existing&&(int)$existing['destination_id']!==$destinationId)$slug=$this->availableSlug($destinationId,$this->slug($translations['es']['display_name']),0);
        $this->pdo->beginTransaction();$storedFiles=[];
        try{
            $imageHelp=$input['image_assistance_requested']??($existing['image_assistance_requested']??'0');if(!in_array((string)$imageHelp,['0','1','2'],true))throw new RuntimeException('Indica si el negocio necesita apoyo con sus imágenes.');$imageHelp=(int)$imageHelp;
            if($id){$q=$this->pdo->prepare('UPDATE businesses SET destination_id=?,category_code=?,address_text=?,location_reference=?,latitude=?,longitude=?,location_source=?,location_map_url=?,location_confirmed=?,opening_hours=?,price_range=?,capacity_count=?,menu_url=?,reservations_url=?,image_assistance_requested=?,publication_status="draft",updated_at=CURRENT_TIMESTAMP WHERE id=?');$q->execute([$destinationId,$category,...array_values($location),$imageHelp,$id]);}
            else{$publicId=$this->uuid();$q=$this->pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,address_text,location_reference,latitude,longitude,location_source,location_map_url,location_confirmed,opening_hours,price_range,capacity_count,menu_url,reservations_url,image_assistance_requested,record_origin,slug,information_status,claim_status,operation_status,publication_status,display_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"admin_created",?,"PENDIENTE_REVISION","NO_RECLAMADO","DESCONOCIDO","draft",0)');$q->execute([$publicId,$destinationId,$category,...array_values($location),$imageHelp,$slug]);$id=(int)$this->pdo->lastInsertId();}
            BusinessCategories::replace($this->pdo,$id,$category,$categoryCodes);
            $this->syncTranslations($id,$translations);$this->syncContacts($id,is_array($input['contacts']??null)?$input['contacts']:[],$features,$existing['contacts']??[]);
            $this->storeImageReferences($id,$input['reference_images']??null,$storedFiles,$this->referenceLimit($planCode));
            $current=$existing['subscription']??null;
            if(!$current||$current['plan_code']!==$planCode||($current['billing_frequency']??'')!==($planCode==='free'?'':$frequency)){
                (new BusinessSubscriptionAdminService($this->pdo))->save(['business_id'=>$id,'plan_code'=>$planCode,'billing_frequency'=>$frequency,'publish_not_before'=>(string)($input['publish_not_before']??''),'payment_reviewed'=>'0']);
            }
            $this->pdo->commit();return $id;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();foreach($storedFiles as $file)if(is_file($file))@unlink($file);throw $error;}
    }

    private function syncTranslations(int $id,array $translations):void
    {
        $driver=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);$sql=$driver==='mysql'?'INSERT INTO business_translations(business_id,locale,display_name,summary,description,brand_story,featured_services) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),summary=VALUES(summary),description=VALUES(description),brand_story=VALUES(brand_story),featured_services=VALUES(featured_services)':'INSERT INTO business_translations(business_id,locale,display_name,summary,description,brand_story,featured_services) VALUES(?,?,?,?,?,?,?) ON CONFLICT(business_id,locale) DO UPDATE SET display_name=excluded.display_name,summary=excluded.summary,description=excluded.description,brand_story=excluded.brand_story,featured_services=excluded.featured_services';
        $q=$this->pdo->prepare($sql);foreach($translations as $locale=>$translation)$q->execute([$id,$locale,$translation['display_name'],$translation['summary'],$translation['description'],$translation['brand_story'],$translation['featured_services']]);
    }

    private static function characterLength(string $value):int
    {
        $count=preg_match_all('/./us',$value,$matches);
        return $count===false?strlen($value):$count;
    }

    private static function textFieldLabel(string $field):string
    {
        return ['summary'=>'la descripción breve','description'=>'la descripción ampliada','brand_story'=>'la historia del negocio','featured_services'=>'los servicios destacados'][$field]??'el texto';
    }

    private function syncContacts(int $id,array $input,array $features,array $existing=[]):void
    {
        $allowed=['email','phone','phone_fixed','whatsapp','website','instagram','facebook'];$clean=[];
        foreach($allowed as $channel){$enabled=in_array(in_array($channel,['email','phone','whatsapp'],true)?'direct_contact':'business_page',$features,true);$row=is_array($input[$channel]??null)?$input[$channel]:[];$previous=$existing[$channel]??[];$value=trim((string)($enabled?($row['value']??''):($previous['contact_value']??'')));if($value==='')continue;
            $ok=match($channel){'email'=>(bool)filter_var($value,FILTER_VALIDATE_EMAIL),'website','instagram','facebook'=>(bool)filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true),default=>(bool)preg_match('/^\+?[0-9() .-]{7,24}$/D',$value)};
            if(!$ok)throw new RuntimeException('Revisa el dato de contacto de '.strtoupper($channel).'.');$isPublic=$enabled&&!empty($row['public'])?1:($enabled?0:0);$clean[]=[$channel,$value,$isPublic];
        }
        $this->pdo->prepare('DELETE FROM business_contacts WHERE business_id=?')->execute([$id]);$q=$this->pdo->prepare('INSERT INTO business_contacts(business_id,channel,contact_value,is_public,display_order) VALUES(?,?,?,?,?)');foreach($clean as $order=>$row)$q->execute([$id,$row[0],$row[1],$row[2],$order]);
    }

    private function locationFields(array $input,?array $existing=null,array $features=[],bool $requiresLocation=true):array
    {
        if(!$requiresLocation&&!array_key_exists('address_text',$input)&&!array_key_exists('latitude',$input)&&!array_key_exists('longitude',$input)&&!array_key_exists('location_map_url',$input)){
            return ['address_text'=>(string)($existing['address_text']??''),'location_reference'=>(string)($existing['location_reference']??''),'latitude'=>$existing['latitude']??null,'longitude'=>$existing['longitude']??null,'location_source'=>$existing['location_source']??null,'location_map_url'=>(string)($existing['location_map_url']??''),'location_confirmed'=>!empty($input['location_map_changed'])?0:(int)($existing['location_confirmed']??0),'opening_hours'=>(string)($existing['opening_hours']??''),'price_range'=>(string)($existing['price_range']??''),'capacity_count'=>$existing['capacity_count']??null,'menu_url'=>(string)($existing['menu_url']??''),'reservations_url'=>(string)($existing['reservations_url']??'')];
        }
        $address=trim((string)($input['address_text']??$existing['address_text']??''));$reference=trim((string)($input['location_reference']??$existing['location_reference']??''));
        if(strlen($address)>300||strlen($reference)>500)throw new RuntimeException('La dirección o referencia es demasiado larga.');
        $hasMapField=$requiresLocation&&!empty($input['map_workflow']);$mapUrl=trim((string)($input['location_map_url']??($existing['location_map_url']??'')));$mapUrl=$this->mapUrl($mapUrl);$coords=$this->coordinatesFromMap($mapUrl);
        $confirmed=array_key_exists('location_confirmed',$input)?(!empty($input['location_confirmed'])?1:0):((int)($existing['location_confirmed']??0));
        if($hasMapField&&!$confirmed)throw new RuntimeException('Selecciona el punto exacto en el mapa y confirma que dirección y destino coinciden.');
        // Legacy latitude/longitude are accepted only for older internal callers; the form no longer exposes independent coordinates.
        $latText=trim((string)($input['latitude']??''));$lonText=trim((string)($input['longitude']??''));$lat=$latText===''?null:filter_var($latText,FILTER_VALIDATE_FLOAT);$lon=$lonText===''?null:filter_var($lonText,FILTER_VALIDATE_FLOAT);
        if($coords){[$lat,$lon]=$coords;}
        elseif($mapUrl!==''&&$latText===''&&$lonText===''&&empty($input['location_map_changed'])){$lat=$existing['latitude']??null;$lon=$existing['longitude']??null;}
        if($hasMapField&&($lat===null||$lon===null))throw new RuntimeException('Selecciona el punto exacto en el mapa y confirma que dirección y destino coinciden.');
        if(($latText!==''&&$lat===false)||($lonText!==''&&$lon===false))throw new RuntimeException('La latitud y longitud deben ser números válidos.');
        if(($lat===null)!==($lon===null))throw new RuntimeException('Ingresa latitud y longitud juntas.');
        if($lat!==null&&($lat < -90||$lat>90||$lon < -180||$lon>180))throw new RuntimeException('Las coordenadas están fuera de rango.');
        $source=$lat!==null?'map':'';
        $hasBusinessPage=in_array('business_page',$features,true);
        $hours=$hasBusinessPage?$this->structuredHours($input['hours']??($input['opening_hours']??null),$existing['opening_hours']??''):(string)($existing['opening_hours']??'');
        $price=$hasBusinessPage?trim((string)($input['price_range']??($existing['price_range']??''))):(string)($existing['price_range']??'');$price=['Económico'=>'$','Moderado'=>'$$','Medio'=>'$$$','Alto'=>'$$$$','Especial'=>'$$$$$'][$price]??$price;
        if(!in_array($price,['','consultar','na','$','$$','$$$','$$$$','$$$$$'],true))throw new RuntimeException('Selecciona un rango de precios de la lista.');
        $capacityText=trim((string)($input['capacity_count']??''));$capacity=!$hasBusinessPage?($existing['capacity_count']??null):($capacityText===''?($existing['capacity_count']??null):filter_var($capacityText,FILTER_VALIDATE_INT));
        if(strlen($hours)>4000||($capacityText!==''&&($capacity===false||$capacity<0||$capacity>65535)))throw new RuntimeException('Revisa el horario semanal o la capacidad.');
        $menu=$this->optionalUrl((string)($hasBusinessPage?($input['menu_url']??$existing['menu_url']??''):($existing['menu_url']??'')),'menú');$reservations=$this->optionalUrl((string)($hasBusinessPage?($input['reservations_url']??$existing['reservations_url']??''):($existing['reservations_url']??'')),'reservas');
        if($hasMapField&&$address==='')throw new RuntimeException('Completa la dirección junto con el punto del mapa.');
        if($hasMapField&&$lat!==null&&$lon!==null)$mapUrl='https://www.openstreetmap.org/?mlat='.rawurlencode((string)$lat).'&mlon='.rawurlencode((string)$lon).'#map=18/'.rawurlencode((string)$lat).'/'.rawurlencode((string)$lon);
        return ['address_text'=>$address,'location_reference'=>$reference,'latitude'=>$lat===false?null:$lat,'longitude'=>$lon===false?null:$lon,'location_source'=>$source?:null,'location_map_url'=>$mapUrl,'location_confirmed'=>$confirmed,'opening_hours'=>$hours,'price_range'=>$price,'capacity_count'=>$capacity===false?null:$capacity,'menu_url'=>$menu,'reservations_url'=>$reservations];
    }

    private function mapUrl(string $value):string
    {
        if($value==='')return '';if(strlen($value)>1000||!filter_var($value,FILTER_VALIDATE_URL)||parse_url($value,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Selecciona un punto válido en el mapa.');
        $host=strtolower((string)parse_url($value,PHP_URL_HOST));if(!in_array($host,['google.com','www.google.com','maps.google.com','maps.app.goo.gl','goo.gl','openstreetmap.org','www.openstreetmap.org'],true))throw new RuntimeException('La ubicación debe corresponder a un enlace de mapa permitido.');return $value;
    }

    private function coordinatesFromMap(string $url):?array
    {
        if($url==='' )return null;$url=rawurldecode($url);$patterns=['/@(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)/','/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/','/[?&](?:query|q)=(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)/i','/[?&]mlat=(-?\d{1,2}\.\d+).*?[&]mlon=(-?\d{1,3}\.\d+)/i'];
        foreach($patterns as $pattern)if(preg_match($pattern,$url,$m)){$lat=(float)$m[1];$lon=(float)$m[2];if($lat>=-90&&$lat<=90&&$lon>=-180&&$lon<=180)return [$lat,$lon];}
        return null;
    }

    private function structuredHours(mixed $input,string $existing):string
    {
        $days=['mon','tue','wed','thu','fri','sat','sun'];$labels=['mon'=>'Lunes','tue'=>'Martes','wed'=>'Miércoles','thu'=>'Jueves','fri'=>'Viernes','sat'=>'Sábado','sun'=>'Domingo'];
        if(!is_array($input)){if($input===null&&$existing!==''&&str_starts_with($existing,'{'))return $existing;if(is_string($input)&&strlen($input)<=4000)return trim($input);return '';}
        $result=[];foreach($days as $day){$row=is_array($input[$day]??null)?$input[$day]:[];$closed=!empty($row['closed']);$open=trim((string)($row['open']??''));$close=trim((string)($row['close']??''));
            if($closed){$result[$day]=['closed'=>true];continue;}if($open===''&&$close==='')continue;
            if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$open)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$close)||$open===$close)throw new RuntimeException('Completa una hora de apertura y cierre válidas para '.$labels[$day].', o marca cerrado.');
            $result[$day]=['closed'=>false,'open'=>$open,'close'=>$close];}
        return $result?json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):'';
    }

    private function optionalUrl(string $value,string $label):string
    {
        $value=trim($value);if($value==='' )return '';if(strlen($value)>500||!filter_var($value,FILTER_VALIDATE_URL)||!in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true))throw new RuntimeException('Revisa el enlace de '.$label.'.');return $value;
    }

    /** Uploaded photos are private source material and never become public media automatically. */
    private function storeImageReferences(int $businessId,mixed $uploads,array &$storedFiles,int $tierLimit):void
    {
        if(!is_array($uploads)||!isset($uploads['name'])||!is_array($uploads['name']))return;
        $pending=[];$total=0;
        foreach($uploads['name'] as $index=>$original){
            $error=(int)($uploads['error'][$index]??UPLOAD_ERR_NO_FILE);if($error===UPLOAD_ERR_NO_FILE)continue;
            if($error!==UPLOAD_ERR_OK)throw new RuntimeException('No se recibió una de las fotos. Vuelve a cargarla o revisa el límite de tamaño del servidor.');
            $tmp=(string)($uploads['tmp_name'][$index]??'');$bytes=(int)($uploads['size'][$index]??0);
            if(!is_uploaded_file($tmp)||$bytes<1||$bytes>8*1024*1024)throw new RuntimeException('Cada foto de referencia debe pesar máximo 8 MB.');
            $info=@getimagesize($tmp);$mime=$info['mime']??'';$detected=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!$info||$detected!==$mime||!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Sube fotos JPG, PNG o WebP válidas. No se admiten SVG ni otros archivos.');
            if((int)$info[0]<240||(int)$info[1]<160||(int)$info[0]>10000||(int)$info[1]>10000)throw new RuntimeException('Cada foto debe medir al menos 240 × 160 y no superar 10000 píxeles por lado.');
            $total+=$bytes;if($total>32*1024*1024)throw new RuntimeException('Las fotos de referencia pueden sumar hasta 32 MB por guardado.');
            $safeOriginal=preg_replace('~^.*[\\\\/]~','',(string)$original)??'imagen';$pending[]=['tmp'=>$tmp,'name'=>$safeOriginal,'bytes'=>$bytes,'width'=>(int)$info[0],'height'=>(int)$info[1],'mime'=>$mime];
        }
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM business_image_references WHERE business_id=?');$q->execute([$businessId]);$existing=(int)$q->fetchColumn();
        if($pending&&$tierLimit===0)throw new RuntimeException('La suscripción seleccionada no solicita imágenes de referencia.');
        if($existing+count($pending)>max($existing,$tierLimit))throw new RuntimeException('Esta suscripción permite pedir hasta '.$tierLimit.' referencias privadas: logo y material visual incluidos.');
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $insert=$this->pdo->prepare('INSERT INTO business_image_references(business_id,storage_name,original_name,mime_type,file_size,width,height) VALUES(?,?,?,?,?,?,?)');
        foreach($pending as $image){$name=bin2hex(random_bytes(20)).'.'.$extensions[$image['mime']];$path=$this->referenceStorage.'/'.$name;if(!move_uploaded_file($image['tmp'],$path))throw new RuntimeException('No se pudo guardar una foto de referencia en el almacenamiento privado.');@chmod($path,0600);$storedFiles[]=$path;$insert->execute([$businessId,$name,substr($image['name'],0,255),$image['mime'],$image['bytes'],$image['width'],$image['height']]);}
    }

    private function referenceLimit(string $plan):int{return match($plan){'featured'=>1,'page'=>2,'page_premium'=>4,default=>0};}

    private function availableSlug(int $destinationId,string $base,int $excludeId):string{$candidate=$base;$suffix=2;while(true){$q=$this->pdo->prepare('SELECT id FROM businesses WHERE destination_id=? AND slug=?');$q->execute([$destinationId,$candidate]);$found=$q->fetchColumn();if($found===false||(int)$found===$excludeId)return $candidate;$candidate=$base.'-'.$suffix++;}}
    private function slug(string $value):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);$slug=strtolower((string)$ascii);$slug=preg_replace('/[^a-z0-9]+/','-',$slug)??'';return trim($slug,'-')?:'negocio';}
    private function uuid():string{$hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-'.dechex((hexdec($hex[16])&0x3)|0x8).substr($hex,17,3).'-'.substr($hex,20,12);}
}

<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessServiceSections.php';

require_once __DIR__.'/ExperienceOperatorAddon.php';
require_once __DIR__.'/SubscriptionChangeService.php';
final class BusinessSubscriptionAdminService
{
    private const FREQUENCIES=['monthly'=>1,'quarterly'=>3,'semiannual'=>6,'annual'=>12];

    public function __construct(private PDO $pdo,private ?DateTimeImmutable $clock=null) {}

    public function options(): array
    {
        $plans=$this->pdo->query('SELECT p.code,p.max_gallery_photos,p.max_summary_chars,p.max_description_chars,p.max_brand_story_chars,p.max_featured_services_chars,t.name,t.summary FROM subscription_plans p JOIN subscription_plan_translations t ON t.plan_code=p.code AND t.locale="es" WHERE p.is_active=1 AND p.code IN ("free","featured","page","page_premium") ORDER BY p.display_order')->fetchAll(PDO::FETCH_ASSOC);
        foreach($plans as &$plan){
            $plan['max_gallery_photos']=(int)$plan['max_gallery_photos'];
            $plan['text_limits']=['summary'=>(int)$plan['max_summary_chars'],'description'=>(int)$plan['max_description_chars'],'brand_story'=>(int)$plan['max_brand_story_chars'],'featured_services'=>(int)$plan['max_featured_services_chars']];
            $q=$this->pdo->prepare('SELECT billing_frequency,amount,currency FROM subscription_plan_prices WHERE plan_code=?');$q->execute([$plan['code']]);
            $plan['prices']=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $price){$amount=$price['amount']===null?null:(float)$price['amount'];$currency=(string)$price['currency'];$valid=$amount!==null&&$amount>0&&preg_match('/^[A-Z]{3}$/',$currency)===1;$plan['prices'][$price['billing_frequency']]=['amount'=>$valid?$amount:null,'currency'=>$valid?$currency:'COP'];}
            $f=$this->pdo->prepare('SELECT feature_code FROM subscription_plan_features WHERE plan_code=? AND included=1');$f->execute([$plan['code']]);$plan['features']=$f->fetchAll(PDO::FETCH_COLUMN);
            $plan['requirements']=[...self::requirements((string)$plan['code'],(int)$plan['max_gallery_photos']),self::contentLimitDescription($plan['text_limits'])];
        }
        unset($plan);return $plans;
    }

    private static function contentLimitDescription(array $limits):string
    {
        $parts=[];
        foreach(['summary'=>'descripción breve','description'=>'descripción ampliada','brand_story'=>'historia del negocio','featured_services'=>'servicios destacados'] as $field=>$label){$limit=(int)($limits[$field]??0);if($limit>0)$parts[]=$label.': hasta '.$limit.' caracteres';}
        return $parts?'Límites de contenido: '.implode('; ',$parts).'.':'Este nivel no admite descripciones públicas.';
    }

    private static function requirements(string $plan,int $galleryPhotos):array
    {
        $base=['Nombre, categoría y destino. Para esta suscripción paga, completa la dirección y confirma su punto en el mapa.'];
        return match($plan){
            'free'=>['Solo requiere nombre, categoría y destino. No exige dirección, mapa, teléfono, descripciones, horarios ni imágenes. Los canales de investigación se conservan privados.'],
            'basic'=>[...$base,'Añade: un resumen corto y al menos un celular/WhatsApp público para contacto directo. No solicita descripción larga, logo, fotos, horarios ni datos operativos.'],
            'featured'=>[...$base,'Añade: descripción intermedia, contacto público y tarjeta destacada. Puede entregar su logo como referencia privada; si no tiene, la tarjeta usa sus iniciales. No incluye fotografías.'],
            'page'=>[...$base,'Añade página propia: descripción completa, logo (si no tiene, se muestran sus iniciales), celular/WhatsApp público, horarios y rango de precios. Puede entregar dos referencias privadas: logo y una imagen para preparar la portada. El equipo crea y revisa la imagen final; las referencias no se publican directamente. Menú/catálogo, reservas, redes y capacidad se piden solo cuando aplican.'],
            'page_premium'=>[...$base,'Incluye todo lo de Página y dos secciones extra obligatorias: historia o propuesta del negocio y servicios destacados, traducidos a los idiomas del portal. Se solicita logo; si no tiene, se usan iniciales. Las referencias son privadas; se prepara una portada y hasta '.$galleryPhotos.' fotos adicionales (tres fotos públicas en total) para revisión. No se publican directamente las referencias. Menú/catálogo, reservas, redes, precios y capacidad se completan cuando aplican.'],
            default=>$base,
        };
    }

    public function save(array $input): int
    {
        $businessId=filter_var($input['business_id']??null,FILTER_VALIDATE_INT);
        $q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?');$q->execute([$businessId]);
        if(!$businessId||!$q->fetchColumn())throw new RuntimeException('Selecciona un negocio existente.');
        $plan=(string)($input['plan_code']??'');if(!in_array($plan,['free','featured','page','page_premium'],true))throw new RuntimeException('Selecciona una suscripción vigente.');$q=$this->pdo->prepare('SELECT code FROM subscription_plans WHERE code=? AND is_active=1');$q->execute([$plan]);
        if(!$q->fetchColumn())throw new RuntimeException('Selecciona una suscripción válida.');
        $frequency=trim((string)($input['billing_frequency']??''));
        if($plan==='free')$frequency='';elseif(!isset(self::FREQUENCIES[$frequency]))throw new RuntimeException('Selecciona una frecuencia de pago.');
        $publishNotBefore=$this->dateOnly((string)($input['publish_not_before']??''));
        $amount=null;$currency='COP';
        if($plan!=='free'){
            $q=$this->pdo->prepare('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?');$q->execute([$plan,$frequency]);$price=$q->fetch(PDO::FETCH_ASSOC);
            if(!$price)throw new RuntimeException('No hay una tarifa cargada para esta suscripción y frecuencia.');
            $amount=$price['amount']===null?null:(float)$price['amount'];$currency=(string)$price['currency'];
            if($amount===null||$amount<=0)throw new RuntimeException('Esta suscripción aún no tiene tarifa definida. No se puede crear ni cotizar hasta que el administrador configure el precio.');
            if(preg_match('/^[A-Z]{3}$/',$currency)!==1)throw new RuntimeException('La moneda de esta tarifa no es válida. Corrige el catálogo de precios antes de crear la suscripción.');
            if(!empty($input['payment_reviewed'])&&($amount===null||$amount<=0))throw new RuntimeException('No se puede confirmar un pago mientras la tarifa no esté configurada.');
        }
        $ownTransaction=!$this->pdo->inTransaction();$savepoint='subscription_'.bin2hex(random_bytes(6));
        if($ownTransaction)$this->pdo->beginTransaction();else $this->pdo->exec('SAVEPOINT '.$savepoint);
        try {
            // All subscription requests for a business serialize on the same InnoDB row.
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.$lock);$q->execute([$businessId]);
            if(!$q->fetchColumn())throw new RuntimeException('El negocio ya no está disponible.');
            $changes=new SubscriptionChangeService($this->pdo);$changes->preservePaidRequests((int)$businessId);
            $changeQuote=$changes->quote((int)$businessId,$plan,$frequency,null,$this->clock);
            $amount=$plan==='free'?null:SubscriptionChangeService::money($changeQuote['due_cents']);
            $status=$plan==='free'?'pending_publication':'pending';$reviewed=null;
            $this->pdo->prepare('UPDATE business_subscriptions SET status="cancelled" WHERE business_id=? AND status IN ("pending","pending_publication","paid_pending_publication")')->execute([$businessId]);
            $q=$this->pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,reviewed_at,publish_not_before,quoted_amount,currency) VALUES(?,?,?,?,NULL,NULL,?,?,?,?)');
            $q->execute([$businessId,$plan,$frequency?:null,$status,$reviewed,$publishNotBefore,$amount,$currency]);
            $id=(int)$this->pdo->lastInsertId();$changes->capture($id,$changeQuote);
            if($ownTransaction)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);
            return $id;
        } catch(Throwable $error) {
            if($ownTransaction){if($this->pdo->inTransaction())$this->pdo->rollBack();}
            elseif($this->pdo->inTransaction()){$this->pdo->exec('ROLLBACK TO SAVEPOINT '.$savepoint);$this->pdo->exec('RELEASE SAVEPOINT '.$savepoint);}
            throw $error;
        }
    }

    /** Read-only readiness check using the same rules as the publication action. */
    public function publicationBlockers(int $businessId): array
    {
        $q=$this->pdo->prepare('SELECT * FROM businesses WHERE id=?');$q->execute([$businessId]);
        try {
            $this->requirePublicationReady($q->fetch(PDO::FETCH_ASSOC),$businessId,(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d'));
            return [];
        } catch (RuntimeException $error) {
            if($error instanceof PDOException)throw $error;
            return [$error->getMessage()];
        }
    }

    private function requirePublicationReady(array|false $business,int $businessId,string $localPublishDate,bool $existingPublication=false): array
    {
            if(!$business)throw new RuntimeException('El negocio no existe.');
            if($business['information_status']==='PUBLICO'&&$business['record_origin']!=='public_import')throw new RuntimeException('Verifica la información y la autorización del negocio antes de publicarlo.');
            if(!in_array($business['information_status'],['PUBLICO','VERIFICADO','ACTIVO'],true))throw new RuntimeException('Verifica la información y la autorización del negocio antes de publicarlo.');
            if($business['operation_status']==='CERRADO_DEFINITIVO')throw new RuntimeException('Un negocio con cierre definitivo no se puede publicar.');
            if(($business['latitude']===null)!==($business['longitude']===null))throw new RuntimeException('La latitud y la longitud deben estar completas juntas.');
            if($business['latitude']!==null&&((float)$business['latitude'] < -90||(float)$business['latitude']>90||(float)$business['longitude'] < -180||(float)$business['longitude']>180))throw new RuntimeException('Las coordenadas están fuera de rango.');
            $q=$this->pdo->prepare('SELECT * FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');$q->execute([$businessId]);$sub=$q->fetch(PDO::FETCH_ASSOC);
            if(!$sub)throw new RuntimeException('Registra la suscripción antes de publicar el negocio.');
            $operatorService=new ExperienceOperatorAddon($this->pdo);$operator=$operatorService->forSubscription((int)$sub['id']);if($operator){$operatorService->requireEligible($businessId);$destinationQuery=$this->pdo->prepare('SELECT destination_id FROM businesses WHERE id=?');$destinationQuery->execute([$businessId]);$operatorService->quote($sub['plan_code'],(string)$sub['billing_frequency'],(int)$destinationQuery->fetchColumn(),(int)$operator['experience_id']);}
            if($sub['plan_code']!=='free'&&(trim((string)$business['location_map_url'])===''||!(bool)$business['location_confirmed']))throw new RuntimeException('Confirma en el mapa que la dirección y el punto corresponden al destino antes de publicar.');
            if(trim((string)$business['address_text'])===''&&($business['latitude']===null||$business['longitude']===null)&&$sub['plan_code']!=='free')throw new RuntimeException('Completa una dirección o un punto de ubicación antes de publicar.');
            if($sub['plan_code']!=='free'){$q=$this->pdo->prepare('SELECT COUNT(*) FROM business_contacts WHERE business_id=? AND channel IN ("phone","whatsapp") AND contact_value<>""');$q->execute([$businessId]);if((int)$q->fetchColumn()<1)throw new RuntimeException('Registra un celular o WhatsApp de contacto antes de publicar.');}
            if($sub['publish_not_before']!==null&&$localPublishDate<$sub['publish_not_before'])throw new RuntimeException('La fecha elegida todavía no permite publicar este negocio.');
            if($sub['plan_code']!=='free'&&(!($existingPublication&&$sub['status']==='active')&&($sub['status']!=='paid_pending_publication'||$sub['reviewed_at']===null)))throw new RuntimeException('Confirma el pago revisado antes de publicar esta suscripción.');
            if($sub['plan_code']!=='free'&&$business['information_status']!=='ACTIVO')throw new RuntimeException('Una suscripción paga requiere que el negocio esté reclamado y autorizado como ACTIVO.');
            if($sub['plan_code']==='free'&&!in_array($sub['status'],['pending_publication','active'],true))throw new RuntimeException('La suscripción Gratis no está lista para publicarse.');
        return $sub;
    }

    /** Activate the selected tier only when an authorized publication actually goes live. */
    public function publish(int $businessId,?DateTimeImmutable $publishedAt=null,?callable $precondition=null,?int $editorialVersion=null): void
    {
        $publishedAt??=new DateTimeImmutable('now',new DateTimeZone('America/Bogota'));
        $localPublishDate=$publishedAt->setTimezone(new DateTimeZone('America/Bogota'))->format('Y-m-d');
        $stamp=$publishedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $ownTransaction=!$this->pdo->inTransaction();if($ownTransaction)$this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.$lock);$q->execute([$businessId]);
            if($precondition!==null)$precondition();
            // Legacy publication cannot bypass an editorial version already in progress.
            try{$q=$this->pdo->prepare('SELECT id,status FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC LIMIT 1');$q->execute([$businessId]);$editorial=$q->fetch(PDO::FETCH_ASSOC);}
            catch(PDOException $error){if(str_contains($error->getMessage(),'no such table')||($error->errorInfo[1]??0)===1146)$editorial=false;else throw $error;}
            if($editorial&&((int)$editorial['id']!==$editorialVersion||$editorial['status']!=='owner_approved'))throw new RuntimeException('Este negocio tiene una versión editorial: publícala desde su revisión final, después de la aprobación del comerciante.');

            $q=$this->pdo->prepare('SELECT id,address_text,latitude,longitude,location_map_url,location_confirmed,information_status,operation_status,record_origin FROM businesses WHERE id=?');$q->execute([$businessId]);$business=$q->fetch(PDO::FETCH_ASSOC);
            $sub=$this->requirePublicationReady($business,$businessId,$localPublishDate);
            $activation=(new SubscriptionChangeService($this->pdo))->activation($sub,$publishedAt);
            $renewalOf=null;
            if($sub['plan_code']!=='free'&&!$activation){
                $previous=$this->pdo->prepare('SELECT id FROM business_subscriptions WHERE business_id=? AND id<>? AND plan_code<>"free" AND status IN ("active","expired") AND starts_at IS NOT NULL AND published_at IS NOT NULL AND reviewed_at IS NOT NULL ORDER BY starts_at DESC,id DESC LIMIT 1');
                $previous->execute([$businessId,$sub['id']]);$renewalOf=$previous->fetchColumn();$renewalOf=$renewalOf===false?null:(int)$renewalOf;
            }
            $this->validateContent($businessId,$sub['plan_code']);
            // Preserve the contractual end date of a previous term. It is needed to
            // distinguish renewals made by the expiry date from later reactivations.
            $q=$this->pdo->prepare('UPDATE business_subscriptions SET status="expired" WHERE business_id=? AND status="active"');$q->execute([$businessId]);
            $starts=$stamp;
            $ends=null;
            if($sub['plan_code']!=='free')$ends=self::addCalendarMonths($publishedAt,self::FREQUENCIES[$sub['billing_frequency']]??0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            if($activation)$ends=$activation['ends_at'];
            $q=$this->pdo->prepare('UPDATE business_subscriptions SET status="active",starts_at=?,ends_at=?,published_at=?,renewal_of_subscription_id=? WHERE id=?');$q->execute([$starts,$ends,$stamp,$renewalOf,$sub['id']]);
            $q=$this->pdo->prepare('UPDATE businesses SET publication_status="published",updated_at=CURRENT_TIMESTAMP WHERE id=?');$q->execute([$businessId]);
            if($ownTransaction)$this->pdo->commit();
        }catch(Throwable $error){if($ownTransaction&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function validateEditorialUpdate(int $businessId):void
    {
        $q=$this->pdo->prepare('SELECT * FROM businesses WHERE id=?');$q->execute([$businessId]);
        $sub=$this->requirePublicationReady($q->fetch(PDO::FETCH_ASSOC),$businessId,(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d'),true);
        if($sub['status']!=='active'||($sub['ends_at']!==null&&$sub['ends_at']<=gmdate('Y-m-d H:i:s')))throw new RuntimeException('La suscripción debe estar vigente.');
        $this->validateContent($businessId,$sub['plan_code']);
    }

    /** Read-only content check, shared by first publication and editorial updates. */
    public function validateContent(int $businessId,string $plan):void
    {
        $sub=['plan_code'=>$plan];
            $planQuery=$this->pdo->prepare('SELECT max_gallery_photos,max_summary_chars,max_description_chars,max_brand_story_chars,max_featured_services_chars FROM subscription_plans WHERE code=?');$planQuery->execute([$sub['plan_code']]);$limits=$planQuery->fetch(PDO::FETCH_ASSOC);if(!$limits)throw new RuntimeException('No se encontró la suscripción.');$photoLimit=(int)$limits['max_gallery_photos'];
            $mediaQuery=$this->pdo->prepare('SELECT role,COUNT(*) AS amount FROM business_media WHERE business_id=? GROUP BY role');$mediaQuery->execute([$businessId]);$mediaCounts=[];foreach($mediaQuery->fetchAll(PDO::FETCH_ASSOC) as $media)$mediaCounts[$media['role']]=(int)$media['amount'];
            if($sub['plan_code']==='page'||$sub['plan_code']==='page_premium'){
                if(($mediaCounts['cover']??0)<1)throw new RuntimeException('Las suscripciones Página requieren una portada aprobada.');
                if(($mediaCounts['gallery']??0)>$photoLimit)throw new RuntimeException('La galería supera el máximo de '.$photoLimit.' fotos de esta suscripción.');
            }elseif(($mediaCounts['gallery']??0)>0||($mediaCounts['cover']??0)>0)throw new RuntimeException('Este nivel no incluye fotos públicas; quita la portada y galería o elige una suscripción con página.');
            if(in_array($sub['plan_code'],['basic','featured','page','page_premium'],true)){
                $q=$this->pdo->prepare('SELECT COUNT(*) FROM business_contacts WHERE business_id=? AND is_public=1 AND channel IN ("phone","whatsapp") AND contact_value<>""');
                $q->execute([$businessId]);
                if((int)$q->fetchColumn()<1)throw new RuntimeException('Este nivel incluye contacto directo: publica al menos un teléfono celular o WhatsApp autorizado.');
            }
            $q=$this->pdo->prepare('SELECT locale,display_name,summary,description,brand_story,featured_services FROM business_translations WHERE business_id=?');$q->execute([$businessId]);$translations=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$translations[$row['locale']]=$row;
            foreach($translations as $locale=>$translation)foreach(['summary','description','brand_story','featured_services'] as $field){$limit=(int)$limits['max_'.$field.'_chars'];if($limit>0&&preg_match_all('/./us',(string)($translation[$field]??''))>$limit)throw new RuntimeException('El campo '.$field.' en '.$locale.' supera el límite de '.$limit.' caracteres de esta suscripción.');}
            if($sub['plan_code']==='page_premium')BusinessServiceSections::publication($this->pdo,$businessId);
            $requiredSpanish=['display_name'];
            if(in_array($sub['plan_code'],['basic','featured','page','page_premium'],true))$requiredSpanish[]='summary';
            if(in_array($sub['plan_code'],['featured','page','page_premium'],true))$requiredSpanish[]='description';
            if($sub['plan_code']==='page_premium')array_push($requiredSpanish,'brand_story','featured_services');
            foreach($requiredSpanish as $field)if(trim((string)($translations['es'][$field]??''))==='')throw new RuntimeException('Completa la información adicional que solicita esta suscripción antes de publicar.');
            foreach(['en','pt-BR'] as $locale){$row=$translations[$locale]??null;if(!$row)throw new RuntimeException('Completa y traduce el contenido a español, inglés y portugués antes de publicar.');foreach($requiredSpanish as $field){$spanish=trim((string)($translations['es'][$field]??''));$translated=trim((string)($row[$field]??''));if($translated===''||($field!=='display_name'&&$translated===$spanish))throw new RuntimeException('Completa y traduce el contenido a español, inglés y portugués antes de publicar.');}}
    }

    private function dateOnly(string $value):?string
    {
        $value=trim($value);if($value==='')return null;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==$value)throw new RuntimeException('Elige una fecha válida para solicitar la publicación.');
        if($date->format('Y-m-d')<(new DateTimeImmutable('today',new DateTimeZone('America/Bogota')))->format('Y-m-d'))throw new RuntimeException('La fecha de publicación no puede estar en el pasado.');
        return $value;
    }

    private static function addCalendarMonths(DateTimeImmutable $date,int $months):DateTimeImmutable
    {
        if($months<1)throw new RuntimeException('La frecuencia de la suscripción no es válida.');
        $year=(int)$date->format('Y');$month=(int)$date->format('n')+$months;$day=(int)$date->format('j');
        $year+=(int)floor(($month-1)/12);$month=(($month-1)%12)+1;
        $lastDay=(int)(new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$month),$date->getTimezone()))->format('t');
        return $date->setDate($year,$month,min($day,$lastDay));
    }
}

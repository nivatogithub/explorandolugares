<?php
declare(strict_types=1);

/**
 * Builds the private source package used by the editorial team.  It is
 * intentionally one-way: legal evidence, account data, payments, phone and
 * email never enter the ZIP sent to a creative or translation workflow.
 */
final class BusinessEditorialPackage
{
    public function __construct(private PDO $pdo,private string $referenceStorage) {}

    /**
     * Private administrative checkpoint before editorial material can leave the
     * application.  The returned identifiers are only for administrator links;
     * none of this evidence is ever copied into an editorial ZIP.
     */
    public function readiness(int $businessId):array
    {
        $business=$this->business($businessId);
        $application=$this->pdo->prepare('SELECT id,status,relationship_code,reviewed_at FROM business_account_applications WHERE business_id=? ORDER BY CASE status WHEN "approved" THEN 0 ELSE 1 END,reviewed_at DESC,id DESC LIMIT 1');
        $application->execute([$businessId]);$application=$application->fetch(PDO::FETCH_ASSOC)?:null;
        $documents=[];$titleDocument=null;
        if($application){
            $evidence=$this->pdo->prepare('SELECT id,evidence_type,original_name,verified_at FROM business_account_application_evidence WHERE application_id=? ORDER BY id');
            $evidence->execute([(int)$application['id']]);$documents=$evidence->fetchAll(PDO::FETCH_ASSOC);
            foreach($documents as $document)if($document['evidence_type']==='business_registration'){$titleDocument=$document;break;}
        }
        $subscription=$this->pdo->prepare('SELECT id,plan_code,status,reviewed_at FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');
        $subscription->execute([$businessId]);$subscription=$subscription->fetch(PDO::FETCH_ASSOC)?:null;
        $payment=null;
        if($subscription){
            $proof=$this->pdo->prepare('SELECT id,status,original_name,reviewed_at FROM business_subscription_payment_evidence WHERE subscription_id=? ORDER BY id DESC LIMIT 1');
            $proof->execute([(int)$subscription['id']]);$payment=$proof->fetch(PDO::FETCH_ASSOC)?:null;
        }
        $titleReady=$application!==null&&$application['status']==='approved'&&$titleDocument!==null&&!empty($titleDocument['verified_at']);
        $paidPlan=$subscription!==null&&$subscription['plan_code']!=='free';
        $paymentReady=$paidPlan?$subscription['status']==='paid_pending_publication'&&$payment!==null&&$payment['status']==='approved':$subscription!==null&&in_array($subscription['status'],['pending_publication','active'],true);
        $blockers=[];
        if(!$titleReady)$blockers[]='Falta validar el documento que respalda la titularidad o representación del negocio.';
        if(!$subscription)$blockers[]='Falta seleccionar una suscripción.';
        elseif(!$paymentReady)$blockers[]=$paidPlan?'Falta confirmar el pago con su comprobante.':'La suscripción Gratis todavía no está lista para revisión.';
        return ['business'=>$business,'application'=>$application,'documents'=>$documents,'title_document'=>$titleDocument,'subscription'=>$subscription,'payment'=>$payment,'title_ready'=>$titleReady,'payment_ready'=>$paymentReady,'ready'=>$titleReady&&$paymentReady,'blockers'=>$blockers];
    }

    public function requireReady(int $businessId):array
    {
        $readiness=$this->readiness($businessId);
        if(!$readiness['ready'])throw new RuntimeException(implode(' ', $readiness['blockers']));
        return $readiness;
    }

    public function exportSource(int $businessId,string $actor=''):string
    {
        if(!class_exists(ZipArchive::class))throw new RuntimeException('El servidor no tiene disponible la extensión ZIP.');
        $business=$this->requireReady($businessId)['business'];
        $temp=tempnam(sys_get_temp_dir(),'explorando-editorial-');
        if($temp===false)throw new RuntimeException('No se pudo preparar el paquete editorial.');
        $zipPath=$temp.'.zip';@unlink($temp);
        $zip=new ZipArchive();
        if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){@unlink($zipPath);throw new RuntimeException('No se pudo crear el paquete editorial.');}
        try{
            $sources=[];$q=$this->pdo->prepare('SELECT storage_name,original_name,mime_type,file_size,width,height FROM business_image_references WHERE business_id=? ORDER BY id');$q->execute([$businessId]);
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $index=>$image){
                $extension=match($image['mime_type']){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>null};
                if($extension===null||!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',(string)$image['storage_name']))continue;
                $path=$this->referenceStorage.'/'.$image['storage_name'];if(!is_file($path)||(int)filesize($path)!==(int)$image['file_size'])continue;
                $entry='source-images/'.str_pad((string)($index+1),2,'0',STR_PAD_LEFT).'.'.$extension;
                if(!$zip->addFile($path,$entry))throw new RuntimeException('No se pudo añadir una fotografía al paquete editorial.');
                $sources[]=['file'=>$entry,'original_name'=>$image['original_name'],'mime_type'=>$image['mime_type'],'width'=>(int)$image['width'],'height'=>(int)$image['height']];
            }
            $brief=['schema'=>'explorandolugares-editorial-source-v1','business'=>['name'=>$business['name'],'destination'=>$business['destination'],'category'=>$business['category'],'plan'=>$business['plan']],'content'=>['summary'=>$business['summary'],'description'=>$business['description']],'source_images'=>$sources,'rules'=>['required_locales'=>['es','en','pt-BR'],'never_include'=>['identity documents','business registration documents','account data','payment evidence','private contact data'],'result_contract'=>'Return a separate editorial ZIP with only final images and public-facing ES, EN and PT-BR texts.']];
            if(!$zip->addFromString('brief.json',json_encode($brief,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))throw new RuntimeException('No se pudo escribir la ficha editorial.');
            $imageProvenanceExample=['creation_route'=>'independent_composition','tool_model'=>'','created_on'=>'YYYY-MM-DD','responsible'=>'','prompt'=>'','factual_basis'=>[''],'reference_assets'=>[''],'reviewed_by'=>'','review_note'=>''];
            $resultExample=['schema'=>'explorandolugares-editorial-result-v1','translations'=>['es'=>['display_name'=>'','summary'=>'','description'=>''],'en'=>['display_name'=>'','summary'=>'','description'=>''],'pt-BR'=>['display_name'=>'','summary'=>'','description'=>'']],'images'=>['logo'=>['file'=>'images/logo.webp','alt'=>['es'=>'','en'=>'','pt-BR'=>''],'caption'=>['es'=>'','en'=>'','pt-BR'=>''],'provenance'=>$imageProvenanceExample],'cover'=>['file'=>'images/cover.webp','alt'=>['es'=>'','en'=>'','pt-BR'=>''],'caption'=>['es'=>'','en'=>'','pt-BR'=>''],'provenance'=>$imageProvenanceExample]],'products'=>[['translations'=>['es'=>['name'=>'','description'=>''],'en'=>['name'=>'','description'=>''],'pt-BR'=>['name'=>'','description'=>'']],'image'=>['file'=>'images/product-1.webp','alt'=>['es'=>'','en'=>'','pt-BR'=>''],'caption'=>['es'=>'','en'=>'','pt-BR'=>''],'provenance'=>$imageProvenanceExample]]]];
            $readme="# Paquete editorial de {$business['name']}\n\n".
                "Este ZIP contiene material editorial privado: `brief.json` y las imágenes originales referenciadas allí. No contiene ni debe recibir documentos legales, datos de cuenta, comprobantes de pago, números de identificación personal, correos ni teléfonos.\n\n".
                "## Trabajo editorial\n\n".
                "1. Antes de usar una referencia, escoge una vía permitida: transformación autorizada de una foto propia/autorizada, o composición nueva independiente a partir de hechos comprobables. Una foto vista en internet no autoriza subirla a un generador ni producir una adaptación cercana.\n".
                "2. Genera una ilustración editorial semirrealista: caricatura realista, con trazos de tinta y pintura tipo gouache claramente visibles, paleta natural y coherente con Explorando Lugares. No debe parecer una fotografía retocada. Conserva los rasgos que identifican el negocio: fachada, distribución, materiales, entorno y ubicación cuando estén confirmados.\n".
                "3. No inventes personas, señalización, arquitectura, premios, servicios, productos, vistas, rutas ni condiciones actuales. No incluyas texto, marcas de agua, logotipos no autorizados, monumentos adicionales ni el estilo de un artista vivo. No copies exactamente la composición de una fotografía.\n".
                "4. Revisa cada salida a tamaño de tarjeta y de portada: el negocio debe reconocerse sin el título, sin deformaciones ni elementos falsos. Si no hay evidencia suficiente, no rellenes el espacio con una escena genérica.\n".
                "5. Mejora los textos públicos y completa español (`es`), inglés (`en`) y portugués de Brasil (`pt-BR`). Respeta: nombre hasta 180 caracteres, presentación hasta 450; la descripción ampliada solo se permite para Premium y tiene máximo 1.200.\n".
                "6. Devuelve un ZIP nuevo con `editorial.json` y las imágenes finales dentro de `images/`. No incluyas este ZIP original ni archivos adicionales. El primer resultado se puede importar aunque la ficha privada siga en borrador. Si el administrador ya envió una prepublicación al propietario, no generes otro ZIP hasta que este solicite correcciones.\n\n".
                "## Formato de editorial.json\n\n```json\n".
                json_encode($resultExample,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n```\n\n".
                "Incluye `images.logo` cuando exista un logotipo aprobado y `images.cover` para Página y Premium. Para Premium puedes añadir hasta cinco productos. Para planes sin página no incluyas imágenes ni productos. El objeto `provenance` es obligatorio por imagen y se conserva privado: registra la vía de creación, prompt, herramienta/modelo, fecha, responsable, fundamento factual, referencias visuales y revisión editorial. La IA no convierte una imagen en libre de derechos ni la deja publicada automáticamente.";
            if(!$zip->addFromString('README.md',$readme))throw new RuntimeException('No se pudo escribir la guía del paquete editorial.');
        }catch(Throwable $error){$zip->close();@unlink($zipPath);throw $error;}
        $zip->close();
        if($actor!=='')try{
            require_once __DIR__.'/BusinessEditorialService.php';
            (new BusinessEditorialService($this->pdo,$this->referenceStorage))->beginEditorial($businessId,$actor);
        }catch(Throwable $error){@unlink($zipPath);throw $error;}
        return $zipPath;
    }

    /** Import a prepared public-facing proposal; legal and account files are never accepted here. */
    public function importPrepared(int $businessId,array $upload,string $publicRoot,string $actor):int
    {
        require_once __DIR__.'/BusinessEditorialService.php';
        if(trim($actor)==='')throw new RuntimeException('La importación requiere un administrador.');
        $this->requireReady($businessId);
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??''))||(int)($upload['size']??0)<1||(int)($upload['size']??0)>32*1024*1024)throw new RuntimeException('Sube un ZIP editorial de máximo 32 MB.');
        if(!class_exists(ZipArchive::class))throw new RuntimeException('El servidor no tiene disponible la extensión ZIP.');
        $zip=new ZipArchive();if($zip->open((string)$upload['tmp_name'])!==true)throw new RuntimeException('El archivo no es un ZIP editorial válido.');
        $created=[];$stored=[];$ownTransaction=!$this->pdo->inTransaction();if($ownTransaction)$this->pdo->beginTransaction();
        try{
            if($zip->numFiles<1||$zip->numFiles>12)throw new RuntimeException('El paquete editorial contiene demasiados archivos.');
            $manifest=$this->manifest($zip);
            $service=new BusinessEditorialService($this->pdo,$this->referenceStorage);if(!$service->ready())throw new RuntimeException('Instala primero el circuito editorial.');
            $latest=$service->latest($businessId);if($latest&&!in_array($latest['status'],['draft','in_editorial','changes_requested'],true))throw new RuntimeException('La última versión ya está en aprobación o publicada; espera una solicitud de correcciones antes de importar otra propuesta.');
            $plan=$service->options($businessId)['code'];$translations=$this->translations($manifest['translations']??[],$plan);
            $images=is_array($manifest['images']??null)?$manifest['images']:[];
            $input=['translations'=>$translations,'media'=>['logo'=>0,'cover'=>0,'gallery'=>[]]];
            $logo=$images['logo']??null;if($logo!==null){if(!is_array($logo))throw new RuntimeException('El logo editorial no es válido.');$input['media']['logo']=$this->storePreparedImage($zip,$logo,$businessId,$publicRoot,$created,$stored,$actor);}
            if(in_array($plan,['page','page_premium'],true)){
                $cover=$images['cover']??null;if(!is_array($cover))throw new RuntimeException('El paquete debe incluir una portada para esta suscripción.');
                $input['media']['cover']=$this->storePreparedImage($zip,$cover,$businessId,$publicRoot,$created,$stored,$actor);
            }
            if($plan==='page_premium'){
                $products=is_array($manifest['products']??null)?array_values($manifest['products']):[];if(count($products)>5)throw new RuntimeException('Premium admite hasta cinco productos o servicios.');
                $input['showcase']=['products'=>[]];
                foreach($products as $index=>$product){
                    if(!is_array($product)||!is_array($product['translations']??null)||!is_array($product['image']??null))throw new RuntimeException('Cada producto debe incluir textos e imagen.');
                    $input['showcase']['products'][$index]=['translations'=>$this->productTranslations($product['translations']),'asset_id'=>$this->storePreparedImage($zip,$product['image'],$businessId,$publicRoot,$created,$stored,$actor)];
                }
            }
            $version=$service->save(0,$businessId,(int)($latest['id']??0),$input,true,true,$actor,null);
            if($ownTransaction)$this->pdo->commit();$zip->close();return $version;
        }catch(Throwable $error){$zip->close();if($ownTransaction&&$this->pdo->inTransaction())$this->pdo->rollBack();foreach($stored as $file)if(is_file($file))@unlink($file);throw $error;}
    }

    private function manifest(ZipArchive $zip):array
    {
        $raw=$zip->getFromName('editorial.json');if(!is_string($raw)||strlen($raw)>262144)throw new RuntimeException('Falta editorial.json en el paquete.');
        $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($data)||($data['schema']??'')!=='explorandolugares-editorial-result-v1')throw new RuntimeException('El manifiesto editorial no corresponde al formato esperado.');return $data;
    }

    private function translations(mixed $translations,string $plan):array
    {
        if(!is_array($translations))throw new RuntimeException('Faltan las traducciones del paquete.');$limits=['display_name'=>180,'summary'=>450,'description'=>$plan==='page_premium'?1200:0];$out=[];
        foreach(['es','en','pt-BR'] as $locale){$row=$translations[$locale]??null;if(!is_array($row))throw new RuntimeException('Falta la traducción '.strtoupper($locale).'.');foreach($limits as $field=>$limit){$value=trim((string)($row[$field]??''));if($field==='display_name'&&$value==='')throw new RuntimeException('Falta el nombre en '.strtoupper($locale).'.');if($limit===0&&$value!=='')throw new RuntimeException('La descripción ampliada solo corresponde a Premium.');if($limit>0&&mb_strlen($value)>$limit)throw new RuntimeException('Un texto del paquete supera el límite permitido.');$out[$locale][$field]=$value;}}
        return $out;
    }

    private function productTranslations(mixed $translations):array
    {
        if(!is_array($translations))throw new RuntimeException('Faltan los textos del producto.');$out=[];foreach(['es','en','pt-BR'] as $locale){$row=$translations[$locale]??null;$name=trim((string)($row['name']??''));$description=trim((string)($row['description']??''));if(!is_array($row)||$name===''||mb_strlen($name)>100||mb_strlen($description)>180)throw new RuntimeException('Revisa los textos de cada producto en los tres idiomas.');$out[$locale]=['name'=>$name,'description'=>$description];}return $out;
    }

    private function storePreparedImage(ZipArchive $zip,array $image,int $business,string $publicRoot,array &$created,array &$stored,string $actor):int
    {
        $entry=(string)($image['file']??'');if(!preg_match('~^images/[A-Za-z0-9_-]{1,80}\.(jpg|jpeg|png|webp)$~D',$entry))throw new RuntimeException('La ruta de una imagen editorial no es válida.');
        $stat=$zip->statName($entry);if(!$stat||(int)$stat['size']<1||(int)$stat['size']>8*1024*1024)throw new RuntimeException('Cada imagen editorial debe pesar máximo 8 MB.');
        $bytes=$zip->getFromName($entry);if(!is_string($bytes)||(int)strlen($bytes)!==(int)$stat['size'])throw new RuntimeException('No se pudo leer una imagen del paquete.');
        $scratch=tempnam(sys_get_temp_dir(),'editorial-image-');if($scratch===false)throw new RuntimeException('No se pudo validar una imagen.');file_put_contents($scratch,$bytes);$info=@getimagesize($scratch);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($scratch);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!$info||!isset($extensions[$mime])||(int)$info[0]<240||(int)$info[1]<160||(int)$info[0]>10000||(int)$info[1]>10000){@unlink($scratch);throw new RuntimeException('Las imágenes deben ser JPG, PNG o WebP válidas, de al menos 240 × 160 píxeles.');}
        $alts=$image['alt']??[];if(!is_array($alts)){$alts=[];}foreach(['es','en','pt-BR'] as $locale)if(trim((string)($alts[$locale]??''))===''){@unlink($scratch);throw new RuntimeException('Cada imagen requiere texto alternativo en los tres idiomas.');}
        $provenance=$this->imageProvenance($image['provenance']??null,$actor);
        $folder=rtrim($publicRoot,'/').'/assets/negocios/'.$business;if(!is_dir($folder)&&!mkdir($folder,0755,true)&&!is_dir($folder)){@unlink($scratch);throw new RuntimeException('No se pudo preparar el directorio de imágenes.');}
        $name='editorial-'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];$path=$folder.'/'.$name;if(!rename($scratch,$path)){@unlink($scratch);throw new RuntimeException('No se pudo guardar una imagen editorial.');}$stored[]=$path;
        $this->pdo->prepare('INSERT INTO media_assets(asset_path,origin,creator,source_url,license_name,license_url,width,height,provenance_json) VALUES(?,?,?,?,?,?,?,?,?)')->execute(['assets/negocios/'.$business.'/'.$name,'ai_generated','Imagen editorial preparada para Explorando Lugares','','Generada o editada para publicación; revisión editorial realizada','',(int)$info[0],(int)$info[1],json_encode($provenance,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$id=(int)$this->pdo->lastInsertId();$created[]=$id;
        $caption=is_array($image['caption']??null)?$image['caption']:[];$insert=$this->pdo->prepare('INSERT INTO media_asset_translations(media_asset_id,locale,alt_text,caption) VALUES(?,?,?,?)');foreach(['es','en','pt-BR'] as $locale)$insert->execute([$id,$locale,trim((string)$alts[$locale]),trim((string)($caption[$locale]??''))]);return $id;
    }

    private function imageProvenance(mixed $value,string $actor):array
    {
        if(!is_array($value))throw new RuntimeException('Cada imagen debe incluir su procedencia editorial.');
        $route=(string)($value['creation_route']??'');if(!in_array($route,['authorized_transformation','independent_composition'],true))throw new RuntimeException('Indica si la imagen es una transformación autorizada o una composición independiente.');
        $required=['tool_model'=>200,'responsible'=>180,'prompt'=>4000,'reviewed_by'=>180,'review_note'=>1000];$out=['creation_route'=>$route];
        foreach($required as $key=>$limit){$text=trim((string)($value[$key]??''));if($text===''||mb_strlen($text)>$limit)throw new RuntimeException('Completa la trazabilidad editorial de cada imagen.');$out[$key]=$text;}
        $date=(string)($value['created_on']??'');$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('La procedencia de cada imagen debe incluir una fecha válida.');$out['created_on']=$date;
        foreach(['factual_basis','reference_assets'] as $key){$items=$value[$key]??null;if(!is_array($items)||count($items)<1||count($items)>10)throw new RuntimeException('Indica las fuentes y referencias usadas para cada imagen.');$out[$key]=[];foreach($items as $item){$item=trim((string)$item);if($item===''||mb_strlen($item)>500)throw new RuntimeException('Una fuente o referencia editorial no es válida.');$out[$key][]=$item;}}
        $out['imported_by']=$actor;$out['imported_at']=gmdate('c');return $out;
    }

    private function business(int $id):array
    {
        $q=$this->pdo->prepare('SELECT b.id,t.display_name AS name,t.summary,t.description,dt.display_name AS destination,ct.label AS category,COALESCE((SELECT plan_code FROM business_subscriptions WHERE business_id=b.id ORDER BY id DESC LIMIT 1),"free") AS plan FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destination_translations dt ON dt.destination_id=b.destination_id AND dt.locale="es" JOIN service_category_translations ct ON ct.category_code=b.category_code AND ct.locale="es" WHERE b.id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('El negocio no existe.');return $row;
    }
}

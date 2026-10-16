<?php
declare(strict_types=1);

require_once __DIR__.'/BusinessCategories.php';

final class BusinessPublicImporter
{
    public static function import(PDO $pdo,array $package,array $images=[],?string $privateStorage=null): int
    {
        if(($package['schema_version']??'')!=='business-import-v1')throw new RuntimeException('Versión de paquete de negocios no compatible.');
        if(class_exists(BusinessPackage::class))BusinessPackage::validateManifest($package);
        $destinationSlug=trim((string)($package['destination_slug']??''));
        $q=$pdo->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([$destinationSlug]);$destinationId=(int)$q->fetchColumn();
        if(!$destinationId)throw new RuntimeException('El destino indicado no existe.');
        $businesses=$package['businesses']??null;if(!is_array($businesses)||count($businesses)<1||count($businesses)>500)throw new RuntimeException('El paquete debe incluir entre 1 y 500 negocios.');
        if($images&&(!$privateStorage||(!is_dir($privateStorage)&&!mkdir($privateStorage,0700,true))||!is_writable($privateStorage)))throw new RuntimeException('No se pudo preparar el almacenamiento privado de imágenes.');
        $storedFiles=[];$pdo->beginTransaction();
        try{
            $insert=$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status,display_order) VALUES(?,?,?,"public_import",?,?,"NO_RECLAMADO","DESCONOCIDO","draft",0)');
            $translation=$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)');
            $source=$pdo->prepare('INSERT INTO business_sources(business_id,source_url,checked_at,note) VALUES(?,?,?,?)');
            $imageInsert=$pdo->prepare('INSERT INTO business_image_references(business_id,storage_name,original_name,mime_type,file_size,width,height) VALUES(?,?,?,?,?,?,?)');
            $freeSubscription=$pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(?,"free","pending_publication")');
            foreach($businesses as $index=>$business){
                if(!is_array($business))throw new RuntimeException('Registro '.($index+1).' no es un objeto.');
                $name=trim((string)($business['name']??''));$summary=trim((string)($business['summary']??''));$description=trim((string)($business['description']??''));$category=trim((string)($business['category_code']??''));$informationStatus=(string)($business['information_status']??'PUBLICO');
                if($name===''||strlen($name)>180||strlen($summary)>500||strlen($description)>10000)throw new RuntimeException('Revisa el nombre o los textos del registro '.($index+1).'.');
                if(preg_match('/^REEMPLAZAR\b/iu',$name))throw new RuntimeException('Completa los datos de ejemplo antes de importar el paquete.');
                if(!in_array($informationStatus,['PUBLICO','VERIFICADO'],true))throw new RuntimeException('La verificación del registro '.($index+1).' debe ser PUBLICO o VERIFICADO.');
                $q=$pdo->prepare('SELECT 1 FROM service_categories WHERE code=? AND is_active=1');$q->execute([$category]);if(!$q->fetchColumn())throw new RuntimeException('Categoría no válida en el registro '.($index+1).'.');
                $duplicate=$pdo->prepare('SELECT 1 FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=? AND LOWER(TRIM(t.display_name))=LOWER(TRIM(?))');$duplicate->execute([$destinationId,$name]);if($duplicate->fetchColumn())throw new RuntimeException('Ya existe un negocio con ese nombre en el destino: '.$name);
                $categoryCodes=BusinessCategories::validate($pdo,$business['category_codes']??[],$category);
                $slug=self::uniqueSlug($pdo,$destinationId,$name);$insert->execute([self::uuid(),$destinationId,$category,$slug,$informationStatus]);$id=(int)$pdo->lastInsertId();BusinessCategories::replace($pdo,$id,$category,$categoryCodes);foreach(['es','en','pt-BR'] as $locale)$translation->execute([$id,$locale,$name,$locale==='es'?$summary:'',$locale==='es'?$description:'']);$freeSubscription->execute([$id]);
                $sources=$business['sources']??[];if(!is_array($sources)||count($sources)<1)throw new RuntimeException('Agrega al menos una fuente comprobable para '.$name.'.');
                foreach($sources as $item){if(!is_array($item))throw new RuntimeException('Fuente no válida para '.$name.'.');$url=trim((string)($item['url']??''));if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true)||strlen($url)>1000)throw new RuntimeException('La fuente de '.$name.' debe ser una URL HTTP o HTTPS válida.');$date=trim((string)($item['checked_at']??''));if($date!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new RuntimeException('La fecha de consulta debe usar AAAA-MM-DD.');$source->execute([$id,$url,$date!==''?$date:null,substr(trim((string)($item['note']??'')),0,500)]);}
                foreach(($business['image_references']??[]) as $reference){
                    $file=(string)($reference['file']??'');$image=$images[$file]??null;if(!is_array($image)||!is_file((string)($image['path']??'')))throw new RuntimeException("No se encontró la imagen de referencia {$file} para {$name}.");
                    $extension=match($image['mime']){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp',default=>throw new RuntimeException('Formato de imagen de referencia no admitido.')};
                    $storageName=bin2hex(random_bytes(20)).'.'.$extension;$target=rtrim((string)$privateStorage,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$storageName;if(!copy($image['path'],$target))throw new RuntimeException('No se pudo guardar una imagen en el almacenamiento privado.');@chmod($target,0600);$storedFiles[]=$target;
                    $original=substr((string)($reference['name']??$image['name']??basename($file)),0,255);$imageInsert->execute([$id,$storageName,$original,$image['mime'],(int)$image['size'],(int)$image['width'],(int)$image['height']]);
                }
            }
            $pdo->commit();return count($businesses);
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();foreach($storedFiles as $file)if(is_file($file))@unlink($file);throw $error;}
    }

    private static function uniqueSlug(PDO $pdo,int $destinationId,string $name):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);$base=trim(preg_replace('/[^a-z0-9]+/','-',strtolower((string)$ascii))??'','-')?:'negocio';$slug=$base;$suffix=2;$q=$pdo->prepare('SELECT 1 FROM businesses WHERE destination_id=? AND slug=?');while(true){$q->execute([$destinationId,$slug]);if(!$q->fetchColumn())return $slug;$slug=$base.'-'.$suffix++;}}
    private static function uuid():string{$hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-'.dechex((hexdec($hex[16])&3)|8).substr($hex,17,3).'-'.substr($hex,20,12);}
}

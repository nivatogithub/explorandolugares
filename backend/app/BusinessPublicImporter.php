<?php
declare(strict_types=1);

final class BusinessPublicImporter
{
    public static function import(PDO $pdo,array $package): int
    {
        if(($package['schema_version']??'')!=='business-import-v1')throw new RuntimeException('Versión de paquete de negocios no compatible.');
        $destinationSlug=trim((string)($package['destination_slug']??''));
        $q=$pdo->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([$destinationSlug]);$destinationId=(int)$q->fetchColumn();
        if(!$destinationId)throw new RuntimeException('El destino indicado no existe.');
        $businesses=$package['businesses']??null;if(!is_array($businesses)||count($businesses)<1||count($businesses)>500)throw new RuntimeException('El paquete debe incluir entre 1 y 500 negocios.');
        $pdo->beginTransaction();
        try{
            $insert=$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status,display_order) VALUES(?,?,?,"public_import",?,?,"NO_RECLAMADO","DESCONOCIDO","draft",0)');
            $translation=$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,?,?)');
            $source=$pdo->prepare('INSERT INTO business_sources(business_id,source_url,checked_at,note) VALUES(?,?,?,?)');
            foreach($businesses as $index=>$business){
                if(!is_array($business))throw new RuntimeException('Registro '.($index+1).' no es un objeto.');
                $name=trim((string)($business['name']??''));$summary=trim((string)($business['summary']??''));$description=trim((string)($business['description']??''));$category=trim((string)($business['category_code']??''));$informationStatus=(string)($business['information_status']??'PUBLICO');
                if($name===''||strlen($name)>180||strlen($summary)>500||strlen($description)>10000)throw new RuntimeException('Revisa el nombre o los textos del registro '.($index+1).'.');
                if(!in_array($informationStatus,['PUBLICO','VERIFICADO'],true))throw new RuntimeException('La verificación del registro '.($index+1).' debe ser PUBLICO o VERIFICADO.');
                $q=$pdo->prepare('SELECT 1 FROM service_categories WHERE code=? AND is_active=1');$q->execute([$category]);if(!$q->fetchColumn())throw new RuntimeException('Categoría no válida en el registro '.($index+1).'.');
                $duplicate=$pdo->prepare('SELECT 1 FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=? AND LOWER(TRIM(t.display_name))=LOWER(TRIM(?))');$duplicate->execute([$destinationId,$name]);if($duplicate->fetchColumn())throw new RuntimeException('Ya existe un negocio con ese nombre en el destino: '.$name);
                $slug=self::uniqueSlug($pdo,$destinationId,$name);$insert->execute([self::uuid(),$destinationId,$category,$slug,$informationStatus]);$id=(int)$pdo->lastInsertId();$translation->execute([$id,$name,$summary,$description]);
                $sources=$business['sources']??[];if(!is_array($sources)||count($sources)<1)throw new RuntimeException('Agrega al menos una fuente comprobable para '.$name.'.');
                foreach($sources as $item){if(!is_array($item))throw new RuntimeException('Fuente no válida para '.$name.'.');$url=trim((string)($item['url']??''));if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true)||strlen($url)>1000)throw new RuntimeException('La fuente de '.$name.' debe ser una URL HTTP o HTTPS válida.');$date=trim((string)($item['checked_at']??''));if($date!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new RuntimeException('La fecha de consulta debe usar AAAA-MM-DD.');$source->execute([$id,$url,$date!==''?$date:null,substr(trim((string)($item['note']??'')),0,500)]);}
            }
            $pdo->commit();return count($businesses);
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    }

    private static function uniqueSlug(PDO $pdo,int $destinationId,string $name):string{$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);$base=trim(preg_replace('/[^a-z0-9]+/','-',strtolower((string)$ascii))??'','-')?:'negocio';$slug=$base;$suffix=2;$q=$pdo->prepare('SELECT 1 FROM businesses WHERE destination_id=? AND slug=?');while(true){$q->execute([$destinationId,$slug]);if(!$q->fetchColumn())return $slug;$slug=$base.'-'.$suffix++;}}
    private static function uuid():string{$hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-'.dechex((hexdec($hex[16])&3)|8).substr($hex,17,3).'-'.substr($hex,20,12);}
}

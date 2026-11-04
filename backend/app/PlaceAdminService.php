<?php
declare(strict_types=1);

require_once __DIR__.'/GeolocationPolicy.php';
require_once __DIR__.'/PlaceAdminSelector.php';
require_once __DIR__.'/PlacePublicationPolicy.php';
require_once __DIR__.'/PlaceImageStorage.php';
require_once __DIR__.'/PlaceWriter.php';
require_once __DIR__.'/PlaceLabels.php';

final class PlaceAdminService
{
    private const LOCALES = ['es', 'en', 'pt-BR'];

    private PlaceAdminSelector $placeReads;
    private PlaceWriter $writes;
    public function __construct(private PDO $pdo) {$this->placeReads=new PlaceAdminSelector($pdo);$this->writes=new PlaceWriter($pdo);}

    private function starters():array
    {
        return json_decode((string)file_get_contents(__DIR__.'/place-starters.json'),true,32,JSON_THROW_ON_ERROR);
    }

    public function starterOptions():array
    {
        $options=[];
        foreach($this->starters() as $slug=>$data)$options[]=['slug'=>$slug,'name'=>$data['translations']['es']['display_name'],'department'=>$data['department'],'municipality'=>$data['municipality'],'existing_id'=>$this->starterMatch($data)];
        return $options;
    }

    private function starterMatch(array $data):?int
    {
        $expected=mb_strtolower(trim($data['translations']['es']['display_name']),'UTF-8');
        foreach($this->all() as $row){
            if($row['slug']!==$data['slug']&&mb_strtolower(trim((string)$row['display_name']),'UTF-8')!==$expected)continue;
            if($row['country_code']!==$data['country_code']||$row['department']!==$data['department'])throw new RuntimeException('Existe un destino con ese nombre o dirección web en otra ubicación. Revísalo antes de crear otro.');
            return (int)$row['id'];
        }
        return null;
    }

    public function createStarterDraft(string $slug,string $publicRoot):int
    {
        $data=$this->starters()[$slug]??null;
        if(!is_array($data))throw new RuntimeException('El destino preparado no existe.');
        if($id=$this->starterMatch($data))return $id;
        unset($data['municipality']);$data['publication_status']='draft';
        try{return $this->save($data,[],[],$publicRoot);}
        catch(PDOException $error){
            // Repeated/concurrent clicks may hit the unique slug: reuse, never overwrite.
            if(($error->errorInfo[0]??'')==='23000'&&($id=$this->starterMatch($data)))return $id;
            throw $error;
        }
    }

    public function all(): array
    {
        return $this->placeReads->all();
    }

    public function find(int $id): ?array
    {
        $d=$this->placeReads->find($id);
        if (!$d) return null;
        $d['labels']=!empty($d['is_heritage_town'])?['heritage_town']:[];
        $d['translations']=[];
        foreach ($this->placeReads->translations($id) as $row) $d['translations'][$row['locale']]=$row;
        $d['history']=['es'=>[],'en'=>[],'pt-BR'=>[]];
        foreach ($this->placeReads->history($id) as $row) if ($row['locale']) $d['history'][$row['locale']][]=$row['body'];
        $d['reasons']=['es'=>[],'en'=>[],'pt-BR'=>[]];
        foreach ($this->placeReads->reasons($id) as $row) if ($row['locale']) $d['reasons'][$row['locale']][]=$row['body'];
        $d['places']=[];
        foreach ($this->placeReads->attractions($id) as $row) {
            $poiId=(int)$row['id'];
            if (!isset($d['places'][$poiId])) $d['places'][$poiId]=['id'=>$poiId,'slug'=>$row['slug'],'type'=>$row['poi_type'],'latitude'=>$row['latitude'],'longitude'=>$row['longitude'],'publication_status'=>$row['publication_status'],'media_asset_id'=>$row['media_asset_id'],'translations'=>[]];
            if ($row['locale']) $d['places'][$poiId]['translations'][$row['locale']]=$row;
        }
        $d['places']=array_values($d['places']);
        if (!is_string($d['internal_research_json']??null)||trim($d['internal_research_json'])==='') $d['internal_research_json']='{"schema_version":1}';
        $d['sources']=['es'=>[],'en'=>[],'pt-BR'=>[]];
        foreach ($this->placeReads->sources($id) as $row) $d['sources'][$row['locale']][]=$row;
        $d['media_options']=$this->mediaOptions();
        $d['destination_media']=[];
        foreach ($this->placeReads->mediaBindings($id) as $row) $d['destination_media'][$row['role']]=$row;
        return $d;
    }

    public function mediaOptions(): array
    {
        return $this->placeReads->mediaOptions();
    }

    public function save(array $in, array $upload, array $placeUploads, string $publicRoot): int
    {
        $data=$this->validate($in);
        $id=(int)($in['id']??0);
        $placeIndexes=array_fill_keys(array_column($data['places'],'form_index'),true);
        foreach (($placeUploads['error']??[]) as $index=>$uploadError) {
            if ($uploadError!==UPLOAD_ERR_NO_FILE && !isset($placeIndexes[$index])) throw new RuntimeException('Hay una imagen seleccionada en una fila sin nombre de atractivo. Completa o retira esa fila y vuelve a guardar.');
        }
        $images=new PlaceImageStorage($publicRoot);
        $this->pdo->beginTransaction();
        try {
            if ($id>0 && !$this->placeReads->placeExists($id)) throw new RuntimeException('El destino seleccionado ya no existe.');
            $id=$this->writes->saveRecord($id,$data);
            if (array_key_exists('labels',$in)) $this->writes->syncLabels($id,PlaceLabels::normalize($in['labels']));
            $this->writes->syncTranslations($id,$data,self::LOCALES);
            $this->writes->syncParagraphs($id,$data['history'],'history',self::LOCALES);
            $this->writes->syncParagraphs($id,$data['reasons'],'reasons',self::LOCALES);
            $placeIds=$this->syncPlaces($id,$data['places']);
            $this->writes->syncSources($id,$data['sources'],self::LOCALES);
            $this->writes->saveResearch($id,$data['internal_research_json']);
            $heroId=(int)($in['hero_asset_id']??0);
            if (($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $heroId=$this->saveUpload($id,$upload,$in,$images);
            if ($heroId>0) {
                if (!$this->placeReads->mediaExists($heroId)) throw new RuntimeException('La imagen de portada seleccionada no existe.');
            }
            $this->writes->replaceMedia($id,'hero',$heroId);
            foreach ($data['places'] as $index=>$place) {
                $placeUpload=$this->indexedUpload($placeUploads,$place['form_index']);
                if (($placeUpload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
                $assetId=$this->saveUpload($id,$placeUpload,$place['image'],$images);
                $poiId=$placeIds[$index]??0;
                if ($poiId<1) throw new RuntimeException('No se pudo asociar la imagen con su atractivo.');
                $this->writes->replaceAttractionGallery($poiId,$assetId);
            }
            $historyMedia=(int)($in['history_asset_id']??0);
            $this->writes->replaceMedia($id,'history',$historyMedia);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if (!$images->discardNewFiles()) error_log('place-image-cleanup: incomplete compensation after failed save');
            throw $e;
        }
    }

    public function archive(int $id): void
    { $this->writes->archive($id); }

    public function delete(int $id): void
    {
        if ($this->placeReads->businessCount($id)>0) throw new RuntimeException('No se puede borrar: hay negocios relacionados. Archiva el destino para conservar las relaciones.');
        $this->writes->delete($id);
    }

    private function validate(array $in): array
    {
        $tr=$in['translations']??[];
        $slug=trim((string)($in['slug']??''));
        if ($slug==='') $slug=$this->slugify((string)($tr['es']['display_name']??''));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)) throw new RuntimeException('Escribe el nombre del destino en español para crear su dirección web automáticamente.');
        $type=(string)($in['destination_type']??'municipality');if (!in_array($type,['municipality','city','other'],true)) throw new RuntimeException('Tipo de destino inválido.');
        $country=strtoupper(trim((string)($in['country_code']??'CO')));if (!preg_match('/^[A-Z]{2}$/D',$country)) throw new RuntimeException('El país debe ser un código de dos letras.');
        $status=(string)($in['publication_status']??'draft');if (!in_array($status,['draft','published','archived'],true)) throw new RuntimeException('Estado de publicación inválido.');
        PlacePublicationPolicy::assertInitialState((int)($in['id']??0),$status);
        $num=static function(string $key, float $min, float $max) use ($in): ?float { $v=trim((string)($in[$key]??''));if ($v==='') return null;if (!is_numeric($v)||(float)$v<$min||(float)$v>$max) throw new RuntimeException('Coordenadas fuera de rango.');return (float)$v; };
        $history=$in['history']??[];$reasons=$in['reasons']??[];$places=$in['places']??[];$sources=$in['sources']??[];
        foreach (self::LOCALES as $locale) {
            foreach (['display_name'=>120,'eyebrow'=>180,'headline'=>240,'intro'=>5000,'meta_description'=>255] as $key=>$max) {
                $v=trim((string)($tr[$locale][$key]??''));if ($this->length($v)>$max) throw new RuntimeException("El campo {$key} de {$locale} supera el límite.");$tr[$locale][$key]=$v;
            }
            PlacePublicationPolicy::assertEditorialCopy($tr[$locale],$status);
            $history[$locale]=$this->lines((string)($history[$locale]??''),5000);
            $reasons[$locale]=$this->lines((string)($reasons[$locale]??''),500);
        }
        if (!is_array($places)) throw new RuntimeException('Revisa los lugares de interés enviados.');
        $filledPlaces=array_filter($places,static fn($place)=>is_array($place)&&trim((string)($place['slug']??''))!=='');
        if (count($filledPlaces)>40) throw new RuntimeException('Un destino admite hasta 40 lugares de interés.');
        $cleanPlaces=[];
        foreach ($places as $formIndex=>$place) {
            if (!is_array($place)) throw new RuntimeException('Hay una fila de lugar de interés inválida.');
            $ps=trim((string)($place['slug']??''));
            if ($ps==='') $ps=$this->slugify((string)($place['translations']['es']['name']??''));
            if ($ps==='') continue;
            if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$ps)) throw new RuntimeException('No se pudo crear la dirección del lugar; revisa su nombre en español.');
            $media=(int)($place['media_asset_id']??0);$poiStatus=(string)($place['publication_status']??'draft');if(!in_array($poiStatus,['draft','published','archived'],true))throw new RuntimeException('El estado de un atractivo no es válido.');$poiType=(string)($place['type']??'place');if(!in_array($poiType,['place','experience'],true))throw new RuntimeException('El tipo del atractivo no es válido.');$clean=['id'=>max(0,(int)($place['id']??0)),'form_index'=>(int)$formIndex,'slug'=>$ps,'type'=>$poiType,'lat'=>null,'lng'=>null,'media'=>$media,'status'=>$poiStatus,'image'=>['image_origin'=>(string)($place['image_origin']??'external'),'image_creator'=>trim((string)($place['image_creator']??'')),'image_source_url'=>trim((string)($place['image_source_url']??'')),'image_license'=>trim((string)($place['image_license']??'')),'image_license_url'=>trim((string)($place['image_license_url']??'')),'image_alt'=>$place['image_alt']??[],'image_caption'=>$place['image_caption']??[]],'translations'=>[]];
            foreach (['latitude'=>['lat',-90,90],'longitude'=>['lng',-180,180]] as $key=>[$target,$min,$max]) {$v=trim((string)($place[$key]??''));if($v!==''){if(!is_numeric($v)||(float)$v<$min||(float)$v>$max)throw new RuntimeException('Las coordenadas de un lugar están fuera de rango.');$clean[$target]=(float)$v;}}
            foreach (self::LOCALES as $locale) {
                $p=$place['translations'][$locale]??[];$clean['translations'][$locale]=['name'=>trim((string)($p['name']??'')),'description'=>trim((string)($p['description']??'')),'map_url'=>$this->httpsOrBlank((string)($p['map_url']??'')),'external_url'=>$this->httpsOrBlank((string)($p['external_url']??'')),'external_label'=>trim((string)($p['external_label']??''))];
                if ($this->length($clean['translations'][$locale]['name'])>180||$this->length($clean['translations'][$locale]['description'])>5000||$this->length($clean['translations'][$locale]['external_label'])>180) throw new RuntimeException('Un nombre, descripción o enlace de lugar supera el límite.');
            }
            PlacePublicationPolicy::assertAttractionTranslations($clean['translations'],$poiStatus,self::LOCALES);
                foreach (self::LOCALES as $locale) {
                    if ($clean['translations'][$locale]['map_url']==='' && $clean['id']===0 && $poiType==='place') {
                        $query=implode(', ',array_filter([
                            $clean['translations'][$locale]['name'],
                            trim((string)($tr[$locale]['display_name']??$tr['es']['display_name']??'')),
                            trim((string)($in['department']??'Antioquia')),
                            trim((string)($in['country_code']??'CO'))==='CO'?'Colombia':trim((string)($in['country_code']??'')),
                        ]));
                        $clean['translations'][$locale]['map_url']='https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);
                    }
                }
                $cleanPlaces[]=$clean;
        }
        if ($status==='published') {
            GeolocationPolicy::requirePoint($num('latitude',-90,90),$num('longitude',-180,180),'el destino');
            foreach($cleanPlaces as $p)if($p['status']==='published'&&!GeolocationPolicy::placeExempt($slug,$p['slug'],$p['type']))GeolocationPolicy::requirePoint($p['lat'],$p['lng'],'el atractivo '.$p['translations']['es']['name']);
            PlacePublicationPolicy::assertReadyToPublish($tr,$history,$reasons,$cleanPlaces,self::LOCALES);
        }
        $cleanSources=[];
        foreach (self::LOCALES as $locale) foreach ($this->lines((string)($sources[$locale]??''),1000) as $line) {
            $parts=array_map('trim',explode('|',$line,3));
            if (count($parts)===1) {
                $url=$parts[0];$host=(string)(parse_url($url,PHP_URL_HOST)??'');
                $label=$host!==''?preg_replace('/^www\./','',$host):'Fuente de información';$section='destination';
            } elseif (count($parts)===3) {
                [$section,$label,$url]=$parts;
            } else {
                throw new RuntimeException("Fuente inválida en {$locale}. Pega una URL completa que empiece por https://.");
            }
            if (!in_array($section,['destination','history','places'],true)||$label===''||!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https') throw new RuntimeException("Fuente inválida en {$locale}. Pega una URL completa que empiece por https://.");
            $cleanSources[$locale][]=['section'=>$section,'label'=>$label,'url'=>$url];
        }
        $order=(int)($in['display_order']??0);
        if ($order<=0) $order=$this->placeReads->nextDisplayOrder();
        $researchJson=$this->validateInternalResearch((string)($in['internal_research_json']??''));
        return ['slug'=>$slug,'type'=>$type,'country'=>$country,'department'=>trim((string)($in['department']??'Antioquia')),'subregion'=>trim((string)($in['subregion']??'')),'lat'=>$num('latitude',-90,90),'lng'=>$num('longitude',-180,180),'status'=>$status,'order'=>max(0,min(9999,$order)),'translations'=>$tr,'history'=>$history,'reasons'=>$reasons,'places'=>$cleanPlaces,'sources'=>$cleanSources,'internal_research_json'=>$researchJson];
    }

    private function validateInternalResearch(string $json): string
    {
        $json=trim($json);
        if ($json==='') return '{"schema_version":1}';
        if (strlen($json)>524288) throw new RuntimeException('La investigación interna supera el máximo de 512 KB.');
        try { $data=json_decode($json,true,24,JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new RuntimeException('La investigación local debe ser un JSON válido.'); }
        if (!is_array($data)||($data['schema_version']??null)!==1) throw new RuntimeException('La investigación local debe usar schema_version 1.');
        $allowed=['schema_version','researched_at','positioning','competitors','potential_allies','business_prospects','acquisition_strategies','sources','notes'];
        if (array_diff(array_keys($data),$allowed)) throw new RuntimeException('La investigación local contiene campos no reconocidos.');
        foreach (['competitors','potential_allies','business_prospects','acquisition_strategies','sources','notes'] as $key) {
            if (!isset($data[$key])) $data[$key]=[];
            if (!is_array($data[$key])||count($data[$key])>100) throw new RuntimeException("La sección {$key} debe ser una lista de hasta 100 elementos.");
        }
        foreach (['researched_at','positioning'] as $key) if (isset($data[$key])&&!is_string($data[$key])) throw new RuntimeException("El campo {$key} debe ser texto.");
        $walk=function(mixed $value,string $key='') use (&$walk): void {
            if (is_array($value)) {
                foreach ($value as $childKey=>$child) $walk($child,(string)$childKey);
                return;
            }
            if (!is_string($value)&&!is_int($value)&&!is_float($value)&&!is_bool($value)&&$value!==null) throw new RuntimeException('La investigación local contiene un valor no admitido.');
            if (is_string($value)&&mb_strlen($value,'UTF-8')>5000) throw new RuntimeException('Un campo de investigación supera el máximo de 5000 caracteres.');
            if (is_string($value)&&$value!==''&&preg_match('/(?:^|_)(?:url)$/i',$key)) {
                if (!filter_var($value,FILTER_VALIDATE_URL)||parse_url($value,PHP_URL_SCHEME)!=='https') throw new RuntimeException('Las URL de investigación deben ser direcciones HTTPS válidas.');
            }
        };
        foreach (['competitors','potential_allies','business_prospects','acquisition_strategies','sources'] as $key) {
            if (!array_is_list($data[$key])) throw new RuntimeException("La sección {$key} debe ser una lista.");
            foreach ($data[$key] as $row) if (!is_array($row)) throw new RuntimeException("Cada elemento de {$key} debe ser un objeto JSON.");
        }
        foreach ($data['notes'] as $note) if (!is_string($note)) throw new RuntimeException('Cada nota de investigación debe ser texto.');
        $walk($data);
        return json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    }

    private function syncPlaces(int $id,array $places): array
    {
        $existing=$this->placeReads->attractionIds($id);$retained=[];$placeIds=[];
        foreach($places as $i=>$p){
            if($p['id']>0&&!in_array($p['id'],$existing,true))throw new RuntimeException('El ID de un lugar no pertenece a este destino.');
            $poiId=$this->writes->saveAttraction($id,$p,$i+1);$retained[]=$poiId;$placeIds[$i]=$poiId;
            $this->writes->syncAttractionTranslations($poiId,$p['translations'],self::LOCALES);
            $this->writes->clearAttractionMedia($poiId);
            if($p['media']>0){
                if(!$this->placeReads->mediaExists($p['media']))throw new RuntimeException('La imagen seleccionada para un lugar no existe.');
                $this->writes->attachAttractionMedia($poiId,$p['media']);
            }
        }
        // Omitted attractions retain their identities but are no longer displayed.
        $this->writes->archiveAttractions(array_values(array_diff($existing,$retained)));
        return $placeIds;
    }

    private function saveUpload(int $id,array $upload,array $metadata,PlaceImageStorage $images): int
    {
        $image=$images->inspect($upload);$width=$image['width'];$height=$image['height'];
        $origin=(string)($metadata['image_origin']??'external');if(!in_array($origin,['external','own','ai_generated'],true))throw new RuntimeException('Selecciona un origen de imagen válido.');
        $creator=trim((string)($metadata['image_creator']??''));$source=trim((string)($metadata['image_source_url']??''));$license=trim((string)($metadata['image_license']??''));$licenseUrl=trim((string)($metadata['image_license_url']??''));
        if($origin==='external'&&($creator===''||$source===''||$license===''||$licenseUrl===''))throw new RuntimeException('Para una imagen externa indica autor, página de origen, licencia y enlace de la licencia.');
        if($origin==='own'){$creator=$creator?:'Imagen propia o cedida para el destino';$license=$license?:'Uso autorizado por su titular';}
        if($origin==='ai_generated'){$creator=$creator?:'Imagen generada con IA para Explorando Lugares';$license=$license?:'Generada con IA; verificar las condiciones de la herramienta utilizada';}
        $source=$this->httpsOrBlank($source);$licenseUrl=$this->httpsOrBlank($licenseUrl);
        $path=$images->store($id,$image);
        $assetId=$this->writes->createMedia([$path,$origin,$creator,$source,$license,$licenseUrl,$width,$height]);
        foreach(self::LOCALES as $locale){$alt=trim((string)($metadata['image_alt'][$locale]??''));$caption=trim((string)($metadata['image_caption'][$locale]??''));if($alt==='')throw new RuntimeException("Falta el texto alternativo de la imagen para {$locale}.");$this->writes->addMediaTranslation($assetId,$locale,$alt,$caption);}
        return $assetId;
    }

    private function indexedUpload(array $uploads,int $index): array
    {
        if (!isset($uploads['error'][$index])) return ['error'=>UPLOAD_ERR_NO_FILE];
        return ['name'=>$uploads['name'][$index]??'','type'=>$uploads['type'][$index]??'','tmp_name'=>$uploads['tmp_name'][$index]??'','error'=>$uploads['error'][$index]??UPLOAD_ERR_NO_FILE,'size'=>$uploads['size'][$index]??0];
    }

    private function httpsOrBlank(string $url): string
    { $url=trim($url);if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'))throw new RuntimeException('Los enlaces deben usar HTTPS.');return $url; }
    private function lines(string $value,int $max): array
    { $items=preg_split('/\R/u',$value)?:[];$out=[];foreach($items as $item){$item=trim($item);if($item==='')continue;if($this->length($item)>$max)throw new RuntimeException('Una línea de texto supera el límite permitido.');$out[]=$item;}return $out; }
    private function length(string $value): int { return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value); }
    private function slugify(string $value): string
    {
        $value=trim(function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value));
        $value=strtr($value,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        if (function_exists('iconv')) $value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:$value;
        $value=preg_replace('/[^a-z0-9]+/','-',strtolower($value))??'';
        return trim($value,'-');
    }
}

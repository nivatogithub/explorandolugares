<?php
declare(strict_types=1);

final class DestinationAdminService
{
    private const LOCALES = ['es', 'en', 'pt-BR'];

    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        return $this->pdo->query('SELECT d.id,d.slug,d.destination_type,d.country_code,d.department,d.publication_status,d.display_order,t.display_name
            FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es"
            ORDER BY d.display_order,d.id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $q=$this->pdo->prepare('SELECT * FROM destinations WHERE id=?');$q->execute([$id]);$d=$q->fetch(PDO::FETCH_ASSOC);
        if (!$d) return null;
        $d['translations']=[];
        $q=$this->pdo->prepare('SELECT * FROM destination_translations WHERE destination_id=?');$q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $d['translations'][$row['locale']]=$row;
        $d['history']=['es'=>[],'en'=>[],'pt-BR'=>[]];
        $q=$this->pdo->prepare('SELECT b.id,b.display_order,t.locale,t.body FROM destination_story_blocks b LEFT JOIN destination_story_translations t ON t.story_block_id=b.id WHERE b.destination_id=? AND b.section_key="history" ORDER BY b.display_order,b.id');$q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['locale']) $d['history'][$row['locale']][]=$row['body'];
        $d['reasons']=['es'=>[],'en'=>[],'pt-BR'=>[]];
        $q=$this->pdo->prepare('SELECT r.id,r.display_order,t.locale,t.body FROM destination_reasons r LEFT JOIN destination_reason_translations t ON t.reason_id=r.id WHERE r.destination_id=? ORDER BY r.display_order,r.id');$q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) if ($row['locale']) $d['reasons'][$row['locale']][]=$row['body'];
        $d['places']=[];
        $q=$this->pdo->prepare('SELECT p.id,p.slug,p.poi_type,p.latitude,p.longitude,p.publication_status,p.display_order,t.locale,t.name,t.description,t.map_url,t.external_url,t.external_label,
                (SELECT pm.media_asset_id FROM point_of_interest_media pm WHERE pm.point_of_interest_id=p.id ORDER BY pm.display_order LIMIT 1) AS media_asset_id
            FROM points_of_interest p LEFT JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id WHERE p.destination_id=? ORDER BY CASE WHEN p.publication_status=\'archived\' THEN 1 ELSE 0 END,p.display_order,p.id');$q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $poiId=(int)$row['id'];
            if (!isset($d['places'][$poiId])) $d['places'][$poiId]=['id'=>$poiId,'slug'=>$row['slug'],'type'=>$row['poi_type'],'latitude'=>$row['latitude'],'longitude'=>$row['longitude'],'publication_status'=>$row['publication_status'],'media_asset_id'=>$row['media_asset_id'],'translations'=>[]];
            if ($row['locale']) $d['places'][$poiId]['translations'][$row['locale']]=$row;
        }
        $d['places']=array_values($d['places']);
        if (!is_string($d['internal_research_json']??null)||trim($d['internal_research_json'])==='') $d['internal_research_json']='{"schema_version":1}';
        $d['sources']=['es'=>[],'en'=>[],'pt-BR'=>[]];
        $q=$this->pdo->prepare('SELECT locale,section_key,source_url,source_label FROM destination_sources WHERE destination_id=? ORDER BY locale,section_key,display_order,id');$q->execute([$id]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $d['sources'][$row['locale']][]=$row;
        $d['media_options']=$this->mediaOptions();
        $q=$this->pdo->prepare('SELECT dm.role,dm.media_asset_id,m.asset_path FROM destination_media dm JOIN media_assets m ON m.id=dm.media_asset_id WHERE dm.destination_id=?');$q->execute([$id]);
        $d['destination_media']=[];foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $d['destination_media'][$row['role']]=$row;
        return $d;
    }

    public function mediaOptions(): array
    {
        return $this->pdo->query('SELECT id,asset_path,creator FROM media_assets ORDER BY asset_path')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $in, array $upload, array $placeUploads, string $publicRoot): int
    {
        $data=$this->validate($in);
        $id=(int)($in['id']??0);
        $placeIndexes=array_fill_keys(array_column($data['places'],'form_index'),true);
        foreach (($placeUploads['error']??[]) as $index=>$uploadError) {
            if ($uploadError!==UPLOAD_ERR_NO_FILE && !isset($placeIndexes[$index])) throw new RuntimeException('Hay una imagen seleccionada en una fila sin nombre de atractivo. Completa o retira esa fila y vuelve a guardar.');
        }
        $this->pdo->beginTransaction();
        $storedPaths=[];
        try {
            if ($id>0) {
                $check=$this->pdo->prepare('SELECT id FROM destinations WHERE id=?');$check->execute([$id]);
                if (!$check->fetchColumn()) throw new RuntimeException('El destino seleccionado ya no existe.');
                $q=$this->pdo->prepare('UPDATE destinations SET slug=?,destination_type=?,country_code=?,department=?,subregion=?,latitude=?,longitude=?,publication_status=?,display_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $q->execute([$data['slug'],$data['type'],$data['country'],$data['department'],$data['subregion'],$data['lat'],$data['lng'],$data['status'],$data['order'],$id]);
            } else {
                $q=$this->pdo->prepare('INSERT INTO destinations(slug,destination_type,country_code,department,subregion,latitude,longitude,publication_status,display_order) VALUES(?,?,?,?,?,?,?,?,?)');
                $q->execute([$data['slug'],$data['type'],$data['country'],$data['department'],$data['subregion'],$data['lat'],$data['lng'],$data['status'],$data['order']]);
                $id=(int)$this->pdo->lastInsertId();
            }
            $this->syncTranslations($id,$data);
            $this->syncParagraphs($id,$data['history'],'destination_story_blocks','destination_story_translations','history');
            $this->syncParagraphs($id,$data['reasons'],'destination_reasons','destination_reason_translations',null);
            $placeIds=$this->syncPlaces($id,$data['places']);
            $this->syncSources($id,$data['sources']);
            $q=$this->pdo->prepare('UPDATE destinations SET internal_research_json=? WHERE id=?');
            $q->execute([$data['internal_research_json'],$id]);
            $heroId=(int)($in['hero_asset_id']??0);
            if (($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $heroId=$this->saveUpload($id,$upload,$in,$publicRoot,$storedPaths);
            if ($heroId>0) {
                $q=$this->pdo->prepare('SELECT id FROM media_assets WHERE id=?');$q->execute([$heroId]);
                if (!$q->fetchColumn()) throw new RuntimeException('La imagen de portada seleccionada no existe.');
            }
            $q=$this->pdo->prepare('DELETE FROM destination_media WHERE destination_id=? AND role="hero"');$q->execute([$id]);
            if ($heroId>0) {$q=$this->pdo->prepare('INSERT INTO destination_media(destination_id,media_asset_id,role,display_order) VALUES(?,?,"hero",0)');$q->execute([$id,$heroId]);}
            foreach ($data['places'] as $index=>$place) {
                $placeUpload=$this->indexedUpload($placeUploads,$place['form_index']);
                if (($placeUpload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
                $assetId=$this->saveUpload($id,$placeUpload,$place['image'],$publicRoot,$storedPaths);
                $poiId=$placeIds[$index]??0;
                if ($poiId<1) throw new RuntimeException('No se pudo asociar la imagen con su atractivo.');
                $this->pdo->prepare('DELETE FROM point_of_interest_media WHERE point_of_interest_id=? AND role="gallery"')->execute([$poiId]);
                $this->pdo->prepare('INSERT INTO point_of_interest_media(point_of_interest_id,media_asset_id,role,display_order) VALUES(?,?,"gallery",1)')->execute([$poiId,$assetId]);
            }
            $historyMedia=(int)($in['history_asset_id']??0);
            $q=$this->pdo->prepare('DELETE FROM destination_media WHERE destination_id=? AND role="history"');$q->execute([$id]);
            if ($historyMedia>0) {$q=$this->pdo->prepare('INSERT INTO destination_media(destination_id,media_asset_id,role,display_order) VALUES(?,? ,"history",0)');$q->execute([$id,$historyMedia]);}
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            foreach ($storedPaths as $storedPath) if (is_file($storedPath)) @unlink($storedPath);
            throw $e;
        }
    }

    public function archive(int $id): void
    { $q=$this->pdo->prepare('UPDATE destinations SET publication_status="archived",updated_at=CURRENT_TIMESTAMP WHERE id=?');$q->execute([$id]); }

    public function delete(int $id): void
    {
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM businesses WHERE destination_id=?');$q->execute([$id]);
        if ((int)$q->fetchColumn()>0) throw new RuntimeException('No se puede borrar: hay negocios relacionados. Archiva el destino para conservar las relaciones.');
        $q=$this->pdo->prepare('DELETE FROM destinations WHERE id=?');$q->execute([$id]);
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
        if ((int)($in['id']??0)===0 && $status!=='draft') throw new RuntimeException('Los destinos nuevos siempre se crean como borrador. Guárdalo primero, revísalo y luego podrás publicarlo.');
        $num=static function(string $key, float $min, float $max) use ($in): ?float { $v=trim((string)($in[$key]??''));if ($v==='') return null;if (!is_numeric($v)||(float)$v<$min||(float)$v>$max) throw new RuntimeException('Coordenadas fuera de rango.');return (float)$v; };
        $history=$in['history']??[];$reasons=$in['reasons']??[];$places=$in['places']??[];$sources=$in['sources']??[];
        foreach (self::LOCALES as $locale) {
            foreach (['display_name'=>120,'eyebrow'=>180,'headline'=>240,'intro'=>5000,'meta_description'=>255] as $key=>$max) {
                $v=trim((string)($tr[$locale][$key]??''));if ($this->length($v)>$max) throw new RuntimeException("El campo {$key} de {$locale} supera el límite.");$tr[$locale][$key]=$v;
            }
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
            if ($poiStatus==='published') foreach (self::LOCALES as $locale) {
                if ($clean['translations'][$locale]['name']===''||$clean['translations'][$locale]['description']==='') throw new RuntimeException("Completa la traducción del atractivo publicado en {$locale}.");
                if ($poiType==='experience'&&($clean['translations'][$locale]['external_url']===''||$clean['translations'][$locale]['external_label']==='')) throw new RuntimeException("Completa el enlace y su texto para la experiencia publicada en {$locale}.");
            }
                foreach (self::LOCALES as $locale) {
                    if ($clean['translations'][$locale]['map_url']==='') {
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
            foreach (self::LOCALES as $locale) if ($tr[$locale]['display_name']===''||$tr[$locale]['headline']===''||$tr[$locale]['intro']===''||!$history[$locale]||!$reasons[$locale]) throw new RuntimeException("Para publicar completa título, introducción, historia y motivos en los tres idiomas; falta contenido en {$locale}.");
            if (!array_filter($cleanPlaces,static fn($place)=>$place['status']==='published')) throw new RuntimeException('Publicar requiere al menos un atractivo con estado «Publicado».');
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
        if ($order<=0) $order=(int)$this->pdo->query('SELECT COALESCE(MAX(display_order),0)+1 FROM destinations')->fetchColumn();
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

    private function syncTranslations(int $id,array $data): void
    {
        $q=$this->pdo->prepare($this->upsert('INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(?,?,?,?,?,?,?) ON CONFLICT(destination_id,locale) DO UPDATE SET display_name=excluded.display_name,eyebrow=excluded.eyebrow,headline=excluded.headline,intro=excluded.intro,meta_description=excluded.meta_description','INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),eyebrow=VALUES(eyebrow),headline=VALUES(headline),intro=VALUES(intro),meta_description=VALUES(meta_description)'));
        foreach (self::LOCALES as $locale) {$t=$data['translations'][$locale];$q->execute([$id,$locale,$t['display_name'],$t['eyebrow'],$t['headline'],$t['intro'],$t['meta_description']]);}
    }

    private function syncParagraphs(int $id,array $rows,string $table,string $translationTable,?string $section): void
    {
        $idColumn=$table==='destination_story_blocks'?'story_block_id':'reason_id';
        $q=$this->pdo->prepare("SELECT id FROM {$table} WHERE destination_id=?".($section?' AND section_key=?':'').' ORDER BY display_order,id');$q->execute($section?[$id,$section]:[$id]);$existing=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $count=max(array_map('count',$rows));
        if ($table==='destination_story_blocks') {$insert=$this->pdo->prepare('INSERT INTO destination_story_blocks(destination_id,section_key,display_order) VALUES(?,?,?)');$upd=$this->pdo->prepare('UPDATE destination_story_blocks SET display_order=? WHERE id=?');}
        else {$insert=$this->pdo->prepare('INSERT INTO destination_reasons(destination_id,display_order) VALUES(?,?)');$upd=$this->pdo->prepare('UPDATE destination_reasons SET display_order=? WHERE id=?');}
        $ids=[];for($i=0;$i<$count;$i++){if(isset($existing[$i])){$ids[]=$existing[$i];$upd->execute([$i+1,$existing[$i]]);}else{if($table==='destination_story_blocks')$insert->execute([$id,'history',$i+1]);else$insert->execute([$id,$i+1]);$ids[]=(int)$this->pdo->lastInsertId();}}
        if(count($existing)>$count){$marks=implode(',',array_fill(0,count($existing)-$count,'?'));$this->pdo->prepare("DELETE FROM {$table} WHERE id IN ({$marks})")->execute(array_slice($existing,$count));}
        $up=$this->pdo->prepare($this->upsert("INSERT INTO {$translationTable}({$idColumn},locale,body) VALUES(?,?,?) ON CONFLICT({$idColumn},locale) DO UPDATE SET body=excluded.body","INSERT INTO {$translationTable}({$idColumn},locale,body) VALUES(?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)"));
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$this->pdo->prepare("DELETE FROM {$translationTable} WHERE {$idColumn} IN ({$marks})")->execute($ids);}
        foreach(self::LOCALES as $locale) foreach($rows[$locale] as $i=>$body)$up->execute([$ids[$i],$locale,$body]);
        // Remove language rows beyond the new canonical Spanish count.
        if($count===0&&$ids===[]) { $q=$this->pdo->prepare("DELETE FROM {$translationTable} WHERE {$idColumn} IN (SELECT id FROM {$table} WHERE destination_id=?".($section?' AND section_key=?':'').')');$q->execute($section?[$id,$section]:[$id]); }
    }

    private function syncPlaces(int $id,array $places): array
    {
        $q=$this->pdo->prepare('SELECT id FROM points_of_interest WHERE destination_id=? ORDER BY display_order,id');$q->execute([$id]);$existing=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $insert=$this->pdo->prepare('INSERT INTO points_of_interest(destination_id,slug,poi_type,latitude,longitude,publication_status,display_order) VALUES(?,?,?,?,?,?,?)');
        $update=$this->pdo->prepare('UPDATE points_of_interest SET slug=?,poi_type=?,latitude=?,longitude=?,publication_status=?,display_order=? WHERE id=?');
        $tr=$this->pdo->prepare($this->upsert('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description,map_url,external_url,external_label) VALUES(?,?,?,?,?,?,?) ON CONFLICT(point_of_interest_id,locale) DO UPDATE SET name=excluded.name,description=excluded.description,map_url=excluded.map_url,external_url=excluded.external_url,external_label=excluded.external_label','INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description,map_url,external_url,external_label) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),map_url=VALUES(map_url),external_url=VALUES(external_url),external_label=VALUES(external_label)'));
        $retained=[];$placeIds=[];
        foreach($places as $i=>$p){$poiId=$p['id'];if($poiId>0){if(!in_array($poiId,$existing,true))throw new RuntimeException('El ID de un lugar no pertenece a este destino.');$update->execute([$p['slug'],$p['type'],$p['lat'],$p['lng'],$p['status'],$i+1,$poiId]);}else{$insert->execute([$id,$p['slug'],$p['type'],$p['lat'],$p['lng'],$p['status'],$i+1]);$poiId=(int)$this->pdo->lastInsertId();}$retained[]=$poiId;$placeIds[$i]=$poiId;
            foreach(self::LOCALES as $locale){$v=$p['translations'][$locale];$tr->execute([$poiId,$locale,$v['name'],$v['description'],$v['map_url'],$v['external_url'],$v['external_label']]);}
            $this->pdo->prepare('DELETE FROM point_of_interest_media WHERE point_of_interest_id=?')->execute([$poiId]);
            if($p['media']>0){$q=$this->pdo->prepare('SELECT id FROM media_assets WHERE id=?');$q->execute([$p['media']]);if(!$q->fetchColumn())throw new RuntimeException('La imagen seleccionada para un lugar no existe.');$this->pdo->prepare('INSERT INTO point_of_interest_media(point_of_interest_id,media_asset_id,role,display_order) VALUES(?,?,"gallery",1)')->execute([$poiId,$p['media']]);}
        }
        // Una fila omitida por un import o error de formulario se conserva, pero deja de mostrarse.
        $remove=array_values(array_diff($existing,$retained));if($remove){$marks=implode(',',array_fill(0,count($remove),'?'));$this->pdo->prepare("UPDATE points_of_interest SET publication_status='archived' WHERE id IN ({$marks})")->execute($remove);}
        return $placeIds;
    }

    private function syncSources(int $id,array $sources): void
    {
        $this->pdo->prepare('DELETE FROM destination_sources WHERE destination_id=?')->execute([$id]);
        $q=$this->pdo->prepare('INSERT INTO destination_sources(destination_id,locale,section_key,source_url,source_label,display_order) VALUES(?,?,?,?,?,?)');
        foreach(self::LOCALES as $locale) foreach(($sources[$locale]??[]) as $i=>$source)$q->execute([$id,$locale,$source['section'],$source['url'],$source['label'],$i+1]);
    }

    private function saveUpload(int $id,array $upload,array $metadata,string $publicRoot,array &$storedPaths): int
    {
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)$upload['tmp_name']))throw new RuntimeException('No se pudo recibir la imagen.');
        if((int)$upload['size']>5*1024*1024)throw new RuntimeException('La imagen debe pesar 5 MB o menos.');
        $info=@getimagesize($upload['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!$info||!isset($extensions[$mime]))throw new RuntimeException('Usa una imagen JPG, PNG o WebP válida; no se admiten SVG.');
        $width=(int)$info[0];$height=(int)$info[1];if($width<240||$height<160||$width>10000||$height>10000)throw new RuntimeException('La imagen debe medir al menos 240 × 160 píxeles y no superar 10000 píxeles por lado.');
        $origin=(string)($metadata['image_origin']??'external');if(!in_array($origin,['external','own','ai_generated'],true))throw new RuntimeException('Selecciona un origen de imagen válido.');
        $creator=trim((string)($metadata['image_creator']??''));$source=trim((string)($metadata['image_source_url']??''));$license=trim((string)($metadata['image_license']??''));$licenseUrl=trim((string)($metadata['image_license_url']??''));
        if($origin==='external'&&($creator===''||$source===''||$license===''||$licenseUrl===''))throw new RuntimeException('Para una imagen externa indica autor, página de origen, licencia y enlace de la licencia.');
        if($origin==='own'){$creator=$creator?:'Imagen propia o cedida para el destino';$license=$license?:'Uso autorizado por su titular';}
        if($origin==='ai_generated'){$creator=$creator?:'Imagen generada con IA para Explorando Lugares';$license=$license?:'Generada con IA; verificar las condiciones de la herramienta utilizada';}
        $source=$this->httpsOrBlank($source);$licenseUrl=$this->httpsOrBlank($licenseUrl);
        $folder=rtrim($publicRoot,'/').'/assets/destinos/'.$id;if(!is_dir($folder)&&!mkdir($folder,0755,true)&&!is_dir($folder))throw new RuntimeException('No se pudo crear la carpeta de imágenes del destino.');
        $name='imagen-'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];$full=$folder.'/'.$name;if(!move_uploaded_file($upload['tmp_name'],$full))throw new RuntimeException('No se pudo guardar la imagen.');$storedPaths[]=$full;
        $path='assets/destinos/'.$id.'/'.$name;$q=$this->pdo->prepare('INSERT INTO media_assets(asset_path,origin,creator,source_url,license_name,license_url,width,height) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$path,$origin,$creator,$source,$license,$licenseUrl,$width,$height]);$assetId=(int)$this->pdo->lastInsertId();
        $t=$this->pdo->prepare('INSERT INTO media_asset_translations(media_asset_id,locale,alt_text,caption) VALUES(?,?,?,?)');
        foreach(self::LOCALES as $locale){$alt=trim((string)($metadata['image_alt'][$locale]??''));$caption=trim((string)($metadata['image_caption'][$locale]??''));if($alt==='')throw new RuntimeException("Falta el texto alternativo de la imagen para {$locale}.");$t->execute([$assetId,$locale,$alt,$caption]);}
        return $assetId;
    }

    private function indexedUpload(array $uploads,int $index): array
    {
        if (!isset($uploads['error'][$index])) return ['error'=>UPLOAD_ERR_NO_FILE];
        return ['name'=>$uploads['name'][$index]??'','type'=>$uploads['type'][$index]??'','tmp_name'=>$uploads['tmp_name'][$index]??'','error'=>$uploads['error'][$index]??UPLOAD_ERR_NO_FILE,'size'=>$uploads['size'][$index]??0];
    }

    private function upsert(string $sqlite,string $mysql): string
    { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?$mysql:$sqlite; }
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

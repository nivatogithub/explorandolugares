<?php
declare(strict_types=1);

final class DestinationImporter
{
    public static function import(PDO $pdo, array $catalog, array $options): array
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();
        try {
            foreach (['destinations', 'service_categories', 'businesses'] as $table) {
                $exists = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
                if ((int)$exists !== 0) {
                    throw new RuntimeException("La tabla {$table} ya contiene datos; importación cancelada sin cambios.");
                }
            }
            $counts = ['destinations'=>0, 'translations'=>0, 'story_blocks'=>0,
                'reasons'=>0, 'points_of_interest'=>0, 'media_assets'=>0, 'sources'=>0,
                'service_categories'=>0];
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
            self::categories($pdo, $options, $counts);
            $mediaIds = self::media($pdo, $catalog, $counts);

            $destinationInsert = $pdo->prepare('INSERT INTO destinations
                (slug,destination_type,country_code,department,subregion,publication_status,display_order)
                VALUES (?,?,?,?,?,?,?)');
            $translationInsert = $pdo->prepare('INSERT INTO destination_translations
                (destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES (?,?,?,?,?,?,?)');
            $storyInsert = $pdo->prepare('INSERT INTO destination_story_blocks
                (destination_id,section_key,display_order) VALUES (?,\'history\',?)');
            $storyTranslationInsert = $pdo->prepare('INSERT INTO destination_story_translations
                (story_block_id,locale,body) VALUES (?,?,?)');
            $reasonInsert = $pdo->prepare('INSERT INTO destination_reasons (destination_id,display_order) VALUES (?,?)');
            $reasonTranslationInsert = $pdo->prepare('INSERT INTO destination_reason_translations (reason_id,locale,body) VALUES (?,?,?)');
            $poiInsert = $pdo->prepare('INSERT INTO points_of_interest
                (destination_id,slug,poi_type,publication_status,display_order) VALUES (?,?,?,?,?)');
            $poiTranslationInsert = $pdo->prepare('INSERT INTO point_of_interest_translations
                (point_of_interest_id,locale,name,description,map_url,external_url,external_label) VALUES (?,?,?,?,?,?,?)');
            $destinationMediaInsert = $pdo->prepare('INSERT INTO destination_media
                (destination_id,media_asset_id,role,display_order) VALUES (?,?,?,?)');
            $poiMediaInsert = $pdo->prepare('INSERT INTO point_of_interest_media
                (point_of_interest_id,media_asset_id,role,display_order) VALUES (?,?,\'gallery\',?)');
            $sourceInsert = $pdo->prepare('INSERT INTO destination_sources
                (destination_id,locale,section_key,source_url,source_label,display_order) VALUES (?,?,?,?,?,?)');

            foreach ($catalog['destinations'] as $destination) {
                self::requireKeys($destination, ['slug','destination_type','country_code','department','subregion','publication_status','display_order','translations']);
                self::slug((string)$destination['slug']);
                $destinationInsert->execute([$destination['slug'],$destination['destination_type'],$destination['country_code'],
                    $destination['department'],$destination['subregion'],$destination['publication_status'],$destination['display_order']]);
                $destinationId = (int)$pdo->lastInsertId();
                $counts['destinations']++;

                $storyCounts = [];
                $reasonCounts = [];
                $poiCounts = [];
                foreach ($catalog['locales'] as $locale) {
                    $tr = $destination['translations'][$locale] ?? null;
                    if (!is_array($tr)) throw new RuntimeException("Falta traducción {$locale} para {$destination['slug']}.");
                    foreach (['name','eyebrow','headline','intro','meta_description','history','reasons','places'] as $key) {
                        if (!array_key_exists($key, $tr)) throw new RuntimeException("Falta {$key}: {$destination['slug']} / {$locale}.");
                    }
                    $translationInsert->execute([$destinationId,$locale,$tr['name'],$tr['eyebrow'],$tr['headline'],$tr['intro'],$tr['meta_description']]);
                    $counts['translations']++;
                    $storyCounts[$locale]=count($tr['history']);
                    $reasonCounts[$locale]=count($tr['reasons']);
                    $poiCounts[$locale]=count($tr['places']);
                    foreach ($tr['sources'] as $sourceOrder=>$source) {
                        self::https((string)$source['url']);
                        $sectionKey=$source['section_key']??'history';
                        if (!in_array($sectionKey,['history','places'],true)) throw new RuntimeException('Sección de fuente inválida.');
                        $sourceInsert->execute([$destinationId,$locale,$sectionKey,$source['url'],$source['label'],$sourceOrder+1]);
                        $counts['sources']++;
                    }
                }
                if (count(array_unique($storyCounts)) !== 1 || count(array_unique($reasonCounts)) !== 1 || count(array_unique($poiCounts)) !== 1) {
                    throw new RuntimeException("La estructura traducida no coincide: {$destination['slug']}.");
                }

                $storyIds=[];
                for ($i=0; $i<reset($storyCounts); $i++) {
                    $storyInsert->execute([$destinationId,$i+1]);
                    $storyId=(int)$pdo->lastInsertId(); $storyIds[$i]=$storyId; $counts['story_blocks']++;
                }
                $reasonIds=[];
                for ($i=0; $i<reset($reasonCounts); $i++) {
                    $reasonInsert->execute([$destinationId,$i+1]);
                    $reasonId=(int)$pdo->lastInsertId(); $reasonIds[$i]=$reasonId; $counts['reasons']++;
                }
                $poiIds=[];
                foreach ($destination['translations']['es']['places'] as $i=>$poi) {
                    self::slug((string)$poi['slug']);
                    $type=(string)($poi['type']??'place');
                    if(!in_array($type,['place','experience'],true))throw new RuntimeException('Tipo de atractivo inválido.');
                    // Los lugares se importan publicados; las experiencias comerciales
                    // quedan en borrador hasta que administración confirme el acuerdo.
                    $status=(string)($poi['publication_status']??($type==='experience'?'draft':'published'));
                    if(!in_array($status,['draft','published','archived'],true))throw new RuntimeException('Estado de publicación del atractivo inválido.');
                    $poiInsert->execute([$destinationId,$poi['slug'],$type,$status,$i+1]);
                    $poiIds[$i]=(int)$pdo->lastInsertId(); $counts['points_of_interest']++;
                }
                foreach ($catalog['locales'] as $locale) {
                    $tr=$destination['translations'][$locale];
                    foreach ($tr['history'] as $i=>$body) $storyTranslationInsert->execute([$storyIds[$i],$locale,$body]);
                    foreach ($tr['reasons'] as $i=>$body) $reasonTranslationInsert->execute([$reasonIds[$i],$locale,$body]);
                    foreach ($tr['places'] as $i=>$poi) {
                        $mapUrl=(string)($poi['map_url']??'');$externalUrl=(string)($poi['external_url']??'');
                        if($mapUrl!=='')self::https($mapUrl);
                        if($externalUrl!=='')self::https($externalUrl);
                        $poiStatus=(string)($destination['translations']['es']['places'][$i]['publication_status']??(($poi['type']??'place')==='experience'?'draft':'published'));
                        if($poiStatus==='published'&&($poi['type']??'place')==='experience'&&($externalUrl===''||trim((string)($poi['external_label']??''))===''))throw new RuntimeException("La experiencia {$poi['slug']} necesita un enlace y texto de consulta en {$locale} antes de publicarse.");
                        $poiTranslationInsert->execute([$poiIds[$i],$locale,$poi['name'],$poi['description'],$mapUrl,$externalUrl,(string)($poi['external_label']??'')]);
                    }
                }
            }
            self::placements($pdo, $catalog, $mediaIds, $counts);
            $pdo->commit();
            return $counts;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    private static function categories(PDO $pdo, array $options, array &$counts): void
    {
        $insert=$pdo->prepare('INSERT INTO service_categories (code,display_order,is_active) VALUES (?,?,1)');
        $translation=$pdo->prepare('INSERT INTO service_category_translations (category_code,locale,label) VALUES (?,?,?)');
        $seen=[];
        foreach ($options['groups'] as $group) foreach ($group['categories'] as $category) {
            $code=(string)$category['value']; if (isset($seen[$code])) continue; $seen[$code]=true;
            $insert->execute([$code,count($seen)]); $counts['service_categories']++;
            foreach (['es'=>'label','en'=>'en','pt-br'=>'pt'] as $locale=>$key) {
                if (!empty($category[$key])) $translation->execute([$code,$locale,$category[$key]]);
            }
        }
    }

    private static function media(PDO $pdo, array $catalog, array &$counts): array
    {
        $insert=$pdo->prepare('INSERT INTO media_assets (asset_path,origin,creator,source_url,license_name,license_url,width,height) VALUES (?,?,?,?,?,?,?,?)');
        $translation=$pdo->prepare('INSERT INTO media_asset_translations (media_asset_id,locale,alt_text,caption) VALUES (?,?,?,?)');
        $ids=[];
        foreach ($catalog['media'] as $media) {
            $path=(string)$media['path'];
            if (!preg_match('~^assets/(?!.*(?:^|/)\.\.(?:/|$))[a-zA-Z0-9/_-]+\.(?:webp|png|jpe?g|svg)$~',$path)) throw new RuntimeException('Ruta de medio no permitida.');
            $origin=(string)($media['origin']??'external');if(!in_array($origin,['external','own','ai_generated'],true))throw new RuntimeException('Origen de imagen inválido.');
            if($origin==='external'){self::https((string)$media['source_url']);self::https((string)$media['license_url']);}
            $creator=trim((string)($media['author']??$media['creator']??''));
            if (!$creator || !$media['license_name']) throw new RuntimeException("Crédito incompleto para {$path}.");
            $source=(string)($media['source_url']??'');$licenseUrl=(string)($media['license_url']??'');
            if($origin==='external'){self::https($source);self::https($licenseUrl);}
            $insert->execute([$path,$origin,$creator,$source,$media['license_name'],$licenseUrl,$media['width'],$media['height']]);
            $id=(int)$pdo->lastInsertId(); $ids[$path]=$id; $counts['media_assets']++;
            foreach ($catalog['locales'] as $locale) {
                $tr=$media['translations'][$locale]??null;
                if (!is_array($tr) || empty($tr['alt']) || empty($tr['caption'])) throw new RuntimeException("Alt o leyenda incompletos: {$path} / {$locale}.");
                $translation->execute([$id,$locale,$tr['alt'],$tr['caption']]);
            }
        }
        return $ids;
    }

    private static function placements(PDO $pdo, array $catalog, array $mediaIds, array &$counts): void
    {
        $destIds=[];
        foreach ($pdo->query('SELECT id,slug FROM destinations')->fetchAll() as $row) $destIds[$row['slug']]=(int)$row['id'];
        $poiIds=[];
        foreach ($pdo->query('SELECT p.id,d.slug AS destination_slug,p.slug AS poi_slug FROM points_of_interest p JOIN destinations d ON d.id=p.destination_id')->fetchAll() as $row) $poiIds[$row['destination_slug']][$row['poi_slug']]=(int)$row['id'];
        $destinationMedia=$pdo->prepare('INSERT INTO destination_media (destination_id,media_asset_id,role,display_order) VALUES (?,?,?,?)');
        $poiMedia=$pdo->prepare('INSERT INTO point_of_interest_media (point_of_interest_id,media_asset_id,role,display_order) VALUES (?,?,\'gallery\',?)');
        foreach ($catalog['media'] as $media) foreach ($media['placements'] as $placement) {
            $dest=$placement['destination_slug']; $mediaId=$mediaIds[$media['path']]; $role=$placement['role'];
            if (str_starts_with($role,'poi:')) {
                $position=(int)substr($role,4)-1;
                $poi=$catalog['destinations'][array_search($dest,array_column($catalog['destinations'],'slug'),true)]['translations']['es']['places'][$position]??null;
                if (!$poi || !isset($poiIds[$dest][$poi['slug']])) throw new RuntimeException("Vínculo de imagen a atractivo inválido: {$dest}.");
                $poiMedia->execute([$poiIds[$dest][$poi['slug']],$mediaId,1]);
            } else {
                $destinationMedia->execute([$destIds[$dest],$mediaId,$role,$role==='hero'?0:1]);
            }
        }
    }

    private static function requireKeys(array $row, array $keys): void
    { foreach ($keys as $key) if (!array_key_exists($key,$row)) throw new RuntimeException("Falta el campo {$key}."); }
    private static function slug(string $value): void
    { if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$value)) throw new RuntimeException('Slug inválido.'); }
    private static function https(string $value): void
    { if (!filter_var($value,FILTER_VALIDATE_URL) || parse_url($value,PHP_URL_SCHEME)!=='https') throw new RuntimeException('Se requiere una URL HTTPS válida.'); }
}

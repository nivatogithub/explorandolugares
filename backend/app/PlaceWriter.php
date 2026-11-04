<?php
declare(strict_types=1);

require_once __DIR__.'/PlaceAdminSelector.php';
require_once __DIR__.'/PlaceLabels.php';

/** Writes normalized place data using the caller's connection and transaction. */
final class PlaceWriter
{
    private PlaceAdminSelector $placeReads;
    public function __construct(private PDO $pdo) {$this->placeReads=new PlaceAdminSelector($pdo);}

    public function saveRecord(int $id,array $data): int
    {
        if ($id>0) {
            $q=$this->pdo->prepare('UPDATE destinations SET slug=?,destination_type=?,country_code=?,department=?,subregion=?,latitude=?,longitude=?,publication_status=?,display_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $q->execute([$data['slug'],$data['type'],$data['country'],$data['department'],$data['subregion'],$data['lat'],$data['lng'],$data['status'],$data['order'],$id]);
        } else {
            $q=$this->pdo->prepare('INSERT INTO destinations(slug,destination_type,country_code,department,subregion,latitude,longitude,publication_status,display_order,internal_research_json) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$data['slug'],$data['type'],$data['country'],$data['department'],$data['subregion'],$data['lat'],$data['lng'],$data['status'],$data['order'],$data['internal_research_json']]);
            $id=(int)$this->pdo->lastInsertId();
        }
        return $id;
    }

    public function syncLabels(int $id,array $codes): void
    {
        if (!$this->pdo->inTransaction()) throw new LogicException('Place labels require the save transaction.');
        // Keep provenance and disabled rows when the administrator removes a label.
        $q=$this->pdo->prepare($this->upsert(
            'INSERT INTO place_labels(place_id,code,enabled) VALUES(?,?,?) ON CONFLICT(place_id,code) DO UPDATE SET enabled=excluded.enabled',
            'INSERT INTO place_labels(place_id,code,enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)'));
        foreach (PlaceLabels::CODES as $code) $q->execute([$id,$code,in_array($code,$codes,true)?1:0]);
    }

    public function saveResearch(int $id,string $json): void
    { $this->pdo->prepare('UPDATE destinations SET internal_research_json=? WHERE id=?')->execute([$json,$id]); }

    public function archive(int $id): void
    { $this->pdo->prepare('UPDATE destinations SET publication_status="archived",updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]); }

    public function delete(int $id): void
    { $this->pdo->prepare('DELETE FROM destinations WHERE id=?')->execute([$id]); }

    public function saveAttraction(int $id,array $p,int $order): int
    {
        $poiId=$p['id'];
        if($poiId>0){$this->pdo->prepare('UPDATE points_of_interest SET slug=?,poi_type=?,latitude=?,longitude=?,publication_status=?,display_order=? WHERE id=?')->execute([$p['slug'],$p['type'],$p['lat'],$p['lng'],$p['status'],$order,$poiId]);}
        else{$this->pdo->prepare('INSERT INTO points_of_interest(destination_id,slug,poi_type,latitude,longitude,publication_status,display_order) VALUES(?,?,?,?,?,?,?)')->execute([$id,$p['slug'],$p['type'],$p['lat'],$p['lng'],$p['status'],$order]);$poiId=(int)$this->pdo->lastInsertId();}
        return $poiId;
    }

    public function syncAttractionTranslations(int $id,array $translations,array $locales): void
    {
        $tr=$this->pdo->prepare($this->upsert('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description,map_url,external_url,external_label) VALUES(?,?,?,?,?,?,?) ON CONFLICT(point_of_interest_id,locale) DO UPDATE SET name=excluded.name,description=excluded.description,map_url=excluded.map_url,external_url=excluded.external_url,external_label=excluded.external_label','INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description,map_url,external_url,external_label) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),map_url=VALUES(map_url),external_url=VALUES(external_url),external_label=VALUES(external_label)'));
        foreach($locales as $locale){$v=$translations[$locale];$tr->execute([$id,$locale,$v['name'],$v['description'],$v['map_url'],$v['external_url'],$v['external_label']]);}
    }

    public function clearAttractionMedia(int $id): void
    { $this->pdo->prepare('DELETE FROM point_of_interest_media WHERE point_of_interest_id=?')->execute([$id]); }

    public function attachAttractionMedia(int $id,int $assetId): void
    { $this->pdo->prepare('INSERT INTO point_of_interest_media(point_of_interest_id,media_asset_id,role,display_order) VALUES(?,?,"gallery",1)')->execute([$id,$assetId]); }

    public function replaceAttractionGallery(int $id,int $assetId): void
    {
        $this->pdo->prepare('DELETE FROM point_of_interest_media WHERE point_of_interest_id=? AND role="gallery"')->execute([$id]);
        $this->attachAttractionMedia($id,$assetId);
    }

    public function archiveAttractions(array $ids): void
    {
        if(!$ids)return;
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $this->pdo->prepare("UPDATE points_of_interest SET publication_status='archived' WHERE id IN ({$marks})")->execute($ids);
    }

    public function replaceMedia(int $id,string $role,int $assetId): void
    {
        $this->pdo->prepare('DELETE FROM destination_media WHERE destination_id=? AND role=?')->execute([$id,$role]);
        if($assetId>0)$this->pdo->prepare('INSERT INTO destination_media(destination_id,media_asset_id,role,display_order) VALUES(?,?,?,0)')->execute([$id,$assetId,$role]);
    }

    public function createMedia(array $values): int
    {
        $this->pdo->prepare('INSERT INTO media_assets(asset_path,origin,creator,source_url,license_name,license_url,width,height) VALUES(?,?,?,?,?,?,?,?)')->execute($values);
        return (int)$this->pdo->lastInsertId();
    }

    public function addMediaTranslation(int $id,string $locale,string $alt,string $caption): void
    { $this->pdo->prepare('INSERT INTO media_asset_translations(media_asset_id,locale,alt_text,caption) VALUES(?,?,?,?)')->execute([$id,$locale,$alt,$caption]); }

    public function syncTranslations(int $id,array $data,array $locales): void
    {
        $q=$this->pdo->prepare($this->upsert('INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(?,?,?,?,?,?,?) ON CONFLICT(destination_id,locale) DO UPDATE SET display_name=excluded.display_name,eyebrow=excluded.eyebrow,headline=excluded.headline,intro=excluded.intro,meta_description=excluded.meta_description','INSERT INTO destination_translations(destination_id,locale,display_name,eyebrow,headline,intro,meta_description) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),eyebrow=VALUES(eyebrow),headline=VALUES(headline),intro=VALUES(intro),meta_description=VALUES(meta_description)'));
        foreach ($locales as $locale) {$t=$data['translations'][$locale];$q->execute([$id,$locale,$t['display_name'],$t['eyebrow'],$t['headline'],$t['intro'],$t['meta_description']]);}
    }

    public function syncParagraphs(int $id,array $rows,string $section,array $locales): void
    {
        [$table,$translationTable,$section]=match($section){
            'history'=>['destination_story_blocks','destination_story_translations','history'],
            'reasons'=>['destination_reasons','destination_reason_translations',null],
            default=>throw new InvalidArgumentException('Sección de lugar desconocida.'),
        };
        $idColumn=$table==='destination_story_blocks'?'story_block_id':'reason_id';
        $existing=$this->placeReads->paragraphIds($id,$table==='destination_story_blocks'?'history':'reasons');
        $count=max(array_map('count',$rows));
        if ($table==='destination_story_blocks') {$insert=$this->pdo->prepare('INSERT INTO destination_story_blocks(destination_id,section_key,display_order) VALUES(?,?,?)');$upd=$this->pdo->prepare('UPDATE destination_story_blocks SET display_order=? WHERE id=?');}
        else {$insert=$this->pdo->prepare('INSERT INTO destination_reasons(destination_id,display_order) VALUES(?,?)');$upd=$this->pdo->prepare('UPDATE destination_reasons SET display_order=? WHERE id=?');}
        $ids=[];for($i=0;$i<$count;$i++){if(isset($existing[$i])){$ids[]=$existing[$i];$upd->execute([$i+1,$existing[$i]]);}else{if($table==='destination_story_blocks')$insert->execute([$id,'history',$i+1]);else$insert->execute([$id,$i+1]);$ids[]=(int)$this->pdo->lastInsertId();}}
        if(count($existing)>$count){$marks=implode(',',array_fill(0,count($existing)-$count,'?'));$this->pdo->prepare("DELETE FROM {$table} WHERE id IN ({$marks})")->execute(array_slice($existing,$count));}
        $up=$this->pdo->prepare($this->upsert("INSERT INTO {$translationTable}({$idColumn},locale,body) VALUES(?,?,?) ON CONFLICT({$idColumn},locale) DO UPDATE SET body=excluded.body","INSERT INTO {$translationTable}({$idColumn},locale,body) VALUES(?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)"));
        if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$this->pdo->prepare("DELETE FROM {$translationTable} WHERE {$idColumn} IN ({$marks})")->execute($ids);}
        foreach($locales as $locale) foreach($rows[$locale] as $i=>$body)$up->execute([$ids[$i],$locale,$body]);
        // Remove language rows beyond the new canonical Spanish count.
        if($count===0&&$ids===[]) { $q=$this->pdo->prepare("DELETE FROM {$translationTable} WHERE {$idColumn} IN (SELECT id FROM {$table} WHERE destination_id=?".($section?' AND section_key=?':'').')');$q->execute($section?[$id,$section]:[$id]); }
    }

    public function syncSources(int $id,array $sources,array $locales): void
    {
        $this->pdo->prepare('DELETE FROM destination_sources WHERE destination_id=?')->execute([$id]);
        $q=$this->pdo->prepare('INSERT INTO destination_sources(destination_id,locale,section_key,source_url,source_label,display_order) VALUES(?,?,?,?,?,?)');
        foreach($locales as $locale) foreach(($sources[$locale]??[]) as $i=>$source)$q->execute([$id,$locale,$source['section'],$source['url'],$source['label'],$i+1]);
    }

    private function upsert(string $sqlite,string $mysql): string
    { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?$mysql:$sqlite; }
}

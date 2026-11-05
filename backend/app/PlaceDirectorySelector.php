<?php
declare(strict_types=1);

/** Public place directory reads; pagination happens in SQL, before loading images. */
final class PlaceDirectorySelector
{
    public function __construct(private PDO $pdo) {}

    private function scope(string $locale,array $filters):array
    {
        $sql='d.publication_status=\'published\' AND t.locale=?';$values=[$locale];
        if (($filters['q']??'')!=='') {
            $escape=static fn(string $text):string=>'%'.strtr($text,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $slug=mb_strtolower($filters['q'],'UTF-8');
            if(function_exists('iconv'))$slug=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$slug)?:$slug;
            $slug=trim(preg_replace('/[^a-z0-9]+/','-',$slug),'-');
            $sql.=' AND (t.display_name LIKE ? ESCAPE \'!\'';$values[]=$escape($filters['q']);
            if($slug!==''){$sql.=' OR d.slug LIKE ? ESCAPE \'!\'';$values[]=$escape($slug);}
            $catalog = json_decode(file_get_contents(__DIR__.'/content/place-search-terms.json'), true, 512, JSON_THROW_ON_ERROR);
            $query = mb_strtolower(trim($filters['q']), 'UTF-8');
            $terms = [$filters['q']];
            foreach ($catalog['topics'] as $variants) {
                if (in_array($query, $variants, true)) {
                    $terms = $variants;
                    break;
                }
            }
            $guide = [];
            $details = [];
            $guideValues = [];
            $detailValues = [];
            foreach ($terms as $term) {
                $pattern = $escape($term);
                foreach (['t.display_name', 't.headline', 't.intro'] as $field) {
                    $guide[] = "$field LIKE ? ESCAPE '!'";
                    $guideValues[] = $pattern;
                }
                foreach (['detail.name', 'detail.description'] as $field) {
                    $details[] = "$field LIKE ? ESCAPE '!'";
                    $detailValues[] = $pattern;
                }
            }
            $sql .= ' OR '.implode(' OR ', $guide)." OR EXISTS (
                SELECT 1 FROM points_of_interest poi
                JOIN point_of_interest_translations detail ON detail.point_of_interest_id = poi.id
                WHERE poi.destination_id = d.id AND poi.publication_status = 'published'
                  AND detail.locale IN (t.locale, 'es') AND (".implode(' OR ', $details).')))'; 
            array_push($values, ...$guideValues, ...$detailValues);
        }
        if(($filters['department']??'')!==''){$sql.=' AND d.department=?';$values[]=$filters['department'];}
        if(($filters['region']??'')!==''){$sql.=' AND COALESCE(NULLIF(TRIM(d.subregion),\'\'),d.department)=?';$values[]=$filters['region'];}
        if(!empty($filters['heritage']))$sql.=' AND EXISTS(SELECT 1 FROM place_labels l WHERE l.place_id=d.id AND l.code=\'heritage_town\' AND l.enabled=1)';
        return [$sql,$values];
    }

    public function options(string $locale):array
    {
        $q=$this->pdo->prepare('SELECT DISTINCT d.department,COALESCE(NULLIF(TRIM(d.subregion),\'\'),d.department) AS region FROM destinations d JOIN destination_translations t ON t.destination_id=d.id WHERE d.publication_status=\'published\' AND t.locale=? ORDER BY d.department,region');
        $q->execute([$locale]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(string $locale,array $filters):int
    {
        [$scope,$values]=$this->scope($locale,$filters);
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM destinations d JOIN destination_translations t ON t.destination_id=d.id WHERE '.$scope);
        $q->execute($values);return (int)$q->fetchColumn();
    }

    public function page(string $locale,array $filters,int $limit,int $offset):array
    {
        if($limit<1||$limit>100||$offset<0)throw new InvalidArgumentException('Invalid directory bounds.');
        return $this->read($locale,$filters,$limit,$offset);
    }

    /** Compatibility for existing PlaceReader consumers that explicitly request the full list. */
    public function all(string $locale):array{return $this->read($locale,[]);}

    private function read(string $locale,array $filters,?int $limit=null,int $offset=0):array
    {
        [$scope,$values]=$this->scope($locale,$filters);
        $sql='SELECT d.slug,d.department,d.subregion,EXISTS(SELECT 1 FROM place_labels l WHERE l.place_id=d.id AND l.code=\'heritage_town\' AND l.enabled=1) AS is_heritage_town,t.display_name,t.intro,m.asset_path,mt.alt_text
            FROM destinations d JOIN destination_translations t ON t.destination_id=d.id
            LEFT JOIN media_assets m ON m.id=(SELECT dm.media_asset_id FROM destination_media dm WHERE dm.destination_id=d.id AND dm.role=\'hero\' ORDER BY dm.display_order,dm.media_asset_id LIMIT 1)
            LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=t.locale
            WHERE '.$scope.' ORDER BY t.display_name,d.slug';
        if($limit!==null)$sql.=' LIMIT ? OFFSET ?';
        $q=$this->pdo->prepare($sql);$index=1;
        foreach($values as $value)$q->bindValue($index++,$value,PDO::PARAM_STR);
        if($limit!==null){$q->bindValue($index++,$limit,PDO::PARAM_INT);$q->bindValue($index,$offset,PDO::PARAM_INT);}
        $q->execute();return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

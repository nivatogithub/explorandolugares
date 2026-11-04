<?php
declare(strict_types=1);

/** Read-only business data needed by a place's directory. No publication or payment writes. */
final class BusinessDirectorySelector
{
    private ?bool $extraCategoriesAvailable=null;
    public function __construct(private PDO $pdo) {}

    private function rows(string $sql,array $parameters=[]):array
    {
        $query=$this->pdo->prepare($sql);$query->execute($parameters);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hasExtraCategories():bool
    {
        if($this->extraCategoriesAvailable!==null)return $this->extraCategoriesAvailable;
        try{$this->pdo->query('SELECT business_id,category_code FROM business_categories LIMIT 0');return $this->extraCategoriesAvailable=true;}
        catch(PDOException $error){if(($error->errorInfo[0]??'')==='42S02'||str_contains($error->getMessage(),'no such table:'))return $this->extraCategoriesAvailable=false;throw $error;}
    }

    public function categoriesForPlace(int $placeId,string $locale,bool $preview=false):array
    {
        $scope=$preview?'b.publication_status IN ("published","draft")':'b.publication_status="published"';
        $categoryJoin=$this->hasExtraCategories()?'(b.category_code=c.code OR EXISTS (SELECT 1 FROM business_categories bc WHERE bc.business_id=b.id AND bc.category_code=c.code))':'b.category_code=c.code';
        return $this->rows('SELECT DISTINCT c.code,ct.label,c.display_order FROM service_categories c
            JOIN service_category_translations ct ON ct.category_code=c.code AND LOWER(ct.locale)=LOWER(?)
            JOIN businesses b ON '.$categoryJoin.' AND b.destination_id=? AND '.$scope.'
            AND b.information_status<>"PENDIENTE_REVISION" AND b.operation_status<>"CERRADO_DEFINITIVO"
            WHERE c.is_active=1 ORDER BY c.display_order',[$locale,$placeId]);
    }

    public function businessesForPlace(int $placeId,string $locale,bool $preview=false):array
    {
        $scope=$preview?'b.publication_status IN ("published","draft")':'b.publication_status="published"';
        $rows=$this->rows('SELECT b.id,b.public_id,b.slug,b.information_status,b.claim_status,b.operation_status,b.address_text,b.location_reference,b.latitude,b.longitude,b.location_map_url,
            c.code,ct.label,bt.display_name,bt.summary,bt.description,
            COALESCE((SELECT bs.plan_code FROM business_subscriptions bs WHERE bs.business_id=b.id AND bs.status="active"
                AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP) AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP)
                ORDER BY bs.id DESC LIMIT 1),"free") AS plan_code,
            m.asset_path AS image_path,m.creator,m.source_url AS image_source,m.license_name,m.license_url,
            mt.alt_text AS image_alt,mt.caption AS image_caption,m.width,m.height
            FROM businesses b JOIN business_translations bt ON bt.business_id=b.id AND bt.locale=?
            JOIN service_categories c ON c.code=b.category_code
            JOIN service_category_translations ct ON ct.category_code=c.code AND LOWER(ct.locale)=LOWER(?)
            LEFT JOIN media_assets m ON m.id=(SELECT bm.media_asset_id FROM business_media bm WHERE bm.business_id=b.id AND bm.role="logo" ORDER BY bm.display_order,bm.media_asset_id LIMIT 1)
            LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=?
            WHERE b.destination_id=? AND '.$scope.' AND b.information_status<>"PENDIENTE_REVISION" AND b.operation_status<>"CERRADO_DEFINITIVO"
            ORDER BY c.display_order,bt.display_name',[$locale,$locale,$locale,$placeId]);
        return $this->enrichCategories($rows,$locale,'code','label');
    }

    public function enrichCategories(array $rows,string $locale='es',string $primaryKey='category_code',string $labelKey='category_label'):array
    {
        if(!$rows)return [];
        foreach($rows as &$row){$row['category_codes']=[$row[$primaryKey]];$row['category_labels']=[$row[$labelKey]??$row[$primaryKey]];}unset($row);
        if(!$this->hasExtraCategories())return $rows;
        $ids=array_map('intval',array_column($rows,'id'));
        $extras=[];
        foreach($this->rows('SELECT bc.business_id,c.code,COALESCE(t.label,c.code) AS label FROM business_categories bc
            JOIN service_categories c ON c.code=bc.category_code AND c.is_active=1
            LEFT JOIN service_category_translations t ON t.category_code=c.code AND LOWER(t.locale)=LOWER(?)
            WHERE bc.business_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY c.display_order,c.code',[$locale,...$ids]) as $item)$extras[(int)$item['business_id']][]=$item;
        foreach($rows as &$row)foreach($extras[(int)$row['id']]??[] as $item)if(!in_array($item['code'],$row['category_codes'],true)){$row['category_codes'][]=$item['code'];$row['category_labels'][]=$item['label'];}unset($row);
        return $rows;
    }

    public function publicContacts(array $businessIds):array
    {
        if(!$businessIds)return [];
        return $this->rows('SELECT business_id,channel,contact_value,display_order FROM business_contacts
            WHERE is_public=1 AND business_id IN ('.implode(',',array_fill(0,count($businessIds),'?')).') ORDER BY display_order',$businessIds);
    }

    public function planFeatures(array $plans):array
    {
        if(!$plans)return [];
        return $this->rows('SELECT plan_code,feature_code FROM subscription_plan_features WHERE included=1 AND plan_code IN ('.implode(',',array_fill(0,count($plans),'?')).')',$plans);
    }
}

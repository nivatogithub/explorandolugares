<?php
declare(strict_types=1);

require_once __DIR__.'/BusinessCategories.php';
require_once __DIR__.'/BusinessServiceSections.php';
require_once __DIR__.'/PremiumBusinessProfile.php';
require_once __DIR__.'/BusinessContactVisibility.php';

final class BusinessRepository
{
    private const LOCALES = ['es','en','pt-BR'];

    public function __construct(private PDO $pdo) {}

    public function findPublicPage(string $publicId, string $locale): ?array
    {
        if (!preg_match('/^[a-f0-9-]{16,64}$/iD', $publicId)) return null;
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'es';
        $query = $this->pdo->prepare('SELECT b.id,b.public_id,b.slug,b.information_status,b.operation_status,b.address_text,b.location_reference,b.latitude,b.longitude,b.location_map_url,b.opening_hours,b.price_range,b.capacity_count,b.menu_url,b.reservations_url,
                d.slug AS destination_slug,d.department,d.country_code,
                c.code AS category_code,ct.label AS category_label,
                COALESCE(NULLIF(bt.display_name,""),bt_es.display_name) AS display_name,
                COALESCE(NULLIF(bt.summary,""),bt_es.summary) AS summary,
                COALESCE(NULLIF(bt.description,""),bt_es.description) AS description,
                COALESCE(NULLIF(bt.brand_story,""),bt_es.brand_story) AS brand_story,
                COALESCE(NULLIF(bt.featured_services,""),bt_es.featured_services) AS featured_services,
                COALESCE((SELECT bs.plan_code FROM business_subscriptions bs
                    WHERE bs.business_id=b.id AND bs.status="active"
                    AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP)
                    AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP)
                    ORDER BY bs.id DESC LIMIT 1),"free") AS plan_code,
                (SELECT pt.name FROM subscription_plan_translations pt
                    WHERE pt.plan_code=COALESCE((SELECT bs.plan_code FROM business_subscriptions bs
                        WHERE bs.business_id=b.id AND bs.status="active"
                        AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP)
                        AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP)
                        ORDER BY bs.id DESC LIMIT 1),"free") AND pt.locale=? LIMIT 1) AS plan_name,
                COALESCE((SELECT sp.has_business_page FROM subscription_plans sp
                    WHERE sp.code=COALESCE((SELECT bs.plan_code FROM business_subscriptions bs
                        WHERE bs.business_id=b.id AND bs.status="active"
                        AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP)
                        AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP)
                        ORDER BY bs.id DESC LIMIT 1),"free") LIMIT 1),0) AS has_business_page
                ,COALESCE((SELECT sp.max_gallery_photos FROM subscription_plans sp
                    WHERE sp.code=COALESCE((SELECT bs.plan_code FROM business_subscriptions bs
                        WHERE bs.business_id=b.id AND bs.status="active"
                        AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP)
                        AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP)
                        ORDER BY bs.id DESC LIMIT 1),"free") LIMIT 1),0) AS max_gallery_photos
            FROM businesses b
            JOIN destinations d ON d.id=b.destination_id AND d.publication_status="published"
            JOIN service_categories c ON c.code=b.category_code
            JOIN service_category_translations ct ON ct.category_code=c.code AND LOWER(ct.locale)=LOWER(?)
            LEFT JOIN business_translations bt ON bt.business_id=b.id AND bt.locale=?
            LEFT JOIN business_translations bt_es ON bt_es.business_id=b.id AND bt_es.locale="es"
            WHERE b.public_id=? AND b.publication_status="published"
                AND b.information_status<>"PENDIENTE_REVISION"
                AND b.operation_status<>"CERRADO_DEFINITIVO"
                AND (bt.business_id IS NOT NULL OR bt_es.business_id IS NOT NULL) LIMIT 1');
        $query->execute([$locale,$locale,$locale,$publicId]);
        $business=$query->fetch(PDO::FETCH_ASSOC);
        if (!$business || !(bool)$business['has_business_page']) return null;
        $business['id']=(int)$business['id'];
        $business=BusinessCategories::enrich($this->pdo,[$business],$locale)[0];
        $business['features']=$this->features((string)$business['plan_code'],$locale);
        if (!isset($business['features']['business_page'])) return null;
        $business['logo']=$this->asset($business['id'],$locale,'logo');
        $business['cover']=$this->asset($business['id'],$locale,'cover');
        $business['gallery']=$this->gallery($business['id'],$locale,(int)$business['max_gallery_photos']);
        $business['contacts']=BusinessContactVisibility::filter((string)$business['plan_code'],$this->contacts($business['id']));
        $business['page_alias']=$business['plan_code']==='page_premium'?PremiumBusinessProfile::currentAlias($this->pdo,$business['id']):'';
        $business['service_sections']=$business['plan_code']==='page_premium'?(BusinessServiceSections::read($this->pdo,$business['id'])[$locale]??[]):[];
        return $business;
    }

    private function features(string $planCode,string $locale): array
    {
        $q=$this->pdo->prepare('SELECT feature_code FROM subscription_plan_features WHERE plan_code=? AND included=1');
        $q->execute([$planCode]);
        return array_fill_keys(array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN)),true);
    }

    private function asset(int $businessId,string $locale,string $role): ?array
    {
        $q=$this->pdo->prepare('SELECT m.asset_path,m.width,m.height,t.alt_text,t.caption FROM business_media bm
            JOIN media_assets m ON m.id=bm.media_asset_id
            LEFT JOIN media_asset_translations t ON t.media_asset_id=m.id AND t.locale=?
            WHERE bm.business_id=? AND bm.role=? ORDER BY bm.display_order,m.id LIMIT 1');
        $q->execute([$locale,$businessId,$role]);$row=$q->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    private function gallery(int $businessId,string $locale,int $limit): array
    {
        if($limit<1)return [];
        $q=$this->pdo->prepare('SELECT m.asset_path,m.width,m.height,t.alt_text,t.caption FROM business_media bm
            JOIN media_assets m ON m.id=bm.media_asset_id
            LEFT JOIN media_asset_translations t ON t.media_asset_id=m.id AND t.locale=?
            WHERE bm.business_id=? AND bm.role="gallery" ORDER BY bm.display_order,m.id LIMIT ?');
        $q->bindValue(1,$locale);$q->bindValue(2,$businessId,PDO::PARAM_INT);$q->bindValue(3,$limit,PDO::PARAM_INT);$q->execute();return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contacts(int $businessId): array
    {
        $q=$this->pdo->prepare('SELECT channel,contact_value FROM business_contacts WHERE business_id=? AND is_public=1 ORDER BY display_order,id');
        $q->execute([$businessId]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

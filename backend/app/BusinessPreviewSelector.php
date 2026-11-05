<?php
declare(strict_types=1);

/** Data needed by the private administrative business-card preview. */
final class BusinessPreviewSelector
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id):?array
    {
        $q=$this->pdo->prepare('SELECT b.id,b.public_id,b.publication_status,b.information_status,b.category_code AS code,b.address_text,b.location_reference,b.latitude,b.longitude,b.location_map_url,t.display_name,t.summary,t.description,ct.label AS category_label FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN service_category_translations ct ON ct.category_code=b.category_code AND ct.locale="es" WHERE b.id=?');
        $q->execute([$id]);$business=$q->fetch(PDO::FETCH_ASSOC);if(!$business)return null;
        $q=$this->pdo->prepare('SELECT plan_code FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);$business['plan_code']=$q->fetchColumn()?:'free';
        $q=$this->pdo->prepare('SELECT name FROM subscription_plan_translations WHERE plan_code=? AND locale="es"');$q->execute([$business['plan_code']]);$business['plan_name']=$q->fetchColumn()?:$business['plan_code'];
        $q=$this->pdo->prepare('SELECT feature_code FROM subscription_plan_features WHERE plan_code=? AND included=1');$q->execute([$business['plan_code']]);$business['features']=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
        $q=$this->pdo->prepare('SELECT m.asset_path AS image_path,m.width,m.height FROM business_media bm JOIN media_assets m ON m.id=bm.media_asset_id WHERE bm.business_id=? AND bm.role="logo" ORDER BY bm.display_order,bm.media_asset_id LIMIT 1');$q->execute([$id]);$business=array_merge($business,['image_path'=>null,'width'=>null,'height'=>null],$q->fetch(PDO::FETCH_ASSOC)?:[]);
        $q=$this->pdo->prepare('SELECT channel,contact_value FROM business_contacts WHERE business_id=? AND is_public=1 ORDER BY display_order,id');$q->execute([$id]);
        return ['business'=>$business,'contacts'=>$q->fetchAll(PDO::FETCH_ASSOC)];
    }
}

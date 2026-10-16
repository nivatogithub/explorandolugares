<?php
declare(strict_types=1);
/** Public targets and current entitlements, never supplied by the browser. */
final class PublicAudienceTargets {
    public function __construct(private PDO $pdo) {}
    public function business(string $publicId):?array {
        if(!preg_match('/^[a-f0-9-]{16,64}$/iD',$publicId))return null;
        $q=$this->pdo->prepare('SELECT b.id,b.public_id,d.slug AS destination_slug,
            COALESCE((SELECT bs.plan_code FROM business_subscriptions bs WHERE bs.business_id=b.id AND bs.status="active" AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP) AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP) ORDER BY bs.id DESC LIMIT 1),"free") AS plan_code
            FROM businesses b JOIN destinations d ON d.id=b.destination_id
            WHERE b.public_id=? AND b.publication_status="published" AND d.publication_status="published"
            AND b.information_status<>"PENDIENTE_REVISION" AND b.operation_status<>"CERRADO_DEFINITIVO"
            AND EXISTS(SELECT 1 FROM business_translations t WHERE t.business_id=b.id AND t.locale="es") LIMIT 1');
        $q->execute([$publicId]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
        $q=$this->pdo->prepare('SELECT feature_code FROM subscription_plan_features WHERE plan_code=? AND included=1');$q->execute([$row['plan_code']]);$row['features']=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
        $q=$this->pdo->prepare('SELECT has_business_page FROM subscription_plans WHERE code=?');$q->execute([$row['plan_code']]);$row['has_page']=(bool)$q->fetchColumn()&&isset($row['features']['business_page']);
        return $row;
    }
}

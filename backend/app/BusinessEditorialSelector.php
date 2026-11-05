<?php
declare(strict_types=1);

/** Reads data used by the private business editor. */
final class BusinessEditorialSelector
{
    public function __construct(private PDO $pdo) {}
    public function latestVersions():array
    {
        return $this->pdo->query('SELECT v.id,v.business_id,v.status,v.plan_code,v.updated_at,v.note,t.display_name FROM business_editorial_versions v JOIN business_translations t ON t.business_id=v.business_id AND t.locale="es" WHERE v.id=(SELECT MAX(v2.id) FROM business_editorial_versions v2 WHERE v2.business_id=v.business_id) ORDER BY v.updated_at DESC,v.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function businessOptions():array
    {
        return $this->pdo->query('SELECT b.id,t.display_name,d.display_name AS destination_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destination_translations d ON d.destination_id=b.destination_id AND d.locale="es" ORDER BY t.display_name,b.id')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function latestStatus(int $businessId):array|false
    {
        $query=$this->pdo->prepare('SELECT id,status FROM business_editorial_versions WHERE business_id=? ORDER BY id DESC LIMIT 1');
        $query->execute([$businessId]);return $query->fetch(PDO::FETCH_ASSOC);
    }
    public function references(int $versionId):array
    {
        $query=$this->pdo->prepare('SELECT id,storage_name,original_name FROM business_editorial_images WHERE version_id=?');
        $query->execute([$versionId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function referenceNames(int $versionId):array
    {
        $query=$this->pdo->prepare('SELECT id,original_name FROM business_editorial_images WHERE version_id=?');
        $query->execute([$versionId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function mediaAssets():array
    {
        return $this->pdo->query('SELECT id,asset_path FROM media_assets ORDER BY id DESC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function publicId(int $businessId):mixed
    {
        $query=$this->pdo->prepare('SELECT public_id FROM businesses WHERE id=?');$query->execute([$businessId]);return $query->fetchColumn();
    }
}

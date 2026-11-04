<?php
declare(strict_types=1);

/** Reads data used by the private business editor. */
final class BusinessEditorialSelector
{
    public function __construct(private PDO $pdo) {}
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

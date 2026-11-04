<?php
declare(strict_types=1);

require_once __DIR__.'/PlaceReader.php';
require_once __DIR__.'/PlaceDirectorySelector.php';

/** Read-only queries for places. Legacy table names are storage details. */
final class PlaceSelector implements PlaceReader
{
    private const MEDIA_COLUMNS='m.asset_path,m.origin,m.creator,m.source_url,m.license_name,m.license_url,m.width,m.height,m.responsive_srcset,m.responsive_sizes';

    public function __construct(private PDO $pdo) {}

    private function rows(string $sql,array $parameters=[]):array
    {
        $query=$this->pdo->prepare($sql);$query->execute($parameters);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isPublishedId(int $placeId):bool
    {
        $query=$this->pdo->prepare('SELECT id FROM destinations WHERE id=? AND publication_status="published"');
        $query->execute([$placeId]);return (bool)$query->fetchColumn();
    }

    public function findPublished(string $slug,string $locale,bool $preview=false):?array
    {
        $scope=$preview?'d.publication_status IN ("published","draft")':'d.publication_status="published"';
        return $this->rows('SELECT d.id,d.slug,d.destination_type,d.department,d.subregion,EXISTS(SELECT 1 FROM place_labels l WHERE l.place_id=d.id AND l.code="heritage_town" AND l.enabled=1) AS is_heritage_town,d.country_code,d.publication_status,
            t.locale,t.display_name,t.eyebrow,t.headline,t.intro,t.meta_description
            FROM destinations d JOIN destination_translations t ON t.destination_id=d.id
            WHERE d.slug=? AND '.$scope.' AND t.locale=? LIMIT 1',[$slug,$locale])[0]??null;
    }

    public function availableLocales(int $placeId):array
    {
        return array_column($this->rows('SELECT locale FROM destination_translations WHERE destination_id=? AND locale IN ("es","en","pt-BR") AND TRIM(display_name)<>"" ORDER BY locale',[$placeId]),'locale');
    }

    public function history(int $placeId,string $locale):array
    {
        return array_column($this->rows('SELECT tr.body FROM destination_story_blocks b JOIN destination_story_translations tr ON tr.story_block_id=b.id WHERE b.destination_id=? AND b.section_key="history" AND tr.locale=? AND TRIM(tr.body)<>"" ORDER BY b.display_order,b.id',[$placeId,$locale]),'body');
    }

    public function reasons(int $placeId,string $locale):array
    {
        return array_column($this->rows('SELECT tr.body FROM destination_reasons r JOIN destination_reason_translations tr ON tr.reason_id=r.id WHERE r.destination_id=? AND tr.locale=? ORDER BY r.display_order',[$placeId,$locale]),'body');
    }

    public function media(int $placeId,string $locale,string $role):?array
    {
        return $this->rows('SELECT '.self::MEDIA_COLUMNS.',t.alt_text,t.caption
            FROM destination_media dm JOIN media_assets m ON m.id=dm.media_asset_id
            JOIN media_asset_translations t ON t.media_asset_id=m.id AND t.locale=?
            WHERE dm.destination_id=? AND dm.role=? ORDER BY dm.display_order LIMIT 1',[$locale,$placeId,$role])[0]??null;
    }

    public function sources(int $placeId,string $locale):array
    {
        return $this->rows('SELECT section_key,source_url,source_label,display_order FROM destination_sources WHERE destination_id=? AND locale=? ORDER BY section_key,display_order',[$placeId,$locale]);
    }

    /** One row per attraction/media relation; the service groups stable identities. */
    public function attractions(int $placeId,string $locale,bool $preview=false):array
    {
        $scope=$preview?'p.publication_status<>"archived"':'p.publication_status="published"';
        return $this->rows('SELECT p.id,p.slug,p.poi_type,tr.name,tr.description,
            COALESCE(NULLIF(tr.map_url,""),fallback.map_url) AS map_url,
            COALESCE(NULLIF(tr.external_url,""),fallback.external_url) AS external_url,
            COALESCE(NULLIF(tr.external_label,""),fallback.external_label) AS external_label,p.display_order,
            '.self::MEDIA_COLUMNS.',mt.alt_text,mt.caption
            FROM points_of_interest p JOIN point_of_interest_translations tr ON tr.point_of_interest_id=p.id AND tr.locale=?
            LEFT JOIN point_of_interest_translations fallback ON fallback.point_of_interest_id=p.id AND fallback.locale="es"
            LEFT JOIN point_of_interest_media pm ON pm.point_of_interest_id=p.id
            LEFT JOIN media_assets m ON m.id=pm.media_asset_id
            LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=?
            WHERE p.destination_id=? AND '.$scope.' ORDER BY p.display_order,p.id,pm.display_order,pm.media_asset_id',[$locale,$locale,$placeId]);
    }

    public function listPublished(string $locale):array
    {
        return (new PlaceDirectorySelector($this->pdo))->all($locale);
    }

    public function homeAttractions(string $locale):array
    {
        return $this->rows('SELECT d.slug AS destination_slug,dt.display_name AS destination,p.slug,t.name,m.asset_path,mt.alt_text,mt.caption,m.creator,m.license_name,m.source_url,m.license_url,p.display_order,p.id
            FROM destinations d JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale=?
            JOIN points_of_interest p ON p.destination_id=d.id AND p.publication_status="published" AND p.poi_type<>"experience"
            JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id AND t.locale=?
            JOIN media_assets m ON m.id=(SELECT pm.media_asset_id FROM point_of_interest_media pm WHERE pm.point_of_interest_id=p.id ORDER BY pm.display_order,pm.media_asset_id LIMIT 1)
            LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=?
            WHERE d.publication_status="published" AND d.country_code="CO" ORDER BY p.display_order,d.slug,p.id',[$locale,$locale,$locale]);
    }

    public function sitemapLocales():array
    {
        return $this->rows('SELECT d.slug,t.locale FROM destinations d JOIN destination_translations t ON t.destination_id=d.id WHERE d.publication_status="published" AND t.locale IN ("es","en","pt-BR") ORDER BY d.id,t.locale');
    }
}

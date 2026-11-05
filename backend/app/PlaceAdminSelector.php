<?php
declare(strict_types=1);

/** Private place queries. Only the authorized administration service consumes this projection. */
final class PlaceAdminSelector
{
    public function __construct(private PDO $pdo) {}

    public function promotionPlaces():array
    {
        return $this->pdo->query('SELECT d.id,d.publication_status,COALESCE(t.display_name,d.slug) AS name,d.department FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function campaignPlaces():array
    {
        return $this->pdo->query('SELECT d.slug,COALESCE(t.display_name,d.slug) AS name FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY name')->fetchAll();
    }

    private function rows(string $sql,array $parameters=[]):array
    {
        $query=$this->pdo->prepare($sql);$query->execute($parameters);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function all():array
    {
        return $this->rows('SELECT d.id,d.slug,d.destination_type,d.country_code,d.department,d.publication_status,d.display_order,t.display_name
            FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY d.display_order,d.id');
    }

    public function find(int $placeId):?array
    {
        // The private editor retains its complete legacy record, including internal research.
        return $this->rows('SELECT d.*,EXISTS(SELECT 1 FROM place_labels l WHERE l.place_id=d.id AND l.code="heritage_town" AND l.enabled=1) AS is_heritage_town FROM destinations d WHERE d.id=?',[$placeId])[0]??null;
    }

    public function translations(int $placeId):array
    {
        return $this->rows('SELECT * FROM destination_translations WHERE destination_id=?',[$placeId]);
    }

    public function history(int $placeId):array
    {
        return $this->rows('SELECT b.id,b.display_order,t.locale,t.body FROM destination_story_blocks b
            LEFT JOIN destination_story_translations t ON t.story_block_id=b.id
            WHERE b.destination_id=? AND b.section_key="history" ORDER BY b.display_order,b.id',[$placeId]);
    }

    public function reasons(int $placeId):array
    {
        return $this->rows('SELECT r.id,r.display_order,t.locale,t.body FROM destination_reasons r
            LEFT JOIN destination_reason_translations t ON t.reason_id=r.id WHERE r.destination_id=? ORDER BY r.display_order,r.id',[$placeId]);
    }

    public function attractions(int $placeId):array
    {
        return $this->rows('SELECT p.id,p.slug,p.poi_type,p.latitude,p.longitude,p.publication_status,p.display_order,t.locale,t.name,t.description,t.map_url,t.external_url,t.external_label,
            (SELECT pm.media_asset_id FROM point_of_interest_media pm WHERE pm.point_of_interest_id=p.id ORDER BY pm.display_order LIMIT 1) AS media_asset_id
            FROM points_of_interest p LEFT JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id
            WHERE p.destination_id=? ORDER BY CASE WHEN p.publication_status=\'archived\' THEN 1 ELSE 0 END,p.display_order,p.id',[$placeId]);
    }

    public function sources(int $placeId):array
    {
        return $this->rows('SELECT locale,section_key,source_url,source_label FROM destination_sources WHERE destination_id=? ORDER BY locale,section_key,display_order,id',[$placeId]);
    }

    public function mediaBindings(int $placeId):array
    {
        return $this->rows('SELECT dm.role,dm.media_asset_id,m.asset_path FROM destination_media dm JOIN media_assets m ON m.id=dm.media_asset_id WHERE dm.destination_id=?',[$placeId]);
    }

    public function mediaOptions():array
    {
        return $this->rows('SELECT id,asset_path,creator FROM media_assets ORDER BY asset_path');
    }

    public function placeExists(int $placeId):bool
    {
        return (bool)$this->rows('SELECT id FROM destinations WHERE id=?',[$placeId]);
    }

    public function mediaExists(int $mediaId):bool
    {
        return (bool)$this->rows('SELECT id FROM media_assets WHERE id=?',[$mediaId]);
    }

    public function businessCount(int $placeId):int
    {
        return (int)$this->rows('SELECT COUNT(*) AS total FROM businesses WHERE destination_id=?',[$placeId])[0]['total'];
    }

    public function nextDisplayOrder():int
    {
        return (int)$this->rows('SELECT COALESCE(MAX(display_order),0)+1 AS next_order FROM destinations')[0]['next_order'];
    }

    public function paragraphIds(int $placeId,string $section):array
    {
        $sql=match($section){
            'history'=>'SELECT id FROM destination_story_blocks WHERE destination_id=? AND section_key="history" ORDER BY display_order,id',
            'reasons'=>'SELECT id FROM destination_reasons WHERE destination_id=? ORDER BY display_order,id',
            default=>throw new InvalidArgumentException('Sección de lugar desconocida.'),
        };
        return array_map('intval',array_column($this->rows($sql,[$placeId]),'id'));
    }

    public function attractionIds(int $placeId):array
    {
        return array_map('intval',array_column($this->rows('SELECT id FROM points_of_interest WHERE destination_id=? ORDER BY display_order,id',[$placeId]),'id'));
    }
}

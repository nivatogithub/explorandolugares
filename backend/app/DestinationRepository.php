<?php
declare(strict_types=1);

final class DestinationRepository
{
    public function __construct(private PDO $pdo) {}

    public function findPublished(string $slug, string $locale, bool $preview = false): ?array
    {
        $locale = in_array($locale, ['es','en','pt-BR'], true) ? $locale : 'es';
        $publicationFilter=$preview?'d.publication_status IN ("published","draft")':'d.publication_status="published"';
        $sql = 'SELECT d.id,d.slug,d.destination_type,d.department,d.subregion,d.country_code,d.publication_status,
                       t.locale,t.display_name,t.eyebrow,t.headline,t.intro,t.meta_description
                FROM destinations d JOIN destination_translations t ON t.destination_id=d.id
                WHERE d.slug=? AND '.$publicationFilter.' AND t.locale=? LIMIT 1';
        $stmt=$this->pdo->prepare($sql); $stmt->execute([$slug,$locale]); $destination=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$destination) return null;
        $id=(int)$destination['id'];
        $destination['history']=$this->translatedRows(
            'SELECT b.display_order,tr.body FROM destination_story_blocks b JOIN destination_story_translations tr ON tr.story_block_id=b.id WHERE b.destination_id=? AND b.section_key="history" AND tr.locale=? ORDER BY b.display_order', $id,$locale);
        $destination['reasons']=$this->translatedRows(
            'SELECT r.display_order,tr.body FROM destination_reasons r JOIN destination_reason_translations tr ON tr.reason_id=r.id WHERE r.destination_id=? AND tr.locale=? ORDER BY r.display_order', $id,$locale);
        $destination['hero']=$this->mediaForDestination($id,$locale,'hero');
        $destination['history_media']=$this->mediaForDestination($id,$locale,'history');
        $stmt=$this->pdo->prepare('SELECT section_key,source_url,source_label,display_order FROM destination_sources WHERE destination_id=? AND locale=? ORDER BY section_key,display_order');
        $stmt->execute([$id,$locale]); $destination['sources']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt=$this->pdo->prepare('SELECT p.id,p.slug,tr.name,tr.description,tr.map_url,p.display_order,
                    m.asset_path,m.origin,m.creator,m.source_url,m.license_name,m.license_url,mt.alt_text,mt.caption,m.width,m.height
                FROM points_of_interest p JOIN point_of_interest_translations tr ON tr.point_of_interest_id=p.id AND tr.locale=?
                LEFT JOIN point_of_interest_media pm ON pm.point_of_interest_id=p.id
                LEFT JOIN media_assets m ON m.id=pm.media_asset_id
                LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=?
                WHERE p.destination_id=? AND '.($preview?'p.publication_status<>"archived"':'p.publication_status="published"').' ORDER BY p.display_order,pm.display_order');
        $stmt->execute([$locale,$locale,$id]); $destination['places']=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key=(int)$row['id'];
            if (!isset($destination['places'][$key])) $destination['places'][$key]=[
                'slug'=>$row['slug'],'name'=>$row['name'],'description'=>$row['description'],'map_url'=>$row['map_url'],'media'=>[]
            ];
            if ($row['asset_path'] !== null) $destination['places'][$key]['media'][]=$this->mediaRow($row);
        }
        $destination['places']=array_values($destination['places']);
        $stmt=$this->pdo->prepare('SELECT DISTINCT c.code,ct.label FROM service_categories c JOIN service_category_translations ct ON ct.category_code=c.code AND ct.locale=? JOIN businesses b ON b.category_code=c.code AND b.destination_id=? AND b.publication_status="published" AND b.operation_status <> "CERRADO_DEFINITIVO" WHERE c.is_active=1 ORDER BY c.display_order');
        $stmt->execute([$locale,$id]); $destination['categories']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt=$this->pdo->prepare('SELECT b.id,b.public_id,b.slug,b.information_status,b.claim_status,b.operation_status,b.display_order,
                    c.code,ct.label,bt.display_name,bt.summary,bt.description,
                    m.asset_path AS image_path,m.creator,m.source_url AS image_source,m.license_name,m.license_url,mt.alt_text AS image_alt,mt.caption AS image_caption,m.width,m.height
                FROM businesses b JOIN business_translations bt ON bt.business_id=b.id AND bt.locale=?
                JOIN service_categories c ON c.code=b.category_code JOIN service_category_translations ct ON ct.category_code=c.code AND ct.locale=?
                LEFT JOIN media_assets m ON m.id=(SELECT bm.media_asset_id FROM business_media bm WHERE bm.business_id=b.id ORDER BY bm.display_order,bm.media_asset_id LIMIT 1)
                LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=?
                WHERE b.destination_id=? AND b.publication_status="published" AND b.operation_status <> "CERRADO_DEFINITIVO"
                ORDER BY b.display_order,bt.display_name');
        $stmt->execute([$locale,$locale,$locale,$id]); $destination['businesses']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $businessIds=array_column($destination['businesses'],'id');$destination['public_contacts']=[];
        if ($businessIds) {
            $marks=implode(',',array_fill(0,count($businessIds),'?'));
            $contacts=$this->pdo->prepare("SELECT business_id,channel,contact_value,display_order FROM business_contacts WHERE is_public=1 AND business_id IN ({$marks}) ORDER BY display_order");
            $contacts->execute($businessIds);
            foreach ($contacts->fetchAll(PDO::FETCH_ASSOC) as $contact) $destination['public_contacts'][(int)$contact['business_id']][]=$contact;
        }
        return $destination;
    }

    private function translatedRows(string $sql,int $id,string $locale): array
    { $stmt=$this->pdo->prepare($sql);$stmt->execute([$id,$locale]);return array_map(static fn($r)=>$r['body'],$stmt->fetchAll(PDO::FETCH_ASSOC)); }

    private function mediaForDestination(int $id,string $locale,string $role): ?array
    {
        $stmt=$this->pdo->prepare('SELECT m.asset_path,m.origin,m.creator,m.source_url,m.license_name,m.license_url,m.width,m.height,t.alt_text,t.caption
            FROM destination_media dm JOIN media_assets m ON m.id=dm.media_asset_id
            JOIN media_asset_translations t ON t.media_asset_id=m.id AND t.locale=? WHERE dm.destination_id=? AND dm.role=? ORDER BY dm.display_order LIMIT 1');
        $stmt->execute([$locale,$id,$role]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?$this->mediaRow($row):null;
    }

    private function mediaRow(array $row): array
    { return ['path'=>$row['asset_path'],'origin'=>$row['origin']??'external','alt'=>$row['alt_text'],'caption'=>$row['caption'],'creator'=>$row['creator'],'source_url'=>$row['source_url'],'license_name'=>$row['license_name'],'license_url'=>$row['license_url'],'width'=>$row['width'],'height'=>$row['height']]; }
}

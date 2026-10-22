<?php
declare(strict_types=1);

/** Applies only the owner-reviewed map fields, preserving all other live data. */
final class ReviewedMapLinks
{
    public static function catalog(): array
    {
        return json_decode(file_get_contents(__DIR__.'/content/reviewed-map-links.json'),true,512,JSON_THROW_ON_ERROR);
    }

    public static function apply(PDO $db): array
    {
        $catalog=self::catalog();
        $find=$db->prepare('SELECT p.id,p.poi_type,t.locale,t.map_url FROM points_of_interest p JOIN destinations d ON d.id=p.destination_id JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id WHERE d.slug=? AND p.slug=?');
        $update=$db->prepare('UPDATE point_of_interest_translations SET map_url=? WHERE point_of_interest_id=? AND locale=? AND map_url=?');
        $coordinates=$db->prepare('UPDATE points_of_interest SET latitude=?,longitude=? WHERE id=?');
        $changed=0;$checked=0;
        $db->beginTransaction();
        try {
            foreach($catalog['places'] as $place){
                $find->execute([$place['destination'],$place['slug']]);$rows=$find->fetchAll(PDO::FETCH_ASSOC);
                if(count($rows)!==3||array_diff(['es','en','pt-BR'],array_column($rows,'locale')))throw new RuntimeException('Missing reviewed map translations: '.$place['destination'].'/'.$place['slug']);
                foreach($rows as $row){
                    if($row['poi_type']!==$place['type'])throw new RuntimeException('Reviewed map type changed.');
                    $old=(string)$row['map_url'];$new=$place['url'];
                    if($old===$new){$checked++;continue;}
                    // Stop rather than overwrite an editorial update made since review.
                    if(!in_array($old,array_values($place['before']),true))throw new RuntimeException('Map changed since review: '.$place['destination'].'/'.$place['slug']);
                    $update->execute([$new,$row['id'],$row['locale'],$old]);
                    if($update->rowCount()!==1)throw new RuntimeException('Concurrent map edit; no changes committed.');
                    $changed++;$checked++;
                }
                if($place['coordinates']!==null)$coordinates->execute([$place['coordinates']['latitude'],$place['coordinates']['longitude'],$rows[0]['id']]);
            }
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        return ['checked_translations'=>$checked,'changed_translations'=>$changed,'places'=>count($catalog['places'])];
    }
}

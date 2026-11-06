<?php
declare(strict_types=1);
require_once __DIR__.'/PlaceSelector.php';
/** One editorial selection for the initial home HTML and its JSON endpoint. */
final class HomeAttractions
{
    public static function items(PDO $pdo,string $locale,string $public):array
    {
        $selection=json_decode(file_get_contents(__DIR__.'/content/home-featured-attractions.json'),true,32,JSON_THROW_ON_ERROR);
        $published=[];
        foreach((new PlaceSelector($pdo))->homeAttractions($locale) as $row){
            $path=ltrim((string)$row['asset_path'],'/');if(!str_starts_with($path,'assets/')||str_contains($path,'..'))continue;
            $published[$row['destination_slug'].'/'.$row['slug']]=$row;
        }
        $rows=[];$prefix=$locale==='en'?'/en':($locale==='pt-BR'?'/pt-br':'');
        foreach($selection['items'] as $item){
            $key=$item['destination_slug'].'/'.$item['attraction_slug'];
            // A draft, removed translation or missing media must never become a placeholder/link.
            if(!isset($published[$key]))continue;
            $row=$published[$key];$row['image']='/'.ltrim((string)$row['asset_path'],'/');
            $row['url']=$prefix.'/lugares/'.rawurlencode($row['destination_slug']).'/#que-conocer';
            $rows[]=array_intersect_key($row,array_flip(['destination_slug','destination','slug','name','image','url']));
        }
        return $rows;
    }
}

<?php
declare(strict_types=1);
require_once __DIR__.'/PlaceSelector.php';
/** One public selection for the initial home HTML and its JSON endpoint. */
final class HomeAttractions
{
    public static function items(PDO $pdo,string $locale,string $public):array
    {
        $groups=[];
        foreach((new PlaceSelector($pdo))->homeAttractions($locale) as $row){
            $path=ltrim((string)$row['asset_path'],'/');if(!str_starts_with($path,'assets/')||str_contains($path,'..'))continue;
            $row['image']='/'.$path;$groups[$row['destination_slug']][]=$row;
        }
        $rows=[];
        while(count($rows)<6&&$groups){
            foreach(array_keys($groups) as $key){
                $row=array_shift($groups[$key]);$prefix=$locale==='en'?'/en':($locale==='pt-BR'?'/pt-br':'');
                $row['url']=$prefix.'/lugares/'.rawurlencode($row['destination_slug']).'/#que-conocer';unset($row['asset_path'],$row['display_order'],$row['id']);$rows[]=$row;
                if(!$groups[$key])unset($groups[$key]);if(count($rows)===6)break;
            }
        }
        return $rows;
    }
}

<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';
require_once __DIR__.'/PlaceLabelPresentation.php';
require_once __DIR__.'/PlaceDirectoryService.php';

final class PlaceDirectoryPresentation
{
    public static function render(array $data,string $publicRoot):void
    {
        PortalPresentation::setPublicRoot($publicRoot);
        $copy=json_decode(file_get_contents(__DIR__.'/presentation/place-directory.json'),true,32,JSON_THROW_ON_ERROR)[$data['locale']];
        $locale=$data['locale'];$filters=$data['filters'];$path=PlaceDirectoryService::url($locale,[]);
        $languages=[];foreach(['es','en','pt-BR'] as $language)$languages[$language]=PlaceDirectoryService::url($language,$filters,$data['page']);
        $context=['locale'=>$locale,'path'=>$path,'languages'=>$languages];$canonical='https://explorandolugares.com'.PlaceDirectoryService::url($locale,$filters,$data['page']);
        $filtered=$filters['q']!==''||$filters['department']!==''||$filters['region']!==''||$filters['heritage'];
        $title=$copy['title'].($data['page']>1?' · '.sprintf($copy['pageTitle'],$data['page']):'').' | Explorando Lugares';
        $rows=$data['rows'];foreach($rows as &$row){
            $row['labels']=PlaceLabelPresentation::labels($row,$locale);
            $row['heritageText']=array_column($row['labels'],'text','code')['heritage_town']??'';
            $row['url']=rtrim($path,'/').'/'.rawurlencode($row['slug']).'/';
        }unset($row);
        $pageLinks=[];$numbers=array_unique([1,...range(max(1,$data['page']-2),min($data['pages'],$data['page']+2)),$data['pages']]);sort($numbers);
        foreach($numbers as $number)$pageLinks[]=['number'=>$number,'url'=>PlaceDirectoryService::url($locale,$filters,$number)];
        $previous=$data['page']>1?PlaceDirectoryService::url($locale,$filters,$data['page']-1):null;
        $next=$data['page']<$data['pages']?PlaceDirectoryService::url($locale,$filters,$data['page']+1):null;
        $items=[];foreach($rows as $index=>$row)$items[]=['@type'=>'ListItem','position'=>$data['first']+$index,'url'=>'https://explorandolugares.com'.$row['url'],'name'=>$row['display_name']];
        $structured=json_encode(['@context'=>'https://schema.org','@graph'=>[['@type'=>'CollectionPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>$title,'inLanguage'=>$locale,'mainEntity'=>['@type'=>'ItemList','itemListElement'=>$items]]]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        $resources=[];
        foreach(['places.css','place-directory.css'] as $name){
            $resource='/assets/'.$name;$resources[]=$resource.'?v='.substr(hash_file('sha256',$publicRoot.$resource),0,12);
        }
        $script='/assets/place-directory.js';$script.='?v='.substr(hash_file('sha256',$publicRoot.$script),0,12);
        $e=[PortalPresentation::class,'escape'];require __DIR__.'/views/place-directory.php';
    }
}

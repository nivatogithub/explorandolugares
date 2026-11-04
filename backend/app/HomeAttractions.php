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
    public static function render(array $items,string $locale):string
    {
        $e=[PortalPresentation::class,'escape'];$html='';
        foreach($items as $item){
            $html.='<article class="home-attraction"><a href="'.$e($item['url']).'"><img draggable="false" src="'.$e($item['image']).'" alt="'.$e($item['alt_text']?:$item['name']).'" loading="lazy" width="640" height="420"><h3>'.$e($item['name']).'</h3><p>'.$e($item['destination']).'</p></a>';
            $credit=$e($item['creator']==='Explorando Lugares'?$item['creator']:implode(' · ',array_filter([$item['creator'],$item['license_name']])));
            foreach([['source_url',$locale==='en'?'Source':($locale==='pt-BR'?'Fonte':'Fuente')],['license_url',$locale==='en'?'License':($locale==='pt-BR'?'Licença':'Licencia')]] as [$key,$label])if(preg_match('~^https?://~i',$item[$key]??''))$credit.=' · <a href="'.$e($item[$key]).'" target="_blank" rel="noopener noreferrer">'.$label.'</a>';
            if($credit!=='')$html.='<small>'.$credit.'</small>';
            $html.='</article>';
        }
        return $html;
    }
}

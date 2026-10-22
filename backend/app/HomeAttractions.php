<?php
declare(strict_types=1);
require_once __DIR__.'/DestinationPresentation.php';
/** One public selection for the initial home HTML and its JSON endpoint. */
final class HomeAttractions
{
    public static function items(PDO $pdo,string $locale,string $public):array
    {
        $sql='SELECT d.slug AS destination_slug,dt.display_name AS destination,p.slug,t.name,m.asset_path,mt.alt_text,mt.caption,m.creator,m.license_name,m.source_url,m.license_url,p.display_order,p.id FROM destinations d JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale=? JOIN points_of_interest p ON p.destination_id=d.id AND p.publication_status="published" AND p.poi_type<>"experience" JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id AND t.locale=? JOIN media_assets m ON m.id=(SELECT pm.media_asset_id FROM point_of_interest_media pm WHERE pm.point_of_interest_id=p.id ORDER BY pm.display_order,pm.media_asset_id LIMIT 1) LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=? WHERE d.publication_status="published" AND d.country_code="CO" ORDER BY p.display_order,d.slug,p.id';
        $query=$pdo->prepare($sql);$query->execute([$locale,$locale,$locale]);$groups=[];
        $approved=json_decode(file_get_contents(__DIR__.'/content/release-places-businesses.json'),true,512,JSON_THROW_ON_ERROR)['public_places'];
        $approved=array_map(static fn($item)=>is_array($item)?$item['slug']:$item,$approved);
        foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row){
            if(!in_array($row['destination_slug'],$approved,true))continue;
            $selected=DestinationPresentation::media($row['destination_slug'],$row['slug'],$locale);
            if($selected){$row['asset_path']=$selected['path'];$row['alt_text']=is_array($selected['alt'])?($selected['alt'][$locale]??''):$selected['alt'];$row['caption']=$selected['caption']??'';$row['creator']='Explorando Lugares';$row['license_name']='';$row['source_url']='';$row['license_url']='';}
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
            $html.='<article class="home-attraction"><a href="'.$e($item['url']).'"><img src="'.$e($item['image']).'" alt="'.$e($item['alt_text']?:$item['name']).'" loading="lazy" width="640" height="420"><h3>'.$e($item['name']).'</h3><p>'.$e($item['destination']).'</p></a>';
            $credit=$e($item['creator']==='Explorando Lugares'?$item['creator']:implode(' · ',array_filter([$item['creator'],$item['license_name']])));
            foreach([['source_url',$locale==='en'?'Source':($locale==='pt-BR'?'Fonte':'Fuente')],['license_url',$locale==='en'?'License':($locale==='pt-BR'?'Licença':'Licencia')]] as [$key,$label])if(preg_match('~^https?://~i',$item[$key]??''))$credit.=' · <a href="'.$e($item[$key]).'" target="_blank" rel="noopener noreferrer">'.$label.'</a>';
            if($credit!=='')$html.='<small>'.$credit.'</small>';
            $html.='</article>';
        }
        return $html;
    }
}

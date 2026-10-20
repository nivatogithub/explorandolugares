<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
header('Cache-Control: no-store');
$locale=is_string($_GET['lang']??null)&&in_array($_GET['lang'],['es','en','pt-BR'],true)?$_GET['lang']:'es';
try{
 $sql='SELECT d.slug AS destination_slug,dt.display_name AS destination,p.slug,t.name,m.asset_path,mt.alt_text,mt.caption,m.creator,m.license_name,m.source_url,m.license_url,p.display_order,p.id FROM destinations d JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale=? JOIN points_of_interest p ON p.destination_id=d.id AND p.publication_status="published" AND p.poi_type<>"experience" JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id AND t.locale=? JOIN media_assets m ON m.id=(SELECT pm.media_asset_id FROM point_of_interest_media pm WHERE pm.point_of_interest_id=p.id ORDER BY pm.display_order,pm.media_asset_id LIMIT 1) LEFT JOIN media_asset_translations mt ON mt.media_asset_id=m.id AND mt.locale=? WHERE d.publication_status="published" AND d.country_code="CO" ORDER BY p.display_order,d.slug,p.id';
 $q=db()->prepare($sql);$q->execute([$locale,$locale,$locale]);$groups=[];
 $approved=json_decode(file_get_contents(dirname(__DIR__,2).'/app/content/release-places-businesses.json'),true,512,JSON_THROW_ON_ERROR)['public_places'];
 $approved=array_map(static fn($v)=>is_array($v)?$v['slug']:$v,$approved);
 $visual=json_decode(file_get_contents(dirname(__DIR__).'/assets/destinos-semirrealistas.json'),true,512,JSON_THROW_ON_ERROR);
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){if(!in_array($r['destination_slug'],$approved,true))continue;
 $v=$visual[$r['destination_slug']]['places'][$r['slug']]??null;if($v){$r['asset_path']=$v['path'];$r['alt_text']=$v['alt'];$r['caption']=$v['caption']??'';$r['creator']='Explorando Lugares';$r['license_name']='';$r['source_url']='';$r['license_url']='';}
 $path=ltrim((string)$r['asset_path'],'/');if(!str_starts_with($path,'assets/')||str_contains($path,'..'))continue;$r['image']='/'.$path;$groups[$r['destination_slug']][]=$r;}
 $rows=[];while(count($rows)<6&&$groups){foreach($groups as $key=>&$group){$r=array_shift($group);$prefix=$locale==='en'?'/en':($locale==='pt-BR'?'/pt-br':'');$r['url']=$prefix.'/destinos/'.rawurlencode($r['destination_slug']).'/#que-conocer';unset($r['asset_path'],$r['display_order'],$r['id']);$rows[]=$r;if(!$group)unset($groups[$key]);if(count($rows)===6)break;}unset($group);}
 json_response(['items'=>$rows,'selection'=>'editorial','ratings'=>false]);
}catch(Throwable){json_response(['items'=>[],'available'=>false],503);}

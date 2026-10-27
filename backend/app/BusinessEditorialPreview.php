<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessShowcase.php';
require_once __DIR__.'/BusinessContactVisibility.php';
/** Build the view model from a private version, using the production templates. */
final class BusinessEditorialPreview
{
 public static function build(PDO $pdo,array $version,string $locale):array{
  $p=json_decode($version['payload_json'],true,32,JSON_THROW_ON_ERROR);$id=(int)$version['business_id'];
  // Older local fixtures can contain an empty snapshot. Render stored business data read-only;
  // never fabricate content, repair the database or require payment merely to preview it.
  if($p===[]){
   $q=$pdo->prepare('SELECT id,destination_id,category_code,address_text,location_reference,latitude,longitude,location_source,location_map_url,location_confirmed,opening_hours,price_range,capacity_count,menu_url,reservations_url FROM businesses WHERE id=?');$q->execute([$id]);$p=$q->fetch(PDO::FETCH_ASSOC)?:throw new RuntimeException('El negocio no existe.');
   $q=$pdo->prepare('SELECT locale,display_name,summary,description FROM business_translations WHERE business_id=?');$q->execute([$id]);foreach($q as $text)$p['translations'][$text['locale']]=$text;
   $q=$pdo->prepare('SELECT channel,contact_value,is_public FROM business_contacts WHERE business_id=? AND is_public=1');$q->execute([$id]);$p['contacts']=$q->fetchAll(PDO::FETCH_ASSOC);
   $q=$pdo->prepare('SELECT category_code FROM business_categories WHERE business_id=?');$q->execute([$id]);$p['category_codes']=$q->fetchAll(PDO::FETCH_COLUMN);
   $q=$pdo->prepare('SELECT role,media_asset_id FROM business_media WHERE business_id=? ORDER BY display_order');$q->execute([$id]);foreach($q as $media)$p['media'][$media['role']][]=(int)$media['media_asset_id'];
  }
  $translation=$p['translations'][$locale]??$p['translations']['es']??null;
  if(!is_array($translation)||empty($p['category_code']))throw new RuntimeException('Todavía no hay contenido guardado para esta vista previa.');
  $q=$pdo->prepare('SELECT b.public_id,b.slug,b.information_status,b.operation_status,d.slug AS destination_slug,d.department,d.country_code FROM businesses b JOIN destinations d ON d.id=b.destination_id WHERE b.id=?');$q->execute([$id]);$b=$q->fetch(PDO::FETCH_ASSOC);if(!$b)throw new RuntimeException('El negocio no existe.');
  $b=array_merge($b,$p,$translation);$b['id']=$id;$b['plan_code']=$version['plan_code'];$b['plan_name']=['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'][$version['plan_code']];
  $q=$pdo->prepare('SELECT feature_code FROM subscription_plan_features WHERE plan_code=? AND included=1');$q->execute([$version['plan_code']]);$b['features']=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
  $q=$pdo->prepare('SELECT label FROM service_category_translations WHERE category_code=? AND locale=?');$q->execute([$b['category_code'],$locale]);$b['category_label']=$q->fetchColumn()?:$b['category_code'];$b['category_labels']=[$b['category_label']];$b['code']=$b['category_code'];$b['category_codes']=array_values(array_unique([$b['category_code'],...($p['category_codes']??[])]));$b['category_labels']=[];foreach(array_unique([$b['category_code'],...$b['category_codes']]) as $category){$q=$pdo->prepare('SELECT label FROM service_category_translations WHERE category_code=? AND locale IN (?,"es") ORDER BY CASE WHEN locale=? THEN 0 ELSE 1 END LIMIT 1');$q->execute([$category,$locale,$locale]);$b['category_labels'][]=$q->fetchColumn()?:$category;}
  // The owner-only preview must show the channels submitted with the request,
  // even though they remain non-public until the editorial version is approved.
  $previewContacts=array_map(static fn(array $contact):array=>array_replace($contact,['is_public'=>1]),array_values($p['contacts']??[]));
  $b['contacts']=BusinessContactVisibility::filter($b['plan_code'],$previewContacts);$b['page_alias']='';$b['service_sections']=$b['plan_code']==='page_premium'?($p['service_sections'][$locale]??[]):[];
  $b['logo']=null;$b['cover']=null;$b['gallery']=[];
  foreach(['logo','cover','gallery'] as $role)foreach((array)($p['media'][$role]??[]) as $asset){if(!(int)$asset)continue;$q=$pdo->prepare('SELECT m.asset_path,m.width,m.height,t.alt_text,t.caption FROM media_assets m LEFT JOIN media_asset_translations t ON t.media_asset_id=m.id AND t.locale=? WHERE m.id=?');$q->execute([$locale,$asset]);$row=$q->fetch(PDO::FETCH_ASSOC);if($row){if($role==='gallery')$b['gallery'][]=$row;else $b[$role]=$row;}}
  if(!in_array($b['plan_code'],['page','page_premium'],true)){$b['cover']=null;$b['gallery']=[];}
  $b['image_path']=$b['logo']['asset_path']??null;$b['width']=$b['logo']['width']??null;$b['height']=$b['logo']['height']??null;
  $b['preview_only']=true;
  $b['demo_page_url']='/business/editorial.php?business='.$id.'&version='.$version['id'].'&preview=page&lang='.rawurlencode($locale);
  $b['showcase']=$b['plan_code']==='page_premium'?BusinessShowcase::renderData($pdo,$id,$p['showcase']??[],$locale):['products'=>[],'experiences'=>[]];
  // Original merchant photos are visible only in authenticated private draft previews.
  if(in_array($version['status'],['draft','in_editorial','changes_requested','rejected'],true)){
   $sourceImages=$p['source_images']??[];
   // First private draft: onboarding photos are copied to the version before
   // an editor assigns slots. Its first reference is the temporary cover.
   if(in_array($b['plan_code'],['page','page_premium'],true)&&empty($sourceImages['cover'])){$q=$pdo->prepare('SELECT storage_name FROM business_editorial_images WHERE version_id=? ORDER BY id LIMIT 1');$q->execute([$version['id']]);if($cover=$q->fetchColumn())$sourceImages['cover']=$cover;}
   foreach($sourceImages as $slot=>$name){$q=$pdo->prepare('SELECT id,width,height FROM business_editorial_images WHERE version_id=? AND storage_name=?');$q->execute([$version['id'],$name]);if(!$ref=$q->fetch(PDO::FETCH_ASSOC))continue;$media=['asset_path'=>'business/editorial-image.php?id='.$ref['id'],'width'=>$ref['width'],'height'=>$ref['height'],'alt_text'=>'Fotografía original del negocio · pendiente de preparación editorial'];
    if($slot==='cover'&&in_array($b['plan_code'],['page','page_premium'],true))$b['cover']=$media;
   }
   if($b['plan_code']==='page_premium'){$b['showcase']['products']=[];foreach($p['showcase']['products']??[] as $i=>$row){$path=null;$source=$p['source_images']['product_'.$i]??'';if($source){$q=$pdo->prepare('SELECT id FROM business_editorial_images WHERE version_id=? AND storage_name=?');$q->execute([$version['id'],$source]);if($ref=$q->fetchColumn())$path='business/editorial-image.php?id='.$ref;}if(!$path&&!empty($row['asset_id'])){$q=$pdo->prepare('SELECT asset_path FROM media_assets WHERE id=?');$q->execute([$row['asset_id']]);$path=$q->fetchColumn();}if($path)$b['showcase']['products'][]=array_merge($row['translations'][$locale]??$row['translations']['es'],['asset_path'=>$path]);}}
  }
  return $b;
 }
}

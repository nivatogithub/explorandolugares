<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessShowcase.php';
require_once __DIR__.'/BusinessContactVisibility.php';
/** Build the view model from a private version, using the production templates. */
final class BusinessEditorialPreview
{
 public static function build(PDO $pdo,array $version,string $locale):array{
  $p=json_decode($version['payload_json'],true,32,JSON_THROW_ON_ERROR);$id=(int)$version['business_id'];
  $q=$pdo->prepare('SELECT b.public_id,b.slug,b.information_status,b.operation_status,d.slug AS destination_slug,d.department,d.country_code FROM businesses b JOIN destinations d ON d.id=b.destination_id WHERE b.id=?');$q->execute([$id]);$b=$q->fetch(PDO::FETCH_ASSOC);if(!$b)throw new RuntimeException('El negocio no existe.');
  $b=array_merge($b,$p,$p['translations'][$locale]??$p['translations']['es']);$b['id']=$id;$b['plan_code']=$version['plan_code'];$b['plan_name']=['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'][$version['plan_code']];
  $q=$pdo->prepare('SELECT feature_code FROM subscription_plan_features WHERE plan_code=? AND included=1');$q->execute([$version['plan_code']]);$b['features']=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
  $q=$pdo->prepare('SELECT label FROM service_category_translations WHERE category_code=? AND locale=?');$q->execute([$b['category_code'],$locale]);$b['category_label']=$q->fetchColumn()?:$b['category_code'];$b['category_labels']=[$b['category_label']];$b['code']=$b['category_code'];$b['category_codes']=array_values(array_unique([$b['category_code'],...($p['category_codes']??[])]));$b['category_labels']=[];foreach(array_unique([$b['category_code'],...$b['category_codes']]) as $category){$q=$pdo->prepare('SELECT label FROM service_category_translations WHERE category_code=? AND locale IN (?,"es") ORDER BY CASE WHEN locale=? THEN 0 ELSE 1 END LIMIT 1');$q->execute([$category,$locale,$locale]);$b['category_labels'][]=$q->fetchColumn()?:$category;}
  $b['contacts']=BusinessContactVisibility::filter($b['plan_code'],array_values($p['contacts']??[]));$b['page_alias']='';$b['service_sections']=$b['plan_code']==='page_premium'?($p['service_sections'][$locale]??[]):[];
  $b['logo']=null;$b['cover']=null;$b['gallery']=[];
  foreach(['logo','cover','gallery'] as $role)foreach((array)($p['media'][$role]??[]) as $asset){if(!(int)$asset)continue;$q=$pdo->prepare('SELECT m.asset_path,m.width,m.height,t.alt_text,t.caption FROM media_assets m LEFT JOIN media_asset_translations t ON t.media_asset_id=m.id AND t.locale=? WHERE m.id=?');$q->execute([$locale,$asset]);$row=$q->fetch(PDO::FETCH_ASSOC);if($row){if($role==='gallery')$b['gallery'][]=$row;else $b[$role]=$row;}}
  if(!in_array($b['plan_code'],['page','page_premium'],true)){$b['cover']=null;$b['gallery']=[];}
  $b['image_path']=$b['logo']['asset_path']??null;$b['width']=$b['logo']['width']??null;$b['height']=$b['logo']['height']??null;
  $b['demo_page_url']='/business/editorial.php?business='.$id.'&version='.$version['id'].'&preview=page&lang='.rawurlencode($locale);
  $b['showcase']=$b['plan_code']==='page_premium'?BusinessShowcase::renderData($pdo,$id,$p['showcase']??[],$locale):['products'=>[],'experiences'=>[]];
  return $b;
 }
}

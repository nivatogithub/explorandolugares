<?php
declare(strict_types=1);
/** Approved Premium content lives in the immutable editorial snapshot, never in a public draft. */
final class BusinessShowcase
{
 public static function published(PDO $pdo,int $business):array{
  try{$q=$pdo->prepare('SELECT payload_json FROM business_editorial_versions WHERE business_id=? AND status="published" ORDER BY id DESC LIMIT 1');$q->execute([$business]);$json=$q->fetchColumn();return $json?(json_decode($json,true,32,JSON_THROW_ON_ERROR)['showcase']??[]):[];}
  catch(PDOException $e){if(str_contains($e->getMessage(),'no such table')||($e->errorInfo[1]??0)===1146)return [];throw $e;}
 }
 public static function clean(PDO $pdo,int $business,array $input,array $previous,bool $editor):array{
  $out=['products'=>[],'experience_ids'=>[]];$products=$input['products']??[];if(!is_array($products)||count($products)>3)throw new RuntimeException('Premium admite hasta tres productos o servicios con imagen.');
  foreach($products as $i=>$product){if(!is_array($product))throw new RuntimeException('Revisa los productos.');$row=['translations'=>[],'asset_id'=>$editor?(int)($product['asset_id']??0):(int)($previous['products'][$i]['asset_id']??0)];
   foreach($editor?['es','en','pt-BR']:['es'] as $locale)foreach(['name'=>100,'description'=>400] as $field=>$limit){$value=trim((string)($product['translations'][$locale][$field]??$previous['products'][$i]['translations'][$locale][$field]??''));if(preg_match_all('/./us',$value)>$limit)throw new RuntimeException('El nombre del producto admite 100 caracteres y su descripción 400.');$row['translations'][$locale][$field]=$value;}
   foreach(['en','pt-BR'] as $locale)if(!isset($row['translations'][$locale]))$row['translations'][$locale]=$previous['products'][$i]['translations'][$locale]??['name'=>'','description'=>''];
   if($row['translations']['es']['name']===''&&$row['translations']['es']['description']===''&&!$row['asset_id'])continue;
   if($row['translations']['es']['name']==='')throw new RuntimeException('Indica el nombre del producto.');
   if($row['asset_id']){$q=$pdo->prepare('SELECT 1 FROM media_assets WHERE id=?');$q->execute([$row['asset_id']]);if(!$q->fetchColumn())throw new RuntimeException('La imagen del producto no existe.');}$out['products'][]=$row;
  }
  $ids=$input['experience_ids']??[];if(!is_array($ids)||count($ids)>20)throw new RuntimeException('Revisa las experiencias.');
  if(array_filter(array_map('intval',$ids))){$q=$pdo->prepare('SELECT 1 FROM businesses b WHERE b.id=? AND (b.category_code="experiencias" OR EXISTS(SELECT 1 FROM business_categories bc WHERE bc.business_id=b.id AND bc.category_code="experiencias"))');$q->execute([$business]);if(array_key_exists('category_codes',$input)?!in_array('experiencias',$input['category_codes'],true):!$q->fetchColumn())throw new RuntimeException('Solo los operadores de experiencias pueden referenciarlas.');}
  foreach(array_unique(array_filter(array_map('intval',$ids))) as $id){$q=$pdo->prepare('SELECT 1 FROM points_of_interest p JOIN businesses b ON b.destination_id=p.destination_id WHERE b.id=? AND p.id=? AND p.poi_type="experience" AND p.publication_status="published"');$q->execute([$business,$id]);if(!$q->fetchColumn())throw new RuntimeException('Selecciona experiencias publicadas del mismo lugar.');$out['experience_ids'][]=$id;}
  return $out;
 }
 public static function validate(array $showcase):void{foreach($showcase['products']??[] as $row){if(empty($row['asset_id']))throw new RuntimeException('Prepara la imagen de cada producto antes de enviar la prepublicación.');foreach(['es','en','pt-BR'] as $locale)if(trim($row['translations'][$locale]['name']??'')==='')throw new RuntimeException('Traduce los productos antes de enviar la prepublicación.');}}
 public static function renderData(PDO $pdo,int $business,array $showcase,string $locale):array{
  $out=['products'=>[],'experiences'=>[]];foreach($showcase['products']??[] as $row){$q=$pdo->prepare('SELECT asset_path FROM media_assets WHERE id=?');$q->execute([(int)($row['asset_id']??0)]);$path=$q->fetchColumn();if(!$path)continue;$out['products'][]=array_merge($row['translations'][$locale]??$row['translations']['es'],['asset_path'=>$path]);}
  foreach($showcase['experience_ids']??[] as $id){$q=$pdo->prepare('SELECT p.slug,COALESCE(t.name,es.name) AS name,COALESCE(t.description,es.description) AS description,(SELECT m.asset_path FROM point_of_interest_media pm JOIN media_assets m ON m.id=pm.media_asset_id WHERE pm.point_of_interest_id=p.id ORDER BY pm.display_order LIMIT 1) AS asset_path FROM points_of_interest p JOIN businesses b ON b.destination_id=p.destination_id LEFT JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id AND t.locale=? JOIN point_of_interest_translations es ON es.point_of_interest_id=p.id AND es.locale="es" WHERE b.id=? AND p.id=? AND p.poi_type="experience" AND p.publication_status="published"');$q->execute([$locale,$business,(int)$id]);if($r=$q->fetch(PDO::FETCH_ASSOC))$out['experiences'][]=$r;}
  return $out;
 }
}

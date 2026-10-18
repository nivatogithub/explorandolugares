<?php
declare(strict_types=1);
// Only the isolated local preprod container; never use production configuration.
if (!is_file('/srv/state/private/config.php')) throw new RuntimeException('Requiere contenedor preprod local.');
$c=require '/srv/state/private/config.php';
if (empty($c['local_test']) || !str_contains($c['dsn'],'dbname=explorando_preprod')) throw new RuntimeException('Configuración no local rechazada.');
require '/srv/portal/app/DestinationPackage.php';
require '/srv/portal/app/DestinationAdminService.php';
$path=$argv[1]??'';
if (!str_starts_with($path,'/srv/state/worker-review/') || !str_ends_with($path,'.zip')) throw new RuntimeException('Paquete fuera del staging permitido.');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$package=DestinationPackage::readZip($path);$m=$package['manifest'];$d=$m['destination'];$slug=$d['slug'];
if(!in_array($slug,['cartagena','bogota','medellin','santa-marta'],true))throw new RuntimeException('Destino no autorizado para esta tanda.');
if(count($m['places'])>8)throw new RuntimeException('Máximo ocho atractivos.');
$service=new DestinationAdminService($p);$q=$p->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([$slug]);$id=(int)$q->fetchColumn();
$before=$id?$service->find($id):null;
$stamp=gmdate('Ymd-His');file_put_contents('/srv/state/worker-review/before-'.$slug.'-'.$stamp.'.json',json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
function media(PDO $p,array $meta,array $images,string $slug):int{
 $image=$images[$meta['file']];$bytes=$image['bytes'];$hash=hash('sha256',$bytes);$ext=pathinfo($image['name'],PATHINFO_EXTENSION);$relative='assets/destinos/preprod-'.$slug.'/'.$hash.'.'.$ext;$target='/srv/portal/public/'.$relative;
 if(!is_dir(dirname($target)))mkdir(dirname($target),0755,true);
 if(!is_file($target))file_put_contents($target,$bytes);
 $dim=getimagesize($target);$q=$p->prepare('INSERT INTO media_assets(asset_path,origin,creator,source_url,license_name,license_url,width,height) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
 $q->execute([$relative,$meta['image_origin'],$meta['image_creator'],$meta['image_source_url'],$meta['image_license'],$meta['image_license_url'],$dim[0],$dim[1]]);$id=(int)$p->lastInsertId();
 foreach(['es','en','pt-BR'] as $l){$q=$p->prepare('INSERT INTO media_asset_translations(media_asset_id,locale,alt_text,caption) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE alt_text=VALUES(alt_text),caption=VALUES(caption)');$q->execute([$id,$l,$meta['alt'][$l],$meta['caption'][$l]]);}return $id;
}
$in=$d;$in['id']=$id;$in['publication_status']='draft';$in['history']=[];$in['reasons']=[];$in['sources']=[];$in['places']=[];
$in['internal_research_json']=json_encode($m['local_research']??['schema_version'=>1],JSON_UNESCAPED_UNICODE);
$in['hero_asset_id']=media($p,$m['hero_image'],$package['images'],$slug);
// Existing admin supports a separate history asset; ZIP v2 has no history field.
// Optional sidecar remains confined to this isolated local review staging area.
$in['history_asset_id']=(int)($before['destination_media']['history']['media_asset_id']??0);
$historySidecar='/srv/state/worker-review/'.$slug.'-history.json';
if(is_file($historySidecar)){
 $h=json_decode(file_get_contents($historySidecar),true,32,JSON_THROW_ON_ERROR);
 if(($h['destination']??'')!==$slug || ($h['image']['file']??'')!=='images/'.$slug.'-history.webp')throw new RuntimeException('Historia fuera de destino o ruta permitidos.');
 $meta=$h['image'];
 if(($meta['image_origin']??'')!=='external')throw new RuntimeException('Sidecar de historia requiere referencia fotográfica externa.');
 foreach(['image_creator','image_source_url','image_license','image_license_url'] as $key){
  if(trim((string)($meta[$key]??''))==='')throw new RuntimeException('Falta procedencia de historia.');
  if(str_ends_with($key,'url')&&(!filter_var($meta[$key],FILTER_VALIDATE_URL)||parse_url($meta[$key],PHP_URL_SCHEME)!=='https'))throw new RuntimeException('Origen de historia debe usar HTTPS.');
 }
 foreach(['es','en','pt-BR'] as $locale)if(trim((string)($meta['alt'][$locale]??''))==='')throw new RuntimeException('Falta alt de historia.');
 $f='/srv/state/worker-review/'.$slug.'-history.webp';
 if(!is_file($f)||filesize($f)>DestinationPackage::MAX_IMAGE_BYTES||!hash_equals((string)($h['sha256']??''),hash_file('sha256',$f)))throw new RuntimeException('Integridad de historia inválida.');
 $bytes=file_get_contents($f);$info=DestinationPackage::inspectImage($bytes,basename($f));
 $in['history_asset_id']=media($p,$meta,[$meta['file']=>['bytes'=>$bytes,'name'=>basename($f),'mime'=>$info['mime']]],$slug);
}

foreach(['es','en','pt-BR'] as $l){
 $in['history'][$l]=implode("\n",$d['history'][$l]);$in['reasons'][$l]=implode("\n",array_map(fn($s)=>mb_substr($s,0,500),$d['reasons'][$l]));
 $in['sources'][$l]=implode("\n",array_map(fn($s)=>($s['section_key']??'destination').'|'.($s['label']??$s['title']).'|'.$s['url'],$d['sources'][$l]));
}
foreach($m['places'] as $place){$place['publication_status']='published';$place['media_asset_id']=media($p,$place['image'],$package['images'],$slug);unset($place['image']);if($before)foreach($before['places'] as $old)if($old['slug']===$place['slug'])$place['id']=$old['id'];$in['places'][]=$place;}
$id=$service->save($in,[],[],'/srv/portal/public');
// User authorized viewing advances without approval: make available ONLY in loopback preprod.
$saved=$service->find($id);foreach($in['places'] as &$place){foreach($saved['places'] as $stored)if($stored['slug']===$place['slug'])$place['id']=$stored['id'];}unset($place);
$in['id']=$id;$in['publication_status']='published';$service->save($in,[],[],'/srv/portal/public');
$report=['destination'=>$slug,'id'=>$id,'package_sha256'=>hash_file('sha256',$path),'loaded_at'=>gmdate('c'),'environment'=>'local_preprod_only','production_authorized'=>false,'editorial_approved'=>false,'provisional_photographs'=>true,'places'=>count($in['places']),'history_asset_id'=>$in['history_asset_id'],'urls'=>['es'=>'http://127.0.0.1:8088/destinos/'.$slug.'/','en'=>'http://127.0.0.1:8088/en/destinos/'.$slug.'/','pt-BR'=>'http://127.0.0.1:8088/pt-br/destinos/'.$slug.'/']];
file_put_contents('/srv/state/worker-review/loaded-'.$slug.'.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($report,JSON_UNESCAPED_UNICODE)."\n";

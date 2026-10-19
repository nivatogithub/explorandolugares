<?php
declare(strict_types=1);
// CLI only, existing loopback preproduction container. Never run against hosting.
if(PHP_SAPI!=='cli'||!is_file('/srv/state/private/config.php'))throw new RuntimeException('Solo contenedor preprod local.');
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))throw new RuntimeException('Base no local rechazada.');
$slug=$argv[1]??'';$allowed=['zipaquira','santa-fe-de-antioquia','jardin','covenas'];
if(!in_array($slug,$allowed,true))throw new RuntimeException('Destino fuera del lote autorizado.');
$folder='/srv/state/worker-review/lote-20261019';$path=$folder.'/'.$slug.'.zip';$businessPath=$folder.'/'.$slug.'-negocios.zip';
if(!hash_equals($argv[2]??'',hash_file('sha256',$path)))throw new RuntimeException('Hash de destino incorrecto.');
if(!hash_equals($argv[3]??'',hash_file('sha256',$businessPath)))throw new RuntimeException('Hash de negocios incorrecto.');
foreach(['DestinationPackage','DestinationAdminService','BusinessPackage','BusinessPublicImporter'] as $class)require_once '/srv/portal/app/'.$class.'.php';
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$package=DestinationPackage::readZip($path);$m=$package['manifest'];$d=$m['destination'];
if($d['slug']!==$slug||count(array_filter($m['places'],fn($x)=>$x['type']==='place'))>8)throw new RuntimeException('Manifiesto fuera del alcance.');
$service=new DestinationAdminService($p);$q=$p->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([$slug]);$id=(int)$q->fetchColumn();$before=$id?$service->find($id):null;
$reportPath=$folder.'/loaded-'.$slug.'.json';
if($before&&$before['publication_status']==='published'&&!is_file($reportPath))throw new RuntimeException('Ya existe destino publicado sin pertenecer a este lote: revisar antes de sustituir.');
$backup=$folder.'/before-'.$slug.'.json';if(!is_file($backup))file_put_contents($backup,json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
function newMedia(PDO $p,array $meta,array $images,string $slug):int{
 $image=$images[$meta['file']];$bytes=$image['bytes'];$hash=hash('sha256',$bytes);$ext=pathinfo($image['name'],PATHINFO_EXTENSION);$relative='assets/destinos/preprod-'.$slug.'/'.$hash.'.'.$ext;$target='/srv/portal/public/'.$relative;
 if(!is_dir(dirname($target)))mkdir(dirname($target),0755,true);if(!is_file($target))file_put_contents($target,$bytes);
 $dim=getimagesize($target);$origin=$meta['image_origin']??'ai_generated';
 $q=$p->prepare('INSERT INTO media_assets(asset_path,origin,creator,source_url,license_name,license_url,width,height,provenance_json) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
 $q->execute([$relative,$origin,$meta['image_creator']??'OpenAI · ilustración editorial IA',$meta['image_source_url']??'',$meta['image_license']??'Generada con IA; revisión pendiente',$meta['image_license_url']??'',$dim[0],$dim[1],json_encode(['sha256'=>$hash,'source_package'=>$slug,'editorial_approved'=>false,'local_preview_only'=>true])]);$id=(int)$p->lastInsertId();
 foreach(['es','en','pt-BR'] as $l){$p->prepare('INSERT INTO media_asset_translations(media_asset_id,locale,alt_text,caption) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE alt_text=VALUES(alt_text),caption=VALUES(caption)')->execute([$id,$l,$meta['alt'][$l],$meta['caption'][$l]??'']);}return $id;
}
$in=$d;$in['id']=$id;$in['publication_status']='draft';$in['display_order']=100+array_search($slug,$allowed,true);$in['places']=[];$in['history_asset_id']=0;
$research=$m['local_research']??['schema_version'=>1];$research['notes'][]='Revisión local 2026-10-19; sin aprobación editorial ni autorización de producción.';$in['internal_research_json']=json_encode($research,JSON_UNESCAPED_UNICODE);
$in['hero_asset_id']=newMedia($p,$m['hero_image'],$package['images'],$slug);
foreach(['es','en','pt-BR'] as $l){$in['history'][$l]=implode("\n",$d['history'][$l]);$in['reasons'][$l]=implode("\n",$d['reasons'][$l]);$in['sources'][$l]=implode("\n",array_map(fn($s)=>($s['section']??$s['section_key']??'destination').'|'.($s['label']??$s['title']??'Fuente').'|'.$s['url'],$d['sources'][$l]));}
foreach($m['places'] as $place){$place['publication_status']='published';$place['media_asset_id']=newMedia($p,$place['image'],$package['images'],$slug);unset($place['image']);if($before)foreach($before['places'] as $old)if($old['slug']===$place['slug'])$place['id']=$old['id'];$in['places'][]=$place;}
$id=$service->save($in,[],[],'/srv/portal/public');
$saved=$service->find($id);foreach($in['places'] as &$place)foreach($saved['places'] as $stored)if($place['slug']===$stored['slug'])$place['id']=$stored['id'];unset($place);
// Local-only visibility requested by owner; does not transfer editorial approval.
$in['id']=$id;$in['publication_status']='published';$service->save($in,[],[],'/srv/portal/public');
$stage=$folder.'/stage-'.$slug;if(!is_dir($stage))mkdir($stage,0700);$b=BusinessPackage::readZip($businessPath,$stage)['manifest'];BusinessPackage::removeDirectory($stage);
if($b['destination_slug']!==$slug||count($b['businesses'])!==30)throw new RuntimeException('Directorio esperado de30 entradas.');
$q=$p->prepare('SELECT t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=?');$q->execute([$id]);$names=array_map(fn($x)=>mb_strtolower(trim($x)),$q->fetchAll(PDO::FETCH_COLUMN));
$b['businesses']=array_values(array_filter($b['businesses'],fn($x)=>!in_array(mb_strtolower(trim($x['name'])),$names,true)));$new=$b['businesses'];$n=$new?BusinessPublicImporter::import($p,$b):0;
$up=$p->prepare('UPDATE businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" SET b.publication_status="published" WHERE b.destination_id=? AND t.display_name=? AND b.record_origin="public_import" AND b.publication_status="draft"');foreach($new as $x)$up->execute([$id,$x['name']]);
$r=['destination'=>$slug,'id'=>$id,'destination_zip_sha256'=>hash_file('sha256',$path),'business_zip_sha256'=>hash_file('sha256',$businessPath),'loaded_at'=>gmdate('c'),'environment'=>'local_preprod_only','editorial_approved'=>false,'production_changed'=>false,'images'=>count($package['images']),'attractions'=>count(array_filter($m['places'],fn($x)=>$x['type']==='place')),'experiences'=>count(array_filter($m['places'],fn($x)=>$x['type']==='experience')),'new_businesses'=>$n,'url'=>'http://127.0.0.1:8088/destinos/'.$slug.'/'];file_put_contents($reportPath,json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";

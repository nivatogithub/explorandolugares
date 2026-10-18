<?php
declare(strict_types=1);
// Guarded content-only update of Cartagena in isolated local preprod. No media uploads.
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))throw new RuntimeException('Solo preprod local');
$targetSlug=$argv[2]??'';if(!in_array($targetSlug,['bogota','medellin','santa-marta'],true))throw new RuntimeException('Destino no autorizado');
$f='/srv/state/worker-review/'.$targetSlug.'-semirrealista-v1.json';
if(!hash_equals($argv[1]??'',hash_file('sha256',$f)))throw new RuntimeException('Hash de contenido inesperado');
$m=json_decode(file_get_contents($f),true,64,JSON_THROW_ON_ERROR);
if(($m['destination']['slug']??'')!==$targetSlug||count($m['editorial_selection'])!==8||count(array_unique($m['editorial_selection']))!==8)throw new RuntimeException('Alcance inválido');
require '/srv/portal/app/DestinationAdminService.php';
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$q=$p->prepare('SELECT id,publication_status FROM destinations WHERE slug=?');$q->execute([$targetSlug]);$row=$q->fetch();
if(!$row||$row['publication_status']!=='draft')throw new RuntimeException('Requiere Cartagena existente en borrador');
$service=new DestinationAdminService($p);$before=$service->find((int)$row['id']);
file_put_contents('/srv/state/worker-review/before-'.$targetSlug.'-semirrealista-v1-'.gmdate('Ymd-His').'.json',json_encode($before,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
$in=$before;$in['places']=[];$by=array_column($m['places'],null,'slug');$existing=array_column($before['places'],null,'slug');
$in['hero_asset_id']=(int)($before['destination_media']['hero']['media_asset_id']??0);$in['history_asset_id']=(int)($before['destination_media']['history']['media_asset_id']??0);
foreach($m['editorial_selection'] as $slug){$x=$by[$slug];$old=$existing[$slug]??[];$in['places'][]=['id'=>$old['id']??0,'slug'=>$slug,'type'=>'place','publication_status'=>'draft','translations'=>$x['translations'],'media_asset_id'=>$old['media_asset_id']??0];}
foreach($m['experiences'] as $x){if(empty($x['review_ready']))continue;$slug=$x['id'];$old=$existing[$slug]??[];$in['places'][]=['id'=>$old['id']??0,'slug'=>$slug,'type'=>'experience','publication_status'=>'draft','translations'=>$x['display_translations'],'media_asset_id'=>$old['media_asset_id']??0];}
// Preserve old records explicitly, outside the preview; never delete their content or images.
foreach($existing as $slug=>$old)if(!in_array($slug,array_column($in['places'],'slug'),true)){$old['publication_status']='archived';$in['places'][]=$old;}
foreach(['es','en','pt-BR'] as $l){$in['translations'][$l]=$m['destination']['translations'][$l];$in['translations'][$l]['eyebrow']=['es'=>'BORRADOR LOCAL · imágenes definitivas pendientes','en'=>'LOCAL DRAFT · final images pending','pt-BR'=>'RASCUNHO LOCAL · imagens finais pendentes'][$l];$in['history'][$l]=implode("\n",$m['destination']['history'][$l]);}
foreach(['es','en','pt-BR'] as $l){$in['reasons'][$l]=implode("\n",$before['reasons'][$l]);$in['sources'][$l]=implode("\n",array_map(fn($x)=>$x['section_key'].'|'.$x['source_label'].'|'.$x['source_url'],$before['sources'][$l]));}
// Existing reasons, sources and other editorial fields remain; no publication status transition.
$id=$service->save($in,[],[],'/srv/portal/public');$after=$service->find($id);
if($after['publication_status']!=='draft')throw new RuntimeException('Estado inesperado');
foreach(['hero','history'] as $role)if(($before['destination_media'][$role]['media_asset_id']??null)!==($after['destination_media'][$role]['media_asset_id']??null))throw new RuntimeException('Medio alterado');
$r=['destination'=>$targetSlug,'content_sha256'=>hash_file('sha256',$f),'loaded_at'=>gmdate('c'),'environment'=>'local_preprod_only','status'=>'draft','attractions'=>8,'experiences'=>count(array_filter($m['experiences'],fn($e)=>!empty($e['review_ready']))),'uploaded_images'=>0,'production_changed'=>false,'url'=>'http://127.0.0.1:8088/destinos/'.$targetSlug.'/?preview=1'];
file_put_contents('/srv/state/worker-review/loaded-'.$targetSlug.'-semirrealista-v1.json',json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($r,JSON_UNESCAPED_UNICODE)."\n";

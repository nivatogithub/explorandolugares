<?php
declare(strict_types=1);
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))throw new RuntimeException('Solo preprod local');
$slug=$argv[1]??'';$expected=['bogota'=>30,'medellin'=>30,'santa-marta'=>30];if(!isset($expected[$slug]))throw new RuntimeException('Destino no permitido');
$f='/srv/state/worker-review/'.$slug.'-directorio-30.json';
if(!hash_equals($argv[2]??'',hash_file('sha256',$f)))throw new RuntimeException('Hash inválido');
$m=json_decode(file_get_contents($f),true,64,JSON_THROW_ON_ERROR);
if($m['destination_slug']!==$slug||count($m['businesses'])!==$expected[$slug])throw new RuntimeException('Paquete fuera de alcance');
require '/srv/portal/app/BusinessPublicImporter.php';require '/srv/portal/app/BusinessPackage.php';require '/srv/portal/app/DestinationRepository.php';
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$q=$p->prepare('SELECT id,publication_status FROM destinations WHERE slug=?');$q->execute([$slug]);$d=$q->fetch();if(!$d||!in_array($d['publication_status'],['draft','published'],true))throw new RuntimeException('Requiere destino en borrador');
$q=$p->prepare('SELECT t.display_name,b.id,b.publication_status FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=?');$q->execute([$d['id']]);$before=$q->fetchAll();
file_put_contents('/srv/state/worker-review/before-'.$slug.'-directorio-gratuito-'.gmdate('Ymd-His').'.json',json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$names=array_map(fn($x)=>mb_strtolower(trim($x['display_name'])),$before);$m['businesses']=array_values(array_filter($m['businesses'],fn($x)=>!in_array(mb_strtolower(trim($x['name'])),$names,true)));
$newEntries=$m['businesses'];$n=$newEntries?BusinessPublicImporter::import($p,$m):0;
// Preserve the destination's local visibility; publish only newly imported source-backed rows.
if($d['publication_status']==='published'){
 $up=$p->prepare('UPDATE businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" SET b.publication_status="published" WHERE b.destination_id=? AND t.display_name=? AND b.record_origin="public_import" AND b.publication_status="draft"');
 foreach($newEntries as $item)$up->execute([$d['id'],$item['name']]);
}

$counts=[];foreach(['es','en','pt-BR'] as $l){$q=$p->prepare('SELECT COUNT(*) FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale=? WHERE b.destination_id=? AND b.publication_status=?');$q->execute([$l,$d['id'],$d['publication_status']]);$counts[$l]=(int)$q->fetchColumn();if($counts[$l]<30)throw new RuntimeException('Conteo incompleto');}
$r=['destination'=>$slug,'environment'=>'local_preprod_only','before'=>count($before),'imported'=>$n,'visible'=>$counts,'publication_status'=>$d['publication_status'],'plan'=>'free','input_sha256'=>hash_file('sha256',$f),'production_changed'=>false];
file_put_contents('/srv/state/worker-review/loaded-'.$slug.'-directorio-30.json',json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($r,JSON_UNESCAPED_UNICODE)."\n";

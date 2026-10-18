<?php
declare(strict_types=1);
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))throw new RuntimeException('Solo preprod local');
$f='/srv/state/worker-review/cartagena-directorio-v10.json';
if(!hash_equals($argv[1]??'',hash_file('sha256',$f)))throw new RuntimeException('Hash inválido');
$package=json_decode(file_get_contents($f),true,64,JSON_THROW_ON_ERROR);
if(count($package['businesses'])!==20||$package['destination_slug']!=='cartagena')throw new RuntimeException('Alcance inválido');
require '/srv/portal/app/BusinessPublicImporter.php';require '/srv/portal/app/BusinessPackage.php';require '/srv/portal/app/DestinationRepository.php';
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$d=$p->query("SELECT id,publication_status FROM destinations WHERE slug='cartagena'")->fetch();if(!$d||$d['publication_status']!=='draft')throw new RuntimeException('Requiere destino borrador');
$q=$p->prepare('SELECT t.display_name,b.id,b.publication_status FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=?');$q->execute([$d['id']]);$before=$q->fetchAll();
file_put_contents('/srv/state/worker-review/before-cartagena-directory-v10.json',json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$existing=array_map(fn($x)=>mb_strtolower(trim($x['display_name'])),$before);$items=[];
foreach($package['businesses'] as $item){if(!in_array(mb_strtolower(trim($item['name'])),$existing,true))$items[]=$item;}
$package['businesses']=$items;$imported=$items?BusinessPublicImporter::import($p,$package):0;
$repo=new DestinationRepository($p);$counts=[];foreach(['es','en','pt-BR'] as $l){$view=$repo->findPublished('cartagena',$l,true);$counts[$l]=count($view['businesses']);if($counts[$l]<30)throw new RuntimeException('Directorio menor de30');}
$receipt=['environment'=>'local_preprod_only','destination'=>'cartagena','input_sha256'=>hash_file('sha256',$f),'before'=>count($before),'imported'=>$imported,'visible'=>$counts,'publication_status'=>'draft','plan'=>'free','production_changed'=>false];
file_put_contents('/srv/state/worker-review/loaded-cartagena-directorio-v10.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($receipt,JSON_UNESCAPED_UNICODE)."\n";

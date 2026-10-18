<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(80);
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))throw new RuntimeException('Solo preproducción local');
require '/srv/portal/app/DestinationAdminService.php';
$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$slugs=['jerico','cartagena','bogota','medellin','santa-marta','cauca-viejo'];$q=$db->prepare('SELECT id,slug FROM destinations WHERE slug IN ('.implode(',',array_fill(0,count($slugs),'?')).')');$q->execute($slugs);$dest=$q->fetchAll();$ids=array_column($dest,'id');
function rows(PDO $db,string $table,string $field,array $ids):array{if(!$ids)return [];$q=$db->prepare('SELECT * FROM `'.$table.'` WHERE `'.$field.'` IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);return $q->fetchAll();}
$out=['schema_version'=>1,'scope'=>$slugs,'public_places'=>array_values(array_diff($slugs,['cauca-viejo'])),'editorial'=>[],'tables'=>[]];$service=new DestinationAdminService($db);
foreach($dest as $d){$v=$service->find((int)$d['id']);unset($v['media_options']);$out['editorial'][$d['slug']]=$v;}
$businesses=rows($db,'businesses','destination_id',$ids);$businesses=array_values(array_filter($businesses,static fn($b)=>!preg_match('/^f00d0000-0000-4000-8000-00000000001[0-4]$/D',$b['public_id'])));$bids=array_column($businesses,'id');$out['tables']['businesses']=$businesses;
foreach(['business_translations','business_categories','business_sources','business_contacts','business_research_contacts','business_media'] as $table)if(in_array($table,$tables))$out['tables'][$table]=rows($db,$table,'business_id',$bids);
$media=[];
foreach($out['editorial'] as $d){foreach($d['destination_media'] as $m)$media[]=$m['media_asset_id'];foreach($d['places'] as $p)if($p['media_asset_id'])$media[]=$p['media_asset_id'];}
foreach($out['tables']['business_media']??[] as $m)$media[]=$m['media_asset_id'];
$out['tables']['media_assets']=rows($db,'media_assets','id',array_values(array_unique($media)));
$out['tables']['media_asset_translations']=rows($db,'media_asset_translations','media_asset_id',array_values(array_unique($media)));
foreach(['service_categories','service_category_translations','subscription_plans','subscription_plan_translations','subscription_features','subscription_feature_translations','subscription_plan_features','subscription_plan_prices','subscription_pricing_policy','experience_operator_prices'] as $table)if(in_array($table,$tables))$out['tables'][$table]=$db->query('SELECT * FROM `'.$table.'`')->fetchAll();
$out['destination_ids']=array_column($dest,'slug','id');
$path='/srv/state/evidencia/candidato-produccion.json';file_put_contents($path,json_encode($out,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($path,0600);
echo json_encode(['exported_places'=>count($dest),'businesses'=>count($businesses),'media'=>count($out['tables']['media_assets']),'tables'=>array_map('count',$out['tables']),'sha256'=>hash_file('sha256',$path)],JSON_UNESCAPED_UNICODE),"\n";

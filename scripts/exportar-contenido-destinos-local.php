<?php
declare(strict_types=1);
/** Read-only DB export. Writes only a NEW output directory; never changes DB or deletes media. */
if(PHP_SAPI!=='cli')throw new RuntimeException('Solo CLI.');
$o=getopt('',['config:','public-root:','output:','slugs:','app-root:']);
foreach(['config','public-root','output'] as $k)if(empty($o[$k]))throw new RuntimeException("Falta --$k");
$c=require $o['config'];
if(empty($c['local_test'])||!preg_match('/dbname=explorando_preprod(?:;|$)/',(string)($c['dsn']??'')))throw new RuntimeException('Solo configuración marcada local_test de explorando_preprod.');
$root=realpath($o['public-root']);if(!$root||!is_dir($root))throw new RuntimeException('public-root inválido.');
$out=rtrim($o['output'],'/');if(file_exists($out))throw new RuntimeException('Salida existente: usar directorio nuevo, no sobrescribir.');
$allowed=['jerico','cartagena','bogota','medellin','santa-marta','jardin','covenas','zipaquira','santa-fe-de-antioquia'];
$slugs=isset($o['slugs'])?explode(',',$o['slugs']):$allowed;if(array_diff($slugs,$allowed))throw new RuntimeException('Slug no autorizado.');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$p->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$p->exec('START TRANSACTION READ ONLY');
$appRoot=$o['app-root']??(__DIR__.'/../backend/app');
require_once rtrim($appRoot,'/').'/DestinationPackage.php';
function rows(PDO $p,string $sql,array $args=[]):array{$q=$p->prepare($sql);$q->execute($args);return $q->fetchAll();}
function writeJson(string $file,mixed $value):void{if(file_put_contents($file,json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n")===false)throw new RuntimeException('No se pudo escribir salida.');}
// Strict projections. No accounts, contacts, subscriptions, payments, stats, internal research or private image references.
function selected(PDO $p,string $table,string $columns,string $where,array $args):array{
 $available=array_column(rows($p,"SHOW COLUMNS FROM `$table`"),'Field');$cols=array_values(array_intersect(explode(',',$columns),$available));
 if(!$cols)throw new RuntimeException('Proyección sin campos: '.$table);
 return rows($p,'SELECT '.implode(',',array_map(fn($x)=>'`'.$x.'`',$cols))." FROM `$table` WHERE $where ORDER BY ".(in_array('id',$cols,true)?'id':implode(',',array_map(fn($x)=>'`'.$x.'`',array_slice($cols,0,2)))),$args);
}
mkdir($out,0700,true);$reports=[];
try{foreach($slugs as $slug){
 $d=selected($p,'destinations','id,slug,destination_type,country_code,department,subregion,latitude,longitude,publication_status,display_order','slug=?',[$slug]);if(count($d)!==1)throw new RuntimeException('Destino no único: '.$slug);$id=(int)$d[0]['id'];$dir=$out.'/'.$slug;mkdir($dir,0700);mkdir($dir.'/media',0700);
 $tables=['destinations'=>$d];
 $direct=[
 'destination_translations'=>'destination_id,locale,display_name,eyebrow,headline,intro,meta_description',
 'destination_story_blocks'=>'id,destination_id,section_key,display_order',
 'destination_reasons'=>'id,destination_id,display_order',
 'destination_sources'=>'id,destination_id,locale,section_key,source_url,source_label,display_order',
 'destination_media'=>'destination_id,media_asset_id,role,display_order',
 'points_of_interest'=>'id,destination_id,slug,poi_type,latitude,longitude,publication_status,display_order',
 'businesses'=>'id,public_id,destination_id,category_code,slug,information_status,operation_status,publication_status,display_order,record_origin,address_text,latitude,longitude,location_map_url,location_reference,opening_hours,price_range'];
 foreach($direct as $table=>$cols)$tables[$table]=selected($p,$table,$cols,'destination_id=?',[$id]);
 $child=[
 'destination_story_translations'=>['story_block_id,locale,body','story_block_id','destination_story_blocks'],
 'destination_reason_translations'=>['reason_id,locale,body','reason_id','destination_reasons'],
 'point_of_interest_translations'=>['point_of_interest_id,locale,name,description,map_url,external_url,external_label','point_of_interest_id','points_of_interest'],
 'point_of_interest_media'=>['point_of_interest_id,media_asset_id,role,display_order','point_of_interest_id','points_of_interest'],
 'business_translations'=>['business_id,locale,display_name,summary,description','business_id','businesses'],
 'business_sources'=>['id,business_id,source_url,checked_at','business_id','businesses'],
 'business_media'=>['business_id,media_asset_id,display_order','business_id','businesses']];
 foreach($child as $table=>[$cols,$fk,$parent])$tables[$table]=selected($p,$table,$cols,"$fk IN (SELECT id FROM $parent WHERE destination_id=?)",[$id]);
 if(rows($p,"SHOW TABLES LIKE 'business_categories'"))$tables['business_categories']=selected($p,'business_categories','business_id,category_code','business_id IN (SELECT id FROM businesses WHERE destination_id=?)',[$id]);
 $ids=[];foreach(['destination_media','point_of_interest_media','business_media'] as $t)foreach($tables[$t] as $r)$ids[(int)$r['media_asset_id']]=true;
 $ids=array_keys($ids);sort($ids);$tables['media_assets']=[];$tables['media_asset_translations']=[];$assets=[];$missing=[];
 if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));$tables['media_assets']=selected($p,'media_assets','id,asset_path,origin,creator,source_url,license_name,license_url,width,height',"id IN ($marks)",$ids);$tables['media_asset_translations']=selected($p,'media_asset_translations','media_asset_id,locale,alt_text,caption',"media_asset_id IN ($marks)",$ids);}
 foreach($tables['media_assets'] as $m){$path=(string)$m['asset_path'];$real=realpath($root.'/'.ltrim($path,'/'));if(!$real||!str_starts_with($real,$root.'/')||!is_file($real)){$missing[]=['id'=>$m['id'],'path'=>$path];continue;}$hash=hash_file('sha256',$real);$ext=strtolower(pathinfo($real,PATHINFO_EXTENSION));if(!in_array($ext,['webp','png','jpg','jpeg','svg','gif'],true))throw new RuntimeException('Extensión media no autorizada.');$target='media/'.$hash.'.'.$ext;if(!is_file($dir.'/'.$target)&&!copy($real,$dir.'/'.$target))throw new RuntimeException('Error copia media.');$assets[(int)$m['id']]=['export_file'=>$target,'sha256'=>$hash,'source_asset_path'=>$path,'bytes'=>filesize($real)];}
 writeJson($dir.'/snapshot-public-content.json',['schema'=>'destination-public-snapshot-v1','destination'=>$slug,'tables'=>$tables,'media_files'=>$assets,'excluded'=>'Accounts, private contacts, subscriptions, payments, analytics, internal_research_json and arbitrary provenance_json omitted. IDs retained for explicit migration mapping, not blind restore.']);
 // Import-v2 is a portable draft; IDs and archived records stay in snapshot above.
 $dest=$d[0];unset($dest['id']);$dest['translations']=[];foreach($tables['destination_translations'] as $t){$locale=$t['locale'];unset($t['destination_id'],$t['locale']);$dest['translations'][$locale]=$t;}
 foreach(['history'=>['destination_story_blocks','destination_story_translations','story_block_id'],'reasons'=>['destination_reasons','destination_reason_translations','reason_id']] as $key=>[$parent,$child,$fk]){$dest[$key]=['es'=>[],'en'=>[],'pt-BR'=>[]];$parents=$tables[$parent];usort($parents,fn($a,$b)=>[$a['display_order'],$a['id']]<=>[$b['display_order'],$b['id']]);foreach($parents as $b){if($key==='history'&&$b['section_key']!=='history')continue;foreach($tables[$child] as $t)if($t[$fk]==$b['id'])$dest[$key][$t['locale']][]=$t['body'];}}
 $meta=function(int $mid)use($tables,$assets):array{foreach($tables['media_assets'] as $m)if((int)$m['id']===$mid){if(!isset($assets[$mid]))throw new RuntimeException('Media faltante');$r=['file'=>'images/'.basename($assets[$mid]['export_file']),'image_origin'=>$m['origin'],'image_creator'=>$m['creator'],'image_source_url'=>$m['source_url'],'image_license'=>$m['license_name'],'image_license_url'=>$m['license_url'],'alt'=>[],'caption'=>[]];foreach($tables['media_asset_translations'] as $t)if($t['media_asset_id']==$mid){$r['alt'][$t['locale']]=$t['alt_text'];$r['caption'][$t['locale']]=$t['caption'];}return $r;}throw new RuntimeException('Media no asociada');};
 $packageStatus='';try{$hero=null;foreach($tables['destination_media'] as $m)if($m['role']==='hero'){$hero=$meta((int)$m['media_asset_id']);break;}if(!$hero)throw new RuntimeException('Portada ausente');$places=[];foreach($tables['points_of_interest'] as $r){if($r['publication_status']==='archived')continue;$item=['slug'=>$r['slug'],'type'=>$r['poi_type']??'place','latitude'=>$r['latitude'],'longitude'=>$r['longitude'],'display_order'=>$r['display_order'],'publication_status'=>'draft','translations'=>[]];foreach($tables['point_of_interest_translations'] as $t)if($t['point_of_interest_id']==$r['id']){$l=$t['locale'];unset($t['point_of_interest_id'],$t['locale']);$item['translations'][$l]=$t;}$pm=array_values(array_filter($tables['point_of_interest_media'],fn($m)=>$m['point_of_interest_id']==$r['id']));usort($pm,fn($a,$b)=>$a['display_order']<=>$b['display_order']);if(!$pm)throw new RuntimeException('Imagen POI ausente:'.$r['slug']);$item['image']=$meta((int)$pm[0]['media_asset_id']);$places[]=$item;}$manifest=['schema_version'=>2,'destination'=>$dest,'hero_image'=>$hero,'places'=>$places];DestinationPackage::validateManifest($manifest);writeJson($dir.'/paquete-destino.json',$manifest);$z=new ZipArchive();$z->open($dir.'/paquete-destino.zip',ZipArchive::CREATE|ZipArchive::EXCL);$z->addFile($dir.'/paquete-destino.json','paquete-destino.json');$refs=[$hero['file']=>true];foreach($places as $r)$refs[$r['image']['file']]=true;foreach(array_keys($refs) as $ref)$z->addFile($dir.'/media/'.basename($ref),$ref);$z->close();DestinationPackage::readZip($dir.'/paquete-destino.zip');$packageStatus='validated_readZip';}catch(Throwable $e){$packageStatus='blocked: '.$e->getMessage();}
 $report=['destination'=>$slug,'destination_id'=>$id,'counts'=>array_map('count',$tables),'media_count'=>count($assets),'missing_media'=>$missing,'destination_package'=>$packageStatus,'snapshot_sha256'=>hash_file('sha256',$dir.'/snapshot-public-content.json'),'database_modified'=>false,'deleted_media'=>false];writeJson($dir.'/report.json',$report);$reports[]=$report;
 }$p->rollBack();writeJson($out.'/report.json',['schema'=>'local-export-report-v1','destinations'=>$reports,'database_modified'=>false]);echo json_encode(['exported'=>count($reports),'output'=>$out,'database_modified'=>false],JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}

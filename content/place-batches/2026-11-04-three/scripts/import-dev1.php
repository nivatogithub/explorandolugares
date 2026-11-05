<?php
declare(strict_types=1);
// Content-only import through the current application's HTTP/service boundary.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local dev1 only.');
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Refusing a non-development database.');
$root=dirname(__DIR__);$inputPath=$root.'/release-input.json';$commit=trim($argv[1]??'');
if(!preg_match('/^[a-f0-9]{40}$/D',$commit))throw new RuntimeException('Exact source Git commit required.');
$payload=json_decode(file_get_contents($inputPath),true,512,JSON_THROW_ON_ERROR);
if($payload['schema_version']!=='dev1-place-content-v1'||$payload['environment']!=='dev1'||array_column(array_column($payload['places'],'destination'),'slug')!==['san-andres','palomino','uribia'])throw new RuntimeException('Wrong batch.');
require '/srv/portal/app/PlaceAdminService.php';require '/srv/portal/app/AccountSessionValidator.php';
$pdo=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$service=new PlaceAdminService($pdo);$slugs=array_column(array_column($payload['places'],'destination'),'slug');
foreach($payload['places'] as $d){
 if(!hash_equals($d['research_sha256'],hash_file('sha256',$root.'/'.$d['research_file'])))throw new RuntimeException('Research hash mismatch.');
 $images=[$d['hero_image']];foreach($d['places'] as $p)if(isset($p['image']))$images[]=$p['image'];
 foreach($images as $im)if(!hash_equals($im['sha256'],hash_file('sha256',$root.'/'.$im['file'])))throw new RuntimeException('Unapproved image bytes.');
}
$marks=implode(',',array_fill(0,count($slugs),'?'));$q=$pdo->prepare("SELECT id FROM destinations WHERE slug NOT IN ($marks) ORDER BY id");$q->execute($slugs);$oldIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$idList=implode(',',$oldIds)?:'0';
$queries=['destinations'=>"SELECT * FROM destinations WHERE id IN ($idList) ORDER BY id",'translations'=>"SELECT * FROM destination_translations WHERE destination_id IN ($idList) ORDER BY destination_id,locale",'attractions'=>"SELECT * FROM points_of_interest WHERE destination_id IN ($idList) ORDER BY id",'businesses'=>"SELECT * FROM businesses WHERE destination_id IN ($idList) ORDER BY id"];
$before=[];foreach($queries as $k=>$sql)$before[$k]=hash('sha256',json_encode($pdo->query($sql)->fetchAll()));
$account=(int)$pdo->query("SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status='active' AND r.role='administrator' LIMIT 1")->fetchColumn();if(!$account)throw new RuntimeException('Local admin fixture missing.');
$session=bin2hex(random_bytes(24));session_name('el_session');session_id($session);session_start();$_SESSION=['admin'=>true,'admin_account_id'=>$account,'business_account_id'=>$account,'last_seen'=>time(),'business_last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];AccountSessionSecurity::bind($pdo,$_SESSION,$account);$csrf=$_SESSION['csrf'];session_write_close();
$dirs=explode(';',session_save_path());$sessionFile=(end($dirs)?:sys_get_temp_dir()).'/sess_'.$session;if(is_file($sessionFile)){chown($sessionFile,'www-data');chmod($sessionFile,0600);}
function flatten(array $in,string $prefix='',array &$out=[]):array{foreach($in as $k=>$v){$key=$prefix===''?(string)$k:$prefix.'['.$k.']';if(is_array($v))flatten($v,$key,$out);else $out[$key]=(string)($v??'');}return $out;}
function meta(array $m):array{$out=[];foreach(['image_origin','image_creator','image_source_url','image_license','image_license_url'] as $k)$out[$k]=$m[$k];$out['image_alt']=$m['alt'];$out['image_caption']=$m['caption'];return $out;}
function post(array $input,array $files,string $session,string $errorPath):void{$fields=flatten($input);$ch=curl_init('http://127.0.0.1/admin/places.php');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$files?array_merge($fields,$files):http_build_query($fields),CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>90,CURLOPT_COOKIE=>'el_session='.$session]);$body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($code!==303){file_put_contents($errorPath,$body?:$err);throw new RuntimeException('Admin save returned '.$code.'; inspect '.$errorPath);}}
$report=['environment'=>'dev1','source_commit'=>$commit,'release_input_sha256'=>hash_file('sha256',$inputPath),'production_touched'=>false,'places'=>[]];
try{foreach($payload['places'] as $d){
 $slug=$d['destination']['slug'];$find=$pdo->prepare('SELECT id FROM destinations WHERE slug=?');$find->execute([$slug]);$id=(int)$find->fetchColumn();
 if($id){$existing=$service->find($id);$research=json_decode($existing['internal_research_json']??'{}',true);if(!in_array('batch:2026-11-04-three',$research['notes']??[],true))throw new RuntimeException('Existing destination not owned by batch: '.$slug);}
 $input=$d['destination'];$input['csrf']=$csrf;$input['action']='save';$input['id']=$id;$input['publication_status']='draft';$input['display_order']=200+count($report['places']);$input['history_asset_id']=0;$input['hero_asset_id']=$id?($existing['destination_media']['hero']['media_asset_id']??0):0;$input['internal_research_json']=json_encode(['schema_version'=>1,'researched_at'=>'2026-11-04','notes'=>['batch:2026-11-04-three','source_commit:'.$commit,json_encode($d['local_research'],JSON_UNESCAPED_UNICODE)]],JSON_UNESCAPED_UNICODE);
 foreach(['es','en','pt-BR'] as $l){foreach(['history','reasons'] as $k)$input[$k][$l]=implode("\n",$input[$k][$l]);$input['sources'][$l]=implode("\n",array_map(fn($s)=>$s['section'].'|'.$s['label'].'|'.$s['url'],$input['sources'][$l]));}
 $input=array_merge($input,meta($d['hero_image']));$files=[];$input['places']=[];
 if(!$id)$files['hero_upload']=new CURLFile($root.'/'.$d['hero_image']['file'],'image/webp','hero.webp');
 foreach($d['places'] as $i=>$poi){$p=$poi;$p['publication_status']='draft';unset($p['image']);if(isset($poi['image'])){$p=array_merge($p,meta($poi['image']));if(!$id)$files['place_uploads['.$i.']']=new CURLFile($root.'/'.$poi['image']['file'],'image/webp',basename($poi['image']['file']));}if($id){$match=array_values(array_filter($existing['places'],fn($old)=>$old['slug']===$p['slug']));if(count($match)!==1)throw new RuntimeException('Existing attraction identity differs.');$p['id']=$match[0]['id'];$p['media_asset_id']=$match[0]['media_asset_id']??0;if(isset($poi['image'])&&!$p['media_asset_id'])$files['place_uploads['.$i.']']=new CURLFile($root.'/'.$poi['image']['file'],'image/webp',basename($poi['image']['file']));}$input['places'][$i]=$p;}
 if(!$id||$files){post($input,$files,$session,$root.'/error-'.$slug.'.html');$find->execute([$slug]);$id=(int)$find->fetchColumn();}
 $saved=$service->find($id);$input['id']=$id;$input['publication_status']='published';$input['hero_asset_id']=$saved['destination_media']['hero']['media_asset_id']??0;
 foreach($input['places'] as $i=>&$p){$match=array_values(array_filter($saved['places'],fn($old)=>$old['slug']===$p['slug']));if(count($match)!==1)throw new RuntimeException('Attraction identity mismatch.');$p['id']=$match[0]['id'];$p['media_asset_id']=$match[0]['media_asset_id']??0;$p['publication_status']=$d['places'][$i]['publication_status'];}unset($p);
 post($input,[],$session,$root.'/error-'.$slug.'.html');$saved=$service->find($id);if($saved['publication_status']!=='published')throw new RuntimeException('Place was not published.');
 $report['places'][]=['slug'=>$slug,'id'=>$id,'status'=>'published','visible_attractions'=>count(array_filter($saved['places'],fn($p)=>$p['type']==='place'&&$p['publication_status']==='published')),'visible_experiences'=>count(array_filter($saved['places'],fn($p)=>$p['type']==='experience'&&$p['publication_status']==='published')),'url'=>'http://127.0.0.1:8086/lugares/'.$slug.'/'];file_put_contents($root.'/dev1-place-receipt.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
 echo $slug." published\n";
}
foreach($queries as $k=>$sql)if(!hash_equals($before[$k],hash('sha256',json_encode($pdo->query($sql)->fetchAll()))))throw new RuntimeException('Other content changed: '.$k);$report['other_content_preserved']=true;
}finally{if(is_file($sessionFile))unlink($sessionFile);file_put_contents($root.'/dev1-place-receipt.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}

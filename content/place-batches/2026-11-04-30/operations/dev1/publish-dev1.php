<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Only reserved local dev1.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Not a synthetic development database.');
require '/srv/portal/app/AccountSessionValidator.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
require '/srv/portal/app/PlaceAdminService.php';
$service=new PlaceAdminService($pdo);
$lot=[];foreach(glob('/srv/state/place-packages-30/places/*/manifest.draft.json') as $f)$lot[]=json_decode(file_get_contents($f),true)['destination']['slug'];
$ids=[];foreach($service->all() as $r)if(in_array($r['slug'],$lot,true))$ids[]=(int)$r['id'];
if(count($ids)!==30)throw new RuntimeException('Expected exactly 30 existing drafts.');
$idList=implode(',',$ids);
$queries=['destinations'=>"SELECT * FROM destinations WHERE id NOT IN ($idList)",'destination_translations'=>"SELECT * FROM destination_translations WHERE destination_id NOT IN ($idList)",'points_of_interest'=>"SELECT * FROM points_of_interest WHERE destination_id NOT IN ($idList)",'point_of_interest_translations'=>"SELECT t.* FROM point_of_interest_translations t JOIN points_of_interest p ON p.id=t.point_of_interest_id WHERE p.destination_id NOT IN ($idList)"];
$before=[];foreach($queries as $table=>$query)$before[$table]=array_map(fn($r)=>hash('sha256',json_encode($r)),$pdo->query($query)->fetchAll());
$account=(int)$pdo->query("SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status='active' AND r.role='administrator' LIMIT 1")->fetchColumn();
if(!$account)throw new RuntimeException('Missing local test administrator.');
$sessionId=bin2hex(random_bytes(24));session_name('el_session');session_id($sessionId);session_start();
$_SESSION=['admin'=>true,'admin_account_id'=>$account,'business_account_id'=>$account,'last_seen'=>time(),'business_last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
AccountSessionSecurity::bind($pdo,$_SESSION,$account);$csrf=$_SESSION['csrf'];session_write_close();
$sessionDirectory=explode(';',session_save_path());$sessionFile=(end($sessionDirectory)?:sys_get_temp_dir()).'/sess_'.$sessionId;
if(is_file($sessionFile)){chown($sessionFile,'www-data');chmod($sessionFile,0600);}
function flatten(array $data,string $prefix='',array &$result=[]):array{foreach($data as $key=>$value){$name=$prefix===''?(string)$key:$prefix.'['.$key.']';if(is_array($value))flatten($value,$name,$result);else $result[$name]=(string)($value??'');}return $result;}
function metadata(array $image):array{return ['image_origin'=>'ai_generated','image_creator'=>$image['image_creator'],'image_source_url'=>'','image_license'=>'','image_license_url'=>'','image_alt'=>['es'=>$image['alt']['es'],'en'=>'Illustration: '.preg_replace('/^Ilustración de /u','',$image['alt']['es']),'pt-BR'=>'Ilustração: '.preg_replace('/^Ilustración de /u','',$image['alt']['es'])],'image_caption'=>['es'=>'','en'=>'','pt-BR'=>'']];}
$report=['production_touched'=>false,'mode'=>'published-dev1','published'=>[],'errors'=>[]];
try{
 foreach(glob('/srv/state/place-packages-30/places/*/manifest.draft.json') as $file){
  $d=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$slug=$d['destination']['slug'];
  $q=$pdo->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([$slug]);$id=(int)$q->fetchColumn();$existing=$service->find($id);if(!$existing)throw new RuntimeException('Missing existing place.');
  $input=$d['destination'];$input['csrf']=$csrf;$input['action']='save';$input['id']=$id;$input['publication_status']='published';$input['hero_asset_id']=$existing['destination_media']['hero']['media_asset_id']??0;$input['history_asset_id']=$existing['destination_media']['history']['media_asset_id']??0;$input['display_order']=$existing['display_order']??100;
  $input['internal_research_json']=json_encode($d['local_research'],JSON_UNESCAPED_UNICODE);
  foreach(['es','en','pt-BR'] as $loc){foreach(['history','reasons'] as $k)$input[$k][$loc]=implode("\n",$input[$k][$loc]);$input['sources'][$loc]=implode("\n",array_map(fn($s)=>$s['section'].'|'.$s['label'].'|'.$s['url'],$input['sources'][$loc]));}
  $input['places']=[];$pending=[];$visible=0;
  foreach($d['places'] as $i=>$poi){
   $match=array_values(array_filter($existing['places'],fn($x)=>$x['slug']===$poi['slug']));if(count($match)!==1)throw new RuntimeException('POI identity mismatch.');
   unset($poi['image']);$poi['id']=$match[0]['id'];$poi['media_asset_id']=$match[0]['media_asset_id'];
   $ready=$poi['type']==='experience'||($poi['latitude']!==null&&$poi['longitude']!==null);$poi['publication_status']=$ready?'published':'draft';
   if($ready)$visible++;else $pending[]=$poi['slug'];
   foreach(['es','en','pt-BR'] as $loc){$poi['translations'][$loc]['map_url']='';$poi['translations'][$loc]['external_url']='';$poi['translations'][$loc]['external_label']='';}
   $input['places'][$i]=$poi;
  }
  $fields=flatten($input);
  $curl=curl_init('http://127.0.0.1/admin/places.php');curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>60,CURLOPT_COOKIE=>'el_session='.$sessionId]);
  $response=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
  if($status!==303){file_put_contents('/srv/state/place-packages-30/publish-error-'.$slug.'.html',$response?:'No response');$report['errors'][]=['slug'=>$slug,'http'=>$status];break;}
  $saved=$service->find($id);if($saved['publication_status']!=='published')throw new RuntimeException('Not published.');
  $report['published'][]=['slug'=>$slug,'id'=>$id,'visible_attractions'=>$visible,'pending_geolocation'=>$pending,'url'=>'http://127.0.0.1:8086/lugares/'.$slug.'/'];
  file_put_contents('/srv/state/place-packages-30/publish-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
 }
 foreach($queries as $table=>$query){$after=array_map(fn($r)=>hash('sha256',json_encode($r)),$pdo->query($query)->fetchAll());if($before[$table]!==$after)throw new RuntimeException('Other content changed: '.$table);}
 $report['other_content_preserved']=true;
}finally{if(is_file($sessionFile))unlink($sessionFile);file_put_contents('/srv/state/place-packages-30/publish-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
echo json_encode(['published'=>count($report['published']),'errors'=>$report['errors'],'other_content_preserved'=>$report['other_content_preserved']??false],JSON_PRETTY_PRINT)."\n";

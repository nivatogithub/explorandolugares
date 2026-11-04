<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Only reserved local dev1.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Not a synthetic development database.');
require '/srv/portal/app/AccountSessionValidator.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$protected=['destinations','destination_translations','points_of_interest','point_of_interest_translations'];$before=[];
foreach($protected as $table)$before[$table]=array_map(fn($r)=>hash('sha256',json_encode($r)), $pdo->query('SELECT * FROM '.$table)->fetchAll());
$account=(int)$pdo->query("SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status='active' AND r.role='administrator' LIMIT 1")->fetchColumn();
if(!$account)throw new RuntimeException('Missing local test administrator.');
$sessionId=bin2hex(random_bytes(24));session_name('el_session');session_id($sessionId);session_start();
$_SESSION=['admin'=>true,'admin_account_id'=>$account,'business_account_id'=>$account,'last_seen'=>time(),'business_last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
AccountSessionSecurity::bind($pdo,$_SESSION,$account);$csrf=$_SESSION['csrf'];session_write_close();
$sessionDirectory=explode(';',session_save_path());$sessionFile=(end($sessionDirectory)?:sys_get_temp_dir()).'/sess_'.$sessionId;
if(is_file($sessionFile)){chown($sessionFile,'www-data');chmod($sessionFile,0600);}
function flatten(array $data,string $prefix='',array &$result=[]):array{foreach($data as $key=>$value){$name=$prefix===''?(string)$key:$prefix.'['.$key.']';if(is_array($value))flatten($value,$name,$result);else $result[$name]=(string)($value??'');}return $result;}
function metadata(array $image):array{return ['image_origin'=>'ai_generated','image_creator'=>$image['image_creator'],'image_source_url'=>'','image_license'=>'','image_license_url'=>'','image_alt'=>['es'=>$image['alt']['es'],'en'=>'Illustration: '.preg_replace('/^Ilustración de /u','',$image['alt']['es']),'pt-BR'=>'Ilustração: '.preg_replace('/^Ilustración de /u','',$image['alt']['es'])],'image_caption'=>['es'=>'','en'=>'','pt-BR'=>'']];}
$report=['production_touched'=>false,'mode'=>'draft','created'=>[],'skipped'=>[],'errors'=>[]];
try{
 foreach(glob('/srv/state/place-packages-30/places/*/manifest.draft.json') as $file){
  $d=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$slug=$d['destination']['slug'];
  $q=$pdo->prepare('SELECT id,publication_status FROM destinations WHERE slug=?');$q->execute([$slug]);
  if($existing=$q->fetch()){$report['skipped'][]=['slug'=>$slug,'existing'=>$existing];continue;}
  $input=$d['destination'];$input['csrf']=$csrf;$input['action']='save';$input['id']='0';$input['publication_status']='draft';$input['hero_asset_id']='0';$input['history_asset_id']='0';$input['display_order']='100';
  $input['internal_research_json']=json_encode($d['local_research'],JSON_UNESCAPED_UNICODE);
  foreach(['es','en','pt-BR'] as $loc){foreach(['history','reasons'] as $k)$input[$k][$loc]=implode("\n",$input[$k][$loc]);$input['sources'][$loc]=implode("\n",array_map(fn($s)=>$s['section'].'|'.$s['label'].'|'.$s['url'],$input['sources'][$loc]));}
  $input=array_merge($input,metadata($d['hero_image']));$input['places']=[];
  foreach($d['places'] as $i=>$poi){$input['places'][$i]=array_merge($poi,metadata($poi['image']));unset($input['places'][$i]['image']);}
  $fields=flatten($input);$fields['hero_upload']=new CURLFile(dirname($file).'/'.$d['hero_image']['file'],'image/webp',basename($d['hero_image']['file']));
  foreach($d['places'] as $i=>$poi)$fields['place_uploads['.$i.']']=new CURLFile(dirname($file).'/'.$poi['image']['file'],'image/webp',basename($poi['image']['file']));
  $curl=curl_init('http://127.0.0.1/admin/places.php');curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>60,CURLOPT_COOKIE=>'el_session='.$sessionId]);
  $response=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
  if($status!==303){file_put_contents('/srv/state/place-packages-30/error-'.$slug.'.html',$response?:'No response');$report['errors'][]=['slug'=>$slug,'http'=>$status];break;}
  $q->execute([$slug]);$saved=$q->fetch();if(!$saved||$saved['publication_status']!=='draft')throw new RuntimeException('Wrong persisted state.');
  $report['created'][]=['slug'=>$slug,'id'=>(int)$saved['id'],'status'=>'draft','images'=>1+count($d['places']),'preview'=>'/lugares/'.$slug.'/?preview=1'];
  file_put_contents('/srv/state/place-packages-30/import-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
 }
 foreach($protected as $table){$after=array_map(fn($r)=>hash('sha256',json_encode($r)),$pdo->query('SELECT * FROM '.$table)->fetchAll());if(array_diff($before[$table],$after))throw new RuntimeException('Existing data changed: '.$table);}
 $report['existing_content_preserved']=true;
}finally{if(is_file($sessionFile))unlink($sessionFile);file_put_contents('/srv/state/place-packages-30/import-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
echo json_encode(['created'=>count($report['created']),'skipped'=>count($report['skipped']),'errors'=>$report['errors'],'existing_content_preserved'=>$report['existing_content_preserved']??false],JSON_PRETTY_PRINT)."\n";

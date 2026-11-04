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

$report=['routes'=>[],'production_touched'=>false];
function fetch(string $path,string $session=''):array{$c=curl_init('http://127.0.0.1'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>3]);if($session!=='')curl_setopt($c,CURLOPT_COOKIE,'el_session='.$session);$html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return [$status,$html];}
try{
 $import=json_decode(file_get_contents('/srv/state/place-packages-30/import-result.json'),true);
 foreach($import['created'] as $row){
  foreach(['es'=>'','en'=>'/en','pt-BR'=>'/pt-br'] as $loc=>$prefix){
   $url=$prefix.'/lugares/'.$row['slug'].'/';[$status,$html]=fetch($url);
   preg_match_all('~(?:src|srcset)="([^" ]*assets/destinos/'.$row['id'].'/[^" ]+)~',$html?:'',$matches);
   $images=array_unique(array_map(fn($x)=>'/'.ltrim(html_entity_decode($x),'/'),$matches[1]));$failed=[];
   foreach($images as $image){[$code,$bytes]=fetch($image);if($code!==200||!str_starts_with($bytes?:'','RIFF'))$failed[]=['path'=>$image,'http'=>$code];}
   $report['routes'][]=['slug'=>$row['slug'],'locale'=>$loc,'url'=>$url,'http'=>$status,'images_found'=>count($images),'failed_images'=>$failed,'public_visible'=>str_contains($html?:'','<h1'),'noindex'=>str_contains($html?:'','noindex')];
  }
  [$status]=fetch('/lugares/'.$row['slug'].'/');$report['public_status'][$row['slug']]=$status;
 }
}finally{if(is_file($sessionFile))unlink($sessionFile);file_put_contents('/srv/state/place-packages-30/public-http-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
echo json_encode(['routes'=>count($report['routes']),'non_200'=>array_count_values(array_column($report['routes'],'http')),'image_failures'=>array_values(array_filter($report['routes'],fn($r)=>count($r['failed_images'])>0)),'public_statuses'=>array_count_values(array_values($report['public_status']))],JSON_PRETTY_PRINT)."\n";

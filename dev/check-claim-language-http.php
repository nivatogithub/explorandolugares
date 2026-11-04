<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
require '/srv/portal/app/BusinessClaimSelector.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$selector=new BusinessClaimSelector($pdo);$places=$selector->places();$place=(int)($places[0]['id']??0);
$categories=$place?$selector->categories($place):[];$category=(string)($categories[0]['code']??'');
if(!$place||$category==='')throw new RuntimeException('Falta contenido sintético de reclamación.');
$results=[];
foreach(['es','en','pt-br'] as $locale){
 $id=bin2hex(random_bytes(24));session_name('el_session');session_id($id);session_start();$_SESSION=['csrf'=>bin2hex(random_bytes(32))];session_write_close();
 $directory=explode(';',session_save_path());$file=(end($directory)?:sys_get_temp_dir()).'/sess_'.$id;
 if(!is_file($file))throw new RuntimeException('Falta sesión sintética.');chown($file,'www-data');chmod($file,0600);
 try{
  $query=http_build_query(['lang'=>$locale,'destino'=>$place,'categoria'=>$category]);
  $ctx=stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>'Cookie: el_session='.$id]]);
  $body=file_get_contents('http://127.0.0.1/reclamar-negocio/?'.$query,false,$ctx);
  if($body===false||!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('No responde reclamación: '.$locale);
  $prefix=$locale==='es'?'':'/'.$locale;$suffix=$locale==='es'?'':'?lang='.$locale;
  foreach(['name="lang" value="'.$locale.'"','href="'.$prefix.'/negocios-locales/interes/"','href="/negocios/registro/'.$suffix.'"','href="/negocios/acceso/'.$suffix.'"'] as $expected)
   if(!str_contains($body,$expected))throw new RuntimeException('Falta navegación localizada: '.$locale);
  $json=file_get_contents('http://127.0.0.1/reclamar-negocio/?'.$query.'&search=1',false,$ctx);
  if($json===false||!is_array(json_decode($json,true,32,JSON_THROW_ON_ERROR)['results']??null))throw new RuntimeException('Falló búsqueda: '.$locale);
  $results[$locale]=['page'=>200,'filter_and_links'=>true,'json_search'=>true];
 }finally{if(is_file($file)&&!unlink($file))throw new RuntimeException('No se pudo retirar sesión sintética.');}
}
echo json_encode($results,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";

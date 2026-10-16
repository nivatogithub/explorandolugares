<?php
declare(strict_types=1);
header('Cache-Control: no-store');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(in_array($path,['/api/cobertura.php','/api/medicion.php','/api/actividad.php'],true)){require dirname(__DIR__).'/backend/public'.$path;return true;}
if($path==='/__design-review/'&&PHP_SAPI==='cli-server'&&getenv('EL_COVERAGE_TEST_CONFIG')&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){header('Content-Type: text/html; charset=utf-8');readfile(dirname(__DIR__).'/backend/tests/design-review.html');return true;}
$shared=['/assets/style.css'=>dirname(__DIR__,2).'/explorandolugares/ensayos/v1/assets/style.css','/assets/menu-principal.js'=>dirname(__DIR__).'/backend/integration-medicion/public/assets/menu-principal.js'];
if(isset($shared[$path])&&is_file($shared[$path])){header('Content-Type: '.(str_ends_with($path,'.css')?'text/css':'text/javascript').'; charset=utf-8');readfile($shared[$path]);return true;}
foreach(['/backend/public','/ensayos/v1'] as $root){
 $base=realpath(dirname(__DIR__).$root);$file=realpath($base.rawurldecode($path));
 if($file&&is_dir($file))$file=realpath($file.'/index.html');
 if($file&&is_file($file)&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)){
  $ext=pathinfo($file,PATHINFO_EXTENSION);$types=['html'=>'text/html; charset=utf-8','css'=>'text/css; charset=utf-8','js'=>'text/javascript; charset=utf-8','json'=>'application/json','geojson'=>'application/geo+json','png'=>'image/png','svg'=>'image/svg+xml','webp'=>'image/webp'];
  if(!isset($types[$ext]))break;header('Content-Type: '.$types[$ext]);if($ext==='html'&&str_contains($file,'/cobertura/')&&getenv('EL_COVERAGE_TEST_CONFIG')){echo str_replace('<main id="contenido" class="wrap">','<main id="contenido" class="wrap"><p class="notice">Vista previa local · datos de prueba, no cifras de producción.</p>',file_get_contents($file));}else{readfile($file);}return true;
 }
}
http_response_code(404);echo 'No encontrado';return true;

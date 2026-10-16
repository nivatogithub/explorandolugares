<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(preg_match('~^/api/cobertura\.php$~',$path)){require dirname(__DIR__).'/backend/public/api/cobertura.php';return true;}
foreach(['/backend/public','/ensayos/v1'] as $root){
 $base=realpath(dirname(__DIR__).$root);$file=realpath($base.rawurldecode($path));
 if($file&&is_dir($file))$file=realpath($file.'/index.html');
 if($file&&is_file($file)&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)){
  $ext=pathinfo($file,PATHINFO_EXTENSION);$types=['html'=>'text/html; charset=utf-8','css'=>'text/css; charset=utf-8','js'=>'text/javascript; charset=utf-8','json'=>'application/json','geojson'=>'application/geo+json','png'=>'image/png','svg'=>'image/svg+xml','webp'=>'image/webp'];
  if(!isset($types[$ext]))break;header('Content-Type: '.$types[$ext]);if($ext==='html'&&str_contains($file,'/cobertura/')&&getenv('EL_COVERAGE_TEST_CONFIG')){echo str_replace('<main id="contenido">','<main id="contenido"><p class="notice">Vista previa local · datos de prueba, no cifras de producción.</p>',file_get_contents($file));}else{readfile($file);}return true;
 }
}
http_response_code(404);echo 'No encontrado';return true;

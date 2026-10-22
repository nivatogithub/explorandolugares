<?php
declare(strict_types=1);
// Local review only; no arbitrary path, deployment, DB connection or network proxy.
$host=explode(':',$_SERVER['HTTP_HOST']??'')[0];
$packages=['baseline.localhost'=>'baseline','candidate.localhost'=>'candidate-final'];
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)||!isset($packages[$host])){http_response_code(404);exit;}
$public=dirname(__DIR__).'/.local/unificacion/'.$packages[$host].'/public';
$_SERVER['DOCUMENT_ROOT']=$public;
header('X-Robots-Tag: noindex, nofollow');
$_SERVER['REQUEST_URI']=str_replace('/lugares/','/destinos/',$_SERVER['REQUEST_URI']);
$requestPath=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(in_array($requestPath,['/','/en/','/pt-br/','/privacidad/','/en/privacidad/','/pt-br/privacidad/'],true)&&is_file($public.rtrim($requestPath,'/').'/index.php')){require $public.rtrim($requestPath,'/').'/index.php';return true;}
$handled=require __DIR__.'/php-destinations-router.php';
if($handled===false&&isset($file)&&is_file($file)){
 $extension=strtolower(pathinfo($file,PATHINFO_EXTENSION));
 if($extension==='php'){require $file;return true;}
 $types=['css'=>'text/css','js'=>'text/javascript','json'=>'application/json','html'=>'text/html','svg'=>'image/svg+xml'];
 header('Content-Type: '.($types[$extension]??(new finfo(FILEINFO_MIME_TYPE))->file($file)));readfile($file);return true;
}
return true;

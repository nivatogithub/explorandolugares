<?php
declare(strict_types=1);

$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/';
if (preg_match('~^/v1/(en/|pt-br/)?negocios-locales/?$~',$path,$hub)) {
    $_GET['lang']=match(trim($hub[1]??'','/')) {'en'=>'en','pt-br'=>'pt-br',default=>'es'};
    require dirname(__DIR__).'/backend/public/business/index.php';
    return true;
}
if ($path==='/v1/negocios/registro/' || $path==='/v1/negocios/registro') { require dirname(__DIR__).'/backend/public/business/register.php'; return true; }
if ($path==='/v1/negocios/acceso/' || $path==='/v1/negocios/acceso') { require dirname(__DIR__).'/backend/public/business/login.php'; return true; }
if ($path==='/v1/negocios/portal/' || $path==='/v1/negocios/portal') { require dirname(__DIR__).'/backend/public/business/dashboard.php'; return true; }
if ($path==='/v1/negocios/salir/' || $path==='/v1/negocios/salir') { require dirname(__DIR__).'/backend/public/business/logout.php'; return true; }
if (preg_match('~^/v1/admin/(index|base-datos|destinos|negocios|suscripciones|usuarios|importar-negocios|reclamaciones|solicitudes-cuenta|paquete-destino|paquete-destino-imagen|imagen-referencia-negocio)\.php$~',$path,$admin)) {
    require dirname(__DIR__).'/backend/public/admin/'.$admin[1].'.php';
    return true;
}
if ($path==='/v1/admin/inversionistas' || $path==='/v1/admin/inversionistas/' || $path==='/v1/admin/inversionistas.php') { require dirname(__DIR__).'/backend/public/admin/inversionistas.php'; return true; }
if (preg_match('~^/v1/assets/destinos/[0-9]+/[a-zA-Z0-9-]+\.(?:jpg|png|webp)$~',$path)) {
    $mediaRoot=getenv('EL_ADMIN_MEDIA_ROOT')?:'';
    $file=$mediaRoot?realpath(rtrim($mediaRoot,'/').substr($path,3)):false;
    $base=$mediaRoot?realpath($mediaRoot):false;
    if(!$file){
        $publicRoot=realpath(dirname(__DIR__).'/ensayos/v1');
        $publicFile=$publicRoot?realpath($publicRoot.substr($path,3)):false;
        if($publicFile&&$publicRoot&&str_starts_with($publicFile,$publicRoot.DIRECTORY_SEPARATOR)&&is_file($publicFile)){$file=$publicFile;$base=$publicRoot;}
    }
    if($file&&$base&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file)){header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file));header('Cache-Control: public,max-age=300');readfile($file);return true;}
    http_response_code(404);echo 'No encontrado';return true;
}
if (preg_match('~^/v1/assets/(admin-business-location|admin-subscriptions|admin-accounts|admin-business-form|business-claim-filter|business-registration)\.js$~',$path,$script)) {
    $file=realpath(dirname(__DIR__).'/backend/public/assets/'.$script[1].'.js');
    $base=realpath(dirname(__DIR__).'/backend/public/assets');
    if($file&&$base&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file)){header('Content-Type: application/javascript; charset=utf-8');header('Cache-Control: no-store');readfile($file);return true;}
    http_response_code(404);echo 'No encontrado';return true;
}
if ($path==='/v1/reclamar-negocio.php' || $path==='/v1/reclamar-negocio/') {
    require dirname(__DIR__).'/backend/public/reclamar-negocio.php';
    return true;
}
if (preg_match('~^/v1/(?:((?:en|pt-br)/))?negocios/([a-f0-9-]{16,64})/?$~i',$path,$match)) {
    $_GET['idioma']=match (trim($match[1]??'','/')) {'en'=>'en','pt-br'=>'pt-BR',default=>'es'};
    $_GET['id']=$match[2];
    require dirname(__DIR__).'/ensayos/v1/negocio.php';
    return true;
}
if (preg_match('~^/v1/(?:((?:en|pt-br)/))?destinos/([a-z0-9-]+)(?:/index\.html)?/?$~',$path,$match)) {
    $_GET['idioma']=match (trim($match[1]??'','/')) {'en'=>'en','pt-br'=>'pt-BR',default=>'es'};
    $_GET['destino']=$match[2];
    require dirname(__DIR__).'/ensayos/v1/destino.php';
    return true;
}
$file=realpath(dirname(__DIR__).'/ensayos'.rawurldecode($path));
$base=realpath(dirname(__DIR__).'/ensayos');
if ($file && str_starts_with($file,$base.DIRECTORY_SEPARATOR) && is_file($file)) return false;
http_response_code(404);echo 'No encontrado';return true;

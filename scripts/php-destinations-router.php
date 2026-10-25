<?php
declare(strict_types=1);

$configuredRoot=$_SERVER['DOCUMENT_ROOT']??'';
$documentRoot=$configuredRoot!==''?realpath($configuredRoot):realpath(dirname(__DIR__).'/ensayos/v1');
$assembled=$documentRoot && is_file($documentRoot.'/sitemap-live.php');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/';
if($assembled && $path==='/sitemap.xml'){require $documentRoot.'/sitemap-live.php';return true;}
// Retired static Jerico pages keep one-hop permanent canonical redirects.
if($assembled && preg_match('~^/((?:en/|pt-br/)?)jerico\\.html$~',$path,$retired)){
    header('Location: /'.$retired[1].'lugares/jerico/'.(($q=parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY))!==null?'?'.$q:''),true,301);return true;
}
if($assembled && preg_match('~^/((?:en/|pt-br/)?)(?:destinos|lugares)/jerico/(agenda|alojamiento|bares-y-vida-nocturna|comercios-y-productores|cultura-y-patrimonio|experiencias-y-naturaleza|gastronomia|informacion-practica|servicios|transporte-y-movilidad)(?:/index\\.html)?/?$~',$path,$retired)){
    header('Location: /'.$retired[1].'lugares/jerico/'.(($q=parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY))!==null?'?'.$q:'').($retired[2]==='agenda'?'':'#catalogo-local'),true,301);return true;
}
// Match hosting canonical redirects, preserving unrelated query parameters.
if($assembled && is_file($documentRoot.'/asset-manifest.json') && preg_match('~^/((?:en/|pt-br/)?)destinos(/.*)?$~',$path,$legacy)){header('Location: /'.$legacy[1].'lugares'.($legacy[2]??'/').(($query=parse_url($_SERVER['REQUEST_URI'],PHP_URL_QUERY))!==null?'?'.$query:''),true,301);return true;}
$query=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_QUERY);
if(str_ends_with($path,'/index.html')){header('Location: '.substr($path,0,-10).($query!==null?'?'.$query:''),true,301);return true;}
if(preg_match('~^/(?:en/|pt-br/)?(?:destinos|lugares)/[a-z0-9-]+$~i',$path)){header('Location: '.$path.'/'.($query!==null?'?'.$query:''),true,301);return true;}
if($path==='/negocios/cambiar-clave/' || $path==='/negocios/cambiar-clave'){require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/business/password.php';return true;}
if (preg_match('~^/(en/|pt-br/)?negocios-locales/?$~',$path,$hub)) {
    $_GET['lang']=match(trim($hub[1]??'','/')) {'en'=>'en','pt-br'=>'pt-br',default=>'es'};
    require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/business/index.php';
    return true;
}
if ($path==='/negocios/registro/' || $path==='/negocios/registro') { require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/business/register.php'; return true; }
if ($path==='/negocios/acceso/' || $path==='/negocios/acceso') { require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/business/login.php'; return true; }
if ($path==='/negocios/portal/' || $path==='/negocios/portal') { require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/business/dashboard.php'; return true; }
if ($path==='/negocios/salir/' || $path==='/negocios/salir') { require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/business/logout.php'; return true; }
if (preg_match('~^/admin/(index|base-datos|destinos|negocios|suscripciones|usuarios|importar-negocios|reclamaciones|solicitudes-cuenta|solicitud-detalle|negocio-vista-previa|paquete-destino|paquete-destino-imagen|imagen-referencia-negocio)\.php$~',$path,$admin)) {
    require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/admin/'.$admin[1].'.php';
    return true;
}
if ($path==='/admin/inversionistas' || $path==='/admin/inversionistas/' || $path==='/admin/inversionistas.php') { require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/admin/inversionistas.php'; return true; }
if (preg_match('~^/assets/destinos/[0-9]+/[a-zA-Z0-9-]+\.(?:jpg|png|webp)$~',$path)) {
    $mediaRoot=getenv('EL_ADMIN_MEDIA_ROOT')?:'';
    $file=$mediaRoot?realpath(rtrim($mediaRoot,'/').$path):false;
    $base=$mediaRoot?realpath($mediaRoot):false;
    if(!$file){
        $publicRoot=$documentRoot;
        $publicFile=$publicRoot?realpath($publicRoot.$path):false;
        if($publicFile&&$publicRoot&&str_starts_with($publicFile,$publicRoot.DIRECTORY_SEPARATOR)&&is_file($publicFile)){$file=$publicFile;$base=$publicRoot;}
    }
    if($file&&$base&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file)){header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file));header('Cache-Control: public,max-age=300');readfile($file);return true;}
    http_response_code(404);echo 'No encontrado';return true;
}
if (preg_match('~^/assets/(admin-business-location|admin-subscriptions|admin-accounts|admin-business-form|business-claim-filter|business-registration)\.js$~',$path,$script)) {
    $file=realpath(($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/assets/'.$script[1].'.js');
    $base=realpath(($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/assets');
    if($file&&$base&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&is_file($file)){header('Content-Type: application/javascript; charset=utf-8');header('Cache-Control: no-store');readfile($file);return true;}
    http_response_code(404);echo 'No encontrado';return true;
}
if ($path==='/reclamar-negocio.php' || $path==='/reclamar-negocio/') {
    require ($assembled?$documentRoot:dirname(__DIR__).'/backend/public').'/reclamar-negocio.php';
    return true;
}
if (preg_match('~^/(?:((?:en|pt-br)/))?negocios/([a-f0-9-]{16,64})/?$~i',$path,$match)) {
    $_GET['idioma']=match (trim($match[1]??'','/')) {'en'=>'en','pt-br'=>'pt-BR',default=>'es'};
    $_GET['id']=$match[2];
    require ($assembled?$documentRoot:dirname(__DIR__).'/ensayos/v1').'/negocio.php';
    return true;
}
if (preg_match('~^/(?:((?:en|pt-br)/))?(?:destinos|lugares)/([a-z0-9-]+)(?:/index\.html)?/?$~',$path,$match)) {
    $_GET['idioma']=match (trim($match[1]??'','/')) {'en'=>'en','pt-br'=>'pt-BR',default=>'es'};
    $_GET['destino']=$match[2];
    require ($assembled?$documentRoot:dirname(__DIR__).'/ensayos/v1').'/destino.php';
    return true;
}
$base=$documentRoot;
$file=$base?realpath($base.rawurldecode($path)):false;
if($file && is_dir($file))$file=realpath($file.'/index.html');
if ($file && $base && str_starts_with($file,$base.DIRECTORY_SEPARATOR) && is_file($file) && !str_starts_with(basename($file),'.')) return false;
http_response_code(404);echo 'No encontrado';return true;

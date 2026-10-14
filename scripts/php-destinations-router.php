<?php
declare(strict_types=1);

$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/';
if (preg_match('~^/v1/admin/(index|destinos|paquete-destino|paquete-destino-imagen)\.php$~',$path,$admin)) {
    require dirname(__DIR__).'/backend/public/admin/'.$admin[1].'.php';
    return true;
}
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

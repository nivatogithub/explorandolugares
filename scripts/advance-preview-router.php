<?php
declare(strict_types=1);
$root=getenv('EL_ADVANCE_PREVIEW_ROOT');
if(!$root){http_response_code(503);exit('Entorno local pendiente.');}
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if($path==='/sitemap.xml'){require $root.'/public/sitemap-live.php';return true;}
if(preg_match('~^/(?:((?:en|pt-br)/))?destinos/([a-z0-9-]+)/?$~',$path,$m)){
    $_GET['idioma']=match(trim($m[1]??'','/')){'en'=>'en','pt-br'=>'pt-BR',default=>'es'};$_GET['destino']=$m[2];
    require $root.'/public/destino.php';return true;
}
return false;

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/seo/PublicSitemap.php';
// Public read-only response: no admin session/bootstrap, secrets or noindex headers.
header('Content-Type: application/xml; charset=UTF-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
try{
    $file=dirname(__DIR__).'/private/config.php';
    if(PHP_SAPI==='cli-server'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&getenv('EL_LOCAL_CONFIG_PATH'))$file=getenv('EL_LOCAL_CONFIG_PATH');
    if(!is_file($file))throw new RuntimeException('Configuración pendiente.');
    $config=require $file;
    $db=new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $static=__DIR__.'/sitemap.xml';
    $xml=(new PublicSitemap($db))->xml($config['public_base_url']??'',is_file($static)?file_get_contents($static):'');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')echo $xml;
}catch(Throwable $e){http_response_code(503);echo '<?xml version="1.0" encoding="UTF-8"?><error>Sitemap temporalmente no disponible.</error>';}

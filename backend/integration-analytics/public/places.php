<?php
declare(strict_types=1);
header('Cache-Control: no-cache, max-age=0, must-revalidate');
$app=dirname(__DIR__).'/app/';
if(!is_file($app.'PlaceDirectoryService.php')){http_response_code(503);exit('Lugares temporalmente no disponibles.');}
require_once $app.'PlaceDirectoryService.php';
require_once $app.'PlaceDirectoryPresentation.php';
$locale=$_GET['locale']??$_GET['idioma']??'es';
if(!is_string($locale)||!in_array($locale,['es','en','pt-BR'],true))$locale='es';
$savedAccountId=0;
if(($_GET['guardados']??'')==='1'){
    require_once $app.'bootstrap.php';
    require_business_portal_access();require_business_session();
    $savedAccountId=(int)$_SESSION['business_account_id'];
    header('Cache-Control: private, no-store');header('Vary: Cookie');
}
$dsn=getenv('EL_DESTINATIONS_DSN')?:'';$user=getenv('EL_DESTINATIONS_DB_USER')?:null;$password=getenv('EL_DESTINATIONS_DB_PASSWORD')?:null;
if($dsn===''){
    $configPath=$app.'../private/config.php';
    if(is_file($configPath)){$config=require $configPath;$dsn=(string)($config['dsn']??'');$user=$config['db_user']??null;$password=$config['db_password']??null;}
}
if($dsn===''){http_response_code(503);exit('Lugares temporalmente no disponibles.');}
try{
    $pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $directory=(new PlaceDirectoryService(new PlaceDirectorySelector($pdo,__DIR__)))->search($locale,$_GET,$savedAccountId);
    if($savedAccountId)$directory['csrf']=(string)$_SESSION['csrf'];
}catch(Throwable $error){error_log('place-index: '.$error->getMessage());http_response_code(503);exit('Lugares temporalmente no disponibles.');}
if($directory['requestedPage']>$directory['pages']){
    header('Location: '.PlaceDirectoryService::url($locale,$directory['filters'],$directory['page']),true,302);exit;
}
PlaceDirectoryPresentation::render($directory,__DIR__);

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
if(($_GET['guardados']??'')==='1'||isset($_COOKIE['el_session'])){
    require_once $app.'bootstrap.php';
    if(($_GET['guardados']??'')==='1'){require_business_portal_access();require_business_session(false,null,'saved_places');}
    if(!empty($_SESSION['business_account_id'])&&time()-(int)($_SESSION['business_last_seen']??0)<=1800){
        $savedAccountId=(int)$_SESSION['business_account_id'];$_SESSION['business_last_seen']=time();
        header('Cache-Control: private, no-store');
    }
}
header('Vary: Cookie');
$dsn=getenv('EL_DESTINATIONS_DSN')?:'';$user=getenv('EL_DESTINATIONS_DB_USER')?:null;$password=getenv('EL_DESTINATIONS_DB_PASSWORD')?:null;
if($dsn===''){
    $configPath=$app.'../private/config.php';
    if(is_file($configPath)){$config=require $configPath;$dsn=(string)($config['dsn']??'');$user=$config['db_user']??null;$password=$config['db_password']??null;}
}
if($dsn===''){http_response_code(503);exit('Lugares temporalmente no disponibles.');}
try{
    $pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $directory=(new PlaceDirectoryService(new PlaceDirectorySelector($pdo,__DIR__)))->search($locale,$_GET,$savedAccountId);
    require_once $app.'SavedPlaceSelector.php';
    $directory['bookmarkAuthenticated']=$savedAccountId>0;
    $directory['csrf']=$savedAccountId?(string)$_SESSION['csrf']:'';
    $directory['savedSlugs']=$savedAccountId?(new SavedPlaceSelector($pdo))->savedSlugs($savedAccountId,array_column($directory['rows'],'slug')):[];
}catch(Throwable $error){error_log('place-index: '.$error->getMessage());http_response_code(503);exit('Lugares temporalmente no disponibles.');}
if($directory['requestedPage']>$directory['pages']){
    header('Location: '.PlaceDirectoryService::url($locale,$directory['filters'],$directory['page']),true,302);exit;
}
PlaceDirectoryPresentation::render($directory,__DIR__);

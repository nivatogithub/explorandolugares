<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/CoverageService.php';
require dirname(__DIR__,2).'/app/analytics/AnalyticsService.php';
require dirname(__DIR__,2).'/app/analytics/BasicAudienceService.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
    header('Allow: GET, HEAD');http_response_code(405);echo '{"error":"Método no permitido"}';exit;
}
try{
    $plan=json_decode(file_get_contents(dirname(__DIR__,2).'/app/content/coverage-plan.json'),true,512,JSON_THROW_ON_ERROR);
    $geography=json_decode(file_get_contents(dirname(__DIR__,2).'/app/content/coverage-geography.json'),true,512,JSON_THROW_ON_ERROR);
    $pdo=null;$configPath=CoverageService::configurationPath();
    // Override is accepted only by the local development server, never by hosting PHP.
    if(PHP_SAPI==='cli-server' && in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){
        $configPath=getenv('EL_COVERAGE_TEST_CONFIG')?:$configPath;
    }
    if(is_file($configPath)){
        $config=require $configPath;
        try{$pdo=new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);}catch(Throwable){}
    }
    $measurement=$pdo?new AnalyticsService($pdo,(string)($config['app_key']??'')):null;
    if($measurement && $measurement->ready()){$plan=$measurement->planFromDatabase();$geography=$measurement->geographyFromDatabase();}
    $result=(new CoverageService($plan,$geography))->snapshot($pdo);
    // Public coverage does not expose audience or business activity metrics.
    unset($result['audience']);
    if(!$result['publication_source_available'])header('Cache-Control: no-store');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable){http_response_code(503);header('Cache-Control: no-store');echo '{"error":"La información de cobertura no está disponible en este momento."}';}

<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/CoverageService.php';
require dirname(__DIR__,2).'/app/analytics/AnalyticsService.php';
require dirname(__DIR__,2).'/app/analytics/BasicAudienceService.php';
require dirname(__DIR__,2).'/app/analytics/PublicDashboardService.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
    header('Allow: GET, HEAD');http_response_code(405);echo '{"error":"Método no permitido"}';exit;
}
try{
    foreach(['days','department','municipality','destination'] as $parameter){
        if(isset($_GET[$parameter])&&!is_string($_GET[$parameter])){http_response_code(400);echo '{"error":"Parámetro inválido"}';exit;}
    }
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
    $days=($_GET['days']??'')==='all'?'all':(in_array((int)($_GET['days']??30),[7,30,90],true)?(int)($_GET['days']??30):30);
    $department=preg_match('/^[0-9]{2}$/D',(string)($_GET['department']??''))?$_GET['department']:null;
    $municipality=preg_match('/^[0-9]{5}$/D',(string)($_GET['municipality']??''))?$_GET['municipality']:null;
    $destination=null;
    if(isset($_GET['destination'])&&$_GET['destination']!==''){
        $candidate=$_GET['destination'];$found=false;
        foreach($result['departments'] as $dep)foreach($dep['places'] as $place)if($place['status']==='published'&&$place['url']==='/destinos/'.$candidate.'/'&&($department===null||($department===$dep['code']||($department==='25'&&$dep['code']==='11'))))$found=true;
        if(!$found){http_response_code(404);echo '{"error":"Lugar no publicado en este ámbito"}';exit;}$destination=$candidate;
    }
    $result['audience']=['status'=>'aggregate_only','cookies'=>false,'visitor_identifiers'=>false];
    $result['audience']['basic']=$pdo?(new BasicAudienceService($pdo,dirname(__DIR__)))->report($days,$department,null,$destination):['status'=>'not_configured','page_views'=>null];
    $result['audience']['aggregate']=$pdo?(new BasicAudienceService($pdo,dirname(__DIR__)))->interestReport($days,$department,$destination):['status'=>'not_configured'];
    $result['dashboard']=$pdo?(new PublicDashboardService($pdo))->report($result['departments'],$department,$destination,$days):['status'=>'not_configured'];
    if(!$result['publication_source_available'])header('Cache-Control: no-store');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable){http_response_code(503);header('Cache-Control: no-store');echo '{"error":"La información de cobertura no está disponible en este momento."}';}

<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/analytics/runtime.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function output_measurement(array $data,int $status=200):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
    if(PHP_SAPI!=='cli-server'&&($_SERVER['HTTPS']??'')!=='on')output_measurement(['error'=>'Se requiere HTTPS'],403);
    $config=measurement_config();$key=(string)($config['app_key']??'');if(strlen($key)<32)output_measurement(['enabled'=>false]);$pdo=measurement_db($config);$service=new AnalyticsService($pdo,$key);
    if($_SERVER['REQUEST_METHOD']==='GET')output_measurement(['enabled'=>$service->ready(),'policy_version'=>'measurement-v1','retention_days'=>180,'consent_required'=>true]);
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: GET, POST');output_measurement(['error'=>'Método no permitido'],405);}
    if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)output_measurement(['error'=>'Tamaño no permitido'],413);
    $origin=parse_url((string)($_SERVER['HTTP_ORIGIN']??''));$host=strtolower((string)($_SERVER['HTTP_HOST']??''));$originHost=strtolower((string)($origin['host']??'')).(isset($origin['port'])?':'.$origin['port']:'');
    if($originHost!==$host||($origin['scheme']??'')!==((PHP_SAPI==='cli-server'&&($_SERVER['HTTPS']??'')!=='on')?'http':'https')||(!empty($_SERVER['HTTP_SEC_FETCH_SITE'])&&$_SERVER['HTTP_SEC_FETCH_SITE']!=='same-origin'))output_measurement(['error'=>'Origen no permitido'],403);
    if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))output_measurement(['error'=>'Formato no permitido'],415);
    $body=json_decode(file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);if(!is_array($body))output_measurement(['error'=>'Solicitud inválida'],400);
    if(($body['action']??'')==='revoke'){
        foreach(['el_stats_visitor','el_stats_session'] as $name)setcookie($name,'',['expires'=>1,'path'=>'/','secure'=>($_SERVER['HTTPS']??'')==='on','httponly'=>true,'samesite'=>'Lax']);output_measurement(['revoked'=>true]);
    }
    if(!$service->ready())output_measurement(['enabled'=>false],503);
    if(($body['consent']??null)!==true||($body['policy_version']??'')!=='measurement-v1')output_measurement(['accepted'=>false],403);
    $ua=strtolower((string)($_SERVER['HTTP_USER_AGENT']??''));if(preg_match('/bot|crawl|spider|preview|lighthouse|headless|slurp/',$ua)||($_SERVER['HTTP_DNT']??'')==='1'||($_SERVER['HTTP_SEC_GPC']??'')==='1')output_measurement(['accepted'=>false]);
    $service->maintain();
    $context=measurement_account($pdo,$key);if($context['excluded'])output_measurement(['accepted'=>false]);
    $context['visitor']=measurement_cookie_id('el_stats_visitor',90*86400,$key);$context['session']=measurement_cookie_id('el_stats_session',1800,$key);if(!$service->sessionMatches($context['session'],$context))$context['session']=measurement_cookie_id('el_stats_session',1800,$key,true);
    $context['ip']=(string)($_SERVER['REMOTE_ADDR']??'');$context['country']=$service->country($context['ip']);
    $context['source']=in_array($body['source']??'', ['direct_unknown','search','instagram','facebook','referral','campaign'],true)?$body['source']:'direct_unknown';
    $accepted=$service->accept($body,$context,gmdate('Y-m-d H:i:s'));output_measurement(['accepted'=>$accepted]);
}catch(InvalidArgumentException|JsonException){output_measurement(['error'=>'Evento inválido'],400);}catch(Throwable){output_measurement(['enabled'=>false,'error'=>'Medición temporalmente no disponible'],503);}

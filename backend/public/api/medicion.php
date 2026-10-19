<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/analytics/runtime.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function output_measurement(array $data,int $status=200):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{
    if(!measurement_local_http()&&($_SERVER['HTTPS']??'')!=='on')output_measurement(['error'=>'Se requiere HTTPS'],403);
    $config=measurement_config();$key=(string)($config['app_key']??'');if(strlen($key)<32)output_measurement(['enabled'=>false]);$pdo=measurement_db($config);$service=new AnalyticsService($pdo,$key);
    if($_SERVER['REQUEST_METHOD']==='GET')output_measurement(['enabled'=>false,'cookies'=>false,'mode'=>'aggregate_only']);
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: GET, POST');output_measurement(['error'=>'Método no permitido'],405);}
    if((int)($_SERVER['CONTENT_LENGTH']??0)>4096)output_measurement(['error'=>'Tamaño no permitido'],413);
    $origin=parse_url((string)($_SERVER['HTTP_ORIGIN']??''));$host=strtolower((string)($_SERVER['HTTP_HOST']??''));$originHost=strtolower((string)($origin['host']??'')).(isset($origin['port'])?':'.$origin['port']:'');
    if($originHost!==$host||($origin['scheme']??'')!==((measurement_local_http()&&($_SERVER['HTTPS']??'')!=='on')?'http':'https')||(!empty($_SERVER['HTTP_SEC_FETCH_SITE'])&&$_SERVER['HTTP_SEC_FETCH_SITE']!=='same-origin'))output_measurement(['error'=>'Origen no permitido'],403);
    if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))output_measurement(['error'=>'Formato no permitido'],415);
    $body=json_decode(file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);if(!is_array($body))output_measurement(['error'=>'Solicitud inválida'],400);
    if(($body['action']??'')==='revoke'){
        foreach(['el_stats_visitor','el_stats_session'] as $name)setcookie($name,'',['expires'=>1,'path'=>'/','secure'=>($_SERVER['HTTPS']??'')==='on','httponly'=>true,'samesite'=>'Lax']);output_measurement(['revoked'=>true]);
    }
    output_measurement(['accepted'=>false,'mode'=>'aggregate_only']);
}catch(InvalidArgumentException|JsonException){output_measurement(['error'=>'Evento inválido'],400);}catch(Throwable){output_measurement(['enabled'=>false,'error'=>'Medición temporalmente no disponible'],503);}

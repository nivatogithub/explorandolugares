<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/analytics/runtime.php';
require dirname(__DIR__,2).'/app/analytics/BasicAudienceService.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
function basic_output(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try {
    if(PHP_SAPI!=='cli-server'&&($_SERVER['HTTPS']??'')!=='on')basic_output(['enabled'=>false],403);
    $config=measurement_config();$service=new BasicAudienceService(measurement_db($config),dirname(__DIR__));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='GET')basic_output(['enabled'=>$service->ready(),'cookies'=>false,'visitor_identifiers'=>false,'unit'=>'page_views']);
    if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: GET, POST');basic_output(['accepted'=>false],405);}
    $origin=parse_url((string)($_SERVER['HTTP_ORIGIN']??''));$host=strtolower((string)($_SERVER['HTTP_HOST']??''));$originHost=strtolower((string)($origin['host']??'')).(isset($origin['port'])?':'.$origin['port']:'');
    if($originHost!==$host||($origin['scheme']??'')!==((PHP_SAPI==='cli-server'&&($_SERVER['HTTPS']??'')!=='on')?'http':'https')||(!empty($_SERVER['HTTP_SEC_FETCH_SITE'])&&$_SERVER['HTTP_SEC_FETCH_SITE']!=='same-origin'))basic_output(['accepted'=>false],403);
    if((int)($_SERVER['CONTENT_LENGTH']??0)>1024)basic_output(['accepted'=>false],413);
    if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))basic_output(['accepted'=>false],415);
    // Read these signals only to discard requests. Never persist or hash them.
    if(($_SERVER['HTTP_DNT']??'')==='1'||($_SERVER['HTTP_SEC_GPC']??'')==='1'||preg_match('/bot|crawl|spider|preview|lighthouse|headless|slurp/',strtolower($_SERVER['HTTP_USER_AGENT']??'')))basic_output(['accepted'=>false]);
    $body=json_decode(file_get_contents('php://input'),true,8,JSON_THROW_ON_ERROR);
    if(!is_array($body)||!is_string($body['path']??null)||array_diff(array_keys($body),['path','metric','business_id']))basic_output(['accepted'=>false],400);
    if(!$service->ready())basic_output(['enabled'=>false],503);
    if(array_keys($body)===['path'])basic_output(['accepted'=>$service->countPage($body['path'])]);
    if(count($body)!==3||!is_string($body['metric']??null)||!is_string($body['business_id']??null))basic_output(['accepted'=>false],400);
    basic_output(['accepted'=>$service->countCard($body['path'],$body['business_id'],$body['metric'])]);
}catch(JsonException|InvalidArgumentException){basic_output(['accepted'=>false],400);}catch(Throwable){basic_output(['enabled'=>false],503);}

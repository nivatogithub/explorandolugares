<?php
declare(strict_types=1);
// Deployment-only, narrow capability: fixed additive analytics installation. Not a SQL/shell executor.
header('Content-Type: application/json');header('Cache-Control: no-store');
$grantFile=__DIR__.'/activation-grant.json';
if(($_SERVER['HTTPS']??'')!=='on'||($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_file($grantFile)){http_response_code(404);exit;}
$grant=json_decode(file_get_contents($grantFile),true);
$provided=(string)($_SERVER['HTTP_X_EL_DEPLOYMENT']??'');
if(!is_array($grant)||($grant['expires']??0)<time()||!hash_equals((string)($grant['hash']??''),hash('sha256',$provided))){http_response_code(404);exit;}
// Consume before mutation: a failed install needs a new explicitly scoped grant.
unlink($grantFile);
require __DIR__.'/runtime.php';
try{$config=measurement_config();if(strlen((string)($config['app_key']??''))<32)throw new RuntimeException();$pdo=measurement_db($config);$pdo->query('SELECT id FROM accounts LIMIT 0');$pdo->query('SELECT slug FROM destinations LIMIT 0');$s=new AnalyticsService($pdo,$config['app_key']);$s->install(json_decode(file_get_contents(dirname(__DIR__).'/content/coverage-plan.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(dirname(__DIR__).'/content/coverage-geography.json'),true,512,JSON_THROW_ON_ERROR));echo '{"installed":true}';}catch(Throwable){http_response_code(503);echo '{"installed":false}';}

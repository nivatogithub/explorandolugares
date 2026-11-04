<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev only.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic local database required.');
$results=[];foreach(['','/en','/pt-br'] as $prefix)foreach(['/','/privacidad/','/negocios-locales/suscripciones/','/negocios-locales/proceso/'] as $path){
 $route=$prefix.$path;$body=file_get_contents('http://127.0.0.1'.$route,false,stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>"User-Agent: Explorando-local-render-validation\r\n"]]));if($body===false)throw new RuntimeException('No response: '.$route);preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);$code=(int)($status[1]??0);if($code!==200)throw new RuntimeException('Unexpected status: '.$route.' '.$code);$results[$route]=['status'=>$code,'hash'=>hash('sha256',$body)];}
echo json_encode($results,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";

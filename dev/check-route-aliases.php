<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo comprobaciones locales.');
$catalog=json_decode(file_get_contents('/srv/portal/app/public-route-aliases.json'),true,512,JSON_THROW_ON_ERROR);
$request=static function(string $path):array{
    $context=stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]);
    $body=file_get_contents('http://127.0.0.1'.$path,false,$context);
    if($body===false||!preg_match('~HTTP/\S+ (\d+)~',$http_response_header[0]??'',$match))throw new RuntimeException('Respuesta local inválida.');
    $status=(int)$match[1];$location='';
    foreach($http_response_header as $header)if(str_starts_with(strtolower($header),'location:'))$location=trim(substr($header,9));
    if($status>=500||preg_match('~(?:Fatal error|Warning:|Parse error)~',$body))throw new RuntimeException('Fallo de ejecución en '.$path);
    return ['status'=>$status,'location'=>$location,'sha256'=>hash('sha256',$body)];
};
$checks=[];
foreach($catalog['aliases'] as $legacy=>$current){
    if(!is_file('/srv/portal/public'.$current))throw new RuntimeException('Controlador ausente: '.$current);
    $before=$request($legacy);$after=$request($current);
    if($before['status']!==$after['status']||$before['location']!==$after['location'])throw new RuntimeException('Alias incompatible: '.$legacy);
    if(str_starts_with($current,'/admin/')&&!in_array($after['status'],[303,403],true))throw new RuntimeException('Administración sin protección: '.$current);
    $checks[]=['legacy'=>$legacy,'current'=>$current,'status'=>$after['status'],'authorization_preserved'=>true];
}
$resources=0;
foreach($catalog['resource_aliases'] as $legacy=>$current){
    if(!is_file('/srv/portal/public'.$current))continue;
    $before=$request($legacy);$after=$request($current);
    if($before['status']!==200||$before['sha256']!==$after['sha256'])throw new RuntimeException('Recurso incompatible: '.$legacy);
    $resources++;
}
$report=['production_accessed'=>false,'controller_checks'=>$checks,'resource_aliases_checked'=>$resources];
file_put_contents('/srv/state/route-aliases-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo 'OK: '.count($checks).' alias de controladores conservan respuesta y protección; '.$resources." recursos conservan los mismos bytes.\n";

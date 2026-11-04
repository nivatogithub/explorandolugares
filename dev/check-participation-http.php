<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
$id=bin2hex(random_bytes(24));session_name('el_session');session_id($id);session_start();
$_SESSION=['csrf'=>str_repeat('c',64),'request_key'=>str_repeat('d',48)];session_write_close();
$directory=explode(';',session_save_path());$file=(end($directory)?:sys_get_temp_dir()).'/sess_'.$id;
if(!is_file($file))throw new RuntimeException('Falta sesión sintética.');chown($file,'www-data');chmod($file,0600);
$result=[];
try{
 foreach([['GET',''],['GET','?lang=en'],['GET','?lang=pt-br'],['PUT',''],['POST','']] as [$method,$query]){
    $headers="Cookie: el_session=$id\r\n".($method==='POST'?"Content-Length: 0\r\n":'');
    $context=stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>$headers,'content'=>'']]);
    $body=file_get_contents('http://127.0.0.1/api/participation.php'.$query,false,$context);
    if($body===false)throw new RuntimeException('No responde participación.');
    preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);
    $json=json_decode($body,true,32,JSON_THROW_ON_ERROR);unset($json['csrf'],$json['request_key']);
    $result[$method.$query]=['status'=>(int)($status[1]??0),'body_hash'=>hash('sha256',json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
 }
 if($result['PUT']['status']!==405||!in_array($result['POST']['status'],[403,503],true))throw new RuntimeException('Cambió el rechazo de solicitudes.');
 echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}finally{if(is_file($file)&&!unlink($file))throw new RuntimeException('No se pudo retirar sesión sintética.');}

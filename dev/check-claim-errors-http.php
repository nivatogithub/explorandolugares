<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
$mode=$argv[1]??'';if(!in_array($mode,['before','after'],true))throw new RuntimeException('Indica before o after.');
$texts=[
 'account_required'=>['es'=>'Crea una cuenta empresarial o inicia sesión para solicitar la reclamación.','en'=>'Create a business account or sign in to request a claim.','pt-br'=>'Crie uma conta empresarial ou entre para solicitar a reivindicação.'],
 'unavailable'=>['es'=>'La recepción de solicitudes no está habilitada en este momento.','en'=>'Requests are not currently being accepted.','pt-br'=>'O recebimento de solicitações não está disponível no momento.'],
];
$result=[];
foreach(['es','en','pt-br'] as $locale){
 $id=bin2hex(random_bytes(24));session_name('el_session');session_id($id);session_start();$csrf=bin2hex(random_bytes(32));$_SESSION=['csrf'=>$csrf];session_write_close();
 $directory=explode(';',session_save_path());$file=(end($directory)?:sys_get_temp_dir()).'/sess_'.$id;
 if(!is_file($file))throw new RuntimeException('Falta sesión sintética.');chown($file,'www-data');chmod($file,0600);
 try{
  foreach(['GET','POST'] as $method){
   $content=$method==='POST'?http_build_query(['csrf'=>$csrf,'business_id'=>'1','relationship_code'=>'owner','consent'=>'1']):'';
   $headers="Cookie: el_session=$id\r\n".($method==='POST'?"Content-Type: application/x-www-form-urlencoded\r\n":'');
   $ctx=stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>$headers,'content'=>$content]]);
   $body=file_get_contents('http://127.0.0.1/reclamar-negocio/?lang='.$locale,false,$ctx);
   if($body===false)throw new RuntimeException('No responde la reclamación.');
   preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);$status=(int)($status[1]??0);
   if($status!==($method==='GET'?200:400))throw new RuntimeException('Respuesta inesperada: '.$locale.'/'.$method.'/'.$status);
   $reason=null;
   if($method==='POST'){
    foreach($texts as $key=>$translations){
     $expected=$translations[$mode==='before'?'es':$locale];
     if(str_contains($body,htmlspecialchars($expected,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))){$reason=$key;break;}
    }
    if($reason===null)throw new RuntimeException('Falta el error esperado: '.$locale);
    $body=str_replace(htmlspecialchars($texts[$reason][$mode==='before'?'es':$locale],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),'ERROR_MESSAGE',$body);
   }
   $body=str_replace($csrf,'CSRF_TOKEN',$body);
   $result[$locale.'-'.$method]=['status'=>$status,'reason'=>$reason,'body_hash'=>hash('sha256',$body)];
  }
 }finally{if(is_file($file)&&!unlink($file))throw new RuntimeException('No se pudo retirar sesión sintética.');}
}
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";

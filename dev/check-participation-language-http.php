<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev only.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic development database required.');
$catalog=json_decode(file_get_contents('/srv/portal/app/presentation/participation-messages.json'),true,64,JSON_THROW_ON_ERROR);
$id=bin2hex(random_bytes(24));session_name('el_session');session_id($id);session_start();$csrf=str_repeat('c',64);$key=str_repeat('d',48);$_SESSION=['csrf'=>$csrf,'request_key'=>$key];session_write_close();
$directory=explode(';',session_save_path());$file=(end($directory)?:sys_get_temp_dir()).'/sess_'.$id;if(!is_file($file))throw new RuntimeException('Missing synthetic session.');chown($file,'www-data');chmod($file,0600);
function participation_request(string $id,string $locale,string $method,?array $post=null):array{
 $headers="Cookie: el_session=$id\r\n";$options=['method'=>$method,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30];
 if($post!==null){$headers.="Content-Type: application/x-www-form-urlencoded\r\n";$options['content']=http_build_query($post);}$options['header']=$headers;
 $body=file_get_contents('http://127.0.0.1/api/participation.php?lang='.$locale,false,stream_context_create(['http'=>$options]));if($body===false)throw new RuntimeException('No response.');preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);return [(int)($status[1]??0),json_decode($body,true,64,JSON_THROW_ON_ERROR)];
}
$checks=[];
try{
 foreach(['es','en','pt-br'] as $locale){
  [$status,$ready]=participation_request($id,$locale,'GET');if($status!==200)throw new RuntimeException('GET failed.');
  if(empty($ready['enabled'])&&($ready['message']??'')!==$catalog['not_ready_preview'][$locale])throw new RuntimeException('Wrong availability translation.');$checks[]=$locale.' GET';
  [$status,$body]=participation_request($id,$locale,'PUT');if($status!==405||$body['message']!==$catalog['method_not_allowed'][$locale])throw new RuntimeException('Wrong method message.');$checks[]=$locale.' PUT';
  if(empty($ready['enabled']))continue;
  foreach(['csrf'=>['csrf'=>'wrong','request_key'=>$key],'bot'=>['csrf'=>$csrf,'request_key'=>$key,'website'=>'synthetic.invalid']] as $case=>$post){
   [$status,$body]=participation_request($id,$locale,'POST',$post);$expected=$case==='csrf'?'csrf_invalid':'validation_failed';$statusExpected=$case==='csrf'?403:422;
   if($status!==$statusExpected||$body['message']!==$catalog[$expected][$locale])throw new RuntimeException('Wrong '.$locale.' '.$case);$checks[]=$locale.' '.$case;
  }
 }
 echo json_encode(['checks'=>$checks,'test_sessions_removed'=>true,'request_rows_written'=>false,'production_accessed'=>false],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
}finally{if(is_file($file)&&!unlink($file))throw new RuntimeException('Could not remove synthetic session.');}

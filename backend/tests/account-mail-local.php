<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/el-mail-'.bin2hex(random_bytes(8));mkdir($dir.'/app',0700,true);mkdir($dir.'/private/brevo',0700,true);
foreach(['AccountMailConfiguration.php','AccountRecoveryDelivery.php','BrevoTransactionalClient.php','AccountMailTemplate.php','BrevoSettings.php'] as $file)copy(__DIR__.'/../app/'.$file,$dir.'/app/'.$file);
require $dir.'/app/AccountMailConfiguration.php';
function checkMail($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
 $key=str_repeat('k',64);BrevoSettings::save($dir.'/private/brevo',$key,['enabled'=>'1','sender_email'=>'sender@example.invalid','sender_name'=>'Test','sender_address'=>'Test address','api_key'=>'synthetic-key']);
 file_put_contents($dir.'/private/account-mail-test.json',json_encode(['enabled'=>true,'recipient'=>'recipient@example.invalid']));
 putenv('EL_PREPROD_LOCAL=1');$_SERVER['HTTP_HOST']='127.0.0.1:8088';$config=['local_test'=>true,'app_key'=>$key];
 $mail=AccountMailConfiguration::resolve($config,'recipient@example.invalid');checkMail(!$mail['mailbox']&&$mail['delivery']->ready(),'allowlisted delivery not ready');
 checkMail(AccountMailConfiguration::resolve($config,'other@example.invalid')['mailbox'],'other recipient left local mailbox');
 $_SERVER['HTTP_HOST']='foreign.example';$foreign=AccountMailConfiguration::resolve($config,'recipient@example.invalid');checkMail(!$foreign['delivery']->ready(),'foreign host enabled real test');
 $_SERVER['HTTP_HOST']='127.0.0.1:8088';checkMail(!AccountMailConfiguration::resolve(['local_test'=>false,'app_key'=>$key],'recipient@example.invalid')['delivery']->ready(),'production used local override');
 $settings=['enabled'=>true,'api_key'=>'fake','sender_name'=>'Test','sender_email'=>'sender@example.invalid','public_origin'=>'http://127.0.0.1:8088'];$payload=[];$transport=function($p)use(&$payload){$payload=$p;return ['status'=>201,'body'=>['messageId'=>'synthetic']];};
 checkMail(!(new AccountRecoveryDelivery($settings,$transport))->ready(),'loopback implicitly accepted');$d=new AccountRecoveryDelivery($settings,$transport,true);$d->send('recipient@example.invalid',str_repeat('a',64),true);checkMail(str_contains($payload['textContent'],'http://127.0.0.1:8088/cuenta/verificar-correo/'),'wrong activation origin');$d->send('recipient@example.invalid',str_repeat('b',64));checkMail(str_contains($payload['textContent'],'http://127.0.0.1:8088/negocios/cambiar-clave/'),'wrong recovery origin');
 BrevoSettings::save($dir.'/private/brevo',$key,['sender_email'=>'sender@example.invalid','sender_name'=>'Test','sender_address'=>'Test address']);checkMail(!AccountMailConfiguration::resolve($config,'recipient@example.invalid')['delivery']->ready(),'disabled connection ignored');
 echo "PASS: single recipient, local guards, disabled connection, canonical activation/recovery URLs; no messages sent\n";
}finally{
 foreach(glob($dir.'/app/*') as $f)unlink($f);foreach(glob($dir.'/private/brevo/*') as $f)unlink($f);unlink($dir.'/private/account-mail-test.json');rmdir($dir.'/private/brevo');rmdir($dir.'/private');rmdir($dir.'/app');rmdir($dir);
}

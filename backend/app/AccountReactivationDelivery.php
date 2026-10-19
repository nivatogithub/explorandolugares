<?php
declare(strict_types=1);
require_once __DIR__.'/AccountReactivationService.php';
require_once __DIR__.'/AccountMailConfiguration.php';
require_once __DIR__.'/AccountPasswordResetService.php';
require_once __DIR__.'/AccountVerificationDelivery.php';
final class AccountReactivationDelivery
{
 public static function send(PDO $pdo,array $config,int $id):void {
  $q=$pdo->prepare('SELECT email,status FROM accounts WHERE id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)return;$email=$row['email'];
  if($row['status']==='pending_activation'){AccountVerificationDelivery::send($pdo,$config,$id);return;}
  $reactivation=$row['status']==='disabled';
  $mail=AccountMailConfiguration::resolve($config,(string)$email);$service=$reactivation?new AccountReactivationService($pdo):new AccountPasswordResetService($pdo);
  if(!$mail['mailbox']&&!$mail['delivery']->ready())throw new RuntimeException('El correo de reactivación no está disponible.');
  $token=$service->issue($id);$suffix=$reactivation?'reactivacion=1&token=':'token=';
  try{
   if($mail['mailbox']){
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));$path=dirname(__DIR__).'/private/mailbox.json';$f=fopen($path,'c+');if(!$f)throw new RuntimeException('Buzón local no disponible.');
    try{if(!flock($f,LOCK_EX))throw new RuntimeException('Buzón ocupado.');$messages=json_decode(stream_get_contents($f)?:'[]',true,32,JSON_THROW_ON_ERROR);$messages[]=['to'=>$email,'created_at'=>gmdate('c'),'kind'=>$reactivation?'reactivation':'recovery','url'=>'http://'.$host.'/negocios/cambiar-clave/?'.$suffix.$token];rewind($f);ftruncate($f,0);fwrite($f,json_encode(array_slice($messages,-50),JSON_THROW_ON_ERROR));fflush($f);flock($f,LOCK_UN);}finally{fclose($f);chmod($path,0600);}
   }else $mail['delivery']->send((string)$email,$token,false,false,$reactivation);
  }catch(Throwable $e){if($reactivation)$service->revoke($token);else $pdo->prepare('UPDATE account_password_resets SET used_at=CURRENT_TIMESTAMP WHERE token_hash=?')->execute([hash('sha256',$token)]);throw $e;}
 }
 /** Uniform public response: never disclose whether an email is suspended or closed. */
 public static function existingRegistration(PDO $pdo,array $config,string $email):bool {
  $q=$pdo->prepare('SELECT id,status FROM accounts WHERE email=?');$q->execute([strtolower(trim($email))]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)return false;
  if(throttle('account_registration_existing',3,900,strtolower(trim($email)))&&($row['status']!=='disabled'||(AccountLifecycle::state($pdo,(int)$row['id'])['event_type']??'')==='closed')){
   try{self::send($pdo,$config,(int)$row['id']);}catch(Throwable $e){error_log('No se pudo confirmar el envío de reactivación.');}
  }
  return true;
 }
}

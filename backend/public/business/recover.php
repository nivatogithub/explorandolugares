<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';require_business_portal_access();
require_once dirname((new ReflectionFunction('db'))->getFileName()).'/AccountPasswordResetService.php';
require_once dirname((new ReflectionFunction('db'))->getFileName()).'/AccountMailConfiguration.php';
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
$localMailbox=getenv('EL_PREPROD_LOCAL')==='1'&&!empty($config['local_test'])&&preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/D',$host);
$mail=AccountMailConfiguration::resolve($config,trim((string)($_POST['email']??'')));$localMailbox=$mail['mailbox'];$delivery=$mail['delivery'];$available=$localMailbox||$delivery->ready();$sent=isset($_GET['solicitado']);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  csrf();if(!$available)throw new RuntimeException('La recuperación automática no está disponible. Contacta al equipo.');
  $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new RuntimeException('Escribe un correo válido.');
  if(!throttle('account_recovery_ip',5,900))throw new RuntimeException('Demasiadas solicitudes. Espera unos minutos.');
  if(throttle('account_recovery_email',3,900,$email)){
   $q=db()->prepare('SELECT id,email,status FROM accounts WHERE email=? AND status IN ("active","pending_activation","disabled")');$q->execute([$email]);$account=$q->fetch();
   if($account){
    require_once dirname(__DIR__,2).'/app/AccountReactivationDelivery.php';
    if($account['status']==='disabled'){
     if((AccountLifecycle::state(db(),(int)$account['id'])['event_type']??'')==='closed'){
      try{AccountReactivationDelivery::send(db(),$config,(int)$account['id']);}catch(Throwable $caughtIncident){PortalIncidents::capture($caughtIncident,'recovery_delivery');error_log('No se confirmó el correo de reactivación.');}
     }
    }else{
    $token=(new AccountPasswordResetService(db()))->issue((int)$account['id'],'self-service',(string)$account['email']);
    try{
     if($localMailbox){
      $message=['to'=>$account['email'],'created_at'=>gmdate('c'),'url'=>'http://'.$host.'/negocios/cambiar-clave/?token='.$token];$path=private_storage_path('mailbox').'/messages.json';
      // Retain the established local mailbox path for reviewers and test tools.
      $path=dirname(__DIR__,2).'/private/mailbox.json';$f=fopen($path,'c+');if(!$f)throw new RuntimeException('Buzón local no disponible.');
      try{if(!flock($f,LOCK_EX))throw new RuntimeException('Buzón ocupado.');$messages=json_decode(stream_get_contents($f)?:'[]',true,32,JSON_THROW_ON_ERROR);$messages[]=$message;rewind($f);ftruncate($f,0);fwrite($f,json_encode(array_slice($messages,-50),JSON_THROW_ON_ERROR));fflush($f);flock($f,LOCK_UN);}finally{fclose($f);chmod($path,0600);}
     }else $delivery->send($account['email'],$token);
    }catch(Throwable $caughtIncident){PortalIncidents::capture($caughtIncident,'recovery_delivery');db()->prepare('UPDATE account_password_resets SET used_at=CURRENT_TIMESTAMP WHERE token_hash=? AND used_at IS NULL')->execute([hash('sha256',$token)]);error_log('Explorando Lugares: no se pudo confirmar un correo de recuperación.');}
    }
   }
  }
  $sent=true;
 }catch(Throwable $e){PortalIncidents::technical($e,'account_recovery');$error=$e instanceof PDOException?'No se pudo procesar la solicitud. Inténtalo más tarde.':$e->getMessage();http_response_code(400);}
}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';
$viewData=['csrf'=>(string)$_SESSION['csrf'],'error'=>$error,'sent'=>$sent,'available'=>$available,'local_mailbox'=>(bool)$localMailbox,'contact'=>filter_var($config['privacy']['contact']??'',FILTER_VALIDATE_EMAIL)?(string)$config['privacy']['contact']:''];
AccountAuthPresentation::render('recover',account_layout_context(),$viewData,dirname(__DIR__));

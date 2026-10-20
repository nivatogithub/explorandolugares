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
    $token=(new AccountPasswordResetService(db()))->issue((int)$account['id'],'self-service');
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
require_once __DIR__.'/../_account-page.php';account_page(true);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Recuperar contraseña | Explorando Lugares</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/account-registration.css?v=2"><link rel="stylesheet" href="/assets/account-access.css?v=2"></head><body><?php require __DIR__.'/../_account-header.php'; ?><main class="wrap account-portal account-access"><a class="account-access-brand brand" href="/index.html"><img class="brand-logo" src="/assets/brand/explorando-lugares-logo-v2-transparent.png" alt="Explorando Lugares" width="82" height="82"><span>Explorando<b>Lugares</b></span></a><section class="account-portal-card"><h1>Recuperar contraseña</h1><?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?><?php if($sent):?><p role="status">Si este correo tiene una cuenta que admite recuperación o reactivación, recibirás instrucciones para continuar. Si no llegan, revisa correo no deseado o contacta al equipo.</p><?php elseif($available):?><form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>Correo electrónico<input type="email" name="email" maxlength="254" autocomplete="email" required></label><button type="submit" class="pill">Solicitar enlace de recuperación</button></form><?php else:?><p>La recuperación automática no está disponible todavía. Contacta al equipo para solicitar un enlace temporal.</p><?php endif ?><?php if($localMailbox):?><p>Preproducción: los mensajes van al buzón local, salvo el destinatario habilitado expresamente para la prueba de correo real.</p><?php endif ?><?php if(filter_var($config['privacy']['contact']??'',FILTER_VALIDATE_EMAIL)):?><p><a href="mailto:<?=esc($config['privacy']['contact'])?>">Contactar al equipo</a></p><?php endif ?><p><a href="/negocios/acceso/">Volver a iniciar sesión</a></p></section></main></body></html>

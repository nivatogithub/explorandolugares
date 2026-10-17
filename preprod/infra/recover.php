<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if(getenv('EL_PREPROD_LOCAL')!=='1'||empty($config['local_test'])||!preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/D',$host)){http_response_code(404);exit('No encontrado.');}
require_once dirname((new ReflectionFunction('db'))->getFileName()).'/AccountPasswordResetService.php';
$sent=false;$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  csrf();if(!throttle('local_recovery',5,900))throw new RuntimeException('Demasiadas solicitudes. Espera unos minutos.');
  $email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new RuntimeException('Indica un correo válido.');
  $q=db()->prepare("SELECT id,email FROM accounts WHERE email=? AND (account_type='business' OR EXISTS(SELECT 1 FROM account_roles ar WHERE ar.account_id=accounts.id AND ar.role='administrator')) AND status IN ('active','pending_activation')");$q->execute([$email]);$account=$q->fetch();
  if($account){
   $token=(new AccountPasswordResetService(db()))->issue((int)$account['id'],'preprod-self-service');
   $message=['to'=>$account['email'],'created_at'=>gmdate('c'),'url'=>'http://127.0.0.1:8088/negocios/cambiar-clave/?token='.$token];
   $path='/srv/state/private/mailbox.json';$f=fopen($path,'c+');if(!$f)throw new RuntimeException('Buzón local no disponible.');
   try{if(!flock($f,LOCK_EX))throw new RuntimeException('Buzón ocupado.');$messages=json_decode(stream_get_contents($f)?:'[]',true,32,JSON_THROW_ON_ERROR);$messages[]=$message;$encoded=json_encode(array_slice($messages,-50),JSON_THROW_ON_ERROR);rewind($f);ftruncate($f,0);fwrite($f,$encoded);fflush($f);flock($f,LOCK_UN);}finally{fclose($f);chmod($path,0600);}
  }
  $sent=true;
 }catch(Throwable $e){$error=$e instanceof PDOException?'No se pudo procesar la solicitud.':$e->getMessage();http_response_code(400);}
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Recuperar contraseña · Preprod</title><link rel="stylesheet" href="/assets/style.css"></head><body><main class="wrap admin-page account-portal"><section class="account-portal-card"><h1>Recuperar contraseña</h1><p>Entorno local: el mensaje se deposita en el buzón de pruebas; no se envía correo real.</p><?php if($sent):?><p role="status">Si existe una cuenta empresarial habilitada con ese correo, recibirás un enlace de recuperación en el buzón local.</p><?php endif ?><?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif ?><form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>Correo electrónico<input type="email" name="email" autocomplete="email" maxlength="254" required></label><button class="pill">Solicitar enlace</button></form><p><a href="/negocios/acceso/">Volver al acceso</a></p></section></main></body></html>

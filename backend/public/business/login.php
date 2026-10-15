<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();
require dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
$service=new BusinessAccountPortalService(db());$error='';
try{
 if(!business_portal_available())throw new RuntimeException('El acceso de cuentas empresariales todavía no está habilitado.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf();$account=$service->authenticate((string)($_POST['email']??''),(string)($_POST['password']??''));session_regenerate_id(true);$_SESSION['business_account_id']=(int)$account['id'];$_SESSION['business_last_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: /v1/negocios/portal/',true,303);exit;
 }
}catch(Throwable $e){$error=$e->getMessage();http_response_code(400);}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Acceso de negocios | Explorando Lugares</title><link rel="stylesheet" href="/v1/assets/style.css?v=business-portal-1"><link rel="icon" href="/v1/assets/favicon.svg" type="image/svg+xml"></head><body><main class="wrap admin-page account-portal"><a class="brand" href="/v1/index.html"><img class="brand-logo" src="/v1/assets/logo.svg" alt="" width="64" height="64"><span>Explorando<b>Lugares</b></span></a><p><a href="/v1/negocios-locales/">← Negocios locales</a></p><section class="account-portal-card"><p class="eyebrow">Espacio para negocios</p><h1>Inicia sesión</h1><?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?><form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><label>Correo electrónico<input type="email" name="email" maxlength="254" autocomplete="username" required></label><label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label><button class="pill" type="submit">Entrar a mi cuenta</button></form><p>¿Aún no tienes cuenta? <a href="/v1/negocios/registro/">Crear cuenta empresarial</a></p><p>Si no recuerdas tu contraseña, comunícate con el equipo para recuperar el acceso. La recuperación automática por correo aún no está configurada.</p></section></main></body></html>

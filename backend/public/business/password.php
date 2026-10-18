<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/AccountPasswordResetService.php';
$service=new AccountPasswordResetService(db());
$token=is_string($_POST['token']??$_GET['token']??null)?(string)($_POST['token']??$_GET['token']):'';
$error='';$saved=false;$valid=$service->isValid($token);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf();
        if(!throttle('business_account_password_reset',8,900))throw new RuntimeException('Demasiados intentos. Inténtalo más tarde.');
        $service->reset($token,(string)($_POST['password']??''),(string)($_POST['password_confirm']??''));
        $saved=true;$valid=false;
    }catch(Throwable $exception){$error=$exception instanceof PDOException?'No fue posible actualizar la contraseña. Inténtalo más tarde.':$exception->getMessage();http_response_code(400);$valid=$service->isValid($token);}
}
require_once __DIR__.'/../_account-page.php';account_page(true);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Crear o cambiar contraseña | Explorando Lugares</title><link rel="stylesheet" href="<?=esc(site_path('/assets/style.css?v=business-password-1'))?>"><link rel="icon" href="<?=esc(site_path('/assets/brand/explorando-lugares-logo-v2-transparent.png'))?>" type="image/png"><link rel="stylesheet" href="/assets/account-registration.css?v=3"><link rel="stylesheet" href="/assets/account-access.css?v=2"></head><body><?php require __DIR__.'/../_account-header.php'; ?><main class="wrap account-portal account-access"><a class="account-access-brand brand" href="/index.html"><img class="brand-logo" src="/assets/brand/explorando-lugares-logo-v2-transparent.png" alt="Explorando Lugares" width="82" height="82"><span>Explorando<b>Lugares</b></span></a><section class="account-portal-card"><p class="eyebrow">Tu cuenta</p><?php if($saved):?><h1>Contraseña actualizada</h1><p>Ya puedes entrar con tu correo y la nueva contraseña.</p><p><a class="pill" href="<?=esc(site_path('/negocios/acceso/'))?>">Iniciar sesión</a></p><?php else:?><h1><?=esc($valid?'Crea o cambia tu contraseña':'Enlace vencido o no válido')?></h1><?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?><?php if($valid):?><p>El enlace es de un solo uso y vence en 24 horas. Elige una contraseña nueva.</p><form method="post" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="token" value="<?=esc($token)?>"><label>Nueva contraseña<input type="password" name="password" minlength="12" maxlength="200" autocomplete="new-password" required><small>Al menos 12 caracteres, con letras y números.</small></label><label>Confirma la contraseña<input type="password" name="password_confirm" minlength="12" maxlength="200" autocomplete="new-password" required></label><button class="pill" type="submit">Guardar contraseña</button></form><?php else:?><p>Solicita al equipo un enlace nuevo. Por seguridad, no compartas este enlace con otras personas.</p><p><a href="<?=esc(site_path('/negocios/acceso/'))?>">Volver a iniciar sesión</a></p><?php endif ?><?php endif ?></section></main></body></html>

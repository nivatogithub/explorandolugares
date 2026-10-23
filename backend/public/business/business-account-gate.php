<?php
declare(strict_types=1);
if (!isset($error)) $error = '';
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Tu cuenta antes del negocio | Explorando Lugares</title>
<link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/account-registration.css"><link rel="stylesheet" href="/assets/account-access.css"></head>
<body><?php require __DIR__.'/../_account-header.php'; ?><main class="wrap account-portal account-access"><section class="account-portal-card"><p class="eyebrow">Paso 1 · Tu cuenta</p><h1>Antes de registrar un negocio</h1><p>El negocio se asociará a una cuenta personal. Primero crea tu cuenta o inicia sesión; así tu nombre y correo se toman de la cuenta y no se vuelven a pedir en la solicitud.</p>
<?php if($error): ?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?>
<p><a class="pill" href="<?=esc(site_path('/negocios/registro/?tipo=traveler'))?>">Crear mi cuenta</a></p>
<p>¿Ya tienes una cuenta? <a class="pill light" href="<?=esc(site_path('/negocios/acceso/'))?>">Iniciar sesión</a></p>
<p class="small-note">Después de verificar el correo e iniciar sesión, te llevaremos directamente al wizard para registrar o reclamar el negocio.</p>
</section></main></body></html>

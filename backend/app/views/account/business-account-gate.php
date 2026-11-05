<?php declare(strict_types=1); ?>
<section class="account-portal-card"><p class="eyebrow">Paso 1 · Tu cuenta</p><h1>Antes de registrar un negocio</h1><p>El negocio se asociará a una cuenta personal. Primero crea tu cuenta o inicia sesión; así tu nombre y correo se toman de la cuenta y no se vuelven a pedir en la solicitud.</p>
<?php if($data['error']): ?><p class="notice" role="alert"><?=$e($data['error'])?></p><?php endif ?>
<p><a class="pill" href="/negocios/registro/?tipo=traveler<?=$locale==='es'?'':'&amp;lang='.$e($locale)?>">Crear mi cuenta</a></p>
<p>¿Ya tienes una cuenta? <a class="pill light" href="/negocios/acceso/<?=$locale==='es'?'':'?lang='.$e($locale)?>"><?=$e($copy['login'])?></a></p>
<p class="small-note">Después de verificar el correo e iniciar sesión, te llevaremos directamente al wizard para registrar o reclamar el negocio.</p>
</section>

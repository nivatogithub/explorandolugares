<?php declare(strict_types=1);
$resendCountdown = match ($locale) {
    'en' => 'You can request another link in {time}.',
    'pt-br' => 'Você pode solicitar outro link em {time}.',
    default => 'Puedes solicitar otro enlace en {time}.',
};
$pendingEmail = $data['pendingEmail'] ?? null;
$correction = match ($locale) {
    'en' => ['Correct my email', 'Email used to register', 'Correct email', 'Confirm your registration password', 'Save and send activation link'],
    'pt-br' => ['Corrigir meu e-mail', 'E-mail usado no cadastro', 'E-mail correto', 'Confirme a senha do cadastro', 'Salvar e enviar link de ativação'],
    default => ['Corregir mi correo', 'Correo del registro', 'Correo correcto', 'Confirma la contraseña del registro', 'Guardar y enviar enlace de activación'],
};
?>
<section class="account-portal-card"><h1><?=$e($data['verified']?$copy['email_verified']:$copy['verify_email'])?></h1><?php if($data['verified']):?><p>Tu cuenta está activada. Inicia sesión para continuar <?=$data['continueToBusiness']?'con el registro del negocio':'en tu cuenta'?>.</p><a class="pill" href="/negocios/acceso/<?=$e($authLinkSuffix)?>"><?=$e($data['continueToBusiness']?'Iniciar sesión y continuar':$copy['login'])?></a><?php else:?><?php if($data['error']):?><p class="notice" role="alert"><?=$e($data['error'])?></p><?php endif ?><?php if($data['sent']):?><p role="status">Si existe una cuenta pendiente con ese correo, recibirás un enlace de activación. Revisa también el correo no deseado y espera al menos un minuto antes de solicitar otro enlace.</p><?php endif ?><p>Abre el enlace que enviamos a tu correo para activar la cuenta. El enlace vence en 24 horas. Tu cuenta todavía no permite iniciar sesión.</p><form method="post" class="registration-form" data-resend-seconds="<?=(int)($data['resendWait']??0)?>"><input type="hidden" name="csrf" value="<?=$e($data['csrf'])?>"><label><?=$e($copy['email'])?><input type="email" name="email" autocomplete="email" maxlength="254" required></label><button class="pill" type="submit"><?=$e($copy['verification_resend'])?></button><p role="timer" data-resend-countdown="<?=$e($resendCountdown)?>" hidden></p></form>
<?php if($pendingEmail!==null):?>
<details<?=!empty($data['error'])?' open':''?>>
  <summary><?=$e($correction[0])?></summary>
  <p><?=$e($correction[1])?> <strong><?=$e($pendingEmail)?></strong></p>
  <form method="post" action="/cuenta/verificar-correo/<?=$e($authLinkSuffix)?>" class="registration-form">
    <input type="hidden" name="csrf" value="<?=$e($data['csrf'])?>">
    <input type="hidden" name="action" value="correct_email">
    <label><?=$e($correction[2])?><input type="email" name="corrected_email" autocomplete="email" maxlength="254" required></label>
    <label><?=$e($correction[3])?><input type="password" name="password" autocomplete="current-password" maxlength="200" required></label>
    <button class="pill" type="submit"><?=$e($correction[4])?></button>
  </form>
</details>
<?php endif ?>
<?php endif ?></section>

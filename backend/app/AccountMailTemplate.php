<?php
declare(strict_types=1);
/** Table-based transactional mail with inline styling and a plain text alternative. */
final class AccountMailTemplate
{
 public static function build(string $email,string $url,bool $verification,bool $example=false,bool $reactivation=false):array{
  $escape=static fn(string $v)=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $type=$verification?'Verificación de correo':'Recuperación de contraseña';
  $title=$verification?'Confirma tu correo y empieza a explorar':'Recupera el acceso a tu cuenta';
  $intro=$verification?'Solo falta un paso para activar tu cuenta en Explorando Lugares. Confirma que este correo te pertenece.':'Recibimos una solicitud para cambiar la contraseña de tu cuenta en Explorando Lugares.';
  $button=$verification?'Verificar mi correo':'Crear nueva contraseña';
  $ignore=$verification?'Si no creaste esta cuenta, puedes ignorar este mensaje.':'Si no solicitaste este cambio, ignora este mensaje. Tu contraseña seguirá siendo la misma.';
  $subject=($example?'[Ejemplo] ':'').($verification?'Verifica tu cuenta de Explorando Lugares':'Recupera tu acceso a Explorando Lugares');
  if($reactivation){
   $type='Reactivación de cuenta';$title='Vuelve a Explorando Lugares';
   $intro='Recibimos una solicitud para reactivar tu cuenta cerrada voluntariamente. Confirma que quieres volver y establece una contraseña nueva. Se conservarán tus datos asociados; no se renovarán suscripciones vencidas.';
   $button='Reactivar mi cuenta';$ignore='Si no solicitaste volver, ignora este mensaje. Tu cuenta seguirá cerrada.';
   $subject='Reactiva tu cuenta de Explorando Lugares';
  }
  $notice=$example?'<tr><td style="padding:16px 32px;background:#ffe4ba;color:#172b42;font-size:13px;line-height:20px;text-align:center;"><strong>Ejemplo de diseño.</strong> Este mensaje y su botón no activan una cuenta ni cambian tu contraseña.</td></tr>':'';
  $html='<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$escape($type).'</title></head><body style="margin:0;padding:0;background:#fffaf2;color:#172b42;font-family:Arial,Helvetica,sans-serif;">'
  .'<div style="display:none;max-height:0;overflow:hidden;opacity:0;">'.$escape($type).' de tu cuenta de Explorando Lugares.</div>'
  .'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;background:#fffaf2;"><tr><td align="center" style="padding:28px 12px;"><table role="presentation" width="560" cellspacing="0" cellpadding="0" style="width:100%;max-width:560px;border:1px solid #f2c77e;border-radius:18px;background:#fffdf8;">'
  .'<tr><td align="center" style="padding:28px 24px 20px;background:#fff0dc;border-radius:18px 18px 0 0;"><img src="https://explorandolugares.com/assets/brand/explorando-lugares-logo-v2-transparent.png" width="116" height="116" alt="Explorando Lugares" style="display:block;width:116px;height:116px;border:0;"><p style="margin:16px 0 0;font-size:12px;line-height:18px;letter-spacing:1.5px;text-transform:uppercase;font-weight:bold;color:#a83b22;">'.$escape($type).'</p></td></tr>'.$notice
  .'<tr><td style="padding:30px 32px 12px;"><h1 style="margin:0 0 18px;font-size:28px;line-height:35px;font-weight:bold;color:#172b42;">'.$escape($title).'</h1><p style="margin:0 0 20px;font-size:16px;line-height:25px;">'.$escape($intro).'</p><p style="margin:0;font-size:13px;line-height:22px;color:#5e625f;">Cuenta asociada<br><strong style="color:#172b42;word-break:break-word;">'.$escape($email).'</strong></p></td></tr>'
  .'<tr><td align="center" style="padding:22px 32px 24px;"><table role="presentation" cellspacing="0" cellpadding="0"><tr><td align="center" bgcolor="#c94720" style="border-radius:9px;background:#c94720;"><a href="'.$escape($url).'" style="display:inline-block;padding:16px 26px;border:1px solid #c94720;border-radius:9px;color:#fffdf8;text-decoration:none;font-size:16px;line-height:22px;font-weight:bold;">'.$escape($button).'</a></td></tr></table></td></tr>'
  .'<tr><td style="padding:0 32px 30px;"><p style="margin:0 0 20px;text-align:center;font-size:13px;line-height:21px;color:#5e625f;">El enlace es válido durante <strong>24 horas</strong> y puede utilizarse una sola vez.</p><p style="margin:0;padding:16px;border-top:1px solid #f2c77e;font-size:13px;line-height:21px;color:#5e625f;">'.$escape($ignore).'</p></td></tr>'
  .'<tr><td align="center" style="padding:20px 24px;border-top:1px solid #f2c77e;font-size:12px;line-height:20px;color:#5e625f;">Explorando Lugares<br>Este es un mensaje de tu cuenta, no una comunicación promocional.</td></tr></table></td></tr></table></body></html>';
  $text=($example?"EJEMPLO DE DISEÑO: no modifica tu cuenta.\n\n":'').$type."\n\n".$intro."\nCuenta: ".$email."\n\n".$button.":\n".$url."\n\nEl enlace es válido durante 24 horas y para un solo uso.\n".$ignore;
  return ['subject'=>$subject,'htmlContent'=>$html,'textContent'=>$text];
 }
}

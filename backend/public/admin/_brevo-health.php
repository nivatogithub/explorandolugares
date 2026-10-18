<?php
$brevoApp=dirname((new ReflectionFunction('db'))->getFileName());
require_once $brevoApp.'/BrevoSettings.php';require_once $brevoApp.'/BrevoHealth.php';
try{$brevoDirectory=private_storage_path('brevo');$brevoSettings=BrevoSettings::load($brevoDirectory,(string)$config['app_key']);$brevoHealth=BrevoHealth::load($brevoDirectory,$brevoSettings);}catch(Throwable){$brevoHealth=['status'=>'error','checked_at'=>null,'checks'=>[]];}
$brevoStatus=['ok'=>'Conexión y configuración verificadas','warning'=>'Conexión disponible con observaciones','error'=>'Revisar conexión o configuración','stale'=>'Resultado desactualizado: vuelve a comprobar','unknown'=>'Conexión todavía sin comprobar'][$brevoHealth['status']]??'Sin comprobar';
?>
<section id="salud-brevo" class="admin-system" aria-label="Salud de Brevo"><h2>Salud del correo · Brevo</h2><p role="status"><strong><?=esc($brevoStatus)?></strong></p>
<?php if($brevoHealth['checked_at']):?><p>Última comprobación UTC: <?=esc($brevoHealth['checked_at'])?> · Vigencia del diagnóstico: 15 minutos.</p><?php endif ?>
<?php if(!in_array($brevoHealth['status'],['stale','unknown'],true)):?><ul><?php foreach($brevoHealth['checks'] as $check):?><li><?=!empty($check['ok'])?'✓':'⚠'?> <?=esc((string)$check['label'])?></li><?php endforeach ?></ul><?php endif ?>
<p><a href="campanas.php?tab=conexion">Configurar conexión y remitente →</a></p>
<p>Consulta la API, el servicio de correo transaccional, el remitente y la autenticación del dominio. No envía correos, no consume envíos ni transfiere destinatarios. No comprueba entrega ni llegada a la bandeja de entrada.</p>
<form method="post" action="campanas.php?tab=conexion" class="registration-form"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="action" value="check_connection"><input type="hidden" name="return_to" value="<?=($tab??'')==='sistema'?'system':'connection'?>"><button class="pill" type="submit">Comprobar conexión con Brevo</button></form></section>

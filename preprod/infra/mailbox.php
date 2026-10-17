<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if(getenv('EL_PREPROD_LOCAL')!=='1'||empty($config['local_test'])||!preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/D',$host)){http_response_code(404);exit('No encontrado.');}
if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){http_response_code(403);exit('Inicia sesión en administración local.');}
$_SESSION['last_seen']=time();header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$messages=[];$path='/srv/state/private/mailbox.json';if(is_file($path)){$f=fopen($path,'r');if($f){try{flock($f,LOCK_SH);$messages=json_decode(stream_get_contents($f)?:'[]',true)?:[];flock($f,LOCK_UN);}finally{fclose($f);}}}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Buzón local · Preprod</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/admin.css"></head><body><main class="wrap admin-page"><h1>Buzón de pruebas de preprod</h1><p>Últimos 50 mensajes locales. Los enlaces permiten cambiar contraseñas: no compartirlos. Este buzón no se incluye en producción.</p><p><a href="/admin/index.php?tab=sistema">Volver al sistema</a></p><?php foreach(array_reverse($messages) as $message):?><article class="admin-system"><h2>Recuperación de contraseña</h2><p>Para <?=esc($message['to'])?> · <?=esc($message['created_at'])?></p><a href="<?=esc($message['url'])?>" rel="noreferrer">Abrir enlace de recuperación</a></article><?php endforeach ?><?php if(!$messages):?><p>No hay mensajes todavía.</p><?php endif ?></main></body></html>

<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/advance/Unsubscribe.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit('Método no permitido.');}
$token=$method==='POST'?($_POST['token']??''):($_GET['token']??'');$error='';$done=false;
try{
    if(!is_string($token))throw new InvalidArgumentException('El enlace de baja no es válido.');
    $service=new Unsubscribe(db(),$config);
    if($method==='POST'){
        csrf();
        if(!throttle('unsubscribe',60,600)){http_response_code(429);exit('Inténtalo nuevamente más tarde.');}
        $service->suppress($token);$done=true;
    }else $service->resolve($token);
}catch(InvalidArgumentException $e){http_response_code(400);$error='El enlace de baja no es válido.';}
catch(Throwable $e){http_response_code(503);$error='No se pudo procesar la solicitud. Inténtalo nuevamente más tarde.';}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Preferencias de correo | Explorando Lugares</title><link rel="stylesheet" href="../admin/advance/style.css"></head><body><main><h1>Preferencias de correo</h1><p>Explorando Lugares</p>
<?php if($error):?><p role="alert"><?=esc($error)?></p>
<?php elseif($done):?><p role="status">Registramos tu solicitud. Este correo quedó excluido de futuras campañas.</p><p>Un correo que ya estaba en proceso de envío puede llegar todavía.</p>
<?php else:?><p>Confirma si quieres dejar de recibir correos de campañas de Explorando Lugares.</p><form method="post" action="unsubscribe.php"><input type="hidden" name="token" value="<?=esc($token)?>"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><button>Dejar de recibir correos</button></form><?php endif ?></main></body></html>

<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';require_business_portal_access();
require_once dirname(__DIR__,2).'/app/AccountVerificationDelivery.php';
require_once dirname(__DIR__,2).'/app/AccountVerificationSelector.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$error='';$verified=false;$sent=isset($_GET['enviado']);$continueToBusiness=($_SESSION['business_registration_return']??null)==='/negocios/registro/?tipo=business';
if(isset($_GET['token'])){try{(new AccountEmailVerificationService(db()))->verify((string)$_GET['token']);$verified=true;}catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'verify_email');$error=$e instanceof PDOException?'No se pudo verificar el enlace. Inténtalo más tarde.':$e->getMessage();http_response_code(400);}}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{csrf();if(!throttle('account_verification_ip',5,900))throw new RuntimeException('Demasiadas solicitudes. Espera quince minutos.');$email=strtolower(trim((string)($_POST['email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Escribe un correo válido.');
 if(throttle('account_verification_email',3,900,$email)){$id=(new AccountVerificationSelector(db()))->eligibleAccountId($email);if($id!==null){try{AccountVerificationDelivery::send(db(),$config,$id,true);}catch(Throwable){error_log('No se confirmó el reenvío de activación.');}}}$sent=true;
 }catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'verify_email');$error=$e->getMessage();http_response_code(400);}
}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';
$accountLayout=account_layout_context();
AccountAuthPresentation::render('verify-email',$accountLayout,[
    'error'=>$error,'verified'=>$verified,'sent'=>$sent,'continueToBusiness'=>$continueToBusiness,
    'csrf'=>$accountLayout['csrf'],
],dirname(__DIR__));

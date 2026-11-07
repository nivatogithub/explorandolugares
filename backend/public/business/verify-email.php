<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';require_business_portal_access();
require_once dirname(__DIR__,2).'/app/AccountVerificationDelivery.php';
require_once dirname(__DIR__,2).'/app/AccountVerificationSelector.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$error='';$verified=false;$sent=isset($_GET['enviado']);$continueToBusiness=($_SESSION['business_registration_return']??null)==='/negocios/registro/?tipo=business';
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['token'])){try{(new AccountEmailVerificationService(db()))->verify((string)$_GET['token']);$verified=true;}catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'verify_email');$error=$e instanceof PDOException?'No se pudo verificar el enlace. Inténtalo más tarde.':$e->getMessage();http_response_code(400);}}
$pending=$_SESSION['pending_email_registration']??[];
$pendingId=(int)($pending['expires']??0)>time()?(int)($pending['id']??0):0;
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  csrf();
  if(($_POST['action']??'')==='correct_email'){
   if(!$pendingId)throw new RuntimeException('Abre la confirmación en el navegador donde empezaste el registro.');
   if(!throttle('account_verification_correction',3,900,(string)$pendingId))throw new RuntimeException('Demasiados intentos. Espera quince minutos.');
   AccountVerificationDelivery::correctPendingEmail(db(),$config,$pendingId,(string)($_POST['corrected_email']??''),(string)($_POST['password']??''));
   $_SESSION['verification_resend_after']=time()+AccountEmailVerificationService::RESEND_WAIT_SECONDS;
   $sent=true;
  }else{
   if(!throttle('account_verification_ip',5,900))throw new RuntimeException('Demasiadas solicitudes. Espera quince minutos.');
   $email=strtolower(trim((string)($_POST['email']??'')));
   if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Escribe un correo válido.');
   if(time()>=(int)($_SESSION['verification_resend_after']??0)&&throttle('account_verification_email',3,900,$email)){
    $id=(new AccountVerificationSelector(db()))->eligibleAccountId($email);
    if($id!==null){try{AccountVerificationDelivery::send(db(),$config,$id,true);}catch(Throwable){error_log('No se confirmó el reenvío de activación.');}}
    $_SESSION['verification_resend_after']=time()+AccountEmailVerificationService::RESEND_WAIT_SECONDS;
   }
   $sent=true;
  }
 }catch(Throwable $e){PortalIncidents::technical($e,'verify_email');$error=$e instanceof PDOException?'No se pudo completar la solicitud. Inténtalo más tarde.':$e->getMessage();http_response_code(400);}
}
$pendingEmail=null;
if($pendingId&&!$verified){
 $q=db()->prepare('SELECT email FROM accounts WHERE id=? AND status="pending_activation" AND email_verified_at IS NULL');
 $q->execute([$pendingId]);
 $pendingEmail=$q->fetchColumn()?:null;
}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';
require_once dirname(__DIR__,2).'/app/SavedPlaceAction.php';
$accountLayout=account_layout_context();
AccountAuthPresentation::render('verify-email',$accountLayout,[
    'error'=>$error,'verified'=>$verified,'sent'=>$sent,'continueToBusiness'=>$continueToBusiness,
    'savedPlacesBenefit'=>SavedPlaceAction::accessNoticeRequired($_GET)||($verified&&!empty($_SESSION['saved_place_intent'])&&!$continueToBusiness),
    'csrf'=>$accountLayout['csrf'],
    'pendingEmail'=>$pendingEmail,
    'resendWait'=>max(0,(int)($_SESSION['verification_resend_after']??0)-time()),
],dirname(__DIR__));

<?php if(!(isset($service,$registrationType))){http_response_code(404);exit;} ?>
<?php
$traveler=true;$error='';
if($currentAccount){header('Location: /negocios/portal/',true,303);exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{csrf();require_once dirname(__DIR__,2).'/app/AccountReactivationDelivery.php';
 if(!$currentAccount&&AccountReactivationDelivery::existingRegistration(db(),$config,(string)($_POST['email']??''))){header('Location: /negocios/recuperar-clave/?solicitado=1',true,303);exit;}
 require_once dirname(__DIR__,2).'/app/AccountResidence.php';$residence=AccountResidence::capture($_POST);if(!$traveler)throw new RuntimeException('Selecciona un tipo de cuenta.');$id=$service->registerTraveler((string)($_POST['display_name']??''),(string)($_POST['email']??''),(string)($_POST['password']??''),(string)($_POST['password_confirm']??''),($_POST['consent']??'')==='1',$residence);require_once dirname(__DIR__,2).'/app/AccountVerificationDelivery.php';AccountVerificationDelivery::send(db(),$config,$id);header('Location: /cuenta/verificar-correo/?enviado=1',true,303);exit;}
 catch(Throwable $e){$incident=PortalIncidents::technical($e,'traveler_registration');$error=$e instanceof PDOException?'No se pudo crear la cuenta. Si ya tienes una con este correo, inicia sesión.':$e->getMessage();if($incident)$error='No pudimos completar la operación. Referencia: '.$incident;http_response_code($incident?503:400);}
}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';
require_once dirname(__DIR__,2).'/app/AccountResidence.php';
require_once dirname(__DIR__,2).'/app/AccountResidencePresentation.php';
$accountLayout=account_layout_context();
AccountAuthPresentation::render('personal-registration',$accountLayout,[
    'error'=>$error,'csrf'=>$accountLayout['csrf'],
    'displayName'=>(string)($_POST['display_name']??''),'email'=>(string)($_POST['email']??''),
    'residence'=>AccountResidencePresentation::context(AccountResidence::catalog(),$_POST),
],dirname(__DIR__));

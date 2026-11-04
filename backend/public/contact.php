<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/PublicRequestService.php';$error='';$reference=$_SESSION['public_request_receipt']??null;unset($_SESSION['public_request_receipt']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{csrf();if(!empty($_POST['website']))throw new PublicRequestException('validation_failed','No se pudo validar la solicitud.');if(!throttle('public_request',5,3600))throw new PublicRequestException('rate_limited','Has enviado varias solicitudes; espera antes de enviar otra.');$reference=(new PublicRequestService(db()))->submit($_POST);$_SESSION['public_request_receipt']=$reference;$locale=(string)($_GET['lang']??$_SESSION['account_locale']??'es');if(!in_array($locale,['es','en','pt-br'],true))$locale='es';header('Location: /contact.php?recibido=1'.($locale==='es'?'':'&lang='.rawurlencode($locale)),true,303);exit;}
 catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'contacto');$error=$e;http_response_code(400);}
}
// Preserve the legacy account-header session/account checks while presenting directly.
$context=account_layout_context();
if($error instanceof Throwable){require_once __DIR__.'/../app/ContactErrorMessage.php';$error=ContactErrorMessage::errorMessage($error,(string)$context['locale']);}
if(isset($_SESSION['business_account_id'])&&time()-(int)($_SESSION['business_last_seen']??0)>1800)unset($_SESSION['business_account_id']);
require_once __DIR__.'/../app/BusinessAccountPortalService.php';
$headerAccount=empty($_SESSION['business_account_id'])?null:(new BusinessAccountPortalService(db()))->current((int)$_SESSION['business_account_id']);
if($headerAccount)AccountAuthorization::isAdmin(db(),(int)$headerAccount['id']);
require_once __DIR__.'/_portal-shell.php';require_once __DIR__.'/../app/ContactPresentation.php';
ContactPresentation::render($context,['reference'=>$reference,'error'=>$error,'csrf'=>(string)($_SESSION['csrf']??''),'input'=>$_POST,'types'=>PublicRequestService::TYPES],__DIR__);

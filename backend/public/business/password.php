<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/AccountPasswordResetService.php';
$reactivation=($_POST['reactivacion']??$_GET['reactivacion']??'')==='1';
require_once dirname(__DIR__,2).'/app/AccountReactivationService.php';
$service=$reactivation?new AccountReactivationService(db()):new AccountPasswordResetService(db());
$token=is_string($_POST['token']??$_GET['token']??null)?(string)($_POST['token']??$_GET['token']):'';
$error='';$saved=false;$valid=$service->isValid($token);
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf();
        if(!throttle('business_account_password_reset',8,900))throw new RuntimeException('Demasiados intentos. Inténtalo más tarde.');
        if($reactivation&&($_POST['confirm_reactivation']??'')!=='1')throw new RuntimeException('Confirma que quieres reactivar tu cuenta.');
        $service->reset($token,(string)($_POST['password']??''),(string)($_POST['password_confirm']??''));
        $saved=true;$valid=false;
    }catch(Throwable $exception){if(class_exists('PortalIncidents',false))PortalIncidents::technical($exception,'password');$error=$exception instanceof PDOException?'No fue posible actualizar la contraseña. Inténtalo más tarde.':$exception->getMessage();http_response_code(400);$valid=$service->isValid($token);}
}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';
$viewData=['csrf'=>(string)$_SESSION['csrf'],'reactivation'=>$reactivation,'token'=>$token,'error'=>$error,'saved'=>$saved,'valid'=>$valid];
AccountAuthPresentation::render('password',account_layout_context(),$viewData,dirname(__DIR__));

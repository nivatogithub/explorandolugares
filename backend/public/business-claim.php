<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
require_business_portal_access();
require __DIR__.'/../app/BusinessClaimService.php';
require __DIR__.'/../app/BusinessAccountPortalService.php';
require __DIR__.'/../app/BusinessClaimPresentation.php';
$service=new BusinessClaimService(db());$error='';$success=false;
$account=empty($_SESSION['business_account_id'])?null:(new BusinessAccountPortalService(db()))->current((int)$_SESSION['business_account_id']);
$claimContext=null;
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if(!ready())throw new BusinessClaimException('unavailable','La recepción de solicitudes no está habilitada en este momento.');
        $service->submit($_POST);$success=true;
    }
}catch(Throwable $exception){if(class_exists('PortalIncidents',false))PortalIncidents::technical($exception,'reclamar_negocio');$claimContext=account_layout_context();$error=BusinessClaimPresentation::errorMessage($exception,$claimContext['locale']);http_response_code(400);}
$claimContext??=account_layout_context();$claimLocale=$claimContext['locale'];
$destinations=$service->places($claimLocale);$destinationId=max(0,(int)($_GET['destino']??$_POST['destination_id']??0));
$destinationIds=array_map(static fn($row)=>(int)$row['id'],$destinations);if(!in_array($destinationId,$destinationIds,true))$destinationId=0;
$categories=$destinationId?$service->categories($destinationId,$claimLocale):[];$category=(string)($_GET['categoria']??$_POST['category_code']??'');
$categoryCodes=array_column($categories,'code');if(!in_array($category,$categoryCodes,true))$category='';
$name=trim((string)($_GET['nombre']??$_POST['nombre']??''));$results=$destinationId&&$category?$service->search($destinationId,$category,$name,$claimLocale):[];
if(($_GET['search']??'')==='1'){
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    echo json_encode(['results'=>$results],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);exit;
}
require_once __DIR__.'/_portal-shell.php';
$phone=(string)($_POST['phone']??'');$digits=preg_replace('/\D/','',$phone);
if(strlen($digits)===12&&str_starts_with($digits,'57'))$digits=substr($digits,2);
BusinessClaimPresentation::render($claimContext,[
    'places'=>$destinations,'placeId'=>$destinationId,'categories'=>$categories,'category'=>$category,
    'name'=>$name,'results'=>$results,'account'=>$account,'error'=>$error,'success'=>$success,
    'registerUrl'=>site_path('/negocios/registro/').($claimLocale==='es'?'':'?lang='.rawurlencode($claimLocale)),
    'loginUrl'=>site_path('/negocios/acceso/').($claimLocale==='es'?'':'?lang='.rawurlencode($claimLocale)),
    'csrf'=>(string)($_SESSION['csrf']??''),'phoneNumber'=>(string)($_POST['phone_number']??$digits),
],__DIR__);

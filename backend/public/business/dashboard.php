<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();require_business_session();
require_once dirname(__DIR__,2).'/app/BusinessWorkspace.php';
require_once dirname(__DIR__,2).'/app/BusinessQrCode.php';
require_once dirname(__DIR__,2).'/app/BusinessPaymentEvidenceService.php';
$portal=new BusinessAccountPortalService(db());
$account=$portal->current((int)$_SESSION['business_account_id']);
if(!$account){unset($_SESSION['business_account_id']);require_business_session();}
$workspace=new BusinessWorkspace(db());$rows=$workspace->rows((int)$account['id']);$portalHasBusinesses=(bool)$rows;
$isAdministrator=AccountAuthorization::isAdmin(db(),(int)$account['id']);
$requestedTab=(string)($_GET['tab']??(($_GET['espacio']??'')==='business'||$_SERVER['REQUEST_METHOD']==='POST'?'business':'personal'));
if(!in_array($requestedTab,['personal','business'],true)){http_response_code(400);exit('Pestaña no válida.');}
$_SESSION['account_space']=$requestedTab==='business'?'business':'explore';
if($requestedTab==='personal'){require __DIR__.'/traveler.php';exit;}
header('Cache-Control: private, no-store');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        csrf();$action=(string)($_POST['action']??'');if(!in_array($action,['owner_publish','owner_changes'],true))throw new RuntimeException('Esta acción no está disponible. Vuelve a elegir el negocio.');
        $business=(int)($_POST['business_id']??0);$editorial=new BusinessEditorialService(db(),private_storage_path('business-image-references'));
        $editorial->authorize((int)$account['id'],$business);$version=$editorial->latest($business);
        if(!$version||(int)$version['id']!==(int)($_POST['version_id']??0))throw new RuntimeException('La propuesta cambió. Recarga y revisa la nueva versión.');
        $hash=(string)($_POST['payload_hash']??'');$note=trim((string)($_POST['note']??''));
        if($action==='owner_publish'&&$version['status']==='published'&&hash_equals($version['payload_hash'],$hash)){header('Location: /negocios/portal/?tab=business&resultado=publicado',true,303);exit;}
        if($action==='owner_changes'&&mb_strlen($note)<8)throw new RuntimeException('Explica qué deseas corregir, con al menos 8 caracteres.');
        $editorial->transition((int)$version['id'],$action==='owner_publish'?'approve':'request_changes',(int)$account['id'],'',$note,$hash);
        if($action==='owner_publish')$editorial->publishByOwner((int)$version['id'],(int)$account['id'],$hash);
        header('Location: /negocios/portal/?tab=business&resultado='.($action==='owner_publish'?'publicado':'correcciones'),true,303);exit;
    }catch(Throwable $e){$reference=PortalIncidents::technical($e,'business_workspace');$error=$reference?'No se pudo completar. Referencia: '.$reference:$e->getMessage();http_response_code($reference?503:400);}
}
if(($_GET['solicitud']??'')==='guardada'&&(int)($_GET['business']??0)>0){
    foreach($rows as $row)if($row['kind']==='business'&&(int)$row['id']===(int)$_GET['business']&&$row['state']['pay']){
        header('Location: /business/payment.php?business='.(int)$row['id'],true,303);exit;
    }
}
$payments=(new BusinessPaymentEvidenceService(db(),private_storage_path('business-payment-evidence')))->history((int)$account['id']);
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/BusinessWorkspacePresentation.php';
$accountLayout=account_layout_context();
$viewRows=[];
foreach($rows as $row){
    $row['qr']=$row['kind']==='business'?BusinessQrCode::publishedPage(db(),$row['public_id']):null;
    $viewRows[]=$row;
}
BusinessWorkspacePresentation::render($accountLayout,[
    'displayName'=>(string)$account['display_name'],'isAdministrator'=>$isAdministrator,
    'error'=>$error,'rows'=>$viewRows,'payments'=>$payments,'csrf'=>$accountLayout['csrf'],
    'couponApplied'=>($_GET['cupon']??'')==='aplicado',
    'result'=>(string)($_GET['resultado']??''),'paymentSent'=>($_GET['pago']??'')==='enviado',
    'registrationSaved'=>($_GET['solicitud']??'')==='guardada',
    'planNames'=>BusinessWorkspace::PLANS,'periodNames'=>BusinessWorkspace::PERIODS,
    'reviewNoteLimit'=>BusinessEditorialService::REVIEW_NOTE_LIMIT,
],dirname(__DIR__));

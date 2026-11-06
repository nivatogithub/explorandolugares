<?php
if(!isset($account)){http_response_code(404);exit;}
header('Cache-Control: private, no-store');
require_once dirname(__DIR__,2).'/app/AccountResidence.php';
require_once dirname(__DIR__,2).'/app/SavedPlaces.php';
$savedPlaces=new SavedPlaces(db());
$saveSlug=is_string($_GET['guardar']??null)?$_GET['guardar']:'';
$saveCandidate=$saveSlug!==''?$savedPlaces->find($saveSlug):null;
$residenceError='';$residenceSaved=false;$residenceValues=AccountResidence::get(db(),(int)$account['id']);
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{csrf();if(in_array($_POST['action']??'',['save_place','remove_place'],true)){
 $savedPlaces->change((int)$account['id'],(string)($_POST['place_slug']??''),$_POST['action']==='save_place');
 header('Location: /lugares/?guardados=1',true,303);exit;
 }if(($_POST['action']??'')==='close_account'){
 require_once dirname(__DIR__,2).'/app/AccountClosureService.php';
 if(!throttle('account_close',5,900,(string)$account['id']))throw new RuntimeException('Demasiados intentos. Inténtalo más tarde.');
 (new AccountClosureService(db()))->close((int)$account['id'],(string)($_POST['current_password']??''),($_POST['confirm_closure']??'')==='1');
 require_once dirname(__DIR__,2).'/app/AccountSessionSecurity.php';AccountSessionSecurity::clear($_SESSION);session_regenerate_id(true);
 header('Location: /negocios/acceso/?cuenta=cerrada',true,303);exit;
 }if(($_POST['action']??'')!=='save_residence')throw new RuntimeException('Acción no válida.');$values=AccountResidence::capture($_POST);AccountResidence::save(db(),(int)$account['id'],$values);header('Location: /negocios/portal/?tab=personal&ubicacion=guardada',true,303);exit;}
 catch(Throwable $e){PortalIncidents::technical($e,'account_profile');http_response_code(400);$residenceError=$e instanceof PDOException?'No se pudo guardar tu residencia.':$e->getMessage();}
}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/AccountProfilePresentation.php';
$accountLayout=account_layout_context();
AccountProfilePresentation::render($accountLayout,[
    'displayName'=>(string)$account['display_name'],'email'=>(string)$account['email'],
    'hasBusinesses'=>$portalHasBusinesses,'isAdministrator'=>!empty($isAdministrator),
    'residenceError'=>$residenceError,'residenceSaved'=>($_GET['ubicacion']??'')==='guardada',
    'placesUpdated'=>($_GET['lugares']??'')==='actualizados',
    'savedPlaces'=>$savedPlaces->list((int)$account['id']),'saveCandidate'=>$saveCandidate,
    'csrf'=>$accountLayout['csrf'],
    'residence'=>AccountResidencePresentation::context(AccountResidence::catalog(),$_POST,$residenceValues),
],dirname(__DIR__));

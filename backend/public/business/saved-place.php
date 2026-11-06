<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();
require_once dirname(__DIR__,2).'/app/SavedPlaceIntent.php';
$slug=$_POST['place']??$_GET['place']??'';$locale=$_POST['locale']??$_GET['locale']??'es';
if(!is_string($slug)||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)){http_response_code(404);exit('Lugar no encontrado.');}
if(!is_string($locale)||!in_array($locale,['es','en','pt-BR'],true))$locale='es';
$places=new SavedPlaces(db());
if(!$places->find($slug)){http_response_code(404);exit('Lugar no encontrado.');}
$path=SavedPlaceAction::guidePath($slug,$locale);
if($_SERVER['REQUEST_METHOD']==='GET'){
 if(empty($_SESSION['business_account_id'])||time()-(int)($_SESSION['business_last_seen']??0)>1800){
  SavedPlaceIntent::remember($_SESSION,$slug,$locale);
  $_SESSION['session_return']=$path;
  header('Location: /negocios/acceso/?beneficio=lugares&lang='.strtolower($locale),true,303);exit;
 }
 header('Location: '.$path,true,303);exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: GET, POST');exit;}
$expectsJson=str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json');
require_business_session($expectsJson,$path,'saved_places');csrf($path);
$action=$_POST['action']??'';
if(!in_array($action,['save_place','remove_place'],true)){if($expectsJson)json_response(['ok'=>false,'error'=>'invalid_action'],400);http_response_code(400);exit('Acción no válida.');}
try{$places->change((int)$_SESSION['business_account_id'],$slug,$action==='save_place');}
catch(Throwable $error){PortalIncidents::technical($error,'saved_place');if($expectsJson)json_response(['ok'=>false,'error'=>'save_failed'],503);header('Location: '.$path.'?guardado=error',true,303);exit;}
if($expectsJson){
 $saved=(new SavedPlaceSelector(db()))->contains((int)$_SESSION['business_account_id'],$slug);
 $state=SavedPlaceAction::context($slug,$locale,true,$saved);
 json_response(['ok'=>true,'saved'=>$saved,'label'=>$state['label']]);
}
header('Location: '.$path,true,303);exit;

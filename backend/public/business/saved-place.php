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
require_business_session(false,$path);csrf($path);
$action=$_POST['action']??'';
if(!in_array($action,['save_place','remove_place'],true)){http_response_code(400);exit('Acción no válida.');}
try{$places->change((int)$_SESSION['business_account_id'],$slug,$action==='save_place');}
catch(Throwable $error){PortalIncidents::technical($error,'saved_place');header('Location: '.$path.'?guardado=error',true,303);exit;}
header('Location: '.$path,true,303);exit;

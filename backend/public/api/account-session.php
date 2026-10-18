<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
header('Cache-Control: private, no-store, max-age=0');header('Vary: Cookie');
if(!business_portal_available())json_response(['enabled'=>false,'authenticated'=>false]);
require_once dirname((new ReflectionFunction('db'))->getFileName()).'/BusinessAccountPortalService.php';
require_once dirname((new ReflectionFunction('db'))->getFileName()).'/AccountWorkspace.php';
if(isset($_SESSION['business_account_id'])&&time()-(int)($_SESSION['business_last_seen']??0)>1800)unset($_SESSION['business_account_id']);
$account=empty($_SESSION['business_account_id'])?null:(new BusinessAccountPortalService(db()))->current((int)$_SESSION['business_account_id']);
if(!$account){unset($_SESSION['business_account_id'],$_SESSION['business_last_seen'],$_SESSION['account_space']);json_response(['enabled'=>true,'authenticated'=>false]);}
$_SESSION['business_last_seen']=time();$destination=null;
if($_SERVER['REQUEST_METHOD']==='POST'){csrf();try{$destination=AccountWorkspace::choose($_SESSION,(string)($_POST['space']??''));}catch(RuntimeException $e){json_response(['message'=>$e->getMessage()],400);}}
elseif($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
json_response(['enabled'=>true,'authenticated'=>true,'name'=>$account['display_name'],'csrf'=>$_SESSION['csrf'],'administrator'=>AccountAuthorization::isAdmin(db(),(int)$account['id']),'space'=>$_SESSION['account_space']??null,'destination'=>$destination]);

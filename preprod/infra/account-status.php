<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if(getenv('EL_PREPROD_LOCAL')!=='1'||empty($config['local_test'])||!preg_match('/^(127\.0\.0\.1|localhost|\[::1\])(?::[0-9]+)?$/D',$host)){http_response_code(404);exit('No encontrado.');}
header('Cache-Control: private, no-store, max-age=0');header('Vary: Cookie');
require_once dirname((new ReflectionFunction('db'))->getFileName()).'/BusinessAccountPortalService.php';
if(isset($_SESSION['business_account_id'])&&time()-(int)($_SESSION['business_last_seen']??0)>1800)unset($_SESSION['business_account_id']);
$account=empty($_SESSION['business_account_id'])?null:(new BusinessAccountPortalService(db()))->current((int)$_SESSION['business_account_id']);
if(!$account){unset($_SESSION['business_account_id'],$_SESSION['business_last_seen']);json_response(['authenticated'=>false]);}
$_SESSION['business_last_seen']=time();
json_response(['authenticated'=>true,'name'=>$account['display_name'],'csrf'=>$_SESSION['csrf'],'administrator'=>AccountAuthorization::isAdmin(db(),(int)$account['id'])]);

<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';require_once dirname(__DIR__,2).'/app/PaymentInstructions.php';
$account=empty($_SESSION['business_account_id'])?null:(new BusinessAccountPortalService(db()))->current((int)$_SESSION['business_account_id']);
$validSession=$account&&time()-(int)($_SESSION['business_last_seen']??0)<=1800;$validAdmin=!empty($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)<=1800;
if(!$validSession&&!$validAdmin){http_response_code(403);exit;}
$directory=private_storage_path('payment-instructions');$settings=PaymentInstructions::load(db(),$directory,!empty($config['local_test']));$file=$settings['qr_file']??'';
if((empty($settings['enabled'])&&!$validAdmin)||!preg_match('/^[a-f0-9]{48}\.png$/D',$file)||is_link($directory.'/'.$file)||!is_file($directory.'/'.$file)){http_response_code(404);exit;}
header('Content-Type: image/png');header('Content-Disposition: inline; filename="qr-pago.png"');header('Content-Length: '.filesize($directory.'/'.$file));readfile($directory.'/'.$file);

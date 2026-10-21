<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialService.php';
header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');
try{
 $q=db()->prepare('SELECT i.*,v.business_id FROM business_editorial_images i JOIN business_editorial_versions v ON v.id=i.version_id WHERE i.id=?');$q->execute([(int)($_GET['id']??0)]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException();
 $admin=!empty($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)<=1800;
 if(!$admin){if(empty($_SESSION['business_account_id'])||time()-(int)($_SESSION['business_last_seen']??0)>1800)throw new RuntimeException();(new BusinessEditorialService(db(),private_storage_path('business-image-references')))->authorize((int)$_SESSION['business_account_id'],(int)$row['business_id']);}
 if(!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D',$row['storage_name'])||!in_array($row['mime_type'],['image/jpeg','image/png','image/webp'],true))throw new RuntimeException();$file=private_storage_path('business-image-references').'/'.$row['storage_name'];if(!is_file($file)||is_link($file))throw new RuntimeException();header('Content-Type: '.$row['mime_type']);header('Content-Disposition: inline; filename="referencia.'.pathinfo($file,PATHINFO_EXTENSION).'"');readfile($file);
}catch(Throwable $e){http_response_code(404);echo 'Imagen no disponible.';}

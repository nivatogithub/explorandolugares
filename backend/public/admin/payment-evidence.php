<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if(!$id){http_response_code(404);exit('Comprobante no encontrado.');}
$query=db()->prepare('SELECT storage_name,mime_type,file_size FROM business_subscription_payment_evidence WHERE id=?');$query->execute([$id]);$row=$query->fetch(PDO::FETCH_ASSOC);
$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if(!$row||!isset($extensions[$row['mime_type']])||!preg_match('/^[a-f0-9]{48}\.'.preg_quote($extensions[$row['mime_type']],'/').'$/D',(string)$row['storage_name'])){http_response_code(404);exit('Comprobante no encontrado.');}
$path=private_storage_path('business-payment-evidence').'/'.$row['storage_name'];if(!is_file($path)||(int)filesize($path)!==(int)$row['file_size']){http_response_code(404);exit('Comprobante no disponible.');}
header('Content-Type: '.$row['mime_type']);header('Content-Length: '.(string)$row['file_size']);header('Content-Disposition: inline; filename="soporte-pago-'.$id.'.'.$extensions[$row['mime_type']].'"');header('Cache-Control: private, no-store, max-age=0');readfile($path);

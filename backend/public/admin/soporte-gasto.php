<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_admin_session();
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if(!$id){http_response_code(404);exit('Soporte no encontrado.');}
$q=db()->prepare('SELECT storage_name,mime_type,file_size,sha256 FROM operating_cost_evidence WHERE id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
$types=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if(!$row||!isset($types[$row['mime_type']])||!preg_match('/^[a-f0-9]{48}\.'.preg_quote($types[$row['mime_type']],'/').'$/D',$row['storage_name'])){http_response_code(404);exit('Soporte no encontrado.');}
$path=private_storage_path('operating-cost-evidence').'/'.$row['storage_name'];
if(!is_file($path)||(int)filesize($path)!==(int)$row['file_size']||!hash_equals($row['sha256'],hash_file('sha256',$path))){http_response_code(404);exit('Soporte no disponible o integridad no confirmada.');}
header('Content-Type: '.$row['mime_type']);header('Content-Length: '.(string)$row['file_size']);header('Content-Disposition: attachment; filename="soporte-gasto-'.$id.'.'.$types[$row['mime_type']].'"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');readfile($path);

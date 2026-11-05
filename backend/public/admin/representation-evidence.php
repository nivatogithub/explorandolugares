<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/AdminEvidenceSelector.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: sandbox");
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el panel de administración.');}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if(!$id){http_response_code(404);exit('Documento no encontrado.');}
$selector=new AdminEvidenceSelector(db());$row=$selector->representation((int)$id);
if($selector->representationRetired((int)$id)){http_response_code(410);exit('Documento eliminado tras validar la titularidad. Se conserva la constancia de revisión.');}
$extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if(!$row||!isset($extensions[$row['mime_type']])||!preg_match('/^[a-f0-9]{48}\.'.preg_quote($extensions[$row['mime_type']],'/').'$/D',(string)$row['storage_name'])){http_response_code(404);exit('Documento no encontrado.');}
$path=private_storage_path('business-representation-evidence').'/'.$row['storage_name'];if(!is_file($path)||(int)filesize($path)!==(int)$row['file_size']){http_response_code(404);exit('Documento no disponible.');}
header('Content-Type: '.$row['mime_type']);header('Content-Length: '.(string)$row['file_size']);header('Content-Disposition: inline; filename="soporte-representacion-'.$id.'.'.$extensions[$row['mime_type']].'"');header('Cache-Control: private, no-store, max-age=0');readfile($path);

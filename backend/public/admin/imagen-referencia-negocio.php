<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
if (empty($_SESSION['admin'])) { http_response_code(403); exit('Inicia sesión desde el panel de administración.'); }
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Imagen no encontrada.'); }
$table=($_GET['source']??'')==='application'?'business_application_images':'business_image_references';
$q=db()->prepare('SELECT storage_name,mime_type,file_size FROM '.$table.' WHERE id=?');
$q->execute([$id]);$image=$q->fetch(PDO::FETCH_ASSOC);
if (!$image||!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/D',(string)$image['storage_name'])) { http_response_code(404); exit('Imagen no encontrada.'); }
$path=private_storage_path('business-image-references').'/'.$image['storage_name'];
$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
if (!isset($allowed[$image['mime_type']])||!str_ends_with($image['storage_name'],'.'.$allowed[$image['mime_type']])||!is_file($path)||(int)filesize($path)!==(int)$image['file_size']) { http_response_code(404); exit('Imagen no disponible.'); }
header('Content-Type: '.$image['mime_type']);header('Content-Length: '.(string)$image['file_size']);header('Content-Disposition: inline; filename="referencia-'.$id.'.'.$allowed[$image['mime_type']].'"');header('Cache-Control: private, no-store, max-age=0');
readfile($path);

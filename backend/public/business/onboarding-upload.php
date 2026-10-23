<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessOnboardingUploadService.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{csrf();$draft=(string)($_SESSION['business_onboarding_draft']??'');$service=new BusinessOnboardingUploadService(db(),private_storage_path('business-onboarding-uploads'));if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Método no permitido.');$action=(string)($_POST['action']??'upload');if($action==='delete'){$service->delete($draft,(string)($_POST['upload_id']??''));echo json_encode(['ok'=>true]);exit;}$result=$service->store($draft,(string)($_POST['kind']??''),(string)($_POST['slot']??''),$_FILES['file']??[]);echo json_encode(['ok'=>true,'upload'=>$result],JSON_UNESCAPED_UNICODE);}catch(Throwable $error){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);}

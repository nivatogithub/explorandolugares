<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessOnboardingUploadService.php';
require_business_portal_access();require_business_session();
if(!empty($_GET['draft'])){require_once dirname(__DIR__,2).'/app/BusinessRegistrationDrafts.php';try{(new BusinessRegistrationDrafts(db()))->load((int)$_SESSION['business_account_id'],(string)$_GET['draft']);$_SESSION['business_onboarding_draft']=(string)$_GET['draft'];}catch(RuntimeException){http_response_code(404);exit;}}
try{$row=(new BusinessOnboardingUploadService(db(),private_storage_path('business-onboarding-uploads')))->preview((string)($_SESSION['business_onboarding_draft']??''),(string)($_GET['id']??''));header('Content-Type: '.$row['mime_type']);header('Content-Length: '.(string)$row['file_size']);header('Cache-Control: private, no-store, max-age=0');header('X-Content-Type-Options: nosniff');readfile($row['path']);}catch(Throwable){http_response_code(404);echo 'Archivo no disponible.';}

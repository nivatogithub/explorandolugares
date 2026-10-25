<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessOnboardingUploadService.php';
$resume='/negocios/registro/?tipo=business';
if(preg_match('/^[a-f0-9]{48}$/D',(string)($_POST['registration_draft']??'')))$resume.='&draft='.$_POST['registration_draft'];
require_business_portal_access();require_business_session(true,$resume);
if(!empty($_POST['registration_draft'])){require_once dirname(__DIR__,2).'/app/BusinessRegistrationDrafts.php';try{(new BusinessRegistrationDrafts(db()))->load((int)$_SESSION['business_account_id'],(string)$_POST['registration_draft']);$_SESSION['business_onboarding_draft']=(string)$_POST['registration_draft'];}catch(RuntimeException){json_response(['ok'=>false,'error'=>'Este borrador no está disponible.'],403);}}
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
    csrf($resume);
    $draft=(string)($_SESSION['business_onboarding_draft']??'');
    $service=new BusinessOnboardingUploadService(db(),private_storage_path('business-onboarding-uploads'));
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Método no permitido.');
    if(($_POST['action']??'upload')==='delete'){
        $service->delete($draft,(string)($_POST['upload_id']??''));
        echo json_encode(['ok'=>true]);exit;
    }
    $result=$service->store($draft,(string)($_POST['kind']??''),(string)($_POST['slot']??''),$_FILES['file']??[]);
    echo json_encode(['ok'=>true,'upload'=>$result],JSON_UNESCAPED_UNICODE);
}catch(Throwable $error){
    http_response_code(400);echo json_encode(['ok'=>false,'error'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);
}

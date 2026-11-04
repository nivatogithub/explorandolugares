<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';require_admin_session();require dirname(__DIR__,2).'/app/PublicRequestService.php';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){try{csrf();(new PublicRequestService(db()))->review((int)($_POST['id']??0),(string)($_POST['status']??''),trim((string)($_POST['response_note']??'')));header('Location: public-requests.php',true,303);exit;}catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'solicitudes_publicas');$error=$e instanceof PDOException?'No se pudo guardar la revisión.':$e->getMessage();http_response_code(400);}}
require_once dirname(__DIR__,2).'/app/PublicRequestSelector.php';
$requests=(new PublicRequestSelector(db()))->recent();
$adminLayout=admin_layout_context();
$csrfToken=(string)($_SESSION['csrf']??'');$adminPublicRoot=__DIR__;
$identityCss='/assets/portal-identity.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/portal-identity.css'),0,12);
$adminCss='../assets/admin.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/admin.css'),0,12);
$registrationCss='../assets/account-registration.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/account-registration.css'),0,12);
require dirname(__DIR__,2).'/app/views/admin/public-requests.php';

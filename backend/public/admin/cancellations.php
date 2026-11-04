<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_admin_session();
$appRoot=dirname((new ReflectionFunction('db'))->getFileName());require_once $appRoot.'/OperatingFinanceService.php';
$finance=new OperatingFinanceService(db());$available=$finance->ready();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){try{csrf();if(!$available)throw new RuntimeException('Falta aplicar la migración 031 en Base de datos.');if(($_POST['action']??'')!=='review')throw new RuntimeException('Acción no permitida.');$finance->reviewCancellation((int)($_POST['id']??0),(string)($_POST['note']??''));header('Location: cancellations.php?guardado=1',true,303);exit;}catch(Throwable $e){PortalIncidents::technical($e,'cancelaciones');$error=$e instanceof PDOException?'No se pudo guardar. Revisa la estructura de Base de datos.':$e->getMessage();http_response_code(400);}}
require_once $appRoot.'/CancellationRequestSelector.php';
$requests=$available?(new CancellationRequestSelector(db()))->recent():[];
$adminLayout=admin_layout_context();
$csrfToken=(string)($_SESSION['csrf']??'');$saved=isset($_GET['guardado']);$adminPublicRoot=__DIR__;
$identityCss='/assets/portal-identity.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/portal-identity.css'),0,12);
require $appRoot.'/views/admin/cancellations.php';

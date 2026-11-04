<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/LeadAdminService.php';
require_once dirname(__DIR__,2).'/app/LeadInboxSelector.php';
require_once dirname(__DIR__,2).'/app/LeadInboxWriter.php';
if (strlen($config['app_key']??'')<32 || (empty($config['unified_admin_only']) && (empty($config['admin_user']) || empty($config['admin_password_hash'])))) {http_response_code(503);exit('Administración pendiente de configuración.');}
$error='';
if(!empty($config['unified_admin_only'])&&empty($_SESSION['admin'])){header('Location: /negocios/acceso/',true,303);exit;}
try {
    if (isset($_SESSION['admin']) && time()-($_SESSION['last_seen']??0)>1800) unset($_SESSION['admin']);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        csrf();$action=$_POST['action']??'';
        if ($action==='login') {
            if (!empty($config['unified_admin_only'])) { http_response_code(403);exit('Inicia sesión desde el portal.'); }
            if (!throttle('login',10,900)) {$error='Demasiados intentos. Intenta nuevamente más tarde.';http_response_code(429);}
            else {
                $user=is_string($_POST['username']??null)?$_POST['username']:'';
                $password=is_string($_POST['password']??null)?$_POST['password']:'';
                $valid=password_verify($password,$config['admin_password_hash']);
                if (hash_equals($config['admin_user'],$user) && $valid) {
                    session_regenerate_id(true);$_SESSION['admin']=true;$_SESSION['last_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
                    header('Location: index.php',true,303);exit;
                }
                $error='Usuario o contraseña incorrectos.';
            }
        } elseif (!empty($_SESSION['admin']) && $action==='logout') {
            $_SESSION=[];session_destroy();header('Location: index.php',true,303);exit;
        } elseif (!empty($_SESSION['admin']) && $action==='status') {
            $status=$_POST['status']??'';$id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);
            if ($id && $id>0 && in_array($status,['nuevo','revisado','contactado','archivado'],true)) {
                $result=(new LeadAdminService(db()))->changeStatus((int)$id,$status);
                $_SESSION['lead_status_notice']=match($result){'saved'=>'Estado guardado.','unchanged'=>'Sin cambios: la solicitud ya tenía ese estado.',default=>'No se guardó: la solicitud ya no existe.'};
                $returnStatus=is_string($_POST['return_status']??null)?$_POST['return_status']:'';$returnQuery=is_string($_POST['return_q']??null)?trim($_POST['return_q']):'';$returnPage=max(1,(int)($_POST['return_page']??1));
                if($result==='saved' && $returnStatus!=='' && $returnStatus!==$status) $_SESSION['lead_status_notice'].=' La solicitud dejó de aparecer porque ya no coincide con el filtro de estado.';
                header('Location: index.php?'.http_build_query(['status'=>$returnStatus,'q'=>$returnQuery,'page'=>$returnPage]),true,303);exit;
            }
            $error='Estado o registro inválido.';
        } elseif (!empty($_SESSION['admin']) && $action==='delete') {
            $ids=[];
            foreach ((array)($_POST['ids']??[]) as $value) {
                $id=filter_var($value,FILTER_VALIDATE_INT);
                if ($id && $id>0) $ids[$id]=$id;
            }
            if (!$ids) {$error='Selecciona al menos una solicitud para eliminar.';}
            else {
                (new LeadInboxWriter(db()))->delete($ids);
                header('Location: index.php',true,303);exit;
            }
        } else {http_response_code(403);$error='Acción no autorizada.';}
    }
    $admin=!empty($_SESSION['admin']);$rows=[];$total=0;$system=[];$page=max(1,(int)($_GET['page']??1));
    $tab=is_string($_GET['tab']??null)?$_GET['tab']:'solicitudes';
    if (!in_array($tab,['solicitudes','sistema'],true)) $tab='solicitudes';
    $status=is_string($_GET['status']??null)?$_GET['status']:'';$query=is_string($_GET['q']??null)?trim($_GET['q']):'';
    if (!in_array($status,['nuevo','revisado','contactado','archivado'],true)) $status='';
    if ($admin && $tab==='solicitudes') {
        $_SESSION['last_seen']=time();
        $result=(new LeadInboxSelector(db()))->page($status,$query,$page);
        $total=$result['total'];$page=$result['page'];$rows=$result['rows'];
    }
    if ($admin && $tab==='sistema') {
        $_SESSION['last_seen']=time();

    }
} catch (Throwable $e) {if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'index');error_log('Explorando Lugares: fallo de administración.');http_response_code(503);exit('El panel no está disponible en este momento.');}
$adminLayout=admin_layout_context();
$notice=null;if($admin&&isset($_SESSION['lead_status_notice'])){$notice=$_SESSION['lead_status_notice'];unset($_SESSION['lead_status_notice']);}
$csrfToken=(string)($_SESSION['csrf']??'');$categoryNames=$rows?categories(true):[];$adminPublicRoot=__DIR__;
$identityCss='/assets/portal-identity.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/portal-identity.css'),0,12);
$adminCss='../assets/admin.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/admin.css'),0,12);
require dirname(__DIR__,2).'/app/views/admin/lead-inbox.php';

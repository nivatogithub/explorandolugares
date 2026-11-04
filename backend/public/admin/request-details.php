<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/LeadBusinessLinks.php';
require_once dirname(__DIR__,2).'/app/AdminReturnContext.php';
require_once dirname(__DIR__,2).'/app/AdminRequestDetailSelector.php';
if(isset($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)>1800)unset($_SESSION['admin']);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
if(empty($_SESSION['admin'])){http_response_code(403);exit('Inicia sesión desde el portal.');}
$_SESSION['last_seen']=time();
$typeValue=$_GET['type']??$_GET['tipo']??'';$type=is_string($typeValue)?$typeValue:'';if($type==='participation')$type='lead';
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id||$id<1||!in_array($type,['lead','account'],true)){http_response_code(404);exit('Solicitud no encontrada.');}
$selector=new AdminRequestDetailSelector(db());
$request=$selector->request($type,(int)$id);
if(!$request){http_response_code(404);exit('Solicitud no encontrada.');}
$return=$type==='lead'?'index.php':'account-applications.php';
if($type==='lead')$return.='?'.http_build_query(['q'=>is_string($_GET['q']??null)?$_GET['q']:'','status'=>is_string($_GET['status']??null)?$_GET['status']:'','page'=>max(1,(int)($_GET['page']??1))]);
$return=AdminReturnContext::safe($_GET['return']??null,$return);
$error='';$notice='';$links=[];$matches=[];$account=null;$evidence=[];$photos=[];$memberships=[];
$search=is_string($_GET['search']??null)?trim($_GET['search']):'';
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if($type!=='lead'||($_POST['action']??'')!=='link'||($_POST['confirm']??'')!=='1')throw new RuntimeException('Confirma la relación informativa con la ficha seleccionada.');
        $business=filter_var($_POST['business_id']??null,FILTER_VALIDATE_INT);
        $created=(new LeadBusinessLinks(db()))->link($id,(int)$business,(!empty($_SESSION['admin_account_id'])?'account:'.(int)$_SESSION['admin_account_id']:(string)($_SESSION['admin_user']??$config['admin_user']??'legacy-admin')));
        $_SESSION['lead_link_notice']=$created?'Relación guardada. No se asignaron permisos ni se publicó el negocio.':'La relación ya estaba registrada; se conservó sin duplicarla.';
        header('Location: request-details.php?'.http_build_query(['return'=>$return,'type'=>$type,'id'=>$id,'q'=>$_GET['q']??'','status'=>$_GET['status']??'','page'=>$_GET['page']??1]),true,303);exit;
    }
    if($type==='lead'){
        $links=(new LeadBusinessLinks(db()))->forLead($id);
        if($search!==''){
            $matches=$selector->businessesMatching($search);
        }
    }else{
        $account=$selector->account((int)$request['account_id']);
        $evidence=$selector->evidence((int)$id);
        $photos=$selector->photos((int)$id);
        if(!empty($request['business_id']))$links=[['business_id'=>$request['business_id']]];
        if(!empty($request['business_id'])){$memberships=$selector->memberships((int)$request['business_id'],(int)$request['account_id']);}
    }
}catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'solicitud_detalle');$error=$e instanceof PDOException?'No se pudo cargar la relación. Revisa las migraciones del panel.':$e->getMessage();http_response_code(400);}
$notice=(string)($_SESSION['lead_link_notice']??'');unset($_SESSION['lead_link_notice']);
$adminLayout=admin_layout_context();
$linkedBusinesses=[];foreach($links as $link){$linkedBusinesses[(int)$link['business_id']]=$selector->business((int)$link['business_id']);}
$snapshot=[];$categoryNames=[];
if($type==='account'){
    $snapshot=json_decode((string)($request['business_details_json']??'{}'),true);
    if(!is_array($snapshot))$snapshot=[];
    if($snapshot)$categoryNames=$selector->categoryNames();
}
$returnFields=[];if($type==='lead')foreach(['q','status','page','return'] as $field)$returnFields[$field]=(string)($_GET[$field]??'');
$csrfToken=(string)($_SESSION['csrf']??'');$adminPublicRoot=__DIR__;
$identityCss='/assets/portal-identity.css?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/portal-identity.css'),0,12);
require dirname(__DIR__,2).'/app/views/admin/request-details.php';

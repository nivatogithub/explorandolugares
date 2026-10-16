<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require dirname(__DIR__,2).'/app/analytics/BasicAudienceService.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
function entity_output(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')entity_output(['error'=>'Método no permitido'],405);
$type=is_string($_GET['type']??null)?$_GET['type']:'';$key=is_string($_GET['key']??null)?$_GET['key']:'';
$requestedDays=$_GET['days']??30;$days=in_array($requestedDays,[30,'30',90,'90'],true)?(int)$requestedDays:30;
$admin=!empty($_SESSION['admin'])&&time()-(int)($_SESSION['last_seen']??0)<=1800;
$owner=!empty($_SESSION['business_account_id'])&&time()-(int)($_SESSION['business_last_seen']??0)<=1800;
if(!$admin&&!$owner)entity_output(['error'=>'Acceso privado requerido'],403);
if(!in_array($type,['business','destination'],true)||!preg_match('/^[a-zA-Z0-9-]{1,160}$/D',$key))entity_output(['error'=>'Entidad inválida'],400);
try {
    if(!$admin){
        require_business_portal_access();
        if($type!=='business')entity_output(['error'=>'Sin acceso a esta entidad'],403);
        $q=db()->prepare('SELECT b.id FROM businesses b JOIN business_account_memberships m ON m.business_id=b.id JOIN accounts a ON a.id=m.account_id WHERE b.public_id=? AND m.account_id=? AND m.status="active" AND a.account_type="business" AND a.status="active" LIMIT 1');$q->execute([$key,(int)$_SESSION['business_account_id']]);
        if(!$q->fetchColumn())entity_output(['error'=>'Sin acceso a esta entidad'],403);
    }
    entity_output((new BasicAudienceService(db(),dirname(__DIR__)))->entityReport($type,$key,$days));
}catch(Throwable){entity_output(['error'=>'Estadísticas temporalmente no disponibles'],503);}

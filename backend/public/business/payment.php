<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();require_business_session();
require_once dirname(__DIR__,2).'/app/BusinessPaymentOrderService.php';
require_once dirname(__DIR__,2).'/app/BusinessPaymentUpload.php';
require_once dirname(__DIR__,2).'/app/PaymentInstructions.php';
require_once dirname(__DIR__,2).'/app/BusinessPaymentPresentation.php';
require_once dirname(__DIR__).'/_portal-shell.php';
$pdo=db();$accountId=(int)$_SESSION['business_account_id'];$read=new BusinessPaymentOrderSelector($pdo);
if(!$read->available()){http_response_code(503);exit('La actualización de órdenes de pago todavía no está disponible.');}
$service=new BusinessPaymentOrderService($pdo,new BusinessPaymentUpload(private_storage_path('business-payment-evidence')));
$instructions=PaymentInstructions::load($pdo,private_storage_path('payment-instructions'),!empty($config['local_test']));
$error='';$order=null;$orderId=(string)($_POST['order']??$_GET['order']??'');
$_SESSION['payment_order_request_key']??=bin2hex(random_bytes(24));
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();$action=(string)($_POST['action']??'');
        if($action==='prepare_order'){
            $selected=$_POST['businesses']??[];if(!is_array($selected))throw new RuntimeException('Selecciona los negocios que quieres pagar.');$choices=[];
            foreach($selected as $id){if(!is_string($id)||!ctype_digit($id))throw new RuntimeException('Selección de negocio no válida.');
                $choices[]=['business_id'=>$id,'plan_code'=>$_POST['plans'][$id]??'','billing_frequency'=>$_POST['periods'][$id]??'','experience_id'=>$_POST['experiences'][$id]??0];}
            $created=$service->create($accountId,$choices,(string)($_POST['request_key']??''));$_SESSION['payment_order_request_key']=bin2hex(random_bytes(24));
            header('Location: /business/payment.php?order='.$created['public_id'],true,303);exit;
        }elseif($action==='apply_coupon'){
            $business=(int)($_POST['business_id']??0);$promotions=new BusinessPromotions($pdo);
            $promotions->applyAtPayment($accountId,$business,(int)($_POST['subscription_id']??0),(string)($_POST['promotion_code']??''));
            header('Location: /business/payment.php?business='.$business.'&cupon=aplicado',true,303);exit;
        }elseif($action==='cancel_order'){
            $service->cancel($accountId,$orderId);header('Location: /business/payment.php',true,303);exit;
        }elseif($action==='submit_evidence'){
            if(empty($instructions['enabled']))throw new RuntimeException('Aún no hay medios de pago configurados. No envíes dinero.');
            if(!throttle('business_payment_order_evidence',5,3600,(string)$accountId))throw new RuntimeException('Demasiados intentos. Inténtalo más tarde.');
            $service->submit($accountId,$orderId,(!empty($instructions['test_mode'])?'[SIMULACIÓN] ':'').(string)($_POST['payment_reference']??''),$_FILES['payment_proof']??[],
                ['transaction_date'=>(string)($_POST['transaction_date']??''),'paid_amount'=>(string)($_POST['paid_amount']??''),'payment_method'=>(string)($_POST['payment_method']??''),'source_bank'=>(string)($_POST['source_bank']??'')]);
            header('Location: /business/payment.php?order='.$orderId.'&enviado=1',true,303);exit;
        }else throw new RuntimeException('Acción no disponible.');
    }
    if($orderId!=='')$order=$service->get($accountId,$orderId);
}catch(Throwable $e){$incident=PortalIncidents::technical($e,'business_payment_order');$error=$incident?'No se pudo completar la operación. Referencia: '.$incident:$e->getMessage();http_response_code($incident?503:400);}
// Recover the same order after validation errors; never create another order on a refresh.
if($order===null&&$orderId!==''){try{$order=$service->get($accountId,$orderId);}catch(RuntimeException){}}
$rows=[];$promotions=new BusinessPromotions($pdo);$addons=new ExperienceOperatorAddon($pdo);
$selected=is_array($_POST['businesses']??null)?$_POST['businesses']:[(string)($_GET['business']??'')];
foreach($read->businesses($accountId) as $business){
    $id=(int)$business['id'];$sub=$read->latestSubscription($id);if(!$sub)continue;$open=$read->openForBusiness($id);
    $offer=$promotions->forSubscription((int)$sub['id']);$context=$promotions->paymentContext($accountId,$id);
    $addon=$addons->forSubscription((int)$sub['id']);$eligible=$addons->eligibleBusiness($id);
    $enabled=in_array($sub['status'],['pending','pending_publication','active','expired'],true)&&!$open&&!$read->pendingEvidence($id);
    $rows[]=[...$business,'subscriptionId'=>(int)$sub['id'],'plan'=>(string)($_POST['plans'][$id]??($sub['plan_code']==='free'?'featured':$sub['plan_code'])),'period'=>(string)($_POST['periods'][$id]??$sub['billing_frequency']??'monthly'),
        'experience'=>(int)($_POST['experiences'][$id]??$addon['experience_id']??0),'experiences'=>$eligible?$addons->options((int)$read->business($accountId,$id)['destination_id']):[],
        'enabled'=>$enabled,'selected'=>in_array((string)$id,$selected,true),'openOrder'=>$open,'offer'=>$offer,
        'canApplyCoupon'=>$context['can_apply']&&!$offer&&$promotions->hasApplicableOffer($id,$sub['plan_code'],$sub['billing_frequency']??'monthly')];
}
$orderView=null;
if($order){$items=[];foreach($order['items'] as $item)$items[]=[...$item,'amount'=>SubscriptionChangeService::money((int)$item['due_cents'])];$orderView=[...$order,'items'=>$items,'amount'=>SubscriptionChangeService::money((int)$order['total_cents'])];}
BusinessPaymentPresentation::render(account_layout_context(),[
    'csrf'=>(string)$_SESSION['csrf'],'error'=>$error,'rows'=>$rows,'orders'=>$read->orders($accountId),'order'=>$orderView,'instructions'=>$instructions,
    'plans'=>BusinessPromotions::PLANS,'periods'=>BusinessSubscriptionChoice::PERIODS,'requestKey'=>$_SESSION['payment_order_request_key'],
    'input'=>$_POST,'today'=>date('Y-m-d'),'couponApplied'=>($_GET['cupon']??'')==='aplicado',
],dirname(__DIR__));

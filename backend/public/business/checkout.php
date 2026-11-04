<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();
require_once dirname(__DIR__,2).'/app/SubscriptionPurchaseIntent.php';
require_once dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
require_once dirname(__DIR__,2).'/app/ExperienceOperatorAddon.php';
header('Cache-Control: no-store');$error='';$account=null;$businesses=[];$intent=null;
try{
    if(isset($_GET['plan'])){$_SESSION['subscription_purchase_intent']=SubscriptionPurchaseIntent::capture(db(),$_GET);unset($_SESSION['subscription_purchase_selected']);}
    $intent=SubscriptionPurchaseIntent::current();if(!$intent)throw new RuntimeException('La selección venció. Regresa al simulador para elegir tu suscripción.');
    require_business_session();
    $portal=new BusinessAccountPortalService(db());$account=$portal->current((int)$_SESSION['business_account_id']);
    if(!$account){unset($_SESSION['business_account_id']);require_business_session();}
    $businesses=$portal->businesses((int)$account['id']);
    if(isset($_GET['business'])){
        $chosen=(int)$_GET['business'];$matches=array_filter($businesses,static fn($row)=>(int)$row['id']===$chosen);
        if(!$matches)throw new RuntimeException('Selecciona un negocio asociado a tu cuenta.');
        if($intent['operator']){(new ExperienceOperatorAddon(db()))->requireEligible($chosen);if(!empty($intent['experience_id'])){$selectedBusiness=array_values($matches)[0];(new ExperienceOperatorAddon(db()))->quote($intent['plan'],$intent['period'],(int)$selectedBusiness['destination_id'],(int)$intent['experience_id']);}}
        $_SESSION['subscription_purchase_selected']=$chosen;
        header('Location: /negocios/portal/?tab=business&business='.$chosen.'&desde=simulador#pagos',true,303);exit;
    }
}catch(Throwable $e){if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'checkout');$error=$e->getMessage();http_response_code(400);}
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/BusinessSelectionPresentation.php';
$accountLayout=account_layout_context();
$viewBusinesses=[];
foreach($businesses as $business)$viewBusinesses[]=[
    'name'=>$business['display_name'],'placeName'=>$business['destination_name'],
    'url'=>'?business='.(string)$business['id'],
];
BusinessSelectionPresentation::render($accountLayout,[
    'error'=>$error,'hasSelection'=>$intent!==null,'businesses'=>$viewBusinesses,
    'planLabel'=>$intent?['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'][$intent['plan']]:'',
    'periodLabel'=>$intent?BusinessSubscriptionChoice::PERIODS[$intent['period']]:'',
    'hasOperator'=>$intent&&!empty($intent['operator']),
],dirname(__DIR__));

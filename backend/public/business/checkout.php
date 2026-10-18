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
    if(time()-(int)($_SESSION['business_last_seen']??0)>1800)unset($_SESSION['business_account_id']);
    $portal=new BusinessAccountPortalService(db());$account=empty($_SESSION['business_account_id'])?null:$portal->current((int)$_SESSION['business_account_id']);
    if(!$account){header('Location: /negocios/acceso/',true,303);exit;}
    $_SESSION['business_last_seen']=time();$businesses=$portal->businesses((int)$account['id']);
    if(isset($_GET['business'])){
        $chosen=(int)$_GET['business'];$matches=array_filter($businesses,static fn($row)=>(int)$row['id']===$chosen);
        if(!$matches)throw new RuntimeException('Selecciona un negocio asociado a tu cuenta.');
        if($intent['operator']){(new ExperienceOperatorAddon(db()))->requireEligible($chosen);if(!empty($intent['experience_id'])){$selectedBusiness=array_values($matches)[0];(new ExperienceOperatorAddon(db()))->quote($intent['plan'],$intent['period'],(int)$selectedBusiness['destination_id'],(int)$intent['experience_id']);}}
        $_SESSION['subscription_purchase_selected']=$chosen;
        header('Location: /negocios/portal/?tab=business&business='.$chosen.'&desde=simulador#pagos',true,303);exit;
    }
}catch(Throwable $e){$error=$e->getMessage();http_response_code(400);}
require_once __DIR__.'/../_account-page.php';account_page(false);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Selecciona tu negocio | Explorando Lugares</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/account-registration.css"><link rel="stylesheet" href="/assets/subscription-simulator.css"></head><body><?php require __DIR__.'/../_account-header.php';?><main class="wrap account-portal"><section class="account-portal-card"><p class="eyebrow">Tu suscripción</p><h1>¿Para cuál negocio?</h1><?php if($error):?><p role="alert"><?=esc($error)?></p><?php endif?><?php if($intent):?><p>Elegiste <strong><?=esc(['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'][$intent['plan']])?></strong> · <?=esc(BusinessSubscriptionChoice::PERIODS[$intent['period']])?><?=$intent['operator']?' + complemento de experiencia':''?>.</p><?php if($intent['operator']):?><p>Después de elegir el negocio, selecciona una experiencia publicada de su mismo lugar. Solo pueden contratar el complemento los negocios con la categoría Experiencias y actividades.</p><?php endif?><div class="checkout-businesses"><?php foreach($businesses as $business):?><a class="pill light" href="?business=<?=esc($business['id'])?>"><?=esc($business['display_name'])?> · <?=esc($business['destination_name'])?></a><?php endforeach?></div><p>¿Es un negocio nuevo o todavía no lo administras?</p><a class="pill" href="/negocios/registro/?tipo=business&desde=simulador">Crear o reclamar un negocio</a><p>Conservaremos la suscripción elegida. Registrar o reclamar requiere revisar tu relación con el negocio; el pago queda disponible al tenerlo asociado a tu cuenta.</p><?php endif?><p><a href="/negocios-locales/planes/">Volver al simulador</a></p></section></main></body></html>

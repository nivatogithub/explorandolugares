<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();require_business_session();
require_once dirname(__DIR__,2).'/app/BusinessWorkspace.php';
require_once dirname(__DIR__,2).'/app/BusinessPaymentEvidenceService.php';
require_once dirname(__DIR__,2).'/app/PaymentInstructions.php';
require_once dirname(__DIR__).'/admin/_asset.php';
$account=(new BusinessAccountPortalService(db()))->current((int)$_SESSION['business_account_id']);
if(!$account){unset($_SESSION['business_account_id']);require_business_session();}
$id=(int)($_GET['business']??0);$row=null;
foreach((new BusinessWorkspace(db()))->rows((int)$account['id']) as $candidate)if($candidate['kind']==='business'&&(int)$candidate['id']===$id){$row=$candidate;break;}
if(!$row){http_response_code(404);exit('Este negocio no está asociado a tu cuenta.');}
if(!$row['state']['pay']){header('Location: /negocios/portal/?tab=business',true,303);exit;}
$q=db()->prepare('SELECT * FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);$subscription=$q->fetch(PDO::FETCH_ASSOC);
if(!$subscription||!in_array($subscription['plan_code'],['featured','page','page_premium'],true)){http_response_code(409);exit('No hay una suscripción pendiente de pago.');}
$plan=$subscription['plan_code'];$period=$subscription['billing_frequency'];
$operator=(new ExperienceOperatorAddon(db()))->forSubscription((int)$subscription['id']);
$addon=$operator?['experience_id'=>(int)$operator['experience_id'],'amount'=>$operator['quoted_amount']]:null;
$onboardingChoice=['plan_code'=>$plan,'billing_frequency'=>$period];
$paymentInstructionsVisible=true;
$paymentInstructions=PaymentInstructions::load(db(),private_storage_path('payment-instructions'),!empty($config['local_test']));
$_SESSION['payment_checkout_references'][$id]??='EL-'.strtoupper(bin2hex(random_bytes(12)));$checkoutReference=$_SESSION['payment_checkout_references'][$id];
$error='';$quote=null;
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        csrf();
        if(empty($paymentInstructions['enabled']))throw new RuntimeException('Aún no hay medios de pago configurados. No envíes dinero.');
        $entry=$_SESSION['business_checkout_quotes'][(string)($_POST['quote_token']??'')]??null;
        if(!$entry||(int)$entry['business_id']!==$id||$entry['quote']['plan_code']!==$plan||$entry['quote']['billing_frequency']!==$period)throw new RuntimeException('La cotización cambió. Revisa el importe y vuelve a adjuntar el comprobante.');
        (new BusinessPaymentEvidenceService(db(),private_storage_path('business-payment-evidence')))->submit((int)$account['id'],$id,$plan,$period,
            (!empty($paymentInstructions['test_mode'])?'[SIMULACIÓN] ':'').(string)($_POST['payment_reference']??''),$_FILES['payment_proof']??[],
            ['subscription_quote'=>$entry['quote'],'checkout_reference'=>$checkoutReference,'transaction_date'=>(string)($_POST['transaction_date']??''),
             'paid_amount'=>(string)($_POST['paid_amount']??''),'payment_method'=>(string)($_POST['payment_method']??''),'source_bank'=>(string)($_POST['source_bank']??''),'operator_experience_id'=>(int)($operator['experience_id']??0)]);
        unset($_SESSION['payment_checkout_references'][$id],$_SESSION['business_checkout_quotes'][(string)$_POST['quote_token']],$_SESSION['subscription_purchase_intent'],$_SESSION['subscription_purchase_selected']);
        header('Location: /negocios/portal/?tab=business&pago=enviado',true,303);exit;
    }
}catch(Throwable $e){$reference=PortalIncidents::technical($e,'business_payment');$error=$reference?'No se pudo guardar el comprobante. Referencia: '.$reference:$e->getMessage();http_response_code($reference?503:400);}
try{
    $quote=(new SubscriptionChangeService(db()))->quote($id,$plan,$period,$addon);
    $promotions=new BusinessPromotions(db());$promotion=$promotions->forSubscription((int)$subscription['id']);
    $quote=$promotions->checkout($subscription,$quote);
    $quoteToken=bin2hex(random_bytes(24));
    $_SESSION['business_checkout_quotes']??=[];
    foreach($_SESSION['business_checkout_quotes'] as $token=>$entry)if(strtotime($entry['quote']['quoted_at'].' UTC')<time()-1800)unset($_SESSION['business_checkout_quotes'][$token]);
    if(count($_SESSION['business_checkout_quotes'])>=20)array_shift($_SESSION['business_checkout_quotes']);
    $_SESSION['business_checkout_quotes'][$quoteToken]=['business_id'=>$id,'quote'=>$quote];
}catch(Throwable $e){$error='No se pudo preparar el importe. '.$e->getMessage();}
require_once dirname(__DIR__).'/_account-page.php';account_page(false);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Pago de mi negocio | Explorando Lugares</title>
<?php foreach(['style.css','account-portal.css','business-workspace.css','payment-instructions.css'] as $asset):?><link rel="stylesheet" href="<?=esc(admin_asset($asset))?>"><?php endforeach?>
</head><body><?php require __DIR__.'/../_account-header.php';?>
<main class="wrap account-portal"><p><a href="/negocios/portal/?tab=business">← Mis negocios</a></p>
<section class="destination-admin-list"><h1>Pago de <?=esc($row['display_name'])?></h1>
<p><?=esc(BusinessWorkspace::PLANS[$plan])?> · <?=esc(BusinessWorkspace::PERIODS[$period]??$period)?></p>
<?php if(!empty($promotion)):?><p>Cupón <strong><?=esc($promotion['code'])?></strong> · <?=esc($promotion['percentage'])?> % · Descuento: <?=esc(number_format((int)$promotion['discount_cents']/100,2,',','.').' '.$promotion['currency'])?>. El importe mostrado ya incluye el descuento.</p><?php endif?>
<?php if($row['message']):?><p class="notice"><?=esc($row['message'])?></p><?php endif?>
<?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif?>
<?php if($quote):?><p class="notice">Importe a pagar: <strong><?=esc(number_format($quote['due_cents']/100,2,',','.').' '.$quote['currency'])?></strong></p><?php endif?>
<p>Tu información ya está guardada. Puedes salir y retomar este pago desde la fila del negocio.</p>
<?php require __DIR__.'/payment-instructions.php';?>
<?php if($quote&&!empty($paymentInstructions['enabled'])):?>
<form method="post" enctype="multipart/form-data" class="destination-editor account-payment-form">
<input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><input type="hidden" name="quote_token" value="<?=esc($quoteToken)?>">
<div class="destination-form-grid">
<label>Número de transacción o autorización<input name="payment_reference" maxlength="140" required value="<?=esc($_POST['payment_reference']??'')?>"></label>
<label>Fecha del pago<input type="date" name="transaction_date" max="<?=date('Y-m-d')?>" required value="<?=esc($_POST['transaction_date']??'')?>"></label>
<label>Valor efectivamente pagado · COP<input type="number" name="paid_amount" min="<?=esc(SubscriptionChangeService::money($quote['due_cents']))?>" step="0.01" required value="<?=esc($_POST['paid_amount']??'')?>"><small>Sin puntos de miles.</small></label>
<label>Medio utilizado<select name="payment_method" required><option value="">Selecciona</option><?php foreach(['breb'=>'Llave Bre-B','transfer'=>'Transferencia bancaria','qr'=>'QR','other'=>'Otro'] as $code=>$label):?><option value="<?=$code?>" <?=($_POST['payment_method']??'')===$code?'selected':''?>><?=$label?></option><?php endforeach?></select></label>
<label class="wide">Banco o aplicación de origen<input name="source_bank" maxlength="120" required value="<?=esc($_POST['source_bank']??'')?>"><small>No incluyas claves ni el número completo de tu cuenta.</small></label>
<label class="wide">Comprobante · PDF, JPG, PNG o WebP · máximo 8 MB<input type="file" name="payment_proof" accept="application/pdf,image/jpeg,image/png,image/webp" required></label>
</div><p>Enviar el comprobante deja el negocio pendiente de validación. No publica ni confirma automáticamente el pago.</p>
<button class="pill" type="submit">Enviar comprobante para validación</button>
</form><?php endif?></section></main>
<script src="<?=esc(admin_asset('business-subscriptions.js'))?>" defer></script>
</body></html>

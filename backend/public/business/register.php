<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/ContactPhone.php';
require_business_portal_access();
require dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
require_once dirname(__DIR__,2).'/app/BusinessSubscriptionChoice.php';
$pdo=db();$service=new BusinessAccountPortalService($pdo);$error='';require_once dirname(__DIR__,2).'/app/SubscriptionPurchaseIntent.php';$registrationPurchase=SubscriptionPurchaseIntent::current();$selectedOperatorExperienceId=(int)($registrationPurchase['experience_id']??0);
if(isset($_SESSION['business_account_id'])&&time()-(int)($_SESSION['business_last_seen']??0)>1800)require_business_session();
$currentAccount=empty($_SESSION['business_account_id'])?null:$service->current((int)$_SESSION['business_account_id']);
if($currentAccount)$_SESSION['business_last_seen']=time();
if(empty($_SESSION['business_onboarding_draft'])||!is_string($_SESSION['business_onboarding_draft']))$_SESSION['business_onboarding_draft']=bin2hex(random_bytes(24));
$personalPhone=''; // Personal contact belongs to the account, not to this application.

$registrationType=(string)($_POST['account_type']??$_GET['tipo']??($_SERVER['REQUEST_METHOD']==='POST'?'business':''));
if($registrationType!=='business'){require __DIR__.'/registration-choice.php';exit;}
// La cuenta es previa a la solicitud. No mezclamos la creación de credenciales
// con el registro de un negocio: la asociación siempre parte de una sesión.
if(!$currentAccount){
    if($_SERVER['REQUEST_METHOD']==='POST'||($_GET['search']??'')==='1'||isset($_GET['promotion_quote']))require_business_session();
    $_SESSION['business_registration_return']='/negocios/registro/?tipo=business';
    require_once __DIR__.'/../_account-page.php';account_page(false);
    require __DIR__.'/business-account-gate.php';
    exit;
}
if(empty($currentAccount['email_verified_at'])){$_SESSION['business_registration_return']='/negocios/registro/?tipo=business';header('Location: /cuenta/verificar-correo/',true,303);exit;}
require_once dirname(__DIR__,2).'/app/BusinessPromotions.php';$promotions=new BusinessPromotions($pdo);
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['promotion_quote'])){
    header('Cache-Control: private, no-store');
    try{
        if(!throttle('business_promotion_quote',40,300,(string)$currentAccount['id']))throw new RuntimeException('Demasiadas validaciones de cupón. Espera unos minutos e inténtalo de nuevo.');
        $offer=$promotions->quote((string)($_GET['code']??''),(string)($_GET['plan']??''),(string)($_GET['period']??''),(int)($_GET['destination_id']??0));
        $_SESSION['business_promotion_preview']=['offer'=>$offer,'expires'=>time()+1800];
        json_response(['ok'=>true,'offer'=>$offer]);
    }catch(Throwable $e){json_response(['ok'=>false,'error'=>$e instanceof PDOException?'No se pudo validar el cupón. Inténtalo de nuevo.':$e->getMessage()],400);}
}
require_once dirname(__DIR__,2).'/app/BusinessRegistrationDrafts.php';
$drafts=new BusinessRegistrationDrafts($pdo);$draftRevision=0;$resumeStep=1;$savedDraftUploads=[];
$draftId=(string)($_POST['registration_draft']??$_GET['draft']??($_SERVER['REQUEST_METHOD']==='POST'?($_SESSION['business_onboarding_draft']??''):''));
if($draftId==='')$draftId=bin2hex(random_bytes(24));
if(!preg_match('/^[a-f0-9]{48}$/D',$draftId)){http_response_code(400);exit('Borrador no válido.');}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['draft_action']??'')==='save'){
    header('Cache-Control: private, no-store');
    try{csrf();$result=$drafts->save((int)$currentAccount['id'],$draftId,(int)($_POST['draft_revision']??0),(int)($_POST['wizard_step']??1),$_POST);json_response(['ok'=>true,...$result]);}
    catch(Throwable $e){$ref=PortalIncidents::technical($e,'business_draft');json_response(['ok'=>false,'error'=>$ref?'No se pudo guardar el borrador. Referencia: '.$ref:$e->getMessage()],$ref?503:409);}
}
if($_SERVER['REQUEST_METHOD']==='GET'&&isset($_GET['draft'])){
    try{
        $saved=$drafts->load((int)$currentAccount['id'],$draftId);$_POST=$saved['payload'];$draftRevision=(int)$saved['revision'];$resumeStep=(int)$saved['wizard_step'];
        $savedDraftUploads=$drafts->uploads($draftId);$validIds=array_column($savedDraftUploads,'id');
        foreach((array)($_POST['temporary_uploads']??[]) as $kind=>$slots)foreach($slots as $slot=>$upload)if(!in_array($upload,$validIds,true)){unset($_POST['temporary_uploads'][$kind][$slot]);$error='Un archivo del borrador venció. Los textos están guardados; vuelve a adjuntar el archivo que falta.';}
    }catch(RuntimeException $e){http_response_code(404);exit(esc($e->getMessage()));}
}elseif($_SERVER['REQUEST_METHOD']==='POST'){$draftRevision=(int)($_POST['draft_revision']??0);$resumeStep=(int)($_POST['wizard_step']??6);}
$_SESSION['business_onboarding_draft']=$draftId;
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https://tile.openstreetmap.org; connect-src 'self' https://nominatim.openstreetmap.org https://ipwho.is; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
$destinationId=max(0,(int)($_POST['destination_id']??$_GET['destination_id']??$registrationPurchase['destination_id']??0));
if(($_GET['search']??'')==='1'){
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try{
        $category=(string)($_GET['category']??'');$term=trim((string)($_GET['q']??''));
        if($category==='__experiences__'){require_once dirname(__DIR__,2).'/app/ExperienceOperatorAddon.php';$results=(new ExperienceOperatorAddon($pdo))->options($destinationId);}
        elseif($category==='__categories__'){$results=$service->registrationCategories($destinationId);}
        else{require_once dirname(__DIR__,2).'/app/ExperienceOperatorAddon.php';$operatorLookup=new ExperienceOperatorAddon($pdo);$results=$service->searchRegistrationBusinesses($destinationId,$category,$term);foreach($results as &$result)$result['is_experience_operator']=$operatorLookup->eligibleBusiness((int)$result['id']);unset($result);}
        echo json_encode(['results'=>$results],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    }catch(Throwable){http_response_code(400);echo '{"results":[]}';}exit;
}
require_once dirname(__DIR__,2).'/app/ExperienceOperatorAddon.php';$addonService=new ExperienceOperatorAddon($pdo);$addonPrices=$addonService->prices();$operatorExperiences=$destinationId?$addonService->options($destinationId):[];
$subscriptionChoice=new BusinessSubscriptionChoice($pdo);$subscriptionOptions=$subscriptionChoice->options();

$destinations=$service->registrationDestinations();$categories=$destinationId?$service->registrationCategories($destinationId):[];
try{
 if(!business_portal_available())throw new RuntimeException('El registro de cuentas empresariales todavía no está habilitado.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if($drafts->ready()){
   $existingDraft=$pdo->prepare('SELECT account_id,revision,status,business_id FROM business_registration_drafts WHERE id=?');$existingDraft->execute([$draftId]);$existingDraft=$existingDraft->fetch(PDO::FETCH_ASSOC);
   if($existingDraft){if((int)$existingDraft['account_id']!==(int)$currentAccount['id'])throw new RuntimeException('Este borrador no pertenece a tu cuenta.');
    if($existingDraft['status']==='submitted'){header('Location: /negocios/portal/?tab=business',true,303);exit;}
    if((int)$existingDraft['revision']!==$draftRevision)throw new RuntimeException('El borrador cambió en otra pestaña. Ábrelo de nuevo desde Mis negocios.');
   }
  }
  csrf();if(!is_string($_POST['plan_code']??null)||trim($_POST['plan_code'])==='')throw new RuntimeException('Elige la suscripción de tu negocio antes de continuar.');
 if(!throttle('business_account_register_form',12,900))throw new RuntimeException('Demasiados intentos. Inténtalo más tarde.');
  $application=['application_type'=>(string)($_POST['application_type']??''),'business_id'=>(string)($_POST['business_id']??''),'business_name'=>(string)($_POST['business_name']??''),'destination_id'=>(string)($_POST['destination_id']??''),'category_code'=>(string)($_POST['category_code']??''),'relationship_code'=>(string)($_POST['relationship_code']??''),'phone'=>$personalPhone,'message'=>(string)($_POST['message']??''),'consent'=>(string)($_POST['consent']??'')];
  $application['temporary_uploads']=(array)($_POST['temporary_uploads']??[]);$application['onboarding_draft']=(string)($_SESSION['business_onboarding_draft']??'');
  $imageSlots=match($_POST['plan_code']){'page'=>['cover'],'page_premium'=>['cover','product-0','product-1','product-2','product-3','product-4'],default=>[]};
  $application['temporary_uploads']['editorial']=array_intersect_key((array)($application['temporary_uploads']['editorial']??[]),array_flip($imageSlots));
  $isFree=($_POST['plan_code']??'free')==='free';$application['self_onboarding_policy']=BusinessSelfOnboarding::POLICY;$application['responsibility_declared']=(string)($_POST['responsibility_declared']??'');
  $existenceInputs=BusinessExistenceVerification::capture(['business_rnt'=>$_POST['business_rnt']??'','business_nit'=>$_POST['business_nit']??'']);
  if($existenceInputs['rnt']===''&&$existenceInputs['nit']==='')throw new RuntimeException('Indica el NIT o el RNT del negocio, según el documento de soporte.');
  $application['business_details']=BusinessOnboardingDetails::capture($pdo,$isFree?array_merge(['plan_code'=>'free'],($_POST['distinct_business']??'')==='1'?['address_text'=>(string)($_POST['distinct_address']??'')]:[]):$_POST,$application['category_code'],private_storage_path('business-image-references'));$application['business_details']['existence_inputs']=$existenceInputs;
  if($isFree&&($_POST['distinct_business']??'')==='1')$application['business_details']['distinct_business_declared']=true;
  $application['business_details']['requested_subscription']=$subscriptionChoice->quote((string)($_POST['plan_code']??'free'),(string)($_POST['billing_frequency']??'monthly'));
  if((int)($_POST['operator_experience_id']??0)){if($application['application_type']==='new_business'){if(!ExperienceOperatorAddon::eligibleCategories($application['business_details']['categories']))throw new RuntimeException('El complemento requiere la categoría Experiencias y actividades.');}else{$addonService->requireEligible((int)$application['business_id']);}}
  $addon=$addonService->quote((string)($_POST['plan_code']??'free'),(string)($_POST['billing_frequency']??'monthly'),$destinationId,(int)($_POST['operator_experience_id']??0));if($addon){$requested=&$application['business_details']['requested_subscription'];if($requested['quoted_amount']===null)throw new RuntimeException('La suscripción base requiere una tarifa antes de añadir el complemento.');$requested['base_amount']=$requested['quoted_amount'];$requested['operator_addon']=$addon;$requested['quoted_amount']=number_format((float)$requested['base_amount']+(float)$addon['amount'],2,'.','');unset($requested);}
  $promotionCode=trim((string)($_POST['promotion_code']??''));
  if($promotionCode!==''){
      $preview=$_SESSION['business_promotion_preview']??null;
      $offer=$promotions->quote($promotionCode,(string)$_POST['plan_code'],(string)($_POST['billing_frequency']??'monthly'),$destinationId);
      if(!$preview||$preview['expires']<time()||$preview['offer']!==$offer)throw new RuntimeException('Aplica de nuevo el cupón en el paso Suscripción para confirmar el descuento vigente. Tu información se conserva.');
      $offer['addon_cents']=SubscriptionChangeService::cents($addon['amount']??0);$offer['due_cents']+=$offer['addon_cents'];
      $application['business_details']['requested_subscription']['promotion']=$offer;
      $application['business_details']['requested_subscription']['quoted_amount']=SubscriptionChangeService::money($offer['due_cents']);
  }
  $required=['business_registration'];
  foreach($required as $evidenceType){$temporaryEvidence=(string)($application['temporary_uploads']['evidence'][$evidenceType]??'');$posted=(($_FILES[$evidenceType]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK);if(!$posted&&!preg_match('/^[a-f0-9]{48}$/D',$temporaryEvidence))throw new RuntimeException('Adjunta el documento privado del negocio. La asociación es declarada y no certifica titularidad.');}
  $accountId=$service->register($currentAccount['display_name'],$currentAccount['email'],'',$application,[
    'identity_document'=>$_FILES['identity_document']??[],
    'business_registration'=>$_FILES['business_registration']??[],
    'authority_document'=>$_FILES['authority_document']??[],
  ],private_storage_path('business-representation-evidence'),!$isFree?($_FILES['reference_images']??null):null,private_storage_path('business-image-references'),$currentAccount?(int)$currentAccount['id']:null);
  require_once dirname(__DIR__,2).'/app/AccountSessionSecurity.php';AccountSessionSecurity::bind(db(),$_SESSION,$accountId);session_regenerate_id(true);$_SESSION['business_account_id']=$accountId;$_SESSION['account_space']='business';$_SESSION['business_last_seen']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
  // A verified account can continue directly to payment. This creates only a
  // private draft and a pending subscription; it never publishes the business.
  require_once dirname(__DIR__,2).'/app/BusinessSelfOnboarding.php';
  $pendingApplication=$pdo->prepare('SELECT id FROM business_account_applications WHERE account_id=? AND destination_id=? AND status IN ("pending","in_review") ORDER BY id DESC LIMIT 1');$pendingApplication->execute([$accountId,$destinationId]);$pendingApplicationId=(int)$pendingApplication->fetchColumn();
  if($pendingApplicationId){$pdo->beginTransaction();try{BusinessSelfOnboarding::complete($pdo,$pendingApplicationId);$pdo->commit();}catch(Throwable $completionError){if($pdo->inTransaction())$pdo->rollBack();throw $completionError;}}
  $nextBusiness=(int)($application['business_id']??0);
  if($application['application_type']==='new_business'){$next=$pdo->prepare('SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN business_account_memberships m ON m.business_id=b.id WHERE m.account_id=? AND m.status="active" AND b.destination_id=? AND LOWER(t.display_name)=LOWER(?) ORDER BY b.id DESC LIMIT 1');$next->execute([$accountId,$destinationId,$application['business_name']]);$nextBusiness=(int)$next->fetchColumn();}
  $hasAccess=$pdo->prepare('SELECT 1 FROM business_account_memberships WHERE account_id=? AND business_id=? AND status="active"');$hasAccess->execute([$accountId,$nextBusiness]);
  $needsPayment=!$isFree&&(!isset($offer)||$offer['due_cents']>0);
  $nextUrl=$nextBusiness&&$hasAccess->fetchColumn()?($needsPayment?'/business/payment.php?business='.$nextBusiness:'/negocios/portal/?tab=business&business='.$nextBusiness.'&solicitud=guardada'):'/negocios/portal/?espacio=business&solicitud=guardada';
  if($drafts->ready())$drafts->finish($accountId,$draftId,$nextBusiness?:null);
  header('Location: '.site_path($nextUrl),true,303);exit;
 }
}catch(Throwable $e){$incident=PortalIncidents::technical($e,'business_registration');$error=$e instanceof PDOException?'Ya existe una cuenta con ese correo o no fue posible guardar la solicitud.':$e->getMessage();if($incident)$error='No pudimos completar el registro. Referencia: '.$incident;http_response_code($incident?503:400);}
require_once __DIR__.'/../_account-page.php';account_page(false);
// A rejected submission must keep the private files visibly recoverable too.
if($error!==''&&$_SERVER['REQUEST_METHOD']==='POST')$savedDraftUploads=$drafts->uploads($draftId);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Registrar o reclamar un negocio | Explorando Lugares</title><link rel="stylesheet" href="<?=esc(site_path('/assets/style.css?v=business-portal-4'))?>"><link rel="stylesheet" href="<?=esc(site_path('/assets/admin.css?v=business-representation-1'))?>"><link rel="stylesheet" href="/assets/subscription-choice.css?v=1"><link rel="stylesheet" href="/assets/leaflet.css"><link rel="stylesheet" href="/assets/account-registration.css?v=2"><link rel="stylesheet" href="/assets/contact-phone.css?v=2"><link rel="icon" href="<?=esc(site_path('/assets/brand/explorando-lugares-logo-v2-transparent.png'))?>" type="image/png"></head><body><?php require __DIR__.'/../_account-header.php'; ?><main class="wrap admin-page account-portal"><a class="brand" href="<?=esc(site_path('/index.html'))?>"><img class="brand-logo" src="<?=esc(site_path('/assets/brand/explorando-lugares-logo-v2-transparent.png'))?>" alt="" width="64" height="64"><span>Explorando<b>Lugares</b></span></a><p><a href="<?=esc(site_path('/negocios-locales/'))?>">← Negocios locales</a></p><section class="account-portal-card account-registration-card"><p class="eyebrow">Espacio para negocios</p><h1>Registra o reclama un negocio</h1><p>La cuenta es tuya como persona. Puedes usarla para viajar y administrar varios negocios en distintos lugares; no tienen que estar donde resides.</p><p>Busca primero el negocio para evitar duplicados. Con tu correo verificado, un documento del negocio y tu declaración, puedes crear o asociar una ficha privada. Si ya tiene otro responsable, abrimos una disputa y conservamos su acceso. La ficha no aparece en el portal hasta revisar los soportes y aprobar su publicación.</p><?php if($error):?><p class="notice" role="alert"><?=esc($error)?></p><?php endif ?><form method="post" enctype="multipart/form-data" class="registration-form" id="business-registration-form" data-registration-state="<?=esc(json_encode(['category'=>$_POST['category_code']??'','type'=>$_POST['application_type']??'','extras'=>$_POST['category_codes']??[],'id'=>$_POST['business_id']??'','name'=>$_POST['business_name']??'']))?>" data-search-url="<?=esc(site_path('/negocios/registro/'))?>"><input type="hidden" name="account_type" value="business"><input type="hidden" name="csrf" value="<?=esc($_SESSION['csrf'])?>"><fieldset><legend>1. Busca el negocio</legend><div class="destination-form-grid"><label>Lugar<select name="destination_id" id="registration-destination" required><option value="">Selecciona un lugar</option><?php foreach($destinations as $item):?><option value="<?=esc($item['id'])?>" data-name="<?=esc($item['display_name'])?>" data-type="<?=esc($item['destination_type'])?>" data-department="<?=esc($item['department'])?>" data-country="<?=esc($item['country_code'])?>" data-lat="<?=esc($item['latitude']??'')?>" data-lon="<?=esc($item['longitude']??'')?>" <?=$destinationId===(int)$item['id']?'selected':''?>><?=esc($item['display_name'])?></option><?php endforeach ?></select></label><label>Categoría<select name="category_code" id="registration-category" required <?=$categories?'':'disabled'?>><option value="">Selecciona una categoría</option><?php foreach($categories as $item):?><option value="<?=esc($item['code'])?>" <?=($_POST['category_code']??'')===$item['code']?'selected':''?>><?=esc($item['label'])?></option><?php endforeach ?></select></label><label class="wide">Nombre del negocio<input id="registration-search" type="search" autocomplete="off" placeholder="Escribe al menos dos letras" disabled><small>Buscamos negocios publicados y registros internos que aún no son visibles.</small></label></div><div id="registration-results" class="business-registration-results" aria-live="polite"><p>Selecciona destino y categoría para iniciar la búsqueda.</p></div><button type="button" class="pill light" id="registration-not-found" disabled>No lo encuentro · Crear negocio</button><input type="hidden" name="application_type" id="registration-application-type" value=""><input type="hidden" name="business_id" id="registration-business-id" value=""><label class="wide" id="registration-new-name-wrap" hidden>Nombre legal o comercial del negocio que falta<input name="business_name" id="registration-new-name" maxlength="180" autocomplete="organization"><small>Para Gratis basta nombre, categoría, documento del negocio y tu declaración; la cuenta debe verificar su correo.</small></label></fieldset><?php ?><fieldset id="registration-plan-choice" class="registration-plan-stage" hidden disabled><?php require __DIR__.'/subscription-choice.php'; ?></fieldset><?php require __DIR__.'/new-business-fields.php'; ?><fieldset id="registration-distinct-business" hidden disabled><legend>Si existe otro establecimiento con nombre parecido</legend><details><summary>Es otro negocio o una sede diferente</summary><label class="admin-checkbox-field"><input type="checkbox" name="distinct_business" value="1"><span>Declaro que es otro establecimiento y no la ficha encontrada. No estoy reclamando el negocio de otra persona.</span></label><label>Dirección que lo diferencia<input name="distinct_address" maxlength="300" minlength="8" value="<?=esc($_POST['distinct_address']??'')?>"></label><p>Solo se permite si la dirección es diferente de las registradas para las coincidencias. La declaración queda conservada con el documento; no se certifica automáticamente.</p></details></fieldset><fieldset id="registration-continuation" hidden disabled><legend>Autorización para solicitar la publicación</legend><fieldset class="operator-registration" data-operator-prices="<?=esc(json_encode($addonPrices))?>"><legend>Visibilidad adicional</legend><?php require __DIR__.'/operator-addon-choice.php'; ?><p data-operator-registration-total role="status"></p></fieldset><fieldset><legend>Tu relación con el negocio y los soportes</legend><div class="destination-form-grid"><section class="registration-account-identity wide"><strong>Cuenta que se asociará al negocio</strong><p><?=esc($currentAccount['display_name'])?> · <?=esc($currentAccount['email'])?></p></section><label>Tu relación<select name="relationship_code" id="registration-relationship" required><option value="">Selecciona</option><option value="owner">Soy propietario/a</option><option value="legal_representative">Soy representante legal</option><option value="authorized_administrator">Soy administrador/a autorizado/a</option></select></label><label class="wide">Documento del negocio · RUT, matrícula o certificado equivalente · máximo 8 MB<input type="file" name="business_registration" accept="application/pdf,image/jpeg,image/png,image/webp" required><small>Adjunta un soporte que relacione el negocio con su titular: RUT, matrícula mercantil o certificado equivalente. Lo revisaremos antes de publicar y eliminaremos el archivo después de publicar el negocio; conservaremos la constancia de revisión.</small></label><label class="admin-checkbox-field wide"><input type="checkbox" name="responsibility_declared" value="1" required><span>Declaro que tengo autorización para administrar este negocio y que el documento corresponde a él. Entiendo que otra persona puede solicitar revisión de esta asociación.</span></label></div><p class="small-note">Los documentos se guardan en un área privada y solo los consulta el equipo autorizado. No se publican en el perfil del negocio.</p></fieldset><label class="registration-consent account-consent"><input type="checkbox" name="consent" value="1" required><span>Autorizo el uso de mis datos y documentos para revisar y responder esta solicitud. Leí la <a href="<?=esc(site_path('/privacidad/'))?>" target="_blank" rel="noopener">política de tratamiento de datos</a>.</span></label><button class="pill" type="submit" id="registration-submit" disabled><?=$currentAccount?'Guardar y asociar negocio':'Crear cuenta y continuar'?></button></fieldset></form><?php if(!$currentAccount):?><p>¿Ya tienes cuenta? <a href="<?=esc(site_path('/negocios/acceso/'))?>">Inicia sesión</a></p><?php endif ?></section></main><?php require __DIR__.'/../_business-map-dialog.php'; ?><script src="/assets/leaflet.js"></script><script src="/assets/admin-business-location.js?v=18" defer></script><script src="/assets/contact-phone.js?v=1" defer></script><script src="<?=esc(site_path('/assets/business-registration.js?v=8'))?>" defer></script><script src="/assets/account-files.js?v=1" defer></script><script src="/assets/operator-addon-registration.js?v=3" defer></script></body></html>

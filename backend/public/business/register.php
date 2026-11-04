<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/ContactPhone.php';
require_business_portal_access();
require dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
require_once dirname(__DIR__,2).'/app/BusinessSubscriptionChoice.php';
require_once dirname(__DIR__,2).'/app/BusinessRegistrationSelector.php';
$pdo=db();$service=new BusinessAccountPortalService($pdo);$registrationQueries=new BusinessRegistrationSelector($pdo);$error='';require_once dirname(__DIR__,2).'/app/SubscriptionPurchaseIntent.php';$registrationPurchase=SubscriptionPurchaseIntent::current();$selectedOperatorExperienceId=(int)($registrationPurchase['experience_id']??0);
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
    if($_SERVER['REQUEST_METHOD']==='POST'||($_GET['search']??'')==='1')require_business_session();
    $_SESSION['business_registration_return']='/negocios/registro/?tipo=business';
    require_once dirname(__DIR__).'/_portal-shell.php';
    require_once dirname(__DIR__,2).'/app/AccountAuthPresentation.php';
    AccountAuthPresentation::render('business-account-gate',account_layout_context(),['error'=>$error],dirname(__DIR__));
    exit;
}
if(empty($currentAccount['email_verified_at'])){$_SESSION['business_registration_return']='/negocios/registro/?tipo=business';header('Location: /cuenta/verificar-correo/',true,303);exit;}
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

$destinations=$service->registrationPlaces();$categories=$destinationId?$service->registrationCategories($destinationId):[];
try{
 if(!business_portal_available())throw new RuntimeException('El registro de cuentas empresariales todavía no está habilitado.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if($drafts->ready()){
   $existingDraft=$registrationQueries->draft($draftId);
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
  $pendingApplicationId=$registrationQueries->pendingApplication($accountId,$destinationId);
  if($pendingApplicationId){$pdo->beginTransaction();try{BusinessSelfOnboarding::complete($pdo,$pendingApplicationId);$pdo->commit();}catch(Throwable $completionError){if($pdo->inTransaction())$pdo->rollBack();throw $completionError;}}
  $nextBusiness=(int)($application['business_id']??0);
  if($application['application_type']==='new_business')$nextBusiness=$registrationQueries->latestBusiness($accountId,$destinationId,$application['business_name']);
  $hasAccess=$registrationQueries->hasActiveMembership($accountId,$nextBusiness);
  $needsPayment=!$isFree;
  $nextUrl=$nextBusiness&&$hasAccess?($needsPayment?'/business/payment.php?business='.$nextBusiness:'/negocios/portal/?tab=business&business='.$nextBusiness.'&solicitud=guardada'):'/negocios/portal/?espacio=business&solicitud=guardada';
  if($drafts->ready())$drafts->finish($accountId,$draftId,$nextBusiness?:null);
  header('Location: '.site_path($nextUrl),true,303);exit;
 }
}catch(Throwable $e){$incident=PortalIncidents::technical($e,'business_registration');$error=$e instanceof PDOException?'Ya existe una cuenta con ese correo o no fue posible guardar la solicitud.':$e->getMessage();if($incident)$error='No pudimos completar el registro. Referencia: '.$incident;http_response_code($incident?503:400);}
// A rejected submission must keep the private files visibly recoverable too.
if($error!==''&&$_SERVER['REQUEST_METHOD']==='POST')$savedDraftUploads=$drafts->uploads($draftId);
require_once dirname(__DIR__).'/_portal-shell.php';
require_once dirname(__DIR__,2).'/app/BusinessRegistrationPresentation.php';
require_once dirname(__DIR__,2).'/app/BusinessRegistrationPlan.php';
require_once dirname(__DIR__,2).'/app/SubscriptionPricing.php';
$accountLayout=account_layout_context();
$pricing=new SubscriptionPricing($pdo);$periodLabels=[];
foreach(BusinessSubscriptionChoice::PERIODS as $period=>$label)$periodLabels[$period]=$pricing->periodLabel($period);
$planRules=BusinessRegistrationPlan::all();
$planCode=is_string($_POST['plan_code']??null)?$_POST['plan_code']:'featured';
BusinessRegistrationPresentation::render($accountLayout,[
    'input'=>$_POST,'csrf'=>(string)$_SESSION['csrf'],'error'=>$error,
    'places'=>$destinations,'selectedPlaceId'=>$destinationId,'categories'=>$categories,'account'=>$currentAccount,
    'subscriptionOptions'=>$subscriptionOptions,'selectedPlan'=>(string)($_POST['plan_code']??$registrationPurchase['plan']??''),
    'selectedPeriod'=>(string)($_POST['billing_frequency']??$registrationPurchase['period']??'monthly'),
    'periodLabels'=>$periodLabels,'addonPrices'=>$addonPrices,'operatorExperiences'=>$operatorExperiences,
    'selectedOperatorExperienceId'=>$selectedOperatorExperienceId,'operatorPriceText'=>$pricing->operatorText(),
    'planRules'=>$planRules,'descriptionLimit'=>$planRules[$planCode]['description_limit']??300,
    'draftId'=>$draftId,'draftRevision'=>$draftRevision,'resumeStep'=>$resumeStep,'savedDraftUploads'=>$savedDraftUploads,
    'phone'=>BusinessPhonePresentation::context($_POST['channels']??[],$_POST['business_phone']??null),
],dirname(__DIR__));

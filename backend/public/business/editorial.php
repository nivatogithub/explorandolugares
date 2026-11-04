<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialService.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialSelector.php';
require_once dirname(__DIR__,2).'/app/BusinessOwnershipEvidence.php';
require_once dirname(__DIR__,2).'/app/BusinessEditorialPreview.php';
require_once dirname(__DIR__,2).'/app/AccountMailConfiguration.php';
require_once dirname(__DIR__,2).'/app/BusinessAccountPortalService.php';
require_once dirname(__DIR__,2).'/app/ExperienceOperatorAddon.php';
require_once dirname(__DIR__).'/admin/_asset.php';
header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');
$admin=($_GET['admin']??'')==='1';$account=null;
if($admin){require_admin_session();}
else{require_business_portal_access();require_business_session();$account=(new BusinessAccountPortalService(db()))->current((int)$_SESSION['business_account_id']);if(!$account){unset($_SESSION['business_account_id']);require_business_session();}}
$business=(int)($_GET['business']??0);$editorial=new BusinessEditorialService(db(),private_storage_path('business-image-references'));$error='';
try{
 if(!$editorial->ready())throw new RuntimeException('El circuito editorial requiere instalar la actualización 049. La ficha pública permanece intacta.');
 if(!$admin)$editorial->authorize((int)$account['id'],$business);
 $v=$editorial->latest($business);$plan=$editorial->options($business);$payload=$editorial->payload($v,$business);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf();if((int)($_POST['version_id']??0)!==(int)($v['id']??0))throw new RuntimeException('La versión cambió. Recarga antes de continuar.');$action=(string)($_POST['action']??'');
  if($action==='verify_document'&&$admin){(new BusinessOwnershipEvidence(db(),private_storage_path('business-representation-evidence')))->verifyAndDelete($business,(int)($_POST['evidence_id']??0),(string)$_SESSION['admin']);}
  elseif($action==='save'){if(!$admin)throw new RuntimeException('El contenido enviado está en revisión. Solo puedes aprobar la prepublicación o solicitar correcciones cuando la recibas.');$editorial->save((int)($account['id']??0),$business,(int)($_POST['version_id']??0),$_POST,!empty($_POST['image_consent']),true,(string)$_SESSION['admin']??'',$_FILES['reference_images']??null);}
  elseif(!$v)throw new RuntimeException('Primero guarda un borrador.');
  elseif($action==='notify'&&$admin){require_once dirname(__DIR__,2).'/app/BusinessEditorialMail.php';require_once dirname(__DIR__,2).'/app/BrevoSettings.php';$simulation=!empty($config['local_test']);$settings=$simulation?BusinessEditorialMail::localSettings((string)($_SERVER['HTTP_HOST']??''),dirname(__DIR__,2).'/private/mailbox.json'):AccountMailConfiguration::transactionalSettings($config);$outcome=(new BusinessEditorialMail(db(),$settings,null,$simulation))->send((int)$v['id'],(string)$_SESSION['admin']);$_SESSION['editorial_mail_result']=$outcome;}
  elseif($action==='publish'&&$admin)throw new RuntimeException('La publicación final se autoriza desde el enlace del propietario.');
  elseif(in_array($action,['submit','prepare','approve','request_changes','reject'],true)){
   $editorial->transition((int)$v['id'],$action,(int)($account['id']??0),$admin?(string)$_SESSION['admin']:'',(string)($_POST['note']??''),(string)($_POST['payload_hash']??''),(array)($_POST['quality']??[]));
   if($action==='approve'&&!$admin)$editorial->publishByOwner((int)$v['id'],(int)$account['id'],(string)($_POST['payload_hash']??''));
  }
  else throw new RuntimeException('Acción no válida.');
  if($action==='prepare'&&$admin){try{require_once dirname(__DIR__,2).'/app/BusinessEditorialMail.php';require_once dirname(__DIR__,2).'/app/BrevoSettings.php';$simulation=!empty($config['local_test']);$settings=$simulation?BusinessEditorialMail::localSettings((string)($_SERVER['HTTP_HOST']??''),dirname(__DIR__,2).'/private/mailbox.json'):AccountMailConfiguration::transactionalSettings($config);$_SESSION['editorial_mail_result']=(new BusinessEditorialMail(db(),$settings,null,$simulation))->send((int)$v['id'],(string)$_SESSION['admin']);}catch(Throwable $notificationError){PortalIncidents::technical($notificationError,'editorial_notification');$_SESSION['editorial_mail_result']='mail_not_sent';}}
  header('Location: /business/editorial.php?business='.$business.($admin?'&admin=1':'').'&saved=1',true,303);exit;
 }
 if(isset($_GET['version'])&&(int)$_GET['version']>0){$v=$editorial->version((int)$_GET['version']);if((int)$v['business_id']!==$business)throw new RuntimeException('La versión pertenece a otro negocio.');$payload=$editorial->payload($v,$business);}
 if(isset($_GET['preview'])){
  header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
  if(!$v)throw new RuntimeException('Guarda el borrador para abrir su vista previa.');$locale=$admin&&in_array($_GET['lang']??'es',['es','en','pt-BR'],true)?(string)($_GET['lang']??'es'):'es';$preview=BusinessEditorialPreview::build(db(),$v,$locale);if($admin)$preview['demo_page_url'].='&admin=1';
  if($_GET['preview']==='page'&&in_array($v['plan_code'],['page','page_premium'],true)){$elEditorialPreviewBusiness=$preview;$_GET['idioma']=$locale;require dirname(__DIR__).'/business.php';exit;}
  require_once dirname(__DIR__,2).'/app/views/directory-helpers.php';require_once dirname(__DIR__).'/_portal-shell.php';$businessRecord=$business;$business=$preview;$localePrefix='';$d=['public_contacts'=>[$business['id']=>$business['contacts']]];$t=['featured'=>'Destacado','map'=>'Cómo llegar','verified'=>'Información verificada','public_data'=>'Información pública','more_info'=>'Más información','contact_business'=>'Contacto del negocio','business_page'=>'Ver página del negocio'];
  ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Vista previa privada</title><?=PortalPresentation::assets(false,'preview')?><link rel="stylesheet" href="<?=esc(admin_asset('directory-tiers.css'))?>"></head><body class="business-preview" data-business-preview-readonly><main class="wrap"><section class="directory-tier" data-directory-tier="<?=esc(in_array($business['plan_code'],['page','page_premium'],true)?'page':$business['plan_code'])?>"><div class="directory-grid"><?php require dirname(__DIR__,2).'/app/views/directory-card.php';?></div></section></main></body></html><?php exit;
 }
 $selector=new BusinessEditorialSelector(db());
 $references=$v?$selector->references((int)$v['id']):[];
 $referenceNames=$v?$selector->referenceNames((int)$v['id']):[];
 $opts=(new BusinessAdminService(db(),private_storage_path('business-image-references')))->options();
 $canEdit=$admin&&$v&&in_array($v['status'],['in_editorial','changes_requested'],true);
 $assetOptions=$canEdit&&in_array($plan['code'],['page','page_premium'],true)?$selector->mediaAssets():[];
 $premiumAlias=$canEdit&&$plan['code']==='page_premium'&&!isset($payload['page_alias'])?PremiumBusinessProfile::currentAlias(db(),$business):null;
 $experienceOptions=$canEdit&&$plan['code']==='page_premium'?(new ExperienceOperatorAddon(db()))->options((int)$payload['destination_id']):[];
 $ownershipDocuments=$admin?(new BusinessOwnershipEvidence(db(),private_storage_path('business-representation-evidence')))->documents($business):[];
 $businessPublicId=!$admin&&$v&&$v['status']==='published'?$selector->publicId($business):false;
}catch(Throwable $e){$ref=PortalIncidents::technical($e,'business_editorial');$error=$ref?'No se pudo completar la revisión. Referencia: '.$ref:($e instanceof PDOException?'No se pudo abrir la revisión. Revisa la actualización de base de datos.':$e->getMessage());http_response_code($_SERVER['REQUEST_METHOD']==='POST'?400:503);}
require_once dirname(__DIR__).'/_portal-shell.php';
if($admin){$adminLayout=admin_layout_context();}else{$accountLayout=account_layout_context();}
$mailResult=$_SESSION['editorial_mail_result']??null;if(!empty($mailResult))unset($_SESSION['editorial_mail_result']);
$csrfToken=(string)($_SESSION['csrf']??'');$businessPublicRoot=__DIR__;
$phoneContext=null;
if(!empty($canEdit)&&$plan['code']!=='free'){
    require_once dirname(__DIR__,2).'/app/BusinessPhonePresentation.php';
    $phoneValues=[];foreach($payload['contacts']??[] as $contact)$phoneValues[$contact['channel']]=$contact['contact_value'];
    $phoneContext=BusinessPhonePresentation::context($phoneValues,$_POST['business_phone']??null);
}
$editorAssets=[];foreach(['style.css','account-registration.css','leaflet.css','leaflet.js','admin-business-location.js','account-files.js'] as $asset){$editorAssets[$asset]='/assets/'.$asset.'?v='.substr(hash_file('sha256',dirname(__DIR__).'/assets/'.$asset),0,12);}
require dirname(__DIR__,2).'/app/views/business/editorial.php';

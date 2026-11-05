<?php
declare(strict_types=1);
header(isset($elEditorialPreviewBusiness)?'Cache-Control: private, no-store':'Cache-Control: no-cache, max-age=0, must-revalidate');
header('Vary: User-Agent');

// The package keeps app/ and private/ as siblings of public/, on every host.
$repositoryCandidates=[dirname(__DIR__).'/app/BusinessRepository.php',dirname(__DIR__,2).'/app/BusinessRepository.php',dirname(__DIR__,2).'/backend/app/BusinessRepository.php',dirname(__DIR__,2).'/explorandolugares/app/BusinessRepository.php'];
$repositoryPath=null;foreach($repositoryCandidates as $candidate)if(is_file($candidate)){$repositoryPath=$candidate;break;}
if($repositoryPath===null){http_response_code(503);exit('Negocio temporalmente no disponible.');}
require $repositoryPath;
require_once dirname($repositoryPath).'/BusinessLocationLink.php';
require_once dirname($repositoryPath).'/BusinessPreviewLinks.php';
$incidentClass=dirname($repositoryPath).'/PortalIncidents.php';if(is_file($incidentClass)){require_once $incidentClass;PortalIncidents::install(dirname(dirname($repositoryPath)).'/private/incidents');}
function business_escape(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function business_contact_url(array $contact): ?string {
    $value=trim((string)($contact['contact_value']??''));
    return match((string)($contact['channel']??'')) {
        'email'=>filter_var($value,FILTER_VALIDATE_EMAIL)?'mailto:'.$value:null,
        'phone','phone_fixed'=>preg_match('/^\+?[0-9() .-]{7,24}$/',$value)?'tel:'.preg_replace('/[^+0-9]/','',$value):null,
        'whatsapp'=>preg_match('/^\+?[0-9() .-]{7,24}$/',$value)?'https://wa.me/'.preg_replace('/\D/','',$value):null,
        'website'=>filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true)?$value:null,
        'instagram','facebook','linkedin'=>filter_var($value,FILTER_VALIDATE_URL)&&in_array(parse_url($value,PHP_URL_SCHEME),['https','http'],true)?$value:null,
        default=>null,
    };
}
function business_initials(string $name): string {
    preg_match_all('/\b\p{L}/u',trim($name),$matches);$value=implode('',array_slice($matches[0]??[],0,2));
    return function_exists('mb_strtoupper')?mb_strtoupper($value,'UTF-8'):strtoupper($value);
}
function business_hours_display(string $raw,string $locale):string {
    $data=json_decode($raw,true);if(!is_array($data))return $raw;
    $days=match($locale){'en'=>['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'],'pt-BR'=>['mon'=>'Segunda-feira','tue'=>'Terça-feira','wed'=>'Quarta-feira','thu'=>'Quinta-feira','fri'=>'Sexta-feira','sat'=>'Sábado','sun'=>'Domingo'],default=>['mon'=>'Lunes','tue'=>'Martes','wed'=>'Miércoles','thu'=>'Jueves','fri'=>'Viernes','sat'=>'Sábado','sun'=>'Domingo']};$rows=[];
    foreach($days as $key=>$label){$hours=$data[$key]??null;if(!is_array($hours))continue;$value=!empty($hours['closed'])?match($locale){'en'=>'Closed','pt-BR'=>'Fechado',default=>'Cerrado'}:trim((string)($hours['open']??'').' – '.(string)($hours['close']??''));if($value!=='')$rows[]=$label.': '.$value;}return implode("\n",$rows);
}
function business_price_display(string $value,string $locale):string {return match($value){'consultar'=>match($locale){'en'=>'Ask for prices','pt-BR'=>'Consultar preços',default=>'Consultar precios'},'na'=>match($locale){'en'=>'Not applicable','pt-BR'=>'Não se aplica',default=>'No aplica'},default=>$value};}

$id=(string)($_GET['id']??'');$locale=(string)($_GET['idioma']??'es');
$locale=in_array($locale,['es','en','pt-BR'],true)?$locale:'es';
$requestPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$u=static fn(string $path):string=>$path;
$dbDsn=getenv('EL_DESTINATIONS_DSN')?:'';$dbUser=getenv('EL_DESTINATIONS_DB_USER')?:null;$dbPassword=getenv('EL_DESTINATIONS_DB_PASSWORD')?:null;
if($dbDsn===''){
    // Resolve configuration from the selected backend, never a guessed host path.
    $configPath=dirname($repositoryPath,2).'/private/config.php';
    if(is_file($configPath)){$config=require $configPath;$dbDsn=(string)($config['dsn']??'');$dbUser=$config['db_user']??null;$dbPassword=$config['db_password']??null;}
}
if($dbDsn===''){http_response_code(503);exit('Negocio temporalmente no disponible.');}
try{$pdo=new PDO($dbDsn,$dbUser,$dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$repository=new BusinessRepository($pdo);if(isset($_GET['alias'])){$id=PremiumBusinessProfile::resolve($pdo,(string)$_GET['alias'])??'';}$business=isset($elEditorialPreviewBusiness)?$elEditorialPreviewBusiness:(isset($elBusinessDemoPlan)?businessDemo($pdo,$elBusinessDemoPlan):$repository->findPublicPage($id,$locale));}
catch(Throwable $error){if(class_exists('PortalIncidents',false))PortalIncidents::capture($error,'negocio');error_log('business-page: '.$error->getMessage());http_response_code(503);exit('Negocio temporalmente no disponible.');}
if(!$business){http_response_code(404);exit('Negocio no encontrado.');}
$businessEmbeddedPreview=isset($elEditorialPreviewBusiness)||(isset($elBusinessDemoPlan)&&!empty($embedded));
$copy=match($locale){
    'en'=>['home'=>'Home','destinations'=>'Places','trip'=>'Plan your visit','portal'=>'About the portal','register'=>'List my business','back'=>'Back to the place','about'=>'About this business','gallery'=>'Photos','contact'=>'Contact','location'=>'Location','hours'=>'Opening hours','price'=>'Price range','capacity'=>'Capacity','menu'=>'Menu or catalog','reservations'=>'Bookings and inquiries','map'=>'Open map','privacy'=>'Privacy','tag'=>'Business page'],
    'pt-BR'=>['home'=>'Início','destinations'=>'Lugares','trip'=>'Planeje sua visita','portal'=>'Conheça o portal','register'=>'Cadastre meu negócio','back'=>'Voltar ao lugar','about'=>'Sobre este negócio','gallery'=>'Imagens','contact'=>'Contato','location'=>'Localização','hours'=>'Horário de funcionamento','price'=>'Faixa de preços','capacity'=>'Capacidade','menu'=>'Cardápio ou catálogo','reservations'=>'Reservas e consultas','map'=>'Abrir mapa','privacy'=>'Privacidade','tag'=>'Página do negócio'],
    default=>['home'=>'Inicio','destinations'=>'Lugares','trip'=>'Planea tu visita','portal'=>'Conoce el portal','register'=>'Registrar mi negocio','back'=>'Volver al lugar','about'=>'Acerca de este negocio','gallery'=>'Imágenes','contact'=>'Contacto','location'=>'Ubicación','hours'=>'Horario de atención','price'=>'Rango de precios','capacity'=>'Capacidad','menu'=>'Menú o catálogo','reservations'=>'Reservas y consultas','map'=>'Abrir mapa','privacy'=>'Privacidad','tag'=>'Página del negocio'],
};
$prefix=$locale==='en'?'/en':($locale==='pt-BR'?'/pt-br':'');
$destinationUrl=$u($prefix.'/lugares/'.$business['destination_slug'].'/');
$pagePath=$prefix.'/negocios/'.(!empty($business['page_alias'])?'perfil/'.$business['page_alias']:$business['public_id']).'/';
if(!isset($elEditorialPreviewBusiness)&&!isset($elBusinessDemoPlan)&&!in_array($locale,$business['available_locales'],true)){
    // Preserve access through the existing fallback, but never index a fake translation.
    $reference=in_array('es',$business['available_locales'],true)?'es':($business['available_locales'][0]??null);
    if($reference===null){http_response_code(404);exit('Negocio no encontrado.');}
    $referencePrefix=match($reference){'en'=>'/en','pt-BR'=>'/pt-br',default=>''};
    header('Location: '.$referencePrefix.'/negocios/'.(!empty($business['page_alias'])?'perfil/'.$business['page_alias']:$business['public_id']).'/',true,301);exit;
}
if(!isset($elEditorialPreviewBusiness)&&(preg_match('~/negocio\.php$~',$requestPath)||((!empty($business['page_alias'])||isset($_GET['alias']))&&$requestPath!==$pagePath))){
    header('Location: '.$pagePath,true,301);exit;
}
$publicOrigin=rtrim(getenv('EL_PUBLIC_BASE_URL')?:'https://explorandolugares.com','/');
require_once dirname($repositoryPath).'/seo/PublicSeo.php';
$location=trim((string)($business['destination_name']??''));
$title=$business['display_name'].' · '.$business['category_label'].($location!==''?' · '.$location:'').' | Explorando Lugares';
$description=PublicSeo::description((string)($business['summary']?:$business['description']));
$logo=$business['logo'];$cover=$business['cover'];$hasContact=isset($business['features']['direct_contact']);
$businessLanguagePaths=[];
foreach($business['available_locales']??[$locale] as $language)$businessLanguagePaths[$language]=PublicSeo::prefix($language).'/negocios/'.(!empty($business['page_alias'])?'perfil/'.$business['page_alias']:$business['public_id']).'/';
$seoHead='';
if(!isset($elBusinessDemoPlan)&&!isset($elEditorialPreviewBusiness)){
    $image=$cover?:$logo;$imageUrl=$image?$publicOrigin.'/'.ltrim($image['asset_path'],'/'):null;
    $seoHead=PublicSeo::alternates($businessLanguagePaths,$publicOrigin).PublicSeo::social($title,$description,$publicOrigin.$pagePath,$locale,$imageUrl)
        .PublicSeo::jsonLd(PublicSeo::businessGraph($business,$publicOrigin.$pagePath,$title,$description,$locale,$imageUrl));
}
$presentationContext=['locale'=>$locale,'path'=>$pagePath,'languages'=>$businessLanguagePaths,'hide_account_language'=>isset($elBusinessDemoPlan),'disable_contacts'=>isset($elBusinessDemoPlan)];
require_once __DIR__.'/_portal-shell.php';
?><!doctype html><html lang="<?=business_escape($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=business_escape($description)?>"><meta name="robots" content="<?=(isset($elBusinessDemoPlan)||isset($elEditorialPreviewBusiness))?'noindex,nofollow':'index,follow'?>"><link rel="canonical" href="<?=business_escape($publicOrigin.$pagePath)?>"><?=$seoHead?><title><?=business_escape((isset($elEditorialPreviewBusiness)?'Vista previa privada · ':'').$title)?></title><?=PortalPresentation::assets(false,'public','css')?><link rel="stylesheet" href="<?=business_escape($u('/assets/style.css?v=business-pages-v2'))?>"><link rel="icon" href="<?=business_escape($u('/assets/brand/explorando-lugares-logo-v2-transparent.png'))?>" type="image/png"><?php if(!isset($elBusinessDemoPlan)&&!isset($elEditorialPreviewBusiness)):?><?=PortalPresentation::assets(false,'public','js')?><?php endif ?></head><body>
<a class="skip" href="#contenido"><?=business_escape($locale==='en'?'Skip to content':($locale==='pt-BR'?'Pular para o conteúdo':'Saltar al contenido'))?></a>
<?php if(!$businessEmbeddedPreview):?><?=PortalPresentation::header($presentationContext)?><?php endif ?>
<main id="contenido" class="wrap business-profile-page"><?php if(isset($elBusinessDemoPlan)):?><p class="notice">Ejemplo ficticio · Esta es la misma página que usa el portal. Las imágenes son ilustrativas. Los iconos de contacto son demostrativos y no abren enlaces. <?php if(!$businessEmbeddedPreview):?><a href="/negocios-locales/suscripciones/">Volver a suscripciones</a><?php endif?></p><?php endif?><?php $mapUrl=BusinessLocationLink::forBusiness($business);?><section class="business-profile-hero"><div class="business-profile-topline"><div class="business-profile-meta"><p class="eyebrow"><span class="business-profile-page-label"><?=business_escape($copy['tag'])?> · </span><?=business_escape($business['category_label'])?></p><span class="business-profile-plan-badge"><?=business_escape($business['plan_name']?:$copy['tag'])?></span></div><?php if(!$businessEmbeddedPreview):?><a class="business-profile-back" href="<?=business_escape($destinationUrl)?>"><?=business_escape($copy['back'])?> →</a><?php endif ?></div><div class="business-profile-copy"><h1><?=business_escape($business['display_name'])?></h1><?php $presentation=trim((string)$business['description'])?:$business['summary'];if($presentation!==''):?><p class="intro business-profile-intro"><?=nl2br(business_escape($presentation))?></p><?php endif;?><?php if($hasContact):?><nav class="business-profile-contact" aria-label="<?=business_escape($copy['contact'])?>"><?php foreach(BusinessContactVisibility::filter((string)$business['plan_code'],$business['contacts']) as $contact):$href=business_contact_url($contact);if(!$href)continue;$label=BusinessContactVisibility::label((string)$contact['channel'],$locale);?><a class="business-channel business-channel-<?=business_escape($contact['channel'])?>" <?=BusinessPreviewLinks::attributes($href,$businessEmbeddedPreview||isset($elBusinessDemoPlan),str_starts_with($href,'http'))?> aria-label="<?=business_escape($label)?>" title="<?=business_escape($label)?>"><?=BusinessContactVisibility::icon((string)$contact['channel'])?></a><?php endforeach;?><?php if($mapUrl!==''):?><a class="business-channel business-channel-location" <?=BusinessPreviewLinks::attributes($mapUrl,$businessEmbeddedPreview||isset($elBusinessDemoPlan),str_starts_with($mapUrl,'http'))?> aria-label="<?=business_escape($copy['map'])?>" title="<?=business_escape($copy['map'])?>"><?=BusinessContactVisibility::icon('location')?></a><?php endif ?></nav><?php endif;?><?php if(trim((string)$business['menu_url'])!==''):?><p><a class="text-link" <?=BusinessPreviewLinks::attributes($business['menu_url'],$businessEmbeddedPreview,true)?>><?=business_escape($copy['menu'])?> ↗</a></p><?php endif ?></div><?php if($cover||$logo):?><figure class="business-profile-image"><?php $heroMedia=$cover?:$logo;?><img src="<?=business_escape($u('/'.$heroMedia['asset_path']))?>" alt="<?=business_escape($heroMedia['alt_text']??$business['display_name'])?>" width="900" height="675" fetchpriority="high"></figure><?php else:?><div class="business-profile-initials" aria-hidden="true"><?=business_escape(business_initials($business['display_name']))?></div><?php endif;?></section>
<?php if(($business['plan_code']??'')==='page_premium'&&count($business['category_codes']??[])>1):?><section class="business-service-tags" aria-label="<?=business_escape($locale==='en'?'Services':($locale==='pt-BR'?'Serviços':'Servicios'))?>"><?php foreach($business['category_codes'] as $categoryIndex=>$categoryCode):?><span class="business-service-tag"><?=BusinessCategories::icon($categoryCode)?><span><?=business_escape($business['category_labels'][$categoryIndex]??$categoryCode)?></span></span><?php endforeach ?></section><?php endif ?>
<?php $hours=business_hours_display((string)($business['opening_hours']??''),$locale);if($hours!==''):?><section class="section business-profile-hours"><h2><?=business_escape($copy['hours'])?></h2><p><?=nl2br(business_escape($hours))?></p></section><?php endif ?>
<?php if(($business['plan_code']??'')==='page_premium'&&!empty($business['showcase']['products'])):?><section class="section"><h2><?=business_escape($locale==='en'?'Signature products and services':($locale==='pt-BR'?'Produtos e serviços de destaque':'Productos y servicios estrella'))?></h2><div class="business-products-grid" data-product-count="<?=min(5,count($business['showcase']['products']))?>"><?php foreach(array_slice($business['showcase']['products'],0,5) as $product):?><article class="business-product"><img src="<?=business_escape($u('/'.$product['asset_path']))?>" alt="<?=business_escape($product['name'])?>" width="640" height="480" loading="lazy"><div class="business-product-copy"><h3><?=business_escape($product['name'])?></h3><p><?=business_escape($product['description'])?></p></div></article><?php endforeach ?></div></section><?php endif ?>
<?php if(($business['plan_code']??'')==='page_premium'&&!empty($business['showcase']['experiences'])):?><section class="section business-profile-description"><h2><?=business_escape($locale==='en'?'Experiences in this place':($locale==='pt-BR'?'Experiências neste lugar':'Experiencias del lugar'))?></h2><div class="business-products-grid"><?php foreach($business['showcase']['experiences'] as $experience):?><article class="business-product"><?php if(!empty($experience['asset_path'])):?><img src="<?=business_escape($u('/'.$experience['asset_path']))?>" alt="<?=business_escape($experience['name'])?>" width="640" height="480" loading="lazy"><?php endif ?><div class="business-product-copy"><h3><?=business_escape($experience['name'])?></h3><p><?=business_escape($experience['description'])?></p><p><a <?=BusinessPreviewLinks::attributes($destinationUrl.'#experiencias-locales',$businessEmbeddedPreview)?>><?=business_escape($locale==='en'?'View in the place':($locale==='pt-BR'?'Ver no lugar':'Ver en el lugar'))?> →</a></p></div></article><?php endforeach ?></div></section><?php endif ?>
<?php if(isset($business['features']['photo_gallery'])&&$business['gallery']&&$business['plan_code']!=='page_premium'):?><section class="section business-profile-gallery"><p class="eyebrow"><?=business_escape($copy['gallery'])?></p><div class="business-gallery-grid"><?php foreach($business['gallery'] as $image):?><figure><img src="<?=business_escape($u('/'.$image['asset_path']))?>" alt="<?=business_escape($image['alt_text']??'')?>" width="<?=business_escape($image['width']?:640)?>" height="<?=business_escape($image['height']?:480)?>" loading="lazy" decoding="async"><?php if(!empty($image['caption'])):?><figcaption><?=business_escape($image['caption'])?></figcaption><?php endif;?></figure><?php endforeach;?></div></section><?php endif;?></main>
<?php if(!$businessEmbeddedPreview):?><?=PortalPresentation::footer($presentationContext)?><?php endif ?></body></html>

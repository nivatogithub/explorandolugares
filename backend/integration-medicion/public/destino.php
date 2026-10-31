<?php
declare(strict_types=1);
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('Vary: User-Agent');

$repositoryCandidates=[
    dirname(__DIR__,2).'/app/DestinationRepository.php',
    dirname(__DIR__,2).'/backend/app/DestinationRepository.php',
    '/home/d9rgqsde6im4/explorandolugares/app/DestinationRepository.php',
    dirname(__DIR__,2).'/explorandolugares/app/DestinationRepository.php',
    dirname(__DIR__).'/app/DestinationRepository.php',
];
$repositoryPath=null;
foreach($repositoryCandidates as $candidate) if(is_file($candidate)){$repositoryPath=$candidate;break;}
if($repositoryPath===null){http_response_code(503);exit('Destino temporalmente no disponible.');}
require $repositoryPath;
$incidentClass=dirname($repositoryPath).'/PortalIncidents.php';if(is_file($incidentClass)){require_once $incidentClass;PortalIncidents::install(dirname(dirname($repositoryPath)).'/private/incidents');}

require_once dirname($repositoryPath).'/views/directory-helpers.php';

require_once dirname($repositoryPath).'/views/destination-copy.php';

$slug=(string)($_GET['destino']??'');$locale=(string)($_GET['idioma']??'es');
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)) { http_response_code(404); exit('Destino no encontrado.'); }
$preview=($_GET['preview']??'')==='1';
if($preview){header('Cache-Control: no-store');session_name('el_session');$secure=($_SERVER['HTTPS']??'')==='on';session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);ini_set('session.use_strict_mode','1');session_start();if(empty($_SESSION['admin'])||time()-(int)($_SESSION['last_seen']??0)>1800){http_response_code(403);exit('La vista previa requiere una sesión activa de administración.');}$_SESSION['last_seen']=time();header('X-Robots-Tag: noindex, nofollow');}
$dbPath=getenv('EL_DESTINATIONS_DSN') ?: '';$dbUser=getenv('EL_DESTINATIONS_DB_USER')?:null;$dbPassword=getenv('EL_DESTINATIONS_DB_PASSWORD')?:null;
if ($dbPath==='') {
    $configPath=dirname(__DIR__).'/private/config.php';
    if(!is_file($configPath))$configPath=dirname(__DIR__,2).'/explorandolugares/private/config.php';
    if (is_file($configPath)) { $config=require $configPath;$dbPath=(string)($config['dsn']??'');$dbUser=$config['db_user']??null;$dbPassword=$config['db_password']??null; }
}
if ($dbPath==='') { http_response_code(503); exit('Destino temporalmente no disponible.'); }
// Local public showcase keeps review styling without relaxing preview authentication.
$localShowcase=false;$localConfigFile=dirname(__DIR__).'/private/config.php';
if(is_file($localConfigFile)){$localCfg=require $localConfigFile;$localShowcase=!empty($localCfg['local_test'])&&str_contains($dbPath,'dbname=explorando_preprod');}
try { $pdo=new PDO($dbPath,$dbUser,$dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$repo=new DestinationRepository($pdo);$d=$repo->findPublished($slug,$locale,$preview); }
catch(Throwable $error) {if(class_exists('PortalIncidents',false))PortalIncidents::capture($error,'destino'); error_log('destination-page: '.$error->getMessage());http_response_code(503);exit('Destino temporalmente no disponible.'); }
if (!$d) { http_response_code(404); exit('Destino no encontrado.'); }
$locale=$d['locale'];$t=langCopy($locale);$name=$d['display_name'];
// Release approved by the owner: presentation does not require local_test or preview.
$publishedPresentation=in_array($slug,['cartagena','bogota','medellin','santa-marta'],true);
// The renderer and home selection use the same current presentation data.
require_once dirname($repositoryPath).'/DestinationPresentation.php';
if($hero=DestinationPresentation::media($slug,null,$locale)){$d['hero']=$hero;$d['history_media']=null;}
foreach($d['places'] as &$entry)if($image=DestinationPresentation::media($slug,$entry['slug'],$locale))$entry['media']=[$image];
unset($entry);

$places=array_values(array_filter($d['places'],static fn($place)=>($place['type']??'place')!=='experience'));
$experiences=array_values(array_filter($d['places'],static fn($place)=>($place['type']??'place')==='experience'));
$requestPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$t['leave_short']=$locale==='en'?'Share your business details':($locale==='pt-BR'?'Deixe seus dados para participar':'Déjanos tus datos para participar');
$t['contact_business']=$locale==='en'?'Contact business':($locale==='pt-BR'?'Entrar em contato':'Contactar negocio');
$t['ideas_heading']=$locale==='en'?'What to see in':($locale==='pt-BR'?'O que conhecer em':'Qué conocer en');
$t['directory_heading']=$locale==='en'?'Directory for':($locale==='pt-BR'?'Diretório de':'Directorio de');
$t['events_heading']=$locale==='en'?'Events in':($locale==='pt-BR'?'Eventos em':'Eventos en');
$t['more_info']=$locale==='en'?'More information':($locale==='pt-BR'?'Mais informações':'Más información');
$t['business_page']=$locale==='en'?'Visit business page':($locale==='pt-BR'?'Ver página do negócio':'Ver página del negocio');
$t['featured']=$locale==='en'?'Featured':($locale==='pt-BR'?'Em destaque':'Destacado');
$t['premium']=$locale==='en'?'Premium':($locale==='pt-BR'?'Premium':'Premium');
$t['contact_business']=$locale==='en'?'Contact business':($locale==='pt-BR'?'Entrar em contato':'Contactar negocio');
require_once dirname($repositoryPath).'/ReviewedMapLinks.php';
$map=ReviewedMapLinks::catalog()['general'][$slug]??('https://www.google.com/maps/search/?api=1&query='.rawurlencode($name.', '.$d['department'].', Colombia'));
$localePrefix=$locale==='es'?'':($locale==='pt-BR'?'pt-br/':'en/');
$interest=u('/'.$localePrefix.'negocios-locales/interes/index.html').'?municipio='.rawurlencode($name).'#contacto';
$localizedRoot=u('/'.$localePrefix);
$langEsUrl=u('/destinos/'.$slug.'/');$langEnUrl=u('/en/destinos/'.$slug.'/');$langPtUrl=u('/pt-br/destinos/'.$slug.'/');
// Keep private preview when switching language; authentication remains required.
if($preview){$langEsUrl.='?preview=1';$langEnUrl.='?preview=1';$langPtUrl.='?preview=1';}
$canonicalPath=match($locale){'en'=>'/en/destinos/'.$slug.'/','pt-BR'=>'/pt-br/destinos/'.$slug.'/',default=>'/destinos/'.$slug.'/'};
$publicOrigin=rtrim(getenv('EL_PUBLIC_BASE_URL')?:'https://explorandolugares.com','/');
$canonicalUrl=$publicOrigin.$canonicalPath;
// Redirect only direct template requests, after validating publication/preview.
if(!$preview && preg_match('~/destino\.php$~',$requestPath)){
    header('Location: '.$canonicalPath,true,301);exit;
}
$historySources=array_values(array_filter($d['sources'],static fn($source)=>$source['section_key']==='history'));
$placeSources=array_values(array_filter($d['sources'],static fn($source)=>$source['section_key']==='places'));
require_once dirname($repositoryPath).'/PortalPresentation.php';
$presentationContext=['locale'=>$locale,'path'=>'/destinos/'.$slug.'/'];
$alternateUrls=PortalPresentation::alternates($presentationContext);
$title=$name.', '.$d['department'].' | Explorando Lugares';
require_once dirname($repositoryPath).'/DestinationPresentation.php';
ob_start(static fn(string $html):string=>DestinationPresentation::normalize($html,$slug,$locale));
?><!doctype html><html lang="<?=e($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=e($d['meta_description'])?>"><meta name="robots" content="<?=$preview?'noindex,nofollow':'index,follow'?>"><?php if(!$preview):?><link rel="canonical" href="<?=e($canonicalUrl)?>"><link rel="alternate" hreflang="es" href="<?=e($alternateUrls['es'])?>"><link rel="alternate" hreflang="en" href="<?=e($alternateUrls['en'])?>"><link rel="alternate" hreflang="pt-BR" href="<?=e($alternateUrls['pt-BR'])?>"><link rel="alternate" hreflang="x-default" href="<?=e($alternateUrls['es'])?>"><?php endif ?><?php if(!$preview): ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Explorando Lugares">
<meta property="og:title" content="<?=e($title)?>">
<meta property="og:description" content="<?=e($d['meta_description'])?>">
<meta property="og:url" content="<?=e($canonicalUrl)?>">
<meta property="og:locale" content="<?=e(match($locale){'en'=>'en_US','pt-BR'=>'pt_BR',default=>'es_CO'})?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?=e($title)?>">
<meta name="twitter:description" content="<?=e($d['meta_description'])?>">
<?php if($d['hero']): $socialImage=$publicOrigin.'/'.ltrim(mediaPath($d['hero']),'/'); ?>
<meta property="og:image" content="<?=e($socialImage)?>">
<meta property="og:image:alt" content="<?=e($d['hero']['alt']??$name)?>">
<meta name="twitter:image" content="<?=e($socialImage)?>">
<meta name="twitter:image:alt" content="<?=e($d['hero']['alt']??$name)?>">
<?php endif; endif; ?>
<title><?=e($title)?></title><link rel="stylesheet" href="<?=e(u('/assets/style.css?v=destination-preview-3'))?>"><link rel="icon" href="<?=e(u('/assets/brand/explorando-lugares-logo-v2-transparent.png'))?>" type="image/png"><link rel="stylesheet" href="/assets/directory-tiers.css?v=3"><!-- zipaquira-reference-review-v3 --><?php if($localShowcase && $slug==='zipaquira'):?><script defer src="/assets/zipaquira-revision/visual-gate.js?v=6"></script><?php endif;?><script defer src="/assets/place-relations.js?v=zipaquira-v7"></script></head><body data-place-presentation="<?=($localShowcase||$publishedPresentation)?'1':'0'?>" class="<?=(($preview || $localShowcase || $publishedPresentation) && $slug==='cartagena')?'el-review-rest':''?>">
<a class="skip" href="#contenido"><?=e($t['skip'])?></a>
<?php if($preview):?><div class="destination-preview-banner"><strong><?=$locale==='en'?'Private preview':($locale==='pt-BR'?'Prévia privada':'Vista previa privada')?></strong><span><?=$locale==='en'?'This view may include unpublished business listings. Destination status: ':($locale==='pt-BR'?'Esta prévia pode incluir negócios ainda não publicados. Estado do destino: ':'Esta vista puede incluir negocios aún no publicados. Estado del destino: ')?><?=e($d['publication_status']==='published'?($locale==='en'?'published':($locale==='pt-BR'?'publicado':'publicado')):($locale==='en'?'draft':($locale==='pt-BR'?'rascunho':'borrador')) )?><?=$locale==='en'?'. Only visible while your administrator session is active.':($locale==='pt-BR'?'. Visível somente enquanto sua sessão administrativa estiver ativa.':'. Solo visible mientras tu sesión administrativa esté activa.')?></span></div><?php endif ?>
<header><div class="wrap"><a class="brand" href="<?=e($localizedRoot)?>" aria-label="Explorando Lugares, <?=e($t['home'])?>"><img class="brand-logo" src="<?=e(u('/assets/brand/explorando-lugares-logo-v2-transparent.png'))?>" alt="" width="64" height="64"><span>Explorando <b>Lugares</b></span></a><nav aria-label="Principal"><a href="<?=e($localizedRoot)?>"><?=e($t['home'])?></a><a href="<?=e($localizedRoot)?>destinos/" aria-current="location"><?=e($t['destinations'])?></a><a href="<?=e($localizedRoot)?>planea-tu-visita/"><?=e($t['trip'])?></a><a href="<?=e($localizedRoot)?>etapas/"><?=e($t['portal'])?></a><a href="/cobertura/colombia/">Estamos creciendo</a><a class="pill light" href="<?=e(u('/negocios/registro/?tipo=business'))?>"><?=e($t['register'])?></a><span class="language-switch" aria-label="Idioma"><a href="<?=e($langEsUrl)?>" lang="es" hreflang="es" aria-current="<?=$locale==='es'?'true':'false'?>">ES</a><a href="<?=e($langEnUrl)?>" lang="en" hreflang="en" aria-current="<?=$locale==='en'?'true':'false'?>">EN</a><a href="<?=e($langPtUrl)?>" lang="pt-BR" hreflang="pt-BR" aria-current="<?=$locale==='pt-BR'?'true':'false'?>">PT</a></span></nav></div><div class="header-note wrap"><span><?=e($locale==='en'?'Discover destinations and connect with local life.':($locale==='pt-BR'?'Descubra destinos e conecte-se com a vida local.':'Descubre destinos y conecta con la vida local.'))?></span><span><?=e($locale==='en'?'Travel · Culture · Businesses · Services':($locale==='pt-BR'?'Turismo · Cultura · Negócios · Serviços':'Turismo · Cultura · Comercios · Servicios'))?></span></div></header>
<aside class="pilot-banner" aria-label="<?=e($t['pilot'])?>"><div class="wrap"><div><strong><?=e($t['pilot'])?></strong><span><?=e($t['pilot_text'])?></span></div><a href="<?=e($localizedRoot)?>etapas/index.html"><?=e($t['portal_link'])?></a></div></aside>
<?php require dirname($repositoryPath).'/views/destination-main.php'; ?>
<footer><div class="wrap"><nav class="footer-contact" aria-label="<?=e($t['contact'])?>">
<a class="footer-contact-link footer-contact-whatsapp" href="https://wa.me/573234870366" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp: +57 323 487 0366"><span class="footer-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20.5 11.5a8.4 8.4 0 0 1-12.4 7.4L3.5 20l1.2-4.3A8.4 8.4 0 1 1 20.5 11.5Z"></path><path d="M8.8 8.1c.2-.5.5-.5.7-.5h.6c.2 0 .4.1.5.4l.8 1.9c.1.2.1.4 0 .6l-.5.7c-.1.1-.1.3 0 .4.5.9 1.2 1.6 2.1 2.1.1.1.3.1.4 0l.7-.5c.2-.1.4-.1.6 0l1.9.8c.3.1.4.3.4.5v.6c0 .2 0 .5-.5.7-.4.2-1.2.3-2.1 0-1.1-.4-2.4-1.2-3.5-2.3-1.1-1.1-1.9-2.4-2.3-3.5-.3-.9-.2-1.7 0-2.1Z"></path></svg></span></a>
<a class="footer-contact-link footer-contact-instagram" href="https://www.instagram.com/explorandolugaresco/" target="_blank" rel="noopener noreferrer" aria-label="Instagram: @explorandolugaresco"><span class="footer-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.4" cy="6.6" r="1"></circle></svg></span></a>
<a class="footer-contact-link footer-contact-email" href="mailto:contacto@explorandolugares.com" aria-label="<?=e($t['contact'])?>: contacto@explorandolugares.com"><span class="footer-contact-icon" aria-hidden="true"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg></span></a>
</nav><span>Explorando Lugares · <?=e($locale==='en'?'New paths, local connections.':($locale==='pt-BR'?'Novos caminhos, conexões locais.':'Nuevos caminos, conexiones locales.'))?></span><span><a href="<?=e(u('/negocios/registro/?tipo=business'))?>"><?=e($t['register'])?></a> · <a href="<?=e($localizedRoot)?>privacidad/index.html"><?=e($t['privacy'])?></a></span></div></footer>
<script>(()=>{const root=document.getElementById('catalogo-local');if(!root)return;const tabs=[...root.querySelectorAll('[role="tab"]')],cards=[...root.querySelectorAll('.directory-card')];function select(tab,focus=false){const category=tab.dataset.category||'';for(const item of tabs){const active=item===tab;item.setAttribute('aria-selected',String(active));item.tabIndex=active?0:-1;if(active){document.getElementById('directory-panel').setAttribute('aria-labelledby',item.id);if(focus)item.focus();}}for(const card of cards)card.hidden=!!category&&!(card.dataset.categories||card.dataset.category||'').split(' ').includes(category);for(const group of root.querySelectorAll('[data-directory-tier]'))group.hidden=![...group.querySelectorAll('.directory-card')].some(card=>!card.hidden);}for(const tab of tabs){tab.addEventListener('click',()=>select(tab));tab.addEventListener('keydown',event=>{const i=tabs.indexOf(tab);let n;if(event.key==='ArrowRight')n=(i+1)%tabs.length;if(event.key==='ArrowLeft')n=(i-1+tabs.length)%tabs.length;if(event.key==='Home')n=0;if(event.key==='End')n=tabs.length-1;if(n!==undefined){event.preventDefault();select(tabs[n],true);}});}})();</script><script>(()=>{const grid=document.getElementById('atractivos-destino');const button=document.getElementById('cargar-mas-atractivos');if(!grid||!button)return;const cards=[...grid.querySelectorAll('article')];const rowSize=()=>Math.max(1,getComputedStyle(grid).gridTemplateColumns.split(' ').length);let visible=Math.min(rowSize(),cards.length);const update=()=>{cards.forEach((card,index)=>card.hidden=index>=visible);button.hidden=visible>=cards.length;};update();button.addEventListener('click',()=>{visible=Math.min(cards.length,visible+rowSize());update();});})();</script>
<script defer data-el-measurement="1" src="/assets/cobertura/medicion.js?v=fcdcf3ff5e4b"></script><script src="/assets/menu-principal.js?v=c0cb170ec9c6" defer></script><?php if(($preview || $localShowcase || $publishedPresentation) && $slug==='cartagena'):?><link rel="stylesheet" href="/assets/descanso-visual.css?v=3"><link rel="stylesheet" href="/assets/experience-art.css?v=3"><script defer src="/assets/cartagena-compacta.js?v=2"></script><?php endif;?><!-- destino-semirrealista-bogota --><?php if(($preview || $localShowcase || $publishedPresentation) && $slug==='bogota'):?><link rel="stylesheet" href="/assets/bogota-compacta.css?v=2"><script defer src="/assets/bogota-compacta.js?v=5"></script><?php endif;?><!-- destino-semirrealista-santa-marta --><?php if(($preview || $localShowcase || $publishedPresentation) && $slug==='santa-marta'):?><link rel="stylesheet" href="/assets/santa-marta-compacta.css?v=2"><script defer src="/assets/santa-marta-compacta.js?v=4"></script><?php endif;?><!-- destino-semirrealista-medellin --><?php if(($preview || $localShowcase || $publishedPresentation) && $slug==='medellin'):?><link rel="stylesheet" href="/assets/medellin-compacta.css?v=2"><script defer src="/assets/medellin-compacta.js?v=5"></script><?php endif;?><!-- destino-lectura-directa --><?php if(in_array($slug,['zipaquira','santa-fe-de-antioquia','jardin','covenas'],true) || (($preview || $localShowcase || $publishedPresentation) && in_array($slug,['bogota','medellin','santa-marta'],true))):?><link rel="stylesheet" href="/assets/destino-lectura-directa.css?v=2"><script defer src="/assets/destino-lectura-directa.js?v=3"></script><?php endif;?></body></html>

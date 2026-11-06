<?php
declare(strict_types=1);
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('Vary: User-Agent, Cookie');

$repositoryCandidates=[
    dirname(__DIR__,2).'/app/PlaceRepository.php',
    dirname(__DIR__,2).'/backend/app/PlaceRepository.php',
    '/home/d9rgqsde6im4/explorandolugares/app/PlaceRepository.php',
    dirname(__DIR__,2).'/explorandolugares/app/PlaceRepository.php',
    dirname(__DIR__).'/app/PlaceRepository.php',
];
$repositoryPath=null;
foreach($repositoryCandidates as $candidate) if(is_file($candidate)){$repositoryPath=$candidate;break;}
if($repositoryPath===null){http_response_code(503);exit('Lugar temporalmente no disponible.');}
require $repositoryPath;
$incidentClass=dirname($repositoryPath).'/PortalIncidents.php';if(is_file($incidentClass)){require_once $incidentClass;PortalIncidents::install(dirname(dirname($repositoryPath)).'/private/incidents');}

require_once dirname($repositoryPath).'/views/directory-helpers.php';

require_once dirname($repositoryPath).'/views/place-copy.php';

$slug=(string)($_GET['place']??$_GET['destino']??'');$locale=(string)($_GET['locale']??$_GET['idioma']??'es');
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)) { http_response_code(404); exit('Lugar no encontrado.'); }
$preview=($_GET['preview']??'')==='1';
if ($preview) {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    require_once dirname($repositoryPath).'/AccountSessionValidator.php';
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('el_session');
        $secure=($_SERVER['HTTPS']??'')==='on';
        session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
        ini_set('session.use_strict_mode','1');
        session_start();
    }
    if (!AccountSessionValidator::isActiveAdmin($_SESSION)) {
        http_response_code(403);exit('La vista previa requiere una sesión activa de administración.');
    }
}
$dbPath=getenv('EL_DESTINATIONS_DSN') ?: '';$dbUser=getenv('EL_DESTINATIONS_DB_USER')?:null;$dbPassword=getenv('EL_DESTINATIONS_DB_PASSWORD')?:null;
if ($dbPath==='') {
    $configPath=dirname(__DIR__).'/private/config.php';
    if(!is_file($configPath))$configPath=dirname(__DIR__,2).'/explorandolugares/private/config.php';
    if (is_file($configPath)) { $config=require $configPath;$dbPath=(string)($config['dsn']??'');$dbUser=$config['db_user']??null;$dbPassword=$config['db_password']??null; }
}
if ($dbPath==='') { http_response_code(503); exit('Lugar temporalmente no disponible.'); }
// Local public showcase keeps review styling without relaxing preview authentication.
$localShowcase=false;$localConfigFile=dirname(__DIR__).'/private/config.php';
if(is_file($localConfigFile)){$localCfg=require $localConfigFile;$localShowcase=!empty($localCfg['local_test'])&&str_contains($dbPath,'dbname=explorando_preprod');}
try {
    $pdo=new PDO($dbPath,$dbUser,$dbPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    if ($preview) {
        require_once dirname($repositoryPath).'/ProductionRuntimeSettings.php';
        $previewConfigPath=is_file($localConfigFile)?$localConfigFile:($configPath??'');
        $previewUnifiedAdminOnly=true;
        if (is_file($previewConfigPath)) {
            $previewConfig=array_replace_recursive($localCfg??$config??[],ProductionRuntimeSettings::load(dirname($previewConfigPath)));
            $previewUnifiedAdminOnly=!empty($previewConfig['unified_admin_only']);
        }
        AccountSessionValidator::synchronize(static fn(): PDO => $pdo, $_SESSION, $previewUnifiedAdminOnly);
        if (!AccountSessionValidator::isActiveAdmin($_SESSION)) {
            http_response_code(403);exit('La vista previa requiere una sesión activa de administración.');
        }
        $_SESSION['last_seen']=time();
    }
    $placeService=PlaceRepository::fromDatabase($pdo);
    $d=$placeService->findPublished($slug,$locale,$preview);
}
catch(Throwable $error) {if(class_exists('PortalIncidents',false))PortalIncidents::capture($error,'place'); error_log('place-page: '.$error->getMessage());http_response_code(503);exit('Lugar temporalmente no disponible.'); }
if (!$d) { http_response_code(404); exit('Lugar no encontrado.'); }
$locale=$d['locale'];$t=placeInterfaceCopy($locale);$name=$d['display_name'];
$sectionTitles=placeSectionTitles($locale,$name);
require_once dirname($repositoryPath).'/PlaceLabelPresentation.php';
$placeLabels=PlaceLabelPresentation::labels($d,$locale);
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
$interest=u('/'.$localePrefix.'negocios-locales/interes/').'?municipio='.rawurlencode($name).'#contacto';
$localizedRoot=u('/'.$localePrefix);
$langEsUrl=u('/lugares/'.$slug.'/');$langEnUrl=u('/en/lugares/'.$slug.'/');$langPtUrl=u('/pt-br/lugares/'.$slug.'/');
// Keep private preview when switching language; authentication remains required.
if($preview){$langEsUrl.='?preview=1';$langEnUrl.='?preview=1';$langPtUrl.='?preview=1';}
$canonicalPath=match($locale){'en'=>'/en/lugares/'.$slug.'/','pt-BR'=>'/pt-br/lugares/'.$slug.'/',default=>'/lugares/'.$slug.'/'};
$publicOrigin=rtrim(getenv('EL_PUBLIC_BASE_URL')?:'https://explorandolugares.com','/');
$canonicalUrl=$publicOrigin.$canonicalPath;
// Redirect only direct template requests, after validating publication/preview.
if(!$preview && preg_match('~/(?:place|destino)\.php$~',$requestPath)){
    header('Location: '.$canonicalPath,true,301);exit;
}
// Personalized bookmark state is rendered on the server; anonymous guides do not start a session.
require_once dirname($repositoryPath).'/SavedPlaceAction.php';
$bookmarkAuthenticated=false;$bookmarkSaved=false;$bookmarkCsrf='';
if(session_status()===PHP_SESSION_ACTIVE||isset($_COOKIE['el_session'])){
 require_once dirname($repositoryPath).'/bootstrap.php';
 $bookmarkAccountId=(int)($_SESSION['business_account_id']??0);
 $bookmarkAuthenticated=$bookmarkAccountId>0&&time()-(int)($_SESSION['business_last_seen']??0)<=1800;
 if($bookmarkAuthenticated){
  require_once dirname($repositoryPath).'/SavedPlaceSelector.php';
  $bookmarkSaved=(new SavedPlaceSelector($pdo))->contains($bookmarkAccountId,$slug);
  $bookmarkCsrf=(string)$_SESSION['csrf'];$_SESSION['business_last_seen']=time();
  header('Cache-Control: private, no-store');
 }
}
$bookmark=SavedPlaceAction::context($slug,$locale,$bookmarkAuthenticated,$bookmarkSaved,$bookmarkCsrf);
$bookmark['error']=($_GET['guardado']??'')==='error';
$historySources=array_values(array_filter($d['sources'],static fn($source)=>$source['section_key']==='history'));
$placeSources=array_values(array_filter($d['sources'],static fn($source)=>$source['section_key']==='places'));
require_once dirname($repositoryPath).'/PortalPresentation.php';
require_once dirname($repositoryPath).'/seo/PublicSeo.php';
$placeLanguagePaths=[];
foreach($d['available_locales'] as $language)$placeLanguagePaths[$language]=PublicSeo::prefix($language).'/lugares/'.$slug.'/';
$presentationContext=['locale'=>$locale,'path'=>'/lugares/'.$slug.'/','languages'=>$placeLanguagePaths];
if($preview)$presentationContext['languages']=array_map(static fn(string $path):string=>$path.'?preview=1',$placeLanguagePaths);
$title=PublicSeo::placeTitle($name,$locale,(string)$d['department']);
$schemaImage=$d['hero']?$publicOrigin.'/'.ltrim(mediaPath($d['hero']),'/'):null;
?><!doctype html><html lang="<?=e($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=e($d['meta_description'])?>"><meta name="robots" content="<?=$preview?'noindex,nofollow':'index,follow'?>"><?php if(!$preview):?><link rel="canonical" href="<?=e($canonicalUrl)?>"><?=PublicSeo::alternates($placeLanguagePaths,$publicOrigin)?><?php endif ?><?php if(!$preview): ?>
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
<?php if(!$preview)echo PublicSeo::jsonLd(PublicSeo::placeGraph($d,$canonicalUrl,$publicOrigin,$locale,$schemaImage)); ?>
<?php require_once __DIR__.'/_portal-shell.php'; ?><title><?=e($title)?></title><?=PortalPresentation::assets(false,'public','css')?><link rel="stylesheet" href="<?=e(u('/assets/style.css?v=destination-preview-3'))?>"><link rel="stylesheet" href="/assets/directory-tiers.css?v=3"><script defer src="/assets/place-relations.js?v=zipaquira-v7"></script><script defer src="/assets/compact-business-cards.js"></script><script defer src="/assets/load-more-cards.js"></script><script defer src="/assets/saved-place.js?v=<?=substr(hash_file('sha256',__DIR__.'/assets/saved-place.js'),0,12)?>"></script><?=PortalPresentation::assets(false,'public','js')?></head><body class="destination-direct-reading">
<a class="skip" href="#contenido"><?=e($t['skip'])?></a>
<?php if($preview):?><div class="destination-preview-banner"><strong><?=$locale==='en'?'Private preview':($locale==='pt-BR'?'Prévia privada':'Vista previa privada')?></strong><span><?=$locale==='en'?'This view may include unpublished business listings. Place status: ':($locale==='pt-BR'?'Esta prévia pode incluir negócios ainda não publicados. Estado do lugar: ':'Esta vista puede incluir negocios aún no publicados. Estado del lugar: ')?><?=e($d['publication_status']==='published'?($locale==='en'?'published':($locale==='pt-BR'?'publicado':'publicado')):($locale==='en'?'draft':($locale==='pt-BR'?'rascunho':'borrador')) )?><?=$locale==='en'?'. Only visible while your administrator session is active.':($locale==='pt-BR'?'. Visível somente enquanto sua sessão administrativa estiver ativa.':'. Solo visible mientras tu sesión administrativa esté activa.')?></span></div><?php endif ?>
<?php require_once __DIR__.'/_portal-shell.php'; echo PortalPresentation::header($presentationContext); ?>
<?php require dirname($repositoryPath).'/views/place-main.php'; ?>

<?=PortalPresentation::footer($presentationContext)?>
<script defer src="/assets/place-interactions.js?v=<?=substr(hash_file('sha256',__DIR__.'/assets/place-interactions.js'),0,12)?>"></script>
</body></html>

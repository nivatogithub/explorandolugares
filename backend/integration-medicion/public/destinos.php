<?php
declare(strict_types=1);
header('Cache-Control: no-cache, max-age=0, must-revalidate');

$repositoryPath=dirname(__DIR__).'/app/DestinationRepository.php';
if(!is_file($repositoryPath)){http_response_code(503);exit('Lugares temporalmente no disponibles.');}
require_once $repositoryPath;
$locale=(string)($_GET['idioma']??'es');
if(!in_array($locale,['es','en','pt-BR'],true)){$locale='es';}
$dsn=getenv('EL_DESTINATIONS_DSN')?:'';$user=getenv('EL_DESTINATIONS_DB_USER')?:null;$password=getenv('EL_DESTINATIONS_DB_PASSWORD')?:null;
if($dsn===''){
    $configPath=dirname(__DIR__).'/private/config.php';
    if(is_file($configPath)){$config=require $configPath;$dsn=(string)($config['dsn']??'');$user=$config['db_user']??null;$password=$config['db_password']??null;}
}
if($dsn===''){http_response_code(503);exit('Lugares temporalmente no disponibles.');}
try{$pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$destinations=(new DestinationRepository($pdo))->listPublished($locale);}
catch(Throwable $error){error_log('destination-index: '.$error->getMessage());http_response_code(503);exit('Lugares temporalmente no disponibles.');}

$prefix=$locale==='es'?'':($locale==='en'?'/en':'/pt-br');
$copy=$locale==='en'
    ?['title'=>'Places','heading'=>'Find your <em>next place.</em>','intro'=>'Places to discover, published once their information has been reviewed.','empty'=>'There are no published places yet.','home'=>'Home','places'=>'Places','trip'=>'Plan your visit','portal'=>'About the portal','business'=>'Local businesses']
    :($locale==='pt-BR'
        ?['title'=>'Lugares','heading'=>'Encontre seu <em>próximo lugar.</em>','intro'=>'Lugares para descobrir, publicados quando suas informações forem revisadas.','empty'=>'Ainda não há lugares publicados.','home'=>'Início','places'=>'Lugares','trip'=>'Planeje sua visita','portal'=>'Conheça o portal','business'=>'Negócios locais']
        :['title'=>'Lugares','heading'=>'Encuentra tu <em>próximo lugar.</em>','intro'=>'Lugares para descubrir, publicados cuando su información ha sido revisada.','empty'=>'Aún no hay lugares publicados.','home'=>'Inicio','places'=>'Lugares','trip'=>'Planea tu visita','portal'=>'Conoce el portal','business'=>'Negocios locales']);
$canonical='https://explorandolugares.com'.($prefix===''?'/destinos/':$prefix.'/destinos/');
function h(string $value):string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="<?=h($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="<?=h(strip_tags($copy['intro']))?>"><meta name="robots" content="index,follow"><link rel="canonical" href="<?=h($canonical)?>"><link rel="alternate" hreflang="es" href="/destinos/"><link rel="alternate" hreflang="en" href="/en/destinos/"><link rel="alternate" hreflang="pt-BR" href="/pt-br/destinos/"><link rel="alternate" hreflang="x-default" href="/destinos/"><title><?=h($copy['title'])?> | Explorando Lugares</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/destinos.css"><link rel="icon" href="/assets/brand/explorando-lugares-logo-v2-transparent.png" type="image/png"></head><body><a class="skip" href="#contenido">Saltar al contenido</a><header><div class="wrap"><a class="brand" href="<?=h($prefix?:'/')?>/" aria-label="Explorando Lugares"><img class="brand-logo" src="/assets/brand/explorando-lugares-logo-v2-transparent.png" alt="" width="64" height="64"><span>Explorando <b>Lugares</b></span></a><nav aria-label="Principal"><a href="<?=h($prefix?:'/')?>/"><?=h($copy['home'])?></a><a href="<?=h($prefix)?>/destinos/" aria-current="page"><?=h($copy['places'])?></a><a href="<?=h($prefix)?>/planea-tu-visita/"><?=h($copy['trip'])?></a><a href="<?=h($prefix)?>/etapas/"><?=h($copy['portal'])?></a><a class="pill light" href="/negocios/registro/?tipo=business"><?=h($copy['business'])?></a></nav></div></header><main id="contenido" class="wrap"><section class="section destination-index"><div class="destination-index-head"><div><p class="eyebrow">Explorando Lugares / <?=h($copy['places'])?></p><h1><?=$copy['heading']?></h1></div><p class="intro"><?=h($copy['intro'])?></p></div><?php if(!$destinations):?><p><?=h($copy['empty'])?></p><?php else:?><div class="destination-grid"><?php foreach($destinations as $destination):$url=$prefix.'/destinos/'.rawurlencode((string)$destination['slug']).'/';?><article class="destination-card"><a class="destination-card-image" href="<?=h($url)?>"><?php if(!empty($destination['asset_path'])):?><img src="/<?=h(ltrim((string)$destination['asset_path'],'/'))?>" alt="<?=h((string)($destination['alt_text']?:$destination['display_name']))?>" loading="lazy"><?php endif;?></a><div class="copy"><?php if($destination['subregion']):?><span class="badge"><?=h((string)$destination['subregion'])?></span><?php endif;?><h2><a href="<?=h($url)?>"><?=h((string)$destination['display_name'])?></a></h2><p><?=h((string)$destination['intro'])?></p><a class="text-link" href="<?=h($url)?>"><?=h($locale==='en'?'Visit place →':($locale==='pt-BR'?'Conhecer o lugar →':'Conocer el lugar →'))?></a></div></article><?php endforeach;?></div><?php endif;?></section></main></body></html>

<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessDemo.php';
require_once dirname(__DIR__).'/admin/_asset.php';
header('X-Frame-Options: SAMEORIGIN');
$embedded=($_GET['embedded']??'')==='1';
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
$view=(string)($_GET['view']??'cards');header('X-Robots-Tag: noindex, nofollow');header('Cache-Control: no-store');
if(in_array($view,['page','page_premium'],true)&&($_GET['presentation']??'page')!=='card'){$elBusinessDemoPlan=$view;require dirname(__DIR__).'/business.php';exit;}
if(!in_array($view,['cards','free','featured','page','page_premium'],true)){http_response_code(404);exit('Ejemplo no encontrado.');}
require_once dirname(__DIR__,2).'/app/views/directory-helpers.php';
$locale=in_array($_GET['idioma']??'', ['en','pt-BR'],true)?$_GET['idioma']:'es';$localePrefix=$locale==='es'?'':'/'.strtolower($locale);$d=['public_contacts'=>[]];$t=['featured'=>'Destacado','map'=>'Ver ubicación','verified'=>'Información verificada','public_data'=>'Información pública','more_info'=>'Más información','contact_business'=>'Contacto del negocio','business_page'=>'Ver página del negocio'];
$labels=match($locale){
'en'=>['Featured','View location','Verified information','Public information','More information','Business contact','View business page','Directory cards','Back to plans','Sample contact icons are inactive. These fictional listings use the same cards as the directory.'],
'pt-BR'=>['Destaque','Ver localização','Informação verificada','Informação pública','Mais informações','Contato do negócio','Ver página do negócio','Cartões do diretório','Voltar aos planos','Os ícones de contato são demonstrativos. Estes exemplos fictícios usam os mesmos cartões do diretório.'],
default=>[...array_values($t),'Tarjetas reales del directorio','Volver a suscripciones','Los iconos de contacto son demostrativos y no abren enlaces. Ejemplos ficticios · Las tarjetas usan el mismo componente, estilos y comportamiento del directorio.']};
$t=array_combine(array_keys($t),array_slice($labels,0,7));
?><!doctype html><html lang="<?=esc($locale)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=esc($labels[7])?></title><link rel="stylesheet" href="<?=esc(admin_asset('style.css'))?>"><link rel="stylesheet" href="<?=esc(admin_asset('directory-tiers.css'))?>"><link rel="stylesheet" href="<?=esc(admin_asset('business-demo.css'))?>"><link rel="stylesheet" href="/assets/portal-identity.css?v=6"><script defer src="/assets/compact-business-cards.js?v=5"></script><script defer src="/assets/load-more-cards.js?v=1"></script></head><body><main class="wrap"><?php if(!$embedded):?><p><a href="<?=esc($localePrefix)?>/negocios-locales/suscripciones/">← <?=esc($labels[8])?></a></p><?php endif ?><p><?=esc($labels[9])?></p><div class="demo-card-grid <?=$view!=='cards'?'demo-card-single':''?>"><?php foreach($view==='cards'?['free','featured','page','page_premium']:[$view] as $plan):$business=businessDemo(db(),$plan);$business['disable_contact_links']=true;$business['demo_page_url']='/business/demo.php?idioma='.$locale.'&view='.$plan.($embedded?'&embedded=1':'').'&presentation=page';$d['public_contacts'][0]=$business['contacts'];?><section class="directory-tier" data-directory-tier="<?=e($plan==='page_premium'?'page':$plan)?>"><h2 class="demo-card-label"><?=e($business['plan_name'])?></h2><div class="directory-grid"><?php require dirname(__DIR__,2).'/app/views/directory-card.php';?></div></section><?php endforeach?></div></main></body></html>

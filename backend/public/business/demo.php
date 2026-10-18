<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_once dirname(__DIR__,2).'/app/BusinessDemo.php';
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
$view=(string)($_GET['view']??'cards');header('X-Robots-Tag: noindex, nofollow');
if(in_array($view,['page','page_premium'],true)){$elBusinessDemoPlan=$view;require dirname(__DIR__).'/negocio.php';exit;}
if($view!=='cards'){http_response_code(404);exit('Ejemplo no encontrado.');}
require_once dirname(__DIR__,2).'/app/views/directory-helpers.php';
$locale='es';$localePrefix='';$d=['public_contacts'=>[]];$t=['featured'=>'Destacado','map'=>'Ver ubicación','verified'=>'Información verificada','public_data'=>'Información pública','more_info'=>'Más información','contact_business'=>'Contacto del negocio','business_page'=>'Ver página del negocio'];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Tarjetas reales del directorio</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/directory-tiers.css?v=3"><link rel="stylesheet" href="/assets/business-demo.css?v=1"></head><body><main class="wrap"><p>Ejemplos ficticios · Las tarjetas usan el mismo componente, estilos y comportamiento del directorio.</p><div class="demo-card-grid"><?php foreach(['free','featured','page','page_premium'] as $plan):$business=businessDemo(db(),$plan);?><section class="directory-tier" data-directory-tier="<?=e($plan==='page_premium'?'page':$plan)?>"><h2 class="demo-card-label"><?=e($business['plan_name'])?></h2><div class="directory-grid"><?php require dirname(__DIR__,2).'/app/views/directory-card.php';?></div></section><?php endforeach?></div></main></body></html>

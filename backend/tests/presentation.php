<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/PortalPresentation.php';
require dirname(__DIR__).'/app/DestinationPresentation.php';
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
foreach(['es','en','pt-BR'] as $locale){
 $context=['locale'=>$locale,'path'=>'/destinos/bogota/'];
 $header=PortalPresentation::header($context);$footer=PortalPresentation::footer($context);
 check(substr_count($header,'id="menu-principal"')===1,'One main menu');
 check(substr_count($header,'class="menu-toggle"')===1,'One toggle');
 check(str_contains($header,'href="/en/lugares/bogota/"'),'Equivalent place language');
 check(str_contains($header,'href="/pt-br/lugares/bogota/"'),'Equivalent Portuguese place');
 check(str_contains($footer,'/contacto.php'),'Help preserved');
 check(!str_contains($footer,'creditos-fotograficos'),'Do not recreate a retired credits route');
 check(!str_contains($header,'<script'),'No header loader');
}
$legacy=PortalPresentation::header(['path'=>'/admin/index.php','legacy_admin'=>true,'csrf'=>'test-token']);
check(str_contains($legacy,'data-admin-legacy')&&str_contains($legacy,'test-token'),'Legacy administrator retains signed logout in the common slot');
check(!str_contains($legacy,'?lang=en'),'Do not advertise an untranslated admin page');
$private=PortalPresentation::header(['path'=>'/business/dashboard.php','locale'=>'en']);
check(!str_contains($private,'/en/business/dashboard.php'),'No invented localized private path');
$html='<!doctype html><html lang="es"><head><title>Jericó</title></head><body><main><section class="destination-hero"><p class="intro">Old intro</p></section><section id="descubre"><div><h2>Old history</h2><p>Old text</p></div><aside>Old aside</aside></section><section id="que-conocer"><div class="sectionhead"><h2>Old heading</h2></div><article data-place-slug="centro-historico"><h3>Centro</h3><p>Old description</p><a href="/assets/example.webp"><img src="/assets/example.webp" alt="Example"></a><a href="https://maps.example.invalid/location">Mapa</a></article></section></main></body></html>';
$output=DestinationPresentation::normalize($html,'jerico','es');
check(str_contains($output,'Fundado en 1850'),'History in initial HTML');
check(str_contains($output,'Parque principal, balcones y calles tradicionales.'),'Final card description');
check(!str_contains($output,'Old intro')&&!str_contains($output,'Old text'),'Old content absent before JS');
check(!str_contains($output,'href="/assets/example.webp"'),'Image not a file link');
check(str_contains($output,'https://maps.example.invalid/location'),'Map link preserved');
check(str_contains($output,'data-place-slug="centro-historico"'),'Stable identity preserved');
echo "OK: common shell in three languages; equivalent/private routes; server-side editorial content, identity and links.\n";

$santa='<html lang="es"><head><title>Test</title></head><body><main><section id="que-conocer"><div id="atractivos-destino"><article data-place-slug="tayrona" id="keep-tayrona"><h3>Tayrona</h3></article><article data-place-slug="ciudad-perdida" id="keep-ciudad"><h3>Ciudad perdida</h3></article></div></section></main></body></html>';
$santa=DestinationPresentation::normalize($santa,'santa-marta','es');
check(str_contains($santa,'keep-tayrona')&&str_contains($santa,'keep-ciudad'),'Existing card attributes survive multiple records');
check(!str_contains($santa,'data-place-slug="minca"'),'Presentation JSON cannot expose records absent from repository output');
check(is_string(DestinationPresentation::media('medellin',null,'en')['alt']),'Media alt resolves before templates render');

$coverageLanguages=['es'=>'/cobertura/colombia/','en'=>'/en/cobertura/colombia/','pt-BR'=>'/pt-br/cobertura/colombia/'];
$coverage=PortalPresentation::context(['path'=>'/cobertura/colombia/','languages'=>$coverageLanguages]);
check(count($coverage['languages'])===3,'Coverage has three real equivalents');
check($coverage['language_navigation']['en']['href']==='/en/cobertura/colombia/'&&$coverage['language_navigation']['en']['equivalent'],'English coverage must remain on the equivalent page');
$coverageHeader=PortalPresentation::header(['path'=>'/cobertura/colombia/','languages'=>$coverageLanguages]);
check(!str_contains($coverageHeader,'EN · Home')&&!str_contains($coverageHeader,'PT-BR · Início'),'Coverage must not replace page translations with home links');
check(str_contains($coverageHeader,'hreflang="en"')&&str_contains($coverageHeader,'/pt-br/cobertura/colombia/'),'Coverage language links present');

foreach(['es','en','pt-BR'] as $locale){
 foreach(['cartagena','bogota','medellin','santa-marta','jerico','jardin','guatape','cauca-viejo','cocorna','arboletes','covenas','zipaquira','santa-fe-de-antioquia'] as $slug){
  foreach(['/lugares/','/destinos/'] as $route){
   $header=PortalPresentation::header(['locale'=>$locale,'path'=>$route.$slug.'/']);
   preg_match('~<nav id="menu-principal"[^>]*>(.*?)</nav>~s',$header,$menu);
   preg_match_all('~<a[^>]*href="([^"]+)"[^>]*aria-current="page"[^>]*>~',$menu[1],$active);
   $prefix=$locale==='en'?'/en':($locale==='pt-BR'?'/pt-br':'');
   check($active[1]===[$prefix.'/lugares/'],'Exactly Places is active for '.$locale.' '.$route.$slug);
   check(!str_contains($menu[1],'class="pill light"'),'Business entry must not look permanently selected');
  }
 }
}
echo "OK: thirteen places in three languages select Places only, including legacy route contexts.\n";

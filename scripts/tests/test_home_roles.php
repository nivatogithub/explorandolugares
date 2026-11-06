<?php
declare(strict_types=1);
require __DIR__.'/../../backend/app/PortalPresentation.php';
foreach(['es'=>'','en'=>'/en','pt-BR'=>'/pt-br'] as $locale=>$prefix){
 $html=PortalPresentation::homeMain($locale);
 foreach([$prefix.'/lugares/',$prefix.'/negocios-locales/','href="#para-negocios"','/negocios/registro/?tipo=business','id="destinos"','id="formas-de-explorar"','id="para-negocios"'] as $expected){
  if(!str_contains($html,$expected))throw new RuntimeException('Lost route/anchor in '.$locale.': '.$expected);
 }
 if(substr_count($html,'<main ')!==1||substr_count($html,'<img ')!==4)throw new RuntimeException('Lost home structure/gallery');
 if(str_contains($html,'<script'))throw new RuntimeException('Home content must be definitive server-rendered HTML');
 if(str_contains($html,'experience-stories'))throw new RuntimeException('Repeated explanation retained');
 preg_match_all('/\$copy\[\x27([^\x27]+)\x27\]/',file_get_contents(__DIR__.'/../../backend/app/views/home-main.php'),$keys);
 $copy=json_decode(file_get_contents(__DIR__.'/../../backend/app/presentation/home.json'),true,32,JSON_THROW_ON_ERROR)[strtolower($locale)];
 foreach(array_unique($keys[1]) as $key)if(empty($copy[$key]))throw new RuntimeException('Missing translation '.$locale.': '.$key);
}
$item=['url'=>'/lugares/jerico/#que-conocer','image'=>'/assets/test.webp','alt_text'=>'Parque & naturaleza','name'=>'Parque <seguro>','destination'=>'Jericó','creator'=>'Explorando Lugares','license_name'=>'','source_url'=>'','license_url'=>''];
$html=PortalPresentation::homeMain('es',[$item]);
if(!str_contains($html,'Parque &lt;seguro&gt;')||!str_contains($html,'/lugares/jerico/#que-conocer'))throw new RuntimeException('Attraction cards escaped/linked incorrectly');
$styles=file_get_contents(__DIR__.'/../../backend/public/assets/home-attractions.css');
foreach(['.home-editorial-copy h1','.home-business-entry','!important'] as $patch){
 if(str_contains($styles,$patch))throw new RuntimeException('Home stylesheet must not patch existing component declarations: '.$patch);
}
$item['creator']='Internal creator';$item['license_name']='INTERNAL LICENSE';$item['source_url']='https://research.example.invalid/image';$item['license_url']='https://license.example.invalid/private';
$html=PortalPresentation::homeMain('es',[$item]);
foreach(['Internal creator','INTERNAL LICENSE','research.example.invalid','license.example.invalid'] as $private)if(str_contains($html,$private))throw new RuntimeException('Private image provenance leaked into home HTML');
echo "OK: traveller/business routes, original gallery, attraction cards, anchors and translations\n";

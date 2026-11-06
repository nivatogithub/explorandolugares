<?php
declare(strict_types=1);
$app=$argv[1]??__DIR__.'/../../backend/app';
require $app.'/PortalPresentation.php';
require $app.'/views/directory-helpers.php';
foreach(['external','own','ai_generated'] as $origin){
 $media=['origin'=>$origin,'creator'=>'PRIVATE PROVENANCE','source_url'=>'https://research.example.invalid/source','license_url'=>'https://license.example.invalid/internal','license_name'=>'PRIVATE LICENSE'];
 if(credit($media)!=='')throw new RuntimeException('Public directory emits media provenance');
 foreach(['es','en','pt-BR'] as $locale){
  $item=$media+['url'=>'/lugares/jerico/#que-conocer','image'=>'/assets/approved.webp','alt_text'=>'Reference license and source metadata','name'=>'Lugar & visita','destination'=>'Jericó'];
  $html=PortalPresentation::homeMain($locale,[$item]);
  foreach(['PRIVATE PROVENANCE','research.example.invalid','license.example.invalid','PRIVATE LICENSE','Reference license'] as $private)if(str_contains($html,$private))throw new RuntimeException('Private media metadata leaked: '.$private);
  if(!str_contains($html,'Lugar &amp; visita')||!str_contains($html,'/assets/approved.webp'))throw new RuntimeException('Public image/name lost');
 }
}
if(credit(null)!=='')throw new RuntimeException('Empty media credit');
echo "OK: all media origins and three languages preserve images/names without exposing provenance\n";

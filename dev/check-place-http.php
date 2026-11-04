<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo pruebas locales.');
$catalog=json_decode(file_get_contents('/srv/portal/app/content/release-places-businesses.json'),true,512,JSON_THROW_ON_ERROR);
$checks=[];
foreach(['es'=>'','en'=>'/en','pt-BR'=>'/pt-br'] as $locale=>$prefix)foreach($catalog['public_places'] as $slug){
    $route=$prefix.'/lugares/'.$slug.'/';
    $html=file_get_contents('http://127.0.0.1'.$route,false,stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>15,'header'=>"User-Agent: Explorando-local-render-validation\r\n"]]));
    if(!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('La ruta no responde directamente 200: '.$route);
    if(str_contains($html,'DestinationPresentation')||str_contains($html,'destination:cards-updated'))throw new RuntimeException('Reapareció un corrector: '.$route);
    $document=new DOMDocument();@$document->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($document);
    if($document->documentElement->getAttribute('lang')!==$locale)throw new RuntimeException('Idioma incorrecto: '.$route);
    foreach(['//header','//footer','//main','//*[@id="menu-principal"]'] as $query)if($xpath->query($query)->length!==1)throw new RuntimeException('Estructura ausente/duplicada: '.$route);
    $name=$catalog['editorial'][$slug]['translations'][$locale]['display_name'];
    $schemas=$xpath->query('//script[@type="application/ld+json"]');
    if($schemas->length!==1)throw new RuntimeException('Datos estructurados ausentes/duplicados.');
    $schema=json_decode($schemas->item(0)->textContent,true,512,JSON_THROW_ON_ERROR);
    if(($schema['@graph'][0]['name']??null)!==$name)throw new RuntimeException('Unicode o nombre incorrecto en JSON-LD: '.$route);
    if($xpath->query('//*[@id="lugares"]/*[contains(@class,"compact-section-heading")]/p[@class="directory-map"]')->length!==1)throw new RuntimeException('Mapa fuera del encabezado nativo.');
    $checks[]=['route'=>$route,'http'=>200,'language'=>$locale,'single_layout'=>true,'json_ld_unicode'=>true];
}
$report=['origin'=>'reserved dev1 / Apache + MariaDB','production_accessed'=>false,'checks'=>$checks];
file_put_contents('/srv/state/refactor-http-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo 'OK Apache/MariaDB: '.count($checks)." rutas canónicas responden 200 con idioma, estructura nativa, mapa y JSON-LD Unicode correctos.\n";

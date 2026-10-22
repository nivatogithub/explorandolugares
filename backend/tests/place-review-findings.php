<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/DestinationPresentation.php';
foreach(['jerico','guatape','santa-fe-de-antioquia'] as $slug){
 $source='<html><body><main><a href="#descubre">Historia y motivos para visitarlo</a><section id="descubre"><div><h2>Historia</h2><p>Texto histórico.</p></div><aside class="destination-reasons"><h3>Por qué visitar</h3><ul><li>Motivo real</li></ul><a href="#que-conocer">Encuentra qué te gustaría conocer</a></aside></section><section class="destination-invitation business-invitation"><div><h2>¿Tienes un negocio?</h2><p>Explicación amplia.</p></div><div class="business-invitation-action"><h3>Participa</h3><a class="pill" href="/negocios-locales/interes/">Solicitar información</a></div></section><img src="/test.webp"></main></body></html>';
 $out=DestinationPresentation::normalize($source,$slug,'es');
 foreach(['href="#descubre"','href="#que-conocer"'] as $removed)if(str_contains($out,$removed))throw new RuntimeException($slug.': redundant jump remains');
 foreach(['Motivo real','business-invitation-action','Explicaci','draggable="false"'] as $kept)if(!str_contains($out,$kept))throw new RuntimeException($slug.': missing shared content '.$kept);
}
echo "OK: common place rendering removes redundant jumps, retains reasons and wide business invitation, disables native image dragging.\n";

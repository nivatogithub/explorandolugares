<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/DestinationPresentation.php';
foreach(['jerico','cartagena','guatape'] as $slug){
 $cards='';foreach(['featured','page','page_premium'] as $tier)$cards.='<article class="directory-card" data-directory-layout="editorial-v2"><div class="directory-card-body"><p class="directory-description">Resumen aprobado '.$tier.'</p><nav class="directory-contact-links"><a href="tel:+573001234567">Contacto '.$tier.'</a></nav><a href="/negocios/'.$tier.'/">Página '.$tier.'</a></div></article>';
 $html='<html><body><main><section id="lugares">'.$cards.'<article class="directory-card"><p class="directory-description">Detalle histórico redundante</p></article></section></main></body></html>';
 $output=DestinationPresentation::normalize($html,$slug,'es');
 foreach(['featured','page','page_premium'] as $tier)if(!str_contains($output,'Resumen aprobado '.$tier)||!str_contains($output,'Contacto '.$tier)||!str_contains($output,'/negocios/'.$tier.'/'))throw new RuntimeException($slug.': se perdió contenido de '.$tier);
 if(str_contains($output,'Detalle histórico redundante'))throw new RuntimeException('No se retiró el detalle heredado.');
}
echo "OK: transformación del lugar conserva resumen, contacto y página de los tres niveles pagos; limpieza limitada al formato heredado.\n";

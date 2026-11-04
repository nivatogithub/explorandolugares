<?php
declare(strict_types=1);
require __DIR__.'/place-template-fixture.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach(['es','en','pt-BR'] as $locale){
 $html=placeTemplateFixture($locale,'zipaquira');
 foreach(['data-place-slug="estacion-tren"','id="lugar-estacion-tren"','id="experiencia-tren-sabana"','href="#experiencia-tren-sabana"','href="#lugar-estacion-tren"','href="https://maps.example.invalid/exact?x=1&amp;y=2"','target="_blank"','rel="noopener noreferrer"','Operador anunciado','experience-paid-operators','Estación &lt;segura&gt;','draggable="false"'] as $expected)check(str_contains($html,$expected),$locale.': missing identity/action '.$expected);
 check(!str_contains($html,'research.example.invalid'),'Research is not an advertisement');
 check(!str_contains($html,'href="/assets/approved.webp"')&&!str_contains($html,'<h4'),'Native semantic image wrapper and heading level');
 check(placePointActions(['map_url'=>'javascript:alert(1)'],[],$locale)==='','Unsafe schemes rejected');
}
echo "OK: native template preserves exact maps, paired relations, paid operators, escaped data and semantic image/heading structure in three languages.\n";

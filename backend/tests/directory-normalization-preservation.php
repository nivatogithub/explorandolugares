<?php
declare(strict_types=1);
require __DIR__.'/place-template-fixture.php';
foreach(['es','en','pt-BR'] as $locale){
 $html=placeTemplateFixture($locale,'jerico',true);
 foreach(['basic','featured','page','page_premium'] as $tier)if(!str_contains($html,'Resumen aprobado '.$tier))throw new RuntimeException('Lost paid summary: '.$tier);
 if(str_contains($html,'Resumen aprobado free'))throw new RuntimeException('Free listing exposed paid text.');
 foreach(['tel:+573001234567','directory-business-page-link','directory-category-icon','draggable="false"'] as $expected)if(!str_contains($html,$expected))throw new RuntimeException('Lost paid action: '.$expected);
}
echo "OK: native directory template retains paid summaries, contacts, pages, icons and image semantics in three languages; free listings retain their limits.\n";

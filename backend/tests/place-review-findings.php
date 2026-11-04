<?php
declare(strict_types=1);
require __DIR__.'/place-template-fixture.php';
foreach(['jerico','guatape','santa-fe-de-antioquia'] as $slug){
 $html=placeTemplateFixture('es',$slug);
 foreach(['href="#descubre"','story-number','place-disclosure','pilot-banner'] as $forbidden)if(str_contains($html,$forbidden))throw new RuntimeException('Obsolete structure emitted: '.$forbidden);
 foreach(['Motivo real','business-invitation-action','Historia en breve','compact-section-heading','draggable="false"','/negocios/registro/?tipo=business'] as $expected)if(!str_contains($html,$expected))throw new RuntimeException('Shared content missing: '.$expected);
 $doc=new DOMDocument();@$doc->loadHTML('<?xml encoding="UTF-8">'.$html);$xpath=new DOMXPath($doc);
 if($xpath->query('//*[@id="lugares"]/*[contains(@class,"sectionhead")]/p[@class="directory-map"]')->length!==1)throw new RuntimeException('Map is not generated inside the directory heading.');
}
echo "OK: native template emits compact sections and map position, keeps reasons/invitation and omits obsolete jumps and labels.\n";

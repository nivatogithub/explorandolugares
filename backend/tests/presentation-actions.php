<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/DestinationPresentation.php';
require dirname(__DIR__).'/app/views/directory-helpers.php';
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
foreach(['es','en','pt-BR'] as $locale){
 $html='<html><body><main><div id="atractivos-destino"><article id="keep-card" data-place-slug="tayrona" data-location-id="stable-42" aria-label="Existing place"><img src="/existing.webp"><h3>Before</h3><p>Before</p><a id="map-action" class="map action" href="https://maps.example.invalid/exact?x=1&amp;y=2" target="_blank" rel="noopener noreferrer" aria-label="Exact location">Map label</a><a href="https://official.example.invalid/exact" aria-describedby="source-note">Official label</a><a href="#related-experience">Related plan</a><button type="button" data-action="expand">Expand</button><form action="/local-action" method="post"><input name="record" value="42"></form></article><article id="unmapped-card" data-place-slug="published-outside-editorial-list"><h3>Existing published record</h3><a href="#keep-other-action">Other action</a></article></div></main></body></html>';
 $out=DestinationPresentation::normalize($html,'santa-marta',$locale);
 foreach(['id="keep-card"','data-location-id="stable-42"','aria-label="Existing place"','id="map-action"','href="https://maps.example.invalid/exact?x=1&amp;y=2"','target="_blank"','rel="noopener noreferrer"','aria-label="Exact location"','Map label','href="https://official.example.invalid/exact"','aria-describedby="source-note"','Official label','href="#related-experience"','data-action="expand"','action="/local-action"','name="record"','id="unmapped-card"','href="#keep-other-action"','draggable="false"'] as $expected)check(str_contains($out,$expected),$locale.': lost action/identity '.$expected);
 $actions=destinationPointActions(['map_url'=>'https://maps.example.invalid/exact?x=1&y=2','external_url'=>'https://official.example.invalid/exact','external_label'=>'Official source'],['map'=>'Exact map'],$locale);
 check(str_contains($actions,'Exact map')&&!str_contains($actions,'Official source')&&!str_contains($actions,'official.example'),'Only approved map action is public');
 check(!str_contains($out,'Consultar la fuente')&&!str_contains($out,'Check the source')&&!str_contains($out,'Consultar a fonte'),'Editorial sources stay private');
 check(destinationPointActions(['map_url'=>'javascript:alert(1)'],[],$locale)==='','Unsafe schemes rejected');
}
echo "OK: all original card actions/attributes/identity survive editorial transforms in three languages; native image dragging disabled; data-backed actions only.\n";

$legacy='<html><body><main><a class="highlight-photo" id="stable-photo" data-place="stable" href="/assets/example.webp" target="_blank" aria-label="Photo"><img src="/assets/example.webp" alt="A real place" width="800" height="600"></a><section><h2>Experiences</h2><div class="destination-experience-copy"><h4 id="stable-experience">Experience</h4></div></section><a href="https://maps.example.invalid/place"><img src="/map-icon.svg" alt="Map"></a></main></body></html>';
$semantic=DestinationPresentation::normalize($legacy,'jardin','es');
check(str_contains($semantic,'<span class="highlight-photo" id="stable-photo" data-place="stable" data-static-image="true">'),'Decorative image wrapper is not a fake link; identity and classes preserved');
check(str_contains($semantic,'<h3 id="stable-experience">Experience</h3>')&&!str_contains($semantic,'<h4'),'No heading level skipped');
check(str_contains($semantic,'href="https://maps.example.invalid/place"'),'Actual image-bearing map link preserved');
check(DestinationPresentation::normalize($semantic,'jardin','es')===$semantic,'Semantic normalization is idempotent');
echo "OK: no false image links, sequential experience headings and exact map/image identity preserved.\n";

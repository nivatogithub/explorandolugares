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
 check(str_contains($actions,'Exact map')&&str_contains($actions,'Official source'),'Data-backed map and external actions');
 check(destinationPointActions(['map_url'=>'javascript:alert(1)'],[],$locale)==='','Unsafe schemes rejected');
}
echo "OK: all original card actions/attributes/identity survive editorial transforms in three languages; native image dragging disabled; data-backed actions only.\n";

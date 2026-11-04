<?php
declare(strict_types=1);
require __DIR__.'/../app/CoverageService.php';
function verify(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$read=fn($name)=>json_decode(file_get_contents(__DIR__.'/../app/content/'.$name.'.json'),true);
$rows=json_decode(file_get_contents(__DIR__.'/fixtures/coverage-publications-88.json'),true);
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE destinations(slug TEXT,department TEXT,publication_status TEXT,country_code TEXT)');
$q=$db->prepare('INSERT INTO destinations VALUES(?,?,?,?)');foreach($rows as $row)$q->execute(array_values($row));
$service=new CoverageService($read('coverage-plan'),$read('coverage-geography'),$read('coverage-scope'));
$s=$service->snapshot($db);$urls=[];$mapped=0;
foreach($s['departments'] as $d){foreach($d['places'] as $p)if($p['status']==='published')$urls[]=$p['url'];foreach($d['municipalities'] as $m)if($m['status']==='published')$mapped++;}
verify(count($urls)===88,'All 88 published guides must resolve');verify(count(array_unique($urls))===88,'No duplicate guides');verify($mapped===87,'88 guides cover 87 municipalities: Cauca Viejo shares Jericó');
verify($s['country']['municipality_total']===221,'Do not silently expand the approved target');
foreach($rows as $r)verify(in_array('/lugares/'.$r['slug'].'/',$urls,true),'Missing '.$r['slug']);
$valle=array_values(array_filter($s['departments'],fn($d)=>$d['code']==='76'))[0];verify(count(array_filter($valle['municipalities'],fn($m)=>$m['status']==='published'))===5,'All Valle municipalities painted');
$db->exec("UPDATE destinations SET publication_status='draft' WHERE slug='yumbo'");
$s2=$service->snapshot($db);$valle=array_values(array_filter($s2['departments'],fn($d)=>$d['code']==='76'))[0];$yumbo=array_values(array_filter($valle['municipalities'],fn($m)=>$m['code']==='76892'))[0];verify($yumbo['status']!=='published','Unpublished guides stop coloring map');
echo json_encode(['published'=>88,'mapped_municipalities'=>$mapped,'covered_target'=>$s['country']['covered_municipalities'],'target'=>221,'percent'=>$s['country']['coverage_percent']],JSON_PRETTY_PRINT)."\n";

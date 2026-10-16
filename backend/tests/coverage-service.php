<?php
declare(strict_types=1);
require __DIR__.'/../app/CoverageService.php';
function expect(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$plan=json_decode(file_get_contents(__DIR__.'/../app/content/coverage-plan.json'),true);
$geo=json_decode(file_get_contents(__DIR__.'/../app/content/coverage-geography.json'),true);
$service=new CoverageService($plan,$geo);
$offline=$service->snapshot(null);expect($offline['country']['coverage_percent']===null,'Offline must not show zero');expect(!$offline['publication_source_available'],'Missing DB is unavailable');
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE destinations(slug TEXT,department TEXT,publication_status TEXT,country_code TEXT,internal_notes TEXT)');
$db->exec("INSERT INTO destinations VALUES ('jerico','Antioquia','published','CO','SECRET_NOTE'),('jardin','Antioquia','draft','CO','PRIVATE_DRAFT'),('salento','Quindío','archived','CO','ARCHIVED'),('private-place','Antioquia','draft','CO','HIDDEN_NAME'),('jerico','Other','published','CO','OTHER'),('cartagena','Bolívar','published','XX','FOREIGN')");
$snapshot=$service->snapshot($db);$ant=array_values(array_filter($snapshot['departments'],fn($d)=>$d['code']==='05'))[0];
expect($snapshot['publication_source_available'],'DB read succeeds');expect($snapshot['country']['counts']['published']===1,'Only matching CO publication counts');
$jerico=array_values(array_filter($ant['places'],fn($p)=>$p['name']==='Jericó'))[0];$jardin=array_values(array_filter($ant['places'],fn($p)=>$p['name']==='Jardín'))[0];$cauca=array_values(array_filter($ant['places'],fn($p)=>$p['name']==='Cauca Viejo'))[0];
expect($jerico['status']==='published'&&$jerico['url']==='/destinos/jerico/','Published links only');expect($jardin['status']==='in_progress'&&$jardin['url']===null,'Draft no link');expect($cauca['status']==='pending','Child not automatically published');expect($cauca['municipality_code']===$jerico['municipality_code'],'Child shares geographic municipality');
expect($ant['coverage_percent']===round(300/67,2),'Weighted department coverage denominator');
$unit=array_values(array_filter($ant['municipalities'],fn($m)=>$m['code']===$jerico['municipality_code']))[0];expect($unit['published_count']===1&&$unit['tracked_count']===2,'Partial municipality clearly counts two destinations');
expect(count($ant['municipalities'])===125,'All 125 map units of Antioquia included');
$json=json_encode($snapshot);foreach(['SECRET_NOTE','PRIVATE_DRAFT','private-place','HIDDEN_NAME','internal_notes','blockers','reviewer','base_commit','priority','weight'] as $secret)expect(!str_contains($json,$secret),'No private fields: '.$secret);
$db->exec('DROP TABLE destinations');expect($service->snapshot($db)['country']['coverage_percent']===null,'Query failure fails closed');
echo "OK: publication, weights, offline, territorial parent and private data isolation\n";

<?php
declare(strict_types=1);
require __DIR__.'/../app/CoverageService.php';
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$read=static fn(string $name):array=>json_decode(file_get_contents(__DIR__.'/../app/content/'.$name.'.json'),true,512,JSON_THROW_ON_ERROR);
$scope=$read('coverage-scope');$service=new CoverageService($read('coverage-plan'),$read('coverage-geography'),$scope);
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE destinations(slug TEXT,department TEXT,publication_status TEXT,country_code TEXT)');
$db->exec("INSERT INTO destinations VALUES ('jerico','Antioquia','published','CO'),('jardin','Antioquia','draft','CO')");
$s=$service->snapshot($db);$ant=array_values(array_filter($s['departments'],fn($d)=>$d['code']==='05'))[0];
check($s['country']['municipality_total']===221,'Selection must include exactly 221 unique territorial units');
check($ant['municipality_total']===24,'Antioquia target is 24, not 125');
check($ant['coverage_percent']===4.17,'One published municipality out of 24');
check($s['country']['coverage_percent']===0.45,'One published municipality out of 221');
check(count(array_filter($ant['municipalities'],fn($m)=>$m['status']==='outside_scope'))===101,'Other municipalities outside current scope');
check($service->snapshot(null)['country']['coverage_percent']===null,'Database unavailable must not show zero');
// A second published guide in the same municipality must not inflate municipal coverage.
$plan=$read('coverage-plan');foreach($plan['departments'] as &$dep){if($dep['code']==='05')$dep['places'][]=['id'=>'test-subplace','name'=>'Subplace','slug'=>'test-subplace','municipality_code'=>'05368','type'=>'autonomous_destination'];}unset($dep);
$db->exec("INSERT INTO destinations VALUES ('test-subplace','Antioquia','published','CO')");
$second=(new CoverageService($plan,$read('coverage-geography'),$scope))->snapshot($db);
check($second['country']['covered_municipalities']===1,'Multiple guides must not duplicate municipal coverage');
$db->exec("INSERT INTO destinations VALUES ('covenas','Sucre','published','CO')");
$added=(new CoverageService($read('coverage-plan'),$read('coverage-geography'),$scope))->snapshot($db);
check($added['country']['covered_municipalities']===2,'New municipal publication in selection counts without initial catalog entry');
echo "OK: 221 target units, departmental denominator, out-of-scope, offline and municipal deduplication\n";

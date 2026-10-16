<?php
declare(strict_types=1);
require __DIR__.'/../app/CoverageService.php';
require __DIR__.'/../app/analytics/AnalyticsService.php';
function checkScope(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$plan=json_decode(file_get_contents(__DIR__.'/../app/content/coverage-plan.json'),true,512,JSON_THROW_ON_ERROR);$geo=json_decode(file_get_contents(__DIR__.'/../app/content/coverage-geography.json'),true,512,JSON_THROW_ON_ERROR);
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$service=new AnalyticsService($db,str_repeat('x',40));$service->install($plan,$geo);
checkScope((int)$db->query('SELECT COUNT(*) FROM el_coverage_registry WHERE original_priority=4')->fetchColumn()===22,'Keep all 22 P4 municipalities for measurement');
checkScope((int)$db->query('SELECT COUNT(*) FROM el_coverage_scope WHERE in_work_scope=1')->fetchColumn()===97,'Target P1/P2 has own denominator');
$version=$db->query("SELECT setting_value FROM el_measurement_settings WHERE setting_key='catalog_version'")->fetchColumn();checkScope($version===$plan['universe_version'],'Catalog version persisted');
$db->exec("UPDATE el_coverage_places SET priority=3,preparing=0 WHERE slug='cartagena'");$service->install($plan,$geo);
checkScope((int)$db->query("SELECT priority FROM el_coverage_places WHERE slug='cartagena'")->fetchColumn()===1,'Reinstall updates old priorities');
checkScope((int)$db->query("SELECT preparing FROM el_coverage_places WHERE slug='cartagena'")->fetchColumn()===1,'Reinstall updates work progress');
$db->exec('CREATE TABLE destinations(slug TEXT,department TEXT,publication_status TEXT,country_code TEXT)');
$db->exec("INSERT INTO destinations VALUES('cartagena','Bolívar','published','CO'),('outside-target','Antioquia','published','CO')");
$stored=$service->planFromDatabase();$snap=(new CoverageService($stored,$geo))->snapshot($db);
checkScope($snap['country']['counts']['published']===2,'Count all publications independently');
checkScope($snap['editorial_target']['published_count']===1 && $snap['editorial_target']['denominator']===97,'Target numerator excludes outside publications');
checkScope($snap['editorial_target']['published_percent']===round(100/97,2),'Target ratio bounded to matching guides');
checkScope($snap['country']['coverage_percent']===null,'Do not present target as complete country');
checkScope($snap['measurement_reference']['retained_category4_count']===22,'P4 registry survives database roundtrip');
checkScope((new CoverageService($stored,$geo))->snapshot(null)['editorial_target']['published_percent']===null,'Offline is not zero');
$before=$db->query('SELECT COUNT(*) FROM el_coverage_registry')->fetchColumn();$short=$plan;$short['departments'][0]['places']=[];(new CoverageCatalogService($db))->synchronize($short);
checkScope($before===$db->query('SELECT COUNT(*) FROM el_coverage_registry')->fetchColumn(),'Sync does not delete registry');
$bad=$plan;$bad['departments'][0]['places'][0]['original_priority']=4;$bad['departments'][0]['places'][0]['in_work_scope']=true;
try{(new CoverageCatalogService($db))->synchronize($bad);throw new RuntimeException('P4 should be rejected');}catch(InvalidArgumentException){}
checkScope(!$db->inTransaction(),'Failed sync rolls back');
echo "OK: separate denominators, retained P4, upsert/idempotence, database roundtrip, offline and rollback\n";

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/analytics/BasicAudienceService.php';
function basic_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$root=sys_get_temp_dir().'/basic-audience-'.bin2hex(random_bytes(8));mkdir($root);file_put_contents($root.'/index.html','<h1>Fixture</h1>');
try {
 $s=new BasicAudienceService($pdo,$root);basic_check(!$s->ready(),'disabled until additive activation');basic_check($s->report()['page_views']===null,'missing is not zero');$s->install();$s->install();basic_check($s->ready(),'idempotent activation');
 $pdo->exec("CREATE TABLE destinations(id INTEGER,slug TEXT,publication_status TEXT);CREATE TABLE businesses(id INTEGER,public_id TEXT,destination_id INTEGER,publication_status TEXT);CREATE TABLE el_coverage_places(slug TEXT,department_code TEXT);INSERT INTO destinations VALUES(1,'jerico','published'),(2,'draft','draft');INSERT INTO el_coverage_places VALUES('jerico','05'),('draft','05');INSERT INTO businesses VALUES(1,'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',1,'published')");
 $time='2026-10-16 04:30:00'; // Still 15 October in Bogotá.
 basic_check($s->countPage('/',$time),'home count without identity or consent');basic_check($s->countPage('/',$time),'refresh adds a view');basic_check($s->countPage('/destinos/jerico/',$time),'destination');basic_check($s->countPage('/negocios/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/',$time),'published business');
 foreach(['/admin/index.html','/api/cobertura.php','/business/dashboard.html','/negocios/portal/','/?email=private@example.com','/../private/config.php','/inventado/','/destinos/draft/','/destinos/jerico/not-real/'] as $path)basic_check(!$s->countPage($path,$time),'reject private, unpublished, unknown or parameterized path');
 $stored=$pdo->query('SELECT * FROM el_basic_page_daily ORDER BY scope_code')->fetchAll(PDO::FETCH_ASSOC);basic_check(count($stored)===2,'only portal and department totals');basic_check((int)$stored[0]['page_views']===2&&(int)$stored[1]['page_views']===4,'scopes not double summed');basic_check($stored[0]['page_day']==='2026-10-15','Bogotá day');basic_check(array_keys($stored[0])===['page_day','scope_code','page_views'],'no identity or path columns');
 $pdo->exec("UPDATE el_basic_settings SET setting_value='2026-01-01 05:00:00' WHERE setting_key='started_at';DELETE FROM el_basic_page_daily;INSERT INTO el_basic_page_daily VALUES('2026-08-20','CO',10),('2026-10-01','CO',20),('2026-10-16','CO',100)");
 $r=$s->report(30,null,'2026-10-16 18:00:00');basic_check($r['page_views']===120,'live views include partial today');basic_check($r['comparison_current_views']===20&&$r['previous_views']===10&&$r['growth_percent']===100.0,'compare completed equal windows, excludes today');basic_check($r['comparison_period_end']==='2026-10-15','comparison ends yesterday');
 $pdo->exec("UPDATE el_basic_settings SET setting_value='2026-10-16 12:00:00' WHERE setting_key='started_at'");basic_check(!$s->report(30,null,'2026-10-16 18:00:00')['comparison_available'],'no invented history');
 basic_check(!str_contains(json_encode($stored),'jerico'),'no raw paths, business IDs or journeys stored');
 echo "Basic audience: OK — aggregate-only schema, refresh, scopes, private route boundaries, activation, Bogotá and full-period comparisons\n";
} finally {unlink($root.'/index.html');rmdir($root);}

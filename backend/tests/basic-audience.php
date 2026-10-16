<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/analytics/BasicAudienceService.php';
function basic_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$root=sys_get_temp_dir().'/basic-audience-'.bin2hex(random_bytes(8));mkdir($root);file_put_contents($root.'/index.html','<h1>Fixture</h1>');
try {
 $s=new BasicAudienceService($pdo,$root);basic_check(!$s->ready(),'disabled until additive activation');basic_check($s->report()['page_views']===null,'missing is not zero');$s->install();$s->install();basic_check($s->ready(),'idempotent activation');
 $pdo->exec("CREATE TABLE destinations(id INTEGER,slug TEXT,publication_status TEXT);CREATE TABLE businesses(id INTEGER,public_id TEXT,destination_id INTEGER,publication_status TEXT,information_status TEXT DEFAULT 'DATOS_PUBLICOS',operation_status TEXT DEFAULT 'EN_OPERACION');CREATE TABLE el_coverage_places(slug TEXT,department_code TEXT);INSERT INTO destinations VALUES(1,'jerico','published'),(2,'draft','draft');INSERT INTO el_coverage_places VALUES('jerico','05'),('draft','05');INSERT INTO businesses(id,public_id,destination_id,publication_status) VALUES(1,'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',1,'published')");
 $pdo->exec("CREATE TABLE business_translations(business_id INTEGER,locale TEXT);INSERT INTO business_translations VALUES(1,'es');CREATE TABLE business_subscriptions(id INTEGER,business_id INTEGER,plan_code TEXT,status TEXT,starts_at TEXT,ends_at TEXT);INSERT INTO business_subscriptions VALUES(1,1,'page_premium','active',NULL,NULL);CREATE TABLE subscription_plans(code TEXT,has_business_page INTEGER);INSERT INTO subscription_plans VALUES('page_premium',1),('free',0);CREATE TABLE subscription_plan_features(plan_code TEXT,feature_code TEXT,included INTEGER);INSERT INTO subscription_plan_features VALUES('page_premium','business_page',1),('page_premium','highlighted_card',1)");
 $time='2026-10-16 04:30:00'; // Still 15 October in Bogotá.
 basic_check($s->countPage('/',$time),'home count without identity or consent');basic_check($s->countPage('/',$time),'refresh adds a view');basic_check($s->countPage('/destinos/jerico/',$time),'destination');basic_check($s->countPage('/negocios/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/',$time),'published business');
 foreach(['/admin/index.html','/api/cobertura.php','/business/dashboard.html','/negocios/portal/','/?email=private@example.com','/../private/config.php','/inventado/','/destinos/draft/','/destinos/jerico/not-real/'] as $path)basic_check(!$s->countPage($path,$time),'reject private, unpublished, unknown or parameterized path');
 $stored=$pdo->query('SELECT * FROM el_basic_page_daily ORDER BY scope_code')->fetchAll(PDO::FETCH_ASSOC);basic_check(count($stored)===2,'only portal and department totals');basic_check((int)$stored[0]['page_views']===2&&(int)$stored[1]['page_views']===4,'scopes not double summed');basic_check($stored[0]['page_day']==='2026-10-15','Bogotá day');basic_check(array_keys($stored[0])===['page_day','scope_code','page_views'],'no identity or path columns');
 basic_check($s->countCard('/destinos/jerico/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','card_impression',$time),'visible paid card');
 basic_check($s->countCard('/destinos/jerico/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','profile_click',$time),'click to paid page');
 basic_check(!$s->countCard('/destinos/otro/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','card_impression',$time),'wrong destination rejected');
 basic_check((int)$pdo->query("SELECT metric_count FROM el_basic_entity_daily WHERE entity_type='business' AND metric='card_impression'")->fetchColumn()===1,'business impression persisted separately');
 basic_check((int)$pdo->query("SELECT metric_count FROM el_basic_entity_daily WHERE entity_type='destination' AND metric='page_view' AND placement='business_profile'")->fetchColumn()===1,'business page rolls up to destination without inflating portal');
 basic_check($s->entityReport('destination','jerico',30,'2026-10-16 18:00:00')['metrics']['page_views']===2,'destination includes guide and business pages once each');
 basic_check($s->entityReport('business','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',30,'2026-10-16 18:00:00')['metrics']['profile_clicks']===1,'private report groups correct target only');
 $pdo->exec("UPDATE business_subscriptions SET ends_at='2000-01-01'");
 basic_check(!$s->countPage('/negocios/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/',$time),'expired page rejected');
 basic_check(!$s->countCard('/destinos/jerico/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','profile_click',$time),'expired profile click rejected');
 basic_check($s->countCard('/destinos/jerico/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','card_impression',$time),'free listing remains measurable');
 basic_check((int)$pdo->query("SELECT metric_count FROM el_basic_entity_daily WHERE entity_type='business' AND metric='card_impression' AND placement='directory'")->fetchColumn()===1,'expired plan never reported as featured');
 $pdo->exec("INSERT INTO subscription_plans VALUES('featured',0),('page',1);INSERT INTO subscription_plan_features VALUES('featured','highlighted_card',1),('page','business_page',1);UPDATE business_subscriptions SET plan_code='featured',ends_at=NULL");
 basic_check(!$s->countPage('/negocios/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/',$time),'featured tier alone does not buy a page');
 basic_check($s->countCard('/destinos/jerico/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','card_impression',$time),'featured tier impression');
 $pdo->exec("UPDATE business_subscriptions SET plan_code='page'");
 basic_check($s->countPage('/negocios/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/',$time),'standard paid page');
 basic_check($s->countCard('/destinos/jerico/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','card_impression',$time),'standard page tier uses ordinary directory placement');
 basic_check((int)$pdo->query("SELECT metric_count FROM el_basic_entity_daily WHERE entity_type='business' AND metric='card_impression' AND placement='featured'")->fetchColumn()===2,'features not inferred from page ownership');
 $pdo->exec("UPDATE business_subscriptions SET starts_at='2999-01-01'");basic_check(!$s->countPage('/negocios/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/',$time),'future entitlement rejected');
 $pdo->exec("UPDATE business_subscriptions SET starts_at=NULL,status='pending_payment'");basic_check(!$s->countPage('/negocios/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/',$time),'pending payment not active entitlement');
 $pdo->exec("UPDATE business_subscriptions SET status='active';UPDATE businesses SET operation_status='CERRADO_DEFINITIVO'");basic_check(!$s->countCard('/destinos/jerico/','aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee','card_impression',$time),'closed business cannot get public impressions');
 $pdo->exec("UPDATE el_basic_settings SET setting_value='2026-01-01 05:00:00' WHERE setting_key='started_at';DELETE FROM el_basic_page_daily;INSERT INTO el_basic_page_daily VALUES('2026-08-20','CO',10),('2026-10-01','CO',20),('2026-10-16','CO',100)");
 $r=$s->report(30,null,'2026-10-16 18:00:00');basic_check($r['page_views']===120,'live views include partial today');basic_check($r['comparison_current_views']===20&&$r['previous_views']===10&&$r['growth_percent']===100.0,'compare completed equal windows, excludes today');basic_check($r['comparison_period_end']==='2026-10-15','comparison ends yesterday');
 $pdo->exec("UPDATE el_basic_settings SET setting_value='2026-10-16 12:00:00' WHERE setting_key='started_at'");basic_check(!$s->report(30,null,'2026-10-16 18:00:00')['comparison_available'],'no invented history');
 basic_check(!str_contains(json_encode($stored),'jerico'),'no raw paths, business IDs or journeys stored');
 echo "Basic audience: OK — aggregate-only schema, refresh, scopes, private route boundaries, activation, Bogotá and full-period comparisons\n";
} finally {unlink($root.'/index.html');rmdir($root);}

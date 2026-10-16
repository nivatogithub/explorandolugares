<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/analytics/AnalyticsService.php';
function check(bool $test,string $message):void{if(!$test)throw new RuntimeException($message);}
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$s=new AnalyticsService($p,str_repeat('k',40));
$plan=json_decode(file_get_contents(dirname(__DIR__).'/app/content/coverage-plan.json'),true);$geo=json_decode(file_get_contents(dirname(__DIR__).'/app/content/coverage-geography.json'),true);
check(!$s->ready(),'not configured');$s->install($plan,$geo);$s->install($plan,$geo);check($s->ready(),'ready');check(count($s->geographyFromDatabase())===count($geo),'idempotent units');
check(count($s->planFromDatabase()['departments'])===33,'departments');
$p->exec("CREATE TABLE accounts(id INTEGER,account_type TEXT,status TEXT,email_verified_at TEXT);INSERT INTO accounts VALUES(1,'traveler','active','2026-10-01'),(2,'traveler','pending_activation',NULL),(3,'business','active','2026-10-01')");
$p->exec("CREATE TABLE destinations(id INTEGER,slug TEXT,department TEXT,publication_status TEXT);CREATE TABLE businesses(id INTEGER,public_id TEXT,destination_id INTEGER,publication_status TEXT);INSERT INTO destinations VALUES(1,'jerico','Antioquia','published');INSERT INTO businesses VALUES(1,'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',1,'published')");
$ctx=['visitor'=>'one','session'=>'one','ip'=>'192.0.2.1','country'=>'CO','source'=>'search','account_kind'=>'anonymous'];
$event=['event'=>'page_view','event_id'=>str_repeat('a',32),'consent'=>true,'path'=>'/destinos/jerico/'];$now='2026-10-15 12:00:00';
check($s->accept($event,$ctx,$now),'accepted');check(!$s->accept($event,$ctx,$now),'replay');
$r=$s->report(30,'05',null,'2026-10-16 12:00:00');check($r['status']==='collecting_baseline'&&$r['metrics']['estimated_visitors']===null,'small group privacy');check($r['metrics']['registered_travelers']===1,'verified traveler count');
for($i=2;$i<=6;$i++){$ctx['visitor']='v'.$i;$ctx['session']='s'.$i;$event['event_id']=str_pad(dechex($i),32,'0',STR_PAD_LEFT);check($s->accept($event,$ctx,$now),'record');}
$r=$s->report(30,'05',null,'2026-10-16 12:00:00');check($r['metrics']['estimated_visitors']===6&&$r['metrics']['anonymous_sessions']===6,'distinct counts');check($r['growth_percent']===null&&!$r['comparison_available'],'no invented growth');
$contact=$event;$contact['event']='contact_click';$contact['event_id']=str_repeat('b',32);$contact['business_id']='aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';$contact['channel']='whatsapp';check($s->accept($contact,$ctx,$now),'contact');$contact['event_id']=str_repeat('c',32);check(!$s->accept($contact,$ctx,$now),'contact duplicate');
$event['path']='/?email=private@example.com';try{$s->accept($event,$ctx,$now);throw new RuntimeException('query accepted');}catch(InvalidArgumentException){}
$stored=json_encode($p->query('SELECT * FROM el_measurement_events')->fetchAll(PDO::FETCH_ASSOC));check(!str_contains($stored,'192.0.2.1')&&!str_contains($stored,'private@'),'no raw personal values');check($s->country('127.0.0.1')==='ZZ','local IP unknown');check(preg_match('/^[A-Z]{2}$/',$s->country('8.8.8.8'))===1,'country reader');
check($s->sessionMatches($ctx['session'],$ctx),'anonymous session matches');
$traveler=$ctx;$traveler['account_kind']='traveler';$traveler['account_hash']=$s->hash('account|1');check(!$s->sessionMatches($ctx['session'],$traveler),'login rotates session');$traveler['session']='traveler-session';$event['path']='/destinos/jerico/';$event['event_id']=str_repeat('d',32);check($s->accept($event,$traveler,$now),'traveler event');check(!$s->sessionMatches($traveler['session'],$ctx),'logout rotates session');$r=$s->report(30,'05',null,'2026-10-16 12:00:00');check($r['metrics']['active_travelers']===1,'active traveler preserved');
echo "Analytics service: OK\n";

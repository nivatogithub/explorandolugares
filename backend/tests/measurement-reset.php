<?php
declare(strict_types=1);
require __DIR__.'/../app/analytics/MeasurementResetService.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach(['el_measurement_events','el_measurement_sessions','el_measurement_limits','el_basic_page_daily','el_basic_entity_daily','el_basic_country_daily','accounts','businesses','business_subscriptions','business_account_memberships','el_coverage_places','el_coverage_departments','el_coverage_units'] as $t){$db->exec('CREATE TABLE '.$t.'(id INTEGER PRIMARY KEY)');$db->exec('INSERT INTO '.$t.' VALUES(1)');}
foreach(['el_measurement_settings','el_basic_settings'] as $t){$db->exec('CREATE TABLE '.$t.'(setting_key TEXT PRIMARY KEY,setting_value TEXT NOT NULL)');$db->exec("INSERT INTO $t VALUES('enabled','1'),('started_at','2020-01-01 00:00:00')");}
$r=(new MeasurementResetService($db))->reset('inicio_campana','owner_request');
foreach($r['counters'] as $row)if($row!==['before'=>1,'after'=>0])throw new RuntimeException('Counter not reset');
foreach($r['protected_counts'] as $t=>$n)if($n!==1||(int)$db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==1)throw new RuntimeException('Protected table changed');
foreach(['el_measurement_settings','el_basic_settings'] as $t)if($db->query("SELECT setting_value FROM $t WHERE setting_key='enabled'")->fetchColumn()!=='1')throw new RuntimeException('Reset changed activation');
$disabled=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$report=(new MeasurementResetService($disabled))->reset('inicio_campana','owner_request');if($report['counters']!==[])throw new RuntimeException('Reset must not install or activate measurement');
// A failed delete rolls the whole reset back.
$db->exec('INSERT INTO el_measurement_events VALUES(2)');$db->exec('INSERT INTO el_measurement_sessions VALUES(2)');$db->exec("CREATE TRIGGER reject_reset BEFORE DELETE ON el_measurement_sessions BEGIN SELECT RAISE(ABORT,'test rejection');END");
$failed=false;try{(new MeasurementResetService($db))->reset('test','owner_request');}catch(Throwable){$failed=true;}if(!$failed||(int)$db->query('SELECT COUNT(*) FROM el_measurement_events')->fetchColumn()!==1)throw new RuntimeException('Reset not atomic');
echo "OK: counters reset atomically, account/catalog/subscription preservation, unchanged activation, absent tables and rollback.\n";

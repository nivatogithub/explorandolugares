<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/DatabaseMaintenance.php';
function maintenance_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$app=dirname(__DIR__).'/app';$public=dirname(__DIR__,2).'/ensayos/v1';
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE leads(id INTEGER PRIMARY KEY,request_key TEXT);CREATE TABLE rate_limits(bucket_key TEXT PRIMARY KEY,hits INTEGER,expires_at INTEGER)');
$tool=new DatabaseMaintenance($pdo,$app,$public);$report=$tool->inspect();
maintenance_check($report['connected']&&$report['driver']==='sqlite','Connection diagnostics identify the local database driver');
maintenance_check($report['base_schema']&&$report['counts']['leads']===0,'Base schema status and counts are read-only');
maintenance_check($report['migrations']['001-phone-type']==='pending','Missing phone-type field is detected');
$rejected=false;try{$tool->applyMigrations();}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'MySQL/MariaDB');}
maintenance_check($rejected&&!in_array('phone_type',$pdo->query('PRAGMA table_info(leads)')->fetchAll(PDO::FETCH_COLUMN,1),true),'Production write actions refuse SQLite and leave it unchanged');

$destinations=new PDO('sqlite::memory:');$destinations->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/006-poi-experience-links.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/008-business-subscriptions.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/009-business-origin.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/010-business-location-subscription-lifecycle.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/011-premium-business-story.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/012-business-image-references.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/013-accounts-location-map.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/014-tiered-business-description.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/015-business-media-quotas.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/016-plan-content-limits.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/017-business-claims.sqlite.sql'));
$destinations->exec(file_get_contents(dirname(__DIR__).'/database/migrations/018-business-portal.sqlite.sql'));
$catalogTool=new DatabaseMaintenance($destinations,$app,$public);$catalog=$catalogTool->inspect();
foreach(['003-dynamic-destinations','004-media-origin','005-destination-internal-research','006-poi-experience-links','007-media-provenance','008-business-subscriptions','009-business-origin','010-business-location-subscription-lifecycle','011-premium-business-story','012-business-image-references','013-accounts-location-map','014-tiered-business-description','015-business-media-quotas','016-plan-content-limits','017-business-claims','018-business-portal'] as $id){
    maintenance_check($catalog['migrations'][$id]==='applied','Existing destination schema is recognized without rerunning '.$id);
}
maintenance_check(!$catalog['empty_database']&&$catalog['catalog_empty']&&$catalog['driver']==='sqlite','Empty catalog is distinguished from empty schema and local SQLite remains non-production');
echo "OK: maintenance diagnostics, migration-state detection, existing schema recognition, and refusal of writes outside MySQL.\n";

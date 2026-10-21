<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/LeadBusinessLinks.php';
require dirname(__DIR__).'/app/BusinessAdminFilters.php';
require dirname(__DIR__).'/app/AdminReturnContext.php';
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON;CREATE TABLE leads(id INTEGER PRIMARY KEY);CREATE TABLE businesses(id INTEGER PRIMARY KEY);CREATE TABLE business_translations(business_id INTEGER,locale TEXT,display_name TEXT);INSERT INTO leads VALUES(1);INSERT INTO businesses VALUES(1),(2);INSERT INTO business_translations VALUES(1,"es","Ficticio"),(2,"es","Otro")');
$p->exec(file_get_contents(dirname(__DIR__).'/database/migrations/047-lead-business-links.sqlite.sql'));
$p->exec(file_get_contents(dirname(__DIR__).'/database/migrations/047-lead-business-links.sqlite.sql'));
$l=new LeadBusinessLinks($p);check($l->link(1,1,'test-admin'),'new association');check(!$l->link(1,1,'other-admin'),'idempotent repeat');check($l->link(1,2,'test-admin'),'preserve multiple links');check(count($l->forLead(1))===2,'preserve previous');check($l->forLead(1)[0]['linked_by']==='test-admin','preserve actor');
try{$l->link(99,1,'test-admin');throw new RuntimeException('missing accepted');}catch(RuntimeException $e){check($e->getMessage()!=='missing accepted','missing rejected');}
check(!$p->inTransaction(),'transaction closed');
$row=['id'=>1,'category_code'=>'hotel','category_codes'=>['hotel','food'],'destination_id'=>4,'display_name'=>'Hotel Ficticio','destination_name'=>'Lugar Ficticio','publication_status'=>'draft','information_status'=>'PUBLICO','record_origin'=>'public_import'];
check(BusinessAdminFilters::matches($row,['category'=>'food','destination'=>4,'origin'=>'public_import']),'secondary category selected');check(!BusinessAdminFilters::matches($row,['category'=>'transport']),'wrong category excluded');check(!BusinessAdminFilters::matches($row,['duplicates'=>'yes']),'duplicates filter');check(BusinessAdminFilters::matches($row,['duplicates'=>'yes'],[1=>['Other']]),'duplicate map');
check(AdminReturnContext::safe('https://example.invalid','index.php')==='index.php','external return blocked');check(AdminReturnContext::safe('//evil.invalid/a','index.php')==='index.php','protocol relative return blocked');check(AdminReturnContext::safe('atencion-comercios.php?query=Hotel&page=3','index.php')==='atencion-comercios.php?query=Hotel&page=3','return context preserved');
echo "Administrative links and filters: passed\n";

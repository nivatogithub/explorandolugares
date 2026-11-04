<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/LeadAdminService.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE leads(id INTEGER PRIMARY KEY,status TEXT NOT NULL); INSERT INTO leads VALUES(1,'nuevo')");
$service=new LeadAdminService($pdo);
function check(bool $ok):void {if(!$ok)throw new RuntimeException('Lead state regression');}
check($service->changeStatus(1,'revisado')==='saved');
check($service->changeStatus(1,'revisado')==='unchanged');
check($service->changeStatus(99,'revisado')==='missing');
check($pdo->query('SELECT status FROM leads WHERE id=1')->fetchColumn()==='revisado');
foreach([[1,'inventado'],[0,'nuevo']] as [$id,$status]){try{$service->changeStatus($id,$status);throw new RuntimeException('Accepted invalid input');}catch(InvalidArgumentException){}}
check(!$pdo->inTransaction());
$pdo->exec("CREATE TRIGGER reject_contact BEFORE UPDATE ON leads WHEN NEW.status='contactado' BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
try{$service->changeStatus(1,'contactado');throw new RuntimeException('Failure was not propagated');}catch(PDOException $expected){}
check(!$pdo->inTransaction());
check($pdo->query('SELECT status FROM leads WHERE id=1')->fetchColumn()==='revisado');
echo "Lead states: passed\n";

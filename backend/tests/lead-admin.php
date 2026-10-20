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
echo "Lead states: passed\n";

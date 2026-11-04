<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev only.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic local database required.');
require '/srv/state/lead-status-test/LeadAdminService.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TEMPORARY TABLE leads(id INT PRIMARY KEY,status VARCHAR(30) NOT NULL,CONSTRAINT status_test CHECK (status <> 'contactado')) ENGINE=InnoDB");$pdo->exec("INSERT INTO leads VALUES(1,'nuevo')");$service=new LeadAdminService($pdo);
if($service->changeStatus(1,'revisado')!=='saved'||$service->changeStatus(1,'revisado')!=='unchanged'||$service->changeStatus(99,'revisado')!=='missing')throw new RuntimeException('Unexpected state.');
try{$service->changeStatus(1,'contactado');throw new LogicException('Expected constraint failure.');}catch(PDOException $expected){}
if($pdo->inTransaction()||$pdo->query('SELECT status FROM leads WHERE id=1')->fetchColumn()!=='revisado')throw new RuntimeException('Failed to roll back.');
echo "OK MariaDB: status change, repeat, missing and rollback on temporary table only.\n";

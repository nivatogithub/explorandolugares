<?php
declare(strict_types=1);
// CLI-only disposable MariaDB database; never included in the production package.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo preproducción local.');
$c=require '/srv/state/private/config.php';if(empty($c['local_test']))throw new RuntimeException('Base productiva rechazada.');
require '/srv/portal/app/BusinessSubscriptionAdminService.php';
$admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_subscription_'.bin2hex(random_bytes(6));$admin->exec('CREATE DATABASE '.$name);
try{
 $dsn='mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4';$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('CREATE TABLE businesses(id BIGINT PRIMARY KEY) ENGINE=InnoDB');$db->exec('INSERT INTO businesses VALUES(1)');
 $db->exec('CREATE TABLE subscription_plans(code VARCHAR(24) PRIMARY KEY,is_active INT) ENGINE=InnoDB');$db->exec("INSERT INTO subscription_plans VALUES('free',1)");
 $db->exec('CREATE TABLE business_subscriptions(id BIGINT AUTO_INCREMENT PRIMARY KEY,business_id BIGINT,plan_code VARCHAR(24),billing_frequency VARCHAR(24),status VARCHAR(40),starts_at DATETIME,ends_at DATETIME,reviewed_at DATETIME,publish_not_before DATE,quoted_amount DECIMAL(12,2),currency CHAR(3)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE markers(id INT PRIMARY KEY) ENGINE=InnoDB');$service=new BusinessSubscriptionAdminService($db);$input=['business_id'=>1,'plan_code'=>'free'];$id=$service->save($input);
 $db->exec("CREATE TRIGGER test_failure BEFORE INSERT ON business_subscriptions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected failure'");
 foreach([false,true] as $nested){
  if($nested){$db->beginTransaction();$db->exec('INSERT INTO markers VALUES(1)');}
  $failed=false;try{$service->save($input);}catch(PDOException){$failed=true;}
  if(!$failed||$db->query('SELECT status FROM business_subscriptions WHERE id='.$id)->fetchColumn()!=='pending_publication')throw new RuntimeException('No preservó la solicitud anterior.');
  if($nested){if(!$db->inTransaction()||(int)$db->query('SELECT COUNT(*) FROM markers')->fetchColumn()!==1)throw new RuntimeException('Alteró la transacción del llamador.');$db->commit();}
 }
 $db->exec('DROP TRIGGER test_failure');$db->beginTransaction();$service->save($input);
 $childCode='require "/srv/portal/app/BusinessSubscriptionAdminService.php";$p=new PDO('.var_export($dsn,true).',"root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("SET SESSION innodb_lock_wait_timeout=1");try{(new BusinessSubscriptionAdminService($p))->save(["business_id"=>1,"plan_code"=>"free"]);echo "UNEXPECTED_SUCCESS";}catch(PDOException $e){if(($e->errorInfo[1]??null)!==1205)throw $e;echo "BLOCKED_BY_ROW_LOCK";}';
 $proc=proc_open([PHP_BINARY,'-r',$childCode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('No inició la conexión concurrente.');fclose($pipes[0]);
 $result=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
 if($exit!==0||$result!=='BLOCKED_BY_ROW_LOCK')throw new RuntimeException('La segunda conexión no quedó bloqueada: '.json_encode(['result'=>$result,'error'=>$error]));
 $db->commit();$retry=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);(new BusinessSubscriptionAdminService($retry))->save($input);
 if((int)$db->query("SELECT COUNT(*) FROM business_subscriptions WHERE status='pending_publication'")->fetchColumn()!==1)throw new RuntimeException('Quedaron solicitudes simultáneas pendientes.');
 echo "OK MariaDB: fallo de INSERT revierte cancelación; savepoint conserva transacción del llamador; dos conexiones serializadas y una solicitud pendiente.\n";
}finally{if(isset($db)&&$db->inTransaction())$db->rollBack();$admin->exec('DROP DATABASE '.$name);}

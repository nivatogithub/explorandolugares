<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo preproducción local.');
$c=require '/srv/state/private/config.php';if(empty($c['local_test']))throw new RuntimeException('Base productiva rechazada.');
require '/srv/portal/app/BusinessSubscriptionAdminService.php';
$root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_upgrades_'.bin2hex(random_bytes(5));$root->exec('CREATE DATABASE '.$name);
try{
 $db=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('CREATE TABLE businesses(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');$db->exec('INSERT INTO businesses VALUES(1)');
 $db->exec('CREATE TABLE subscription_plans(code VARCHAR(24) PRIMARY KEY,is_active INT) ENGINE=InnoDB');$db->exec("INSERT INTO subscription_plans VALUES('free',1),('featured',1),('page',1),('page_premium',1)");
 $db->exec('CREATE TABLE subscription_plan_prices(plan_code VARCHAR(24),billing_frequency VARCHAR(24),amount DECIMAL(14,2),currency CHAR(3)) ENGINE=InnoDB');$db->exec("INSERT INTO subscription_plan_prices VALUES('featured','monthly',49900,'COP'),('page','monthly',69900,'COP'),('page_premium','monthly',119900,'COP')");
 $db->exec('CREATE TABLE business_subscriptions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,business_id BIGINT UNSIGNED,plan_code VARCHAR(24),billing_frequency VARCHAR(24),status VARCHAR(40),starts_at DATETIME,ends_at DATETIME,reviewed_at DATETIME,publish_not_before DATE,quoted_amount DECIMAL(14,2),currency CHAR(3)) ENGINE=InnoDB');
 $sql=file_get_contents('/srv/portal/app/database/migrations/044-subscription-changes.mysql.sql');$db->exec($sql);$db->exec($sql);
 $db->exec("INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,reviewed_at,quoted_amount,currency) VALUES(1,'featured','monthly','active','2028-04-01','2028-05-01','2028-04-01',49900,'COP')");
 $at=new DateTimeImmutable('2028-04-16',new DateTimeZone('UTC'));$service=new BusinessSubscriptionAdminService($db,$at);$changes=new SubscriptionChangeService($db);$id=$service->save(['business_id'=>1,'plan_code'=>'page','billing_frequency'=>'monthly']);$record=$changes->record($id);
 if((int)$record['credit_cents']!==2495000||(int)$record['due_cents']!==1000000)throw new RuntimeException('Prorrateo incorrecto en MariaDB.');
 if($db->query('SELECT status FROM business_subscriptions WHERE id=1')->fetchColumn()!=='active')throw new RuntimeException('El upgrade anuló la suscripción vigente.');
 $db->exec("CREATE TRIGGER injected_failure BEFORE INSERT ON subscription_changes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected failure'");
 $failed=false;try{$service->save(['business_id'=>1,'plan_code'=>'page_premium','billing_frequency'=>'monthly']);}catch(PDOException){$failed=true;}
 if(!$failed||$db->query('SELECT status FROM business_subscriptions WHERE id='.$id)->fetchColumn()!=='pending')throw new RuntimeException('Falló la reversión conjunta de solicitud y saldo.');
 $db->exec('DROP TRIGGER injected_failure');$db->exec("UPDATE business_subscriptions SET status='paid_pending_publication',reviewed_at='2028-04-16' WHERE id=".$id);
 $sub=$db->query('SELECT * FROM business_subscriptions WHERE id='.$id)->fetch(PDO::FETCH_ASSOC);$activation=$changes->activation($sub,$at);
 if($activation['ends_at']!=='2028-05-01 00:00:00')throw new RuntimeException('El upgrade extendió su vigencia.');
 echo "OK MariaDB: migración 044 idempotente, crédito 24950, diferencia 10000, vigencia preservada y rollback de saldo/solicitud ante fallo. Base aislada.\n";
}finally{if(isset($db)&&$db->inTransaction())$db->rollBack();$root->exec('DROP DATABASE '.$name);}

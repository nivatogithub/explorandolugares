<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Disposable local test only.');
$root=$argv[1]??'';require $root.'/app/BusinessPromotions.php';
$admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_promotion_'.bin2hex(random_bytes(6));if(!preg_match('/^el_test_promotion_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Invalid test database.');
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
try{
    $connect=static function()use($name):PDO{$p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('SET time_zone="+00:00"');return $p;};$p=$connect();
    $p->exec('CREATE TABLE accounts(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
    $p->exec('CREATE TABLE destinations(id BIGINT UNSIGNED PRIMARY KEY,publication_status VARCHAR(32)) ENGINE=InnoDB');
    $p->exec('CREATE TABLE businesses(id BIGINT UNSIGNED PRIMARY KEY,destination_id BIGINT UNSIGNED) ENGINE=InnoDB');
    $p->exec('CREATE TABLE business_subscriptions(id BIGINT UNSIGNED PRIMARY KEY,business_id BIGINT UNSIGNED,plan_code VARCHAR(32),billing_frequency VARCHAR(32),status VARCHAR(32),quoted_amount DECIMAL(14,2),currency CHAR(3),starts_at DATETIME NULL,reviewed_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $p->exec('CREATE TABLE subscription_plans(code VARCHAR(32) PRIMARY KEY,is_active INT) ENGINE=InnoDB');
    $p->exec('CREATE TABLE subscription_plan_prices(plan_code VARCHAR(32),billing_frequency VARCHAR(32),amount DECIMAL(14,2),currency CHAR(3)) ENGINE=InnoDB');
    $p->exec('CREATE TABLE business_account_memberships(business_id BIGINT UNSIGNED,account_id BIGINT UNSIGNED,status VARCHAR(32)) ENGINE=InnoDB');
    $sql=file_get_contents($root.'/database/migrations/056-business-promotions.mysql.sql');
    $apply=static function()use($p,$sql):void{foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$p->exec($statement);};$apply();$apply();
    $p->exec('INSERT INTO accounts VALUES(1);INSERT INTO destinations VALUES(1,"published"),(2,"published");INSERT INTO businesses VALUES(1,1),(2,2);INSERT INTO subscription_plans VALUES("page",1);INSERT INTO subscription_plan_prices VALUES("page","monthly",79900,"COP");INSERT INTO business_account_memberships VALUES(1,1,"active"),(2,1,"active");INSERT INTO business_subscriptions VALUES(1,1,"page","monthly","pending",79900,"COP",NULL,NULL),(2,2,"page","monthly","pending",79900,"COP",NULL,NULL)');
    // Upgrade a legacy code before geographic rules existed.
    $p->exec('INSERT INTO business_promotion_codes(code,percentage,plans_json,periods_json,created_by) VALUES("LEGACY",100,\'["page"]\',\'["monthly"]\',"fixture")');
    $p->exec('INSERT INTO businesses VALUES(3,2);INSERT INTO business_subscriptions VALUES(3,3,"page","monthly","paid_pending_publication",0,"COP",NULL,CURRENT_TIMESTAMP);INSERT INTO business_promotion_redemptions(subscription_id,code,business_id,account_id,plan_code,billing_frequency,percentage,list_cents,addon_cents,discount_cents,due_cents,currency) VALUES(3,"LEGACY",3,1,"page","monthly",100,7990000,0,7990000,0,"COP")');
    $sqlPlaces=file_get_contents($root.'/database/migrations/057-business-promotion-places.mysql.sql');
    $applyPlaces=static function()use($p,$sqlPlaces):void{foreach(explode(';',$sqlPlaces) as $statement)if(trim($statement)!=='')$p->exec($statement);};$applyPlaces();$applyPlaces();
    $service=new BusinessPromotions($p);if($service->quote('LEGACY','page','monthly',2)['due_cents']!==0||!$service->covered(3)||(int)$p->query('SELECT destination_id FROM business_promotion_redemption_places WHERE subscription_id=3')->fetchColumn()!==2)throw new RuntimeException('Legacy availability or previously redeemed coverage changed.');
    $settings=['code'=>'UNUSO','percentage'=>100,'plans'=>['page'],'periods'=>['monthly'],'destinations'=>[1,2],'enabled'=>1,'max_uses'=>1];$service->save($settings,'fixture-admin');
    // Another transaction starts with an old consistent snapshot before the first redemption.
    $other=$connect();$other->beginTransaction();$other->query('SELECT COUNT(*) FROM business_promotion_redemptions')->fetchColumn();
    $p->beginTransaction();$service->redeem(1,1,1,'UNUSO');$p->commit();
    $denied=false;try{(new BusinessPromotions($other))->redeem(2,2,1,'UNUSO');}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$denied=str_contains($e->getMessage(),'límite');}$other->rollBack();
    if(!$denied||!$service->covered(1)||(int)$p->query('SELECT COUNT(*) FROM business_promotion_redemptions WHERE code="UNUSO"')->fetchColumn()!==1)throw new RuntimeException('Usage limit or coverage failed under stale snapshot.');
    $service->save([...$settings,'code'=>'SCOPE','max_uses'=>''],'fixture-admin');
    $other->beginTransaction();$other->query('SELECT destination_id FROM business_promotion_places WHERE code="SCOPE"')->fetchAll();
    $service->save([...$settings,'code'=>'SCOPE','max_uses'=>'','destinations'=>[1]],'fixture-admin');
    $denied=false;try{(new BusinessPromotions($other))->redeem(2,2,1,'SCOPE');}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$denied=str_contains($e->getMessage(),'lugar');}$other->rollBack();
    if(!$denied)throw new RuntimeException('Stale geographic scope allowed a newly excluded place.');
    $apply();if(!$service->covered(1))throw new RuntimeException('Migration rerun changed redeemed terms.');
    $service->save([...$settings,'destinations'=>[2],'enabled'=>0],'fixture-admin');$applyPlaces();
    if(!$service->covered(1)||(int)$p->query('SELECT destination_id FROM business_promotion_redemption_places WHERE subscription_id=1')->fetchColumn()!==1||(int)$p->query('SELECT COUNT(*) FROM business_promotion_places WHERE code="UNUSO"')->fetchColumn()!==1)throw new RuntimeException('Geographic edits or migration rerun changed historic coverage.');
    $denied=false;try{$p->exec('DELETE FROM business_promotion_codes WHERE code="UNUSO"');}catch(PDOException $e){$denied=$e->getCode()==='23000';}if(!$denied)throw new RuntimeException('Used coupon deleted.');
    // The late-checkout path uses the business lock also used by receipt submission.
    $p->exec('ALTER TABLE accounts ADD status VARCHAR(32) NOT NULL DEFAULT "active";ALTER TABLE businesses ADD publication_status VARCHAR(32) NOT NULL DEFAULT "draft";ALTER TABLE business_account_memberships ADD role VARCHAR(32) NOT NULL DEFAULT "owner"');
    $p->exec('CREATE TABLE business_editorial_versions(id BIGINT UNSIGNED PRIMARY KEY,business_id BIGINT UNSIGNED,status VARCHAR(32)) ENGINE=InnoDB;CREATE TABLE business_subscription_payment_evidence(id BIGINT UNSIGNED PRIMARY KEY,subscription_id BIGINT UNSIGNED) ENGINE=InnoDB;CREATE TABLE business_experience_subscriptions(subscription_id BIGINT UNSIGNED PRIMARY KEY,quoted_amount DECIMAL(14,2)) ENGINE=InnoDB');
    $p->exec('INSERT INTO businesses(id,destination_id) VALUES(4,1),(5,1);INSERT INTO business_account_memberships(business_id,account_id,status) VALUES(4,1,"active"),(5,1,"active");INSERT INTO business_subscriptions VALUES(4,4,"page","monthly","pending",70000,"COP",NULL,NULL),(5,5,"page","monthly","pending",70000,"COP",NULL,NULL);INSERT INTO business_editorial_versions VALUES(4,4,"draft"),(5,5,"draft")');
    $service->save([...$settings,'code'=>'ENPAGO','max_uses'=>1,'destinations'=>[1]],'fixture-admin');
    $offer=$service->applyAtPayment(1,4,4,'ENPAGO');
    if($offer['list_cents']!==7000000||$offer['due_cents']!==0||!$service->covered(4)||$service->applyAtPayment(1,4,4,'ENPAGO')['due_cents']!=0)throw new RuntimeException('Payment coupon captured price, retry or coverage failed.');
    $denied=false;try{$service->applyAtPayment(1,5,5,'ENPAGO');}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$denied=str_contains($e->getMessage(),'límite');}
    if(!$denied||$p->inTransaction()||(int)$p->query('SELECT COUNT(*) FROM business_subscription_payment_evidence')->fetchColumn()!==0||$p->query('SELECT publication_status FROM businesses WHERE id=4')->fetchColumn()!=='draft')throw new RuntimeException('Late checkout quota, rollback or privacy failed.');
    echo "OK MariaDB: 056/057 twice, legacy scope, explicit places, protected history, concurrent quota and late-checkout captured price/coverage/idempotency without receipt or publication.\n";
}finally{$other=null;$p=null;$admin->exec('DROP DATABASE `'.$name.'`');}

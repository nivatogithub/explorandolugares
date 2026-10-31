<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo pruebas locales.');
$root=$argv[1]??'/srv/portal/app';require $root.'/DatabaseMaintenance.php';require $root.'/SubscriptionPricing.php';
$admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_prices_'.bin2hex(random_bytes(6));
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try{
    $p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $p->exec('CREATE TABLE subscription_plans(code VARCHAR(32) PRIMARY KEY,is_active INT);CREATE TABLE subscription_plan_prices(plan_code VARCHAR(32),billing_frequency VARCHAR(16),amount DECIMAL(14,2),currency CHAR(3),PRIMARY KEY(plan_code,billing_frequency));CREATE TABLE subscription_pricing_policy(period_code VARCHAR(16) PRIMARY KEY,billable_months DECIMAL(4,2));CREATE TABLE experience_operator_prices(billing_frequency VARCHAR(16) PRIMARY KEY,amount DECIMAL(14,2),currency CHAR(3));');
    $p->exec('CREATE TABLE business_subscriptions(id INT PRIMARY KEY,quoted_amount DECIMAL(14,2));CREATE TABLE business_promotion_redemptions(id INT PRIMARY KEY,list_cents INT,due_cents INT);CREATE TABLE accounts(id INT PRIMARY KEY);CREATE TABLE statistics(id INT PRIMARY KEY,total INT);INSERT INTO business_subscriptions VALUES(1,199750),(2,0);INSERT INTO business_promotion_redemptions VALUES(1,19975000,0);INSERT INTO accounts VALUES(1);INSERT INTO statistics VALUES(1,1234)');
    foreach(['featured','page','page_premium'] as $plan)$p->prepare('INSERT INTO subscription_plans VALUES(?,1)')->execute([$plan]);
    $snapshot=static function()use($p):array{$s=[];foreach(['business_subscriptions','business_promotion_redemptions','accounts','statistics'] as $table)$s[$table]=$p->query('SELECT * FROM '.$table)->fetchAll();return $s;};$before=$snapshot();
    $maintenance=new DatabaseMaintenance($p,$root,'/srv/portal/public');
    for($run=0;$run<2;$run++){
        $maintenance->applyMigrations(['058-rounded-subscription-prices']);
        if($before!==$snapshot())throw new RuntimeException('Se alteró el historial financiero o datos protegidos.');
    }
    $expected=['featured'=>[25000,67500,127500,240000],'page'=>[40000,108000,204000,384000],'page_premium'=>[60000,162000,306000,576000]];$choice=new BusinessSubscriptionChoice($p);
    foreach($expected as $plan=>$amounts)foreach(array_values(array_keys(SubscriptionPricing::MONTHS)) as $i=>$period)if((float)$choice->quote($plan,$period)['quoted_amount']!==$amounts[$i]*1.0)throw new RuntimeException('Tarifa distinta: '.$plan.'/'.$period);
    foreach(['monthly'=>20000,'quarterly'=>54000,'semiannual'=>102000,'annual'=>192000] as $period=>$amount)if((float)(new ExperienceOperatorAddon($p))->prices()[$period]['amount']!==$amount*1.0)throw new RuntimeException('Complemento distinto.');
    // Force a later statement to fail: the public plan updates must roll back too.
    $p->exec('UPDATE subscription_plan_prices SET amount=123 WHERE plan_code="page" AND billing_frequency="monthly"');
    $p->exec("CREATE TRIGGER reject_prices BEFORE UPDATE ON experience_operator_prices FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
    $failed=false;try{$maintenance->applyMigrations(['058-rounded-subscription-prices']);}catch(PDOException){$failed=true;}
    if(!$failed||$p->inTransaction()||(float)$choice->quote('page','monthly')['quoted_amount']!==123.0||$before!==$snapshot())throw new RuntimeException('Falló el rollback integral.');
    $p->exec('DROP TRIGGER reject_prices');$maintenance->applyMigrations(['058-rounded-subscription-prices']);
    echo "OK MariaDB: 16 tarifas, migración 058 repetible, aplicación transaccional, fallo inyectado y reintento; suscripciones/cupones/cuentas/estadísticas intactos.\n";
}finally{$p=null;$admin->exec('DROP DATABASE `'.$name.'`');}

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev required.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'dbname=el_test_destination_'))throw new RuntimeException('Synthetic dev required.');
$app=realpath($argv[1]??'');if(!$app||!str_starts_with($app,'/srv/state/')||!is_file($app.'/database/migrations/061-business-payment-orders.mysql.sql'))throw new RuntimeException('Candidate required.');
require $app.'/DatabaseMaintenance.php';$p=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$tables=['accounts','businesses','business_subscriptions','business_subscription_payment_evidence','business_promotion_redemptions'];$before=[];
foreach($tables as $table)$before[$table]=$p->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
$backup='/srv/state/payment-orders-pre-migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';file_put_contents($backup,json_encode($before,JSON_THROW_ON_ERROR));chmod($backup,0600);
$maintenance=new DatabaseMaintenance($p,$app,'/srv/portal/public');$maintenance->applyMigrations(['061-business-payment-orders']);$maintenance->applyMigrations(['061-business-payment-orders']);
foreach($tables as $table)if($p->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll()!==$before[$table])throw new RuntimeException('Protected rows changed concurrently; inspect before continuing: '.$table);
$report=['migration'=>'061-business-payment-orders','applied_twice'=>true,'protected_tables_unchanged'=>true,'private_backup'=>basename($backup),'production_accessed'=>false,'payment_instructions_flags'=>$p->query('SELECT enabled,test_mode FROM payment_instructions WHERE id=1')->fetch()];file_put_contents('/srv/state/payment-orders-dev-migration-report.json',json_encode($report,JSON_PRETTY_PRINT)."\n");echo json_encode($report)."\n";

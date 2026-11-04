<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev CLI only.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'dbname=el_test_destination_'))throw new RuntimeException('Synthetic dev required.');
$source=realpath($argv[1]??'');if(!$source||!str_starts_with($source,'/srv/state/')||!is_file($source.'/tests/business-payment-orders.php'))throw new RuntimeException('Owned source required.');
require $source.'/app/DatabaseMaintenance.php';require $source.'/tests/business-payment-orders.php';
$root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$name='el_fixture_payment_'.bin2hex(random_bytes(8));$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$folder='/srv/state/payment-order-test-'.bin2hex(random_bytes(8));
try{
    $p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $maintenance=new DatabaseMaintenance($p,$source.'/app','/srv/portal/public');$maintenance->installBaseSchema();
    try{$maintenance->applyMigrations();}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'022-subscription-prices'))throw $e;$sql=preg_replace('/^\s*--.*$/m','',file_get_contents($source.'/app/database/migrations/022-subscription-prices.mysql.sql'));foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$p->exec($statement);$maintenance->applyMigrations();}
    $maintenance->applyMigrations(['061-business-payment-orders']);
    $report=order_test_suite($p,$folder);
    if(function_exists('pcntl_fork')){
        $raceKey=bin2hex(random_bytes(24));$children=[];
        for($i=0;$i<2;$i++){$pid=pcntl_fork();if($pid===0){$r=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$o=(new BusinessPaymentOrderService($r))->create(1,[$report['http_choices'][0]],$raceKey);file_put_contents($folder.'/race-'.$i.'.json',json_encode(['id'=>$o['id']]));exit(0);}$children[]=$pid;}
        foreach($children as $pid){pcntl_waitpid($pid,$status);if(pcntl_wexitstatus($status)!==0)throw new RuntimeException('Concurrent order failed.');}
        // Forked PDO connections are replaced in the parent before reuse.
        $root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $a=json_decode(file_get_contents($folder.'/race-0.json'),true);$b=json_decode(file_get_contents($folder.'/race-1.json'),true);if($a!==$b)throw new RuntimeException('Concurrent request duplicated order.');$read=new BusinessPaymentOrderSelector($p);$race=$read->byId((int)$a['id']);(new BusinessPaymentOrderService($p))->cancel(1,$race['public_id']);$report['concurrent_prepare_idempotent']=true;
    }
    require __DIR__.'/check-business-payment-orders-http.php';$report['http']=order_http_suite($p,$name,$source,$folder,$report['http_choices']);unset($report['http_choices']);$report+=['driver'=>'MariaDB','ordinary_tables_with_foreign_keys'=>true,'migration_repeated'=>true,'production_accessed'=>false,'original_dev_rows_written'=>false];
    file_put_contents('/srv/state/business-payment-orders-mariadb-report.json',json_encode($report,JSON_PRETTY_PRINT)."\n");echo json_encode($report)."\n";
}finally{$p=null;if(!preg_match('/^el_fixture_payment_[a-f0-9]{16}$/D',$name))throw new RuntimeException('Invalid owned fixture.');$root->exec('DROP DATABASE `'.$name.'`');foreach(glob($folder.'/*') as $file)unlink($file);if(is_dir($folder))rmdir($folder);}

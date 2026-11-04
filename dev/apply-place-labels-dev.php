<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev required.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'dbname=el_test_destination_'))throw new RuntimeException('Synthetic dev required.');
$app=realpath($argv[1]??'');
if(!$app||!str_starts_with($app,'/srv/state/')||!is_file($app.'/database/migrations/062-place-labels.mysql.sql'))throw new RuntimeException('Candidate required.');
require $app.'/DatabaseMaintenance.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$protected=['accounts','businesses','business_subscriptions','business_subscription_payment_evidence','business_promotion_redemptions','business_payment_orders','business_payment_order_items','business_payment_order_evidence'];$before=[];
foreach($protected as $table)$before[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
$places=$pdo->query('SELECT * FROM destinations ORDER BY id')->fetchAll();
$backup='/srv/state/place-labels-pre-migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
file_put_contents($backup,json_encode(['protected'=>$before,'places'=>$places],JSON_THROW_ON_ERROR));chmod($backup,0600);
$maintenance=new DatabaseMaintenance($pdo,$app,'/srv/portal/public');$maintenance->applyMigrations(['062-place-labels']);$maintenance->applyMigrations(['062-place-labels']);
foreach($protected as $table)if($pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll()!==$before[$table])throw new RuntimeException('Protected rows changed concurrently; inspect before continuing: '.$table);
$after=$pdo->query('SELECT * FROM destinations ORDER BY id')->fetchAll();if(count($after)!==count($places))throw new RuntimeException('Place cardinality changed.');
$changes=[];$expected=['barranquilla'=>'Atlántico','riohacha'=>'La Guajira','valledupar'=>'Cesar','san-bernardo-del-viento'=>'Córdoba'];
foreach($places as $index=>$row){$new=$after[$index];
    if($row['country_code']==='CO'&&($expected[$row['slug']]??null)===$row['department']&&trim((string)$row['subregion'])===''){
        if($new['subregion']!=='Caribe')throw new RuntimeException('Reviewed region missing.');
        $changes[]=$row['slug'];$row['subregion']='Caribe';$row['updated_at']=$new['updated_at'];
    }
    if($row!==$new)throw new RuntimeException('An unrelated place field changed; inspect without restoring.');
}
$labels=$pdo->query("SELECT d.slug,l.code,l.enabled FROM place_labels l JOIN destinations d ON d.id=l.place_id ORDER BY d.slug,l.code")->fetchAll();
$report=['migration'=>'062-place-labels','applied_twice'=>true,'region_updates'=>$changes,'labels'=>$labels,'protected_rows_unchanged'=>true,'place_ids_and_other_fields_preserved'=>true,'private_backup'=>basename($backup),'production_accessed'=>false];
file_put_contents('/srv/state/place-labels-dev-migration-report.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($report,JSON_UNESCAPED_UNICODE)."\n";

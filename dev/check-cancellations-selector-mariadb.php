<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev only.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic local database required.');
require '/srv/state/cancellation-selector-test/CancellationRequestSelector.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TEMPORARY TABLE subscription_cancellation_requests(id INT PRIMARY KEY,business_id INT,created_at DATETIME,status VARCHAR(20))');
$pdo->exec('CREATE TEMPORARY TABLE business_translations(business_id INT,locale VARCHAR(10),display_name VARCHAR(180))');
$pdo->exec("INSERT INTO business_translations VALUES(1,'es','Negocio sintético'),(1,'en','Synthetic business')");
$insert=$pdo->prepare('INSERT INTO subscription_cancellation_requests VALUES(?,?,?,?)');for($i=1;$i<=205;$i++)$insert->execute([$i,1,gmdate('Y-m-d H:i:s',1700000000+$i),'pending']);
$sql='SELECT r.*,t.display_name AS business_name FROM subscription_cancellation_requests r JOIN business_translations t ON t.business_id=r.business_id AND t.locale="es" ORDER BY r.created_at DESC LIMIT 200';
$expected=$pdo->query($sql)->fetchAll();$actual=(new CancellationRequestSelector($pdo))->recent();if($expected!==$actual||count($actual)!==200||(int)$actual[0]['id']!==205||(int)$actual[199]['id']!==6)throw new RuntimeException('Mismatch in ordering, limit or rows.');
echo "OK MariaDB: 205 synthetic cancellation requests; exact query and 200-row limit; temporary tables only.\n";

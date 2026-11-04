<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev required.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic local database required.');
require '/srv/state/public-request-selector-test/PublicRequestSelector.php';
require '/srv/state/public-request-selector-test/public-request-selector-contract.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);checkPublicRequestSelector($pdo);
echo "OK MariaDB: empty and 105 synthetic requests; same query, ordering and limit; temporary table only.\n";

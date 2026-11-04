<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
require '/srv/state/audience-selector-test/BusinessAudienceAccessSelector.php';
require '/srv/state/audience-selector-test/business-audience-selector-contract.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
checkBusinessAudienceAccessSelector($pdo);
echo "OK: 9 audience access cases on connection-scoped temporary MariaDB tables.\n";

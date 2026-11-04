<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
foreach(['ParticipationSelector.php','ParticipationWriter.php','ParticipationService.php','participation-persistence-contract.php'] as $file)require_once '/srv/state/participation-persistence-test/'.$file;
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
checkParticipationPersistence($pdo);
echo "OK: participation persistence and retry on temporary MariaDB tables.\n";

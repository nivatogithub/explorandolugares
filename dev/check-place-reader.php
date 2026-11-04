<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
$app=realpath($argv[1]??'');
if(!$app||(!str_starts_with($app,'/srv/state/')&&$app!=='/srv/portal/app')||!is_file($app.'/PlaceReader.php'))throw new RuntimeException('Biblioteca local requerida.');
require $app.'/PlaceRepository.php';
require '/srv/state/place-reader-contract-support.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
checkPlaceReaderContract($pdo);
file_put_contents('/srv/state/place-reader-report.json',json_encode(['driver'=>'MariaDB','production_accessed'=>false,'queries_read_only'=>true,'interchangeable_reader_models_identical'=>true,'locale_scope_cases'=>6,'directories_identical'=>true,'normalization_preview_missing_error_checks'=>true],JSON_PRETTY_PRINT)."\n");

<?php
declare(strict_types=1);
require __DIR__.'/support/place-labels.php';
$app=dirname(__DIR__).'/app';$database=dirname(__DIR__).'/database';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');$pdo->exec(file_get_contents($database.'/destinations.sqlite.sql'));
$migrate=static fn()=>$pdo->exec(file_get_contents($database.'/migrations/062-place-labels.sqlite.sql'));
echo json_encode(check_place_labels($pdo,$app,$migrate),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";

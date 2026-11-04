<?php
declare(strict_types=1);
require __DIR__.'/support/place-directory.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');$pdo->exec(file_get_contents(__DIR__.'/../database/destinations.sqlite.sql'));
echo json_encode(check_place_directory($pdo),JSON_PRETTY_PRINT)."\n";

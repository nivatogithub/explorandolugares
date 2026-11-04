<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev required.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!preg_match('/host=127\.0\.0\.1;dbname=(el_test_destination_[a-f0-9]+)(?:;|$)/D',$config['dsn'],$match))throw new RuntimeException('Synthetic dev required.');
$candidate=realpath($argv[1]??'');
if(!$candidate||!str_starts_with($candidate,'/srv/state/')||!is_file($candidate.'/app/database/migrations/062-place-labels.mysql.sql'))throw new RuntimeException('Candidate required.');
require $candidate.'/tests/support/place-labels.php';require $candidate.'/app/DatabaseMaintenance.php';
$pdo=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$name='el_fixture_place_labels_'.bin2hex(random_bytes(10));$source=$match[1];
$tables=['destinations','destination_translations','media_assets','media_asset_translations','destination_media'];$created=false;
try{
    $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$pdo->exec('USE `'.$name.'`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    try{foreach($tables as $table){$sql=$pdo->query('SHOW CREATE TABLE `'.$source.'`.`'.$table.'`')->fetch(PDO::FETCH_NUM)[1];$pdo->exec(str_replace('`'.$source.'`.','`'.$name.'`.',$sql));}}
    finally{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
    $foreign=$pdo->query('SELECT REFERENCED_TABLE_SCHEMA FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
    if(!$foreign||array_diff($foreign,[$name]))throw new RuntimeException('Fixture foreign keys are not isolated.');
    $maintenance=new DatabaseMaintenance($pdo,$candidate.'/app','/srv/portal/public');
    $migrate=static fn()=>$maintenance->applyMigrations(['062-place-labels']);
    $result=check_place_labels($pdo,$candidate.'/app',$migrate);
    $result['ordinary_tables_with_foreign_keys']=true;$result['production_accessed']=false;$result['original_dev_rows_written']=false;
    file_put_contents('/srv/state/place-labels-mariadb-report.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");echo json_encode($result)."\n";
}finally{if($created)$pdo->exec('DROP DATABASE `'.$name.'`');}

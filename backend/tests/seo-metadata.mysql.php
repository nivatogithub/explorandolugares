<?php
declare(strict_types=1);
// Disposable local database, never an application database or a configurable DSN.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local test only.');
$root=$argv[1]??'';
$admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_seo_'.bin2hex(random_bytes(6));
if(!preg_match('/^el_test_seo_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Invalid test database.');
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try{
    $seoFixtureDb=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    require $root.'/tests/seo-metadata.php';
    echo "OK MariaDB: metadata-only patch applied twice, custom copy and publication states preserved.\n";
}finally{$admin->exec('DROP DATABASE `'.$name.'`');}

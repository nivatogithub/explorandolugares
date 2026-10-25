<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local development only.');
require ($argv[1]??'/srv/portal').'/app/DatabaseClock.php';
$pdo=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$global=$pdo->query('SELECT @@global.time_zone')->fetchColumn();
$pdo->exec("SET SESSION time_zone='-07:00'");
if((int)$pdo->query('SELECT UTC_TIMESTAMP()<=CURRENT_TIMESTAMP')->fetchColumn()!==0)throw new RuntimeException('Did not reproduce time-zone mismatch.');
DatabaseClock::configure($pdo);
if($pdo->query('SELECT @@session.time_zone')->fetchColumn()!=='+00:00')throw new RuntimeException('Session is not UTC.');
if((int)$pdo->query('SELECT UTC_TIMESTAMP()<=CURRENT_TIMESTAMP AND DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 MONTH)>CURRENT_TIMESTAMP')->fetchColumn()!==1)throw new RuntimeException('New Premium period is not current.');
if($pdo->query('SELECT @@global.time_zone')->fetchColumn()!==$global)throw new RuntimeException('Changed server-wide time zone.');
echo "OK MariaDB: reproduces -07:00 failure, new UTC period immediately current, global configuration unchanged; no data written.\n";

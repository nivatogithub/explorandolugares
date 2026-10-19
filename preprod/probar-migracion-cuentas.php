<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')exit(1);
$pdo=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$name='el_test_migration_'.bin2hex(random_bytes(5));$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
try{
 $pdo->exec('USE `'.$name.'`');
 $pdo->exec('CREATE TABLE accounts(id BIGINT UNSIGNED PRIMARY KEY,email VARCHAR(255),password_hash VARCHAR(255),status VARCHAR(32),display_name VARCHAR(180)) ENGINE=InnoDB');
 $pdo->exec("INSERT INTO accounts VALUES(1,'admin@example.invalid','hash-sintetico-no-autenticable','active','Administrador'),(2,'pending@example.invalid','otra-prueba','pending_activation','Pendiente'),(3,'disabled@example.invalid','otro-hash','disabled','Deshabilitado')");
 $pdo->exec('CREATE TABLE el_basic_page_daily(day DATE,page_views BIGINT UNSIGNED) ENGINE=InnoDB');$pdo->exec("INSERT INTO el_basic_page_daily VALUES('2026-10-19',12345)");
 $before=$pdo->query('SELECT * FROM accounts ORDER BY id')->fetchAll();$stats=$pdo->query('SELECT * FROM el_basic_page_daily')->fetchAll();
 require '/srv/portal/app/DatabaseMaintenance.php';$m=new DatabaseMaintenance($pdo,'/srv/portal/app','/srv/portal/public');
 $m->applyMigrations(['043-account-lifecycle']);
 if($before!==$pdo->query('SELECT * FROM accounts ORDER BY id')->fetchAll()||$stats!==$pdo->query('SELECT * FROM el_basic_page_daily')->fetchAll())throw new RuntimeException('Cambió una cuenta o un contador.');
 if((int)$pdo->query('SELECT COUNT(*) FROM account_access_events')->fetchColumn()!==3)throw new RuntimeException('Faltó conservar un estado inicial.');
 $pdo->exec('DROP TABLE account_reactivations'); // Simulated interrupted DDL in this disposable DB only.
 $m->applyMigrations(['043-account-lifecycle']);
 if((int)$pdo->query('SELECT COUNT(*) FROM account_access_events')->fetchColumn()!==3)throw new RuntimeException('Reintentar duplicó estados.');
 $m->applyMigrations(['043-account-lifecycle']);
 if($before!==$pdo->query('SELECT * FROM accounts ORDER BY id')->fetchAll()||$stats!==$pdo->query('SELECT * FROM el_basic_page_daily')->fetchAll())throw new RuntimeException('El reintento alteró datos existentes.');
 echo "OK MariaDB: cuentas activas/pendientes/deshabilitadas preservadas; estados iniciales completos; migración interrumpida reanudable y sin duplicados; estadísticas intactas.\n";
}finally{$pdo->exec('DROP DATABASE `'.$name.'`');}

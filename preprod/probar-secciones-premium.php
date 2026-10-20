<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1'||!is_dir('/srv/state'))exit(80);
require '/srv/portal/app/BusinessServiceSections.php';
$p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$name='el_test_premium_'.bin2hex(random_bytes(6));
try{
 $p->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$p->exec('USE `'.$name.'`');$p->exec('CREATE TABLE businesses(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');$p->exec('INSERT INTO businesses VALUES(1)');$sql=file_get_contents('/srv/portal/app/database/migrations/045-business-service-sections.mysql.sql');$p->exec($sql);$p->exec($sql);
 $sections=['es'=>['restaurant'=>'Cocina local'],'en'=>['restaurant'=>'Local cuisine'],'pt-BR'=>['restaurant'=>'Cozinha local']];
 $p->beginTransaction();BusinessServiceSections::save($p,1,$sections);$p->commit();BusinessServiceSections::publication($p,1);if(BusinessServiceSections::read($p,1)!=$sections)throw new RuntimeException('Persistencia incompleta');
 $p->beginTransaction();BusinessServiceSections::save($p,1,['es'=>['other'=>'Temporal']]);$p->rollBack();if(BusinessServiceSections::read($p,1)!=$sections)throw new RuntimeException('Rollback incompleto');
 echo "OK MariaDB: migración idempotente, tres idiomas, persistencia y rollback en base aislada.\n";
}finally{$p->exec('DROP DATABASE IF EXISTS `'.$name.'`');}

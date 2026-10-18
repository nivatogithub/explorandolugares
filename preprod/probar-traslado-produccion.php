<?php
declare(strict_types=1);
$c=require '/srv/state/private/config.php';if(empty($c['local_test'])||!str_contains($c['dsn'],'dbname=explorando_preprod'))exit(80);
require '/srv/state/evidencia/ProductionContentTransfer.php';
$source=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$name='el_release_test_'.bin2hex(random_bytes(6));
try{
 $root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$root->exec('USE `'.$name.'`');$root->exec('SET FOREIGN_KEY_CHECKS=0');
 foreach($source->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){$ddl=$source->query('SHOW CREATE TABLE `'.$table.'`')->fetch();$root->exec(array_values($ddl)[1]);}
 $root->exec('SET FOREIGN_KEY_CHECKS=1');
 $pack=json_decode(file_get_contents('/srv/state/evidencia/candidato-produccion.json'),true,64,JSON_THROW_ON_ERROR);$transfer=new ProductionContentTransfer($root);
 $first=$transfer->apply($pack,'/srv/portal/public');$sourceRow=$root->query('SELECT business_id,source_url,checked_at,note FROM business_sources LIMIT 1')->fetch();$root->prepare('INSERT INTO business_sources(business_id,source_url,checked_at,note) VALUES(?,?,?,?)')->execute(array_values($sourceRow));$counts=[];foreach(['destinations','businesses','media_assets','business_sources','business_subscriptions'] as $table)$counts[$table]=(int)$root->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
 // Simulate production keeping a different UUID for the same existing business.
 $existingId=(int)$root->query('SELECT MIN(id) FROM businesses')->fetchColumn();$uuid='a1111111-2222-4333-8444-555555555555';$root->prepare('UPDATE businesses SET public_id=? WHERE id=?')->execute([$uuid,$existingId]);
 $second=$transfer->apply($pack,'/srv/portal/public');$check=$root->prepare('SELECT public_id FROM businesses WHERE id=?');$check->execute([$existingId]);if($check->fetchColumn()!==$uuid)throw new RuntimeException('Production UUID was replaced');foreach($counts as $table=>$count)if((int)$root->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==$count)throw new RuntimeException('Repeated import duplicated '.$table);
 if((int)$root->query('SELECT COUNT(*) FROM accounts')->fetchColumn()!==0)throw new RuntimeException('Accounts leaked');
 echo json_encode(['pass'=>true,'counts'=>$counts,'idempotent'=>true,'accounts_imported'=>0,'production_changed'=>false]),"\n";
}finally{$root->exec('DROP DATABASE `'.$name.'`');}

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1'||!isset($options))exit(1);
$pdo=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_restore_check_'.bin2hex(random_bytes(5));$file=$options['file'];
if(!preg_match('~^/tmp/el-restore-[a-f0-9]+\.sql\.gz$~D',$file))exit(1);
$created=false;$process=null;$error=tmpfile();
try{
 if(!in_array(strtoupper((string)$pdo->query('SELECT @@event_scheduler')->fetchColumn()),['OFF','DISABLED'],true))throw new RuntimeException('El verificador requiere el planificador local apagado.');
 $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$created=true;
 $pipes=[];$process=proc_open(['/usr/bin/mariadb','--user=root','--socket=/run/mysqld/mysqld.sock','--one-database',$name],[0=>['pipe','r'],1=>['file','/dev/null','w'],2=>$error],$pipes);
 $gz=gzopen($file,'rb');while(!gzeof($gz)){$chunk=gzread($gz,1048576);if($chunk===false)throw new RuntimeException('Archivo incompleto.');$offset=0;while($offset<strlen($chunk)){$n=fwrite($pipes[0],substr($chunk,$offset));if(!$n)throw new RuntimeException('Importación interrumpida.');$offset+=$n;}}
 gzclose($gz);fclose($pipes[0]);$code=proc_close($process);$process=null;if($code!==0)throw new RuntimeException('La restauración aislada no completó.');
 $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE'");$q->execute([$name]);$tables=(int)$q->fetchColumn();
 if($tables!==(int)$options['tables'])throw new RuntimeException('El número de tablas restauradas no coincide.');
 $counts=[];foreach($pdo->query('SHOW TABLES FROM `'.$name.'`')->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Nombre de tabla inesperado.');if(str_starts_with($table,'el_basic_')||str_starts_with($table,'el_measurement_'))$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$name.'`.`'.$table.'`')->fetchColumn();}
 $upgrade=[];
 if(!empty($options['audit2_migrations'])){
  $copy=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $original=$copy->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$checks=[];
  foreach($original as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Nombre inesperado.');$row=$copy->query('CHECKSUM TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM);if($row[1]===null)throw new RuntimeException('No se pudo verificar checksum.');$checks[$table]=$row[1];}
  require '/srv/portal/app/DatabaseMaintenance.php';$maintenance=new DatabaseMaintenance($copy,'/srv/portal/app','/srv/portal/public');$selected=['045-business-service-sections','046-premium-page-aliases'];$maintenance->applyMigrations($selected);$maintenance->applyMigrations($selected);
  $state=$maintenance->inspect();foreach($selected as $migration)if(($state['migrations'][$migration]??'')!=='applied')throw new RuntimeException('Migración incompleta.');
  foreach($checks as $table=>$checksum){if($table==='el_schema_migrations')continue;$row=$copy->query('CHECKSUM TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM);if((string)$row[1]!== (string)$checksum)throw new RuntimeException('Cambió una tabla anterior.');}
  $upgrade=['audit2_migrations_passed'=>true,'existing_tables_unchanged'=>count($checks)-(isset($checks['el_schema_migrations'])?1:0),'migration_repeat_passed'=>true];
 }
 echo json_encode(array_merge(['restore_test_passed'=>true,'tables'=>$tables,'statistics_tables_restored'=>count($counts),'isolated_database_only'=>true,'production_modified'=>false],$upgrade));
}finally{if(is_resource($process))proc_terminate($process);fclose($error);if($created)$pdo->exec('DROP DATABASE `'.$name.'`');if(is_file($file))unlink($file);}

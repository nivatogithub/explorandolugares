<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo preproducción local.');
$c=require '/srv/state/private/config.php';if(empty($c['local_test']))throw new RuntimeException('Producción rechazada.');
$root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$name='el_test_audit_migrations_'.bin2hex(random_bytes(5));$root->exec('CREATE DATABASE '.$name);$file='/tmp/'.$name.'.php';
try{
 $dsn='mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4';$db=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->exec('CREATE TABLE accounts(id BIGINT UNSIGNED PRIMARY KEY,status VARCHAR(32)) ENGINE=InnoDB');$db->exec("INSERT INTO accounts VALUES(1,'active'),(2,'pending_activation'),(3,'disabled')");
 $db->exec('CREATE TABLE business_subscriptions(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');$db->exec('INSERT INTO business_subscriptions VALUES(1)');
 $db->exec('CREATE TABLE el_basic_page_daily(day DATE,page_views BIGINT) ENGINE=InnoDB');$db->exec("INSERT INTO el_basic_page_daily VALUES('2026-10-19',12345)");
 file_put_contents($file,'<?php return '.var_export(['dsn'=>$dsn,'db_user'=>'root','db_password'=>''],true).';');chmod($file,0600);
 $run=static function(string $operation,array $selected)use($file):array{
  $options=['config'=>$file,'app_root'=>'/srv/portal/app','public_root'=>'/srv/portal/public','operation'=>$operation,'migrations'=>$selected];
  $code='$options='.var_export($options,true).';require "/tmp/el-audit-migration-helper.php";';
  $process=proc_open([PHP_BINARY,'-r',$code],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
  if($exit!==0)throw new RuntimeException('El helper de migración rechazó el ensayo: '.$out.' '.$err);return json_decode($out,true,512,JSON_THROW_ON_ERROR);
 };
 $selected=['043-account-lifecycle','044-subscription-changes'];$probe=$run('probe',$selected);
 if($probe['migration_states']!==array_fill_keys(['044-subscription-changes','043-account-lifecycle'],'pending'))throw new RuntimeException('La consulta no identificó ambas pendientes.');
 if((int)$db->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn()!==3)throw new RuntimeException('La consulta creó tablas.');
 $apply=$run('apply',$selected);$retry=$run('apply',$selected);
 foreach([$apply,$retry] as $result)foreach($result['migration_states'] as $state)if($state!=='applied')throw new RuntimeException('No confirmó el esquema completo.');
 if((int)$db->query('SELECT COUNT(*) FROM account_access_events')->fetchColumn()!==3||(int)$db->query('SELECT page_views FROM el_basic_page_daily')->fetchColumn()!==12345||(int)$db->query('SELECT COUNT(*) FROM business_subscriptions')->fetchColumn()!==1)throw new RuntimeException('Cambió datos anteriores o duplicó estados.');
 if($db->query('SELECT event_type FROM account_access_events WHERE account_id=3')->fetchColumn()!=='suspended')throw new RuntimeException('No conservó el bloqueo previo.');
 echo "OK MariaDB: helper consulta sin crear tablas, aplica solo 043/044, reintento idempotente, estados preservados y estadísticas intactas. Base temporal.\n";
}finally{@unlink($file);$root->exec('DROP DATABASE '.$name);}

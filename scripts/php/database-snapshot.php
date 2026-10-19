<?php
declare(strict_types=1);
// CLI only. The launcher injects $options; credentials never leave the host.
if((PHP_SAPI!=='cli'&&!(defined('EL_PRIVATE_SNAPSHOT')&&EL_PRIVATE_SNAPSHOT))||!isset($options))exit(1);
try{
 $config=require $options['config'];$dsn=(string)$config['dsn'];
 if(!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Se requiere MySQL/MariaDB.');
 $parts=[];foreach(explode(';',substr($dsn,6)) as $piece){if(str_contains($piece,'=')){[$k,$v]=explode('=',$piece,2);$parts[$k]=$v;}}
 $pdo=new PDO($dsn,$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $tables=$pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_ASSOC);
 $unsupported=array_filter($tables,static fn($t)=>strtolower((string)$t['ENGINE'])!=='innodb');
 $binary=null;foreach(['/usr/bin/mariadb-dump','/usr/bin/mysqldump','/usr/local/bin/mysqldump'] as $candidate)if(is_executable($candidate)){$binary=$candidate;break;}
 $metadata=['database_version'=>$pdo->query('SELECT VERSION()')->fetchColumn(),'tables'=>count($tables),'transactional'=>!$unsupported,'dump_tool_available'=>$binary!==null&&function_exists('proc_open')&&function_exists('gzopen'),'snapshot_method'=>'single-transaction; no table locks','statistics_modified'=>false];
 if(($options['operation']??'')==='probe'){echo json_encode($metadata,JSON_THROW_ON_ERROR);exit;}
 if(!$binary||$unsupported)throw new RuntimeException('No se puede garantizar el respaldo consistente sin bloqueos; revisar motor y herramienta.');
 $directory=dirname($options['config']).'/backups';if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('No se pudo crear el directorio privado.');chmod($directory,0700);
 $prefix=$directory.'/db-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));$path=$prefix.'.sql.gz';$auth=tempnam($directory,'.dump-auth-');chmod($auth,0600);
 $escape=static fn($v)=>'"'.addcslashes((string)$v,"\\\"\n\r").'"';
 $ini="[client]\nuser=".$escape($config['db_user']??'')."\npassword=".$escape($config['db_password']??'')."\nhost=".$escape($parts['host']??'localhost')."\n";
 if(isset($parts['port']))$ini.='port='.(int)$parts['port']."\n";
 if(isset($parts['unix_socket']))$ini.='socket='.$escape($parts['unix_socket'])."\n";
 file_put_contents($auth,$ini,LOCK_EX);
 $errorFile=tmpfile();$pipes=[];$gz=null;$process=null;
 try{
  $args=[$binary,'--defaults-extra-file='.$auth,'--single-transaction','--quick','--skip-lock-tables','--no-tablespaces','--routines','--events','--triggers','--hex-blob','--default-character-set=utf8mb4',(string)$parts['dbname']];
  $process=proc_open($args,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>$errorFile],$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($process))throw new RuntimeException('No se pudo iniciar la herramienta de respaldo.');
  $gz=gzopen($path,'wb6');chmod($path,0600);if(!$gz)throw new RuntimeException('No se pudo abrir el respaldo.');
  while(!feof($pipes[1])){$chunk=fread($pipes[1],1048576);if($chunk===false)throw new RuntimeException('Lectura incompleta.');if($chunk!==''&&gzwrite($gz,$chunk)!==strlen($chunk))throw new RuntimeException('Escritura incompleta.');}
  fclose($pipes[1]);gzclose($gz);$gz=null;$exit=proc_close($process);$process=null;
  if($exit!==0||filesize($path)<100)throw new RuntimeException('La herramienta no terminó correctamente; respaldo no válido.');
 }catch(Throwable $e){if(is_resource($gz))gzclose($gz);if(is_resource($process))proc_terminate($process);if(is_file($path))unlink($path);throw $e;}
 finally{unlink($auth);fclose($errorFile);}
 $metadata+=['created_at'=>gmdate('c'),'remote_path'=>$path,'sha256'=>hash_file('sha256',$path),'bytes'=>filesize($path),'consistency'=>'Datos InnoDB en una transacción; no ejecutar cambios de esquema durante el respaldo.'];
 file_put_contents($prefix.'.json',json_encode($metadata,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($prefix.'.json',0600);
 if(!class_exists('BackupRetention'))require_once $options['retention_class']??__DIR__.'/BackupRetention.php';
 try{$metadata['retention']=BackupRetention::run($directory,$directory,true);}catch(Throwable){$metadata['retention']=['applied'=>false,'error'=>'No se retiraron todas las copias vencidas; requiere revisión.'];}
 echo json_encode($metadata,JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(PHP_SAPI==='cli')fwrite(STDERR,"No se completó el respaldo. Revisa disponibilidad, permisos y espacio.\n");else{http_response_code(503);echo json_encode(['error'=>'Respaldo incompleto; revisar permisos, motor y espacio.']);}exit(1);}

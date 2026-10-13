<?php
/** Herramienta privada CLI: no colocar dentro de public_html. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
$mode=$argv[1]??'';
if (!in_array($mode,['--instalar','--comprobar'],true) || count($argv)!==2) {
 fwrite(STDERR,"Uso: php bin/base-datos.php --instalar|--comprobar\n");exit(1);
}
try {
 $file=dirname(__DIR__).'/private/config.php';
 if (!is_file($file)) throw new RuntimeException('Falta private/config.php.');
 $config=require $file;
 if (!str_starts_with($config['dsn']??'','mysql:')) throw new RuntimeException('Esta herramienta requiere MySQL/MariaDB.');
 $pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if ($mode==='--instalar') {
  // MySQL DDL no admite rollback completo: nunca instalar encima de tablas existentes.
  if ($pdo->query('SHOW TABLES')->fetchColumn()!==false) throw new RuntimeException('La base no está vacía. No se modificó; revisa las migraciones y un respaldo.');
  $sql=file_get_contents(dirname(__DIR__).'/database/mysql.sql');
  foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $pdo->exec($statement);
  echo "Esquema de interesados instalado.\n";
 }
 foreach (['leads'=>['id','request_key','name','business','municipality','category','email','phone','phone_type','participation_version','has_brand','brand_name','description','interest','wants_contact','consent_version','status','created_at'],'rate_limits'=>['bucket_key','hits','expires_at']] as $table=>$fields) {
  $pdo->query('SELECT '.implode(',',$fields).' FROM '.$table.' LIMIT 0');
 }
 echo "Conexión y columnas de interesados correctas.\n";
 echo empty($config['registration_enabled'])?"Recepción desactivada en configuración.\n":"Recepción habilitada en configuración; verifica también formulario, privacidad y HTTPS.\n";
} catch (Throwable $e) {
 // No imprimir excepciones PDO: pueden incluir servidor, usuario o datos privados.
 fwrite(STDERR, $e instanceof PDOException?"No se pudo completar la operación de base de datos. Revisa conexión, permisos y esquema en el hosting. Si fue una instalación, revisa tablas parciales antes de repetir.\n":$e->getMessage()."\n");exit(1);
}

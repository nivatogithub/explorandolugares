<?php
declare(strict_types=1);
$appDirectory=dirname(__DIR__);
if (!is_file($appDirectory.'/DestinationImporter.php')) $appDirectory.='/app';
require $appDirectory.'/DestinationImporter.php';
$root=dirname(__DIR__,2);
if (!is_file($root.'/content/destinos-dinamicos.json')) $root=dirname(__DIR__);
$configCandidates=[dirname(__DIR__).'/private/config.php',dirname(__DIR__,2).'/private/config.php'];
$defaultConfig=dirname(__DIR__).'/private/config.php';
foreach($configCandidates as $candidate) if(is_file($candidate)){$defaultConfig=$candidate;break;}
$configPath=getenv('EL_DESTINATIONS_CONFIG') ?: $defaultConfig;
$dsn=getenv('EL_DESTINATIONS_DSN') ?: '';
$user=getenv('EL_DESTINATIONS_DB_USER') ?: null;
$password=getenv('EL_DESTINATIONS_DB_PASSWORD') ?: null;
if ($dsn==='') {
    if (!is_file($configPath)) { fwrite(STDERR,"Falta configuración privada de base de datos.\n"); exit(2); }
    $config=require $configPath;
    $dsn=(string)($config['dsn']??'');$user=$config['db_user']??null;$password=$config['db_password']??null;
}
if ($dsn==='') { fwrite(STDERR,"Falta el DSN.\n"); exit(2); }
$pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' && !in_array('--confirm-empty-catalog',$argv,true)) {
    fwrite(STDERR,"La importación MySQL requiere --confirm-empty-catalog y solo funciona con el catálogo vacío.\n"); exit(2);
}
$catalog=json_decode(file_get_contents($root.'/content/destinos-dinamicos.json'),true,512,JSON_THROW_ON_ERROR);
$options=json_decode(file_get_contents($appDirectory.'/contact-options.json'),true,512,JSON_THROW_ON_ERROR);
try {
    $counts=DestinationImporter::import($pdo,$catalog,$options);
    foreach ($counts as $name=>$count) fwrite(STDOUT,"{$name}: {$count}\n");
    fwrite(STDOUT,"Carga terminada. No se crearon comercios ni cuentas de prueba.\n");
} catch (Throwable $error) {
    fwrite(STDERR,"Importación cancelada: ".$error->getMessage()."\n"); exit(1);
}

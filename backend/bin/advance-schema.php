<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/advance/SharedCatalogMigration.php';
if($argc!==2||$argv[1]!=='--integrar-catalogo'){fwrite(STDERR,"Uso: php backend/bin/advance-schema.php --integrar-catalogo\n");exit(1);}
try{
    $path=dirname(__DIR__).'/private/config.php';if(!is_file($path))throw new RuntimeException('Falta la configuración privada.');
    $config=require $path;
    $db=new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    SharedCatalogMigration::apply($db);echo "Advance Admin integrado con el catálogo compartido.\n";
}catch(Throwable $e){fwrite(STDERR,$e instanceof PDOException?"No se completó la integración. Revisa esquema, permisos y respaldo; MySQL puede dejar DDL parcial.\n":$e->getMessage()."\n");exit(1);}

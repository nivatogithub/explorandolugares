<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if($argc!==2){fwrite(STDERR,"Uso: php backend/bin/importar-negocios.php ruta/paquete.json\n");exit(2);}
$appDirectory=dirname(__DIR__);if(!is_file($appDirectory.'/bootstrap.php'))$appDirectory.='/app';
require $appDirectory.'/bootstrap.php';
require $appDirectory.'/BusinessPublicImporter.php';
$path=realpath($argv[1]);if(!$path||!is_file($path)){fwrite(STDERR,"No se encontró el paquete JSON.\n");exit(2);}
try{$package=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$count=BusinessPublicImporter::import(db(),$package);fwrite(STDOUT,"Importados {$count} negocios como borradores privados; no se asignaron suscripciones ni se publicaron.\n");}
catch(Throwable $error){fwrite(STDERR,'Importación cancelada sin guardar el lote: '.$error->getMessage()."\n");exit(1);}

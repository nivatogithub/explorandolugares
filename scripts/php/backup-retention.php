<?php
declare(strict_types=1);
if((PHP_SAPI!=='cli'&&!(defined('EL_PRIVATE_SNAPSHOT')&&EL_PRIVATE_SNAPSHOT))||!isset($options))exit(1);
try{
 require_once $options['retention_class'];
 $directory=$options['directory']??(dirname($options['config']).'/backups');
 echo json_encode(BackupRetention::run($directory,$options['remote_directory']??$directory,($options['apply']??false)===true),JSON_THROW_ON_ERROR);
}catch(Throwable $e){if(PHP_SAPI!=='cli')http_response_code(503);echo json_encode(['error'=>'Conservación detenida para proteger las copias existentes.']);exit(1);}

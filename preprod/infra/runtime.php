<?php
// Local inspection only. This file is never included in the hosting release.
if (getenv('EL_PREPROD_LOCAL')!=='1'||!in_array(parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST),['127.0.0.1','localhost','[::1]'],true)){http_response_code(404);exit;}
header('Content-Type: application/json');header('Cache-Control: no-store');
$config=require dirname(__DIR__).'/private/config.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$ini=[];foreach(['memory_limit','upload_max_filesize','post_max_size','max_execution_time','max_input_vars','max_file_uploads','date.timezone','default_charset','display_errors','error_reporting','session.save_handler','session.gc_maxlifetime'] as $key)$ini[$key]=ini_get($key);
$extensions=get_loaded_extensions();sort($extensions);
$runtime=$pdo->query('SELECT VERSION() AS version,@@version_comment AS edition,@@sql_mode AS sql_mode,@@character_set_server AS server_charset,@@collation_server AS server_collation,@@character_set_connection AS connection_charset,@@collation_connection AS connection_collation,@@time_zone AS time_zone,@@system_time_zone AS system_time_zone,@@lower_case_table_names AS lower_case_table_names')->fetch(PDO::FETCH_ASSOC);
$schema=$pdo->query('SELECT DEFAULT_CHARACTER_SET_NAME AS charset,DEFAULT_COLLATION_NAME AS collation FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE()')->fetch(PDO::FETCH_ASSOC);
echo json_encode(['php'=>['version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'ini'=>$ini,'extensions'=>$extensions],'web_server'=>$_SERVER['SERVER_SOFTWARE']??'unknown','os_family'=>PHP_OS_FAMILY,'architecture'=>php_uname('m'),'database'=>['driver'=>'mysql','runtime'=>$runtime,'schema'=>$schema]],JSON_THROW_ON_ERROR);

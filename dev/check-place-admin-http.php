<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
require '/srv/portal/app/AccountSessionValidator.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$account=(int)$pdo->query("SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status='active' AND r.role='administrator' LIMIT 1")->fetchColumn();
$place=(int)$pdo->query("SELECT id FROM destinations WHERE slug='jerico'")->fetchColumn();
if(!$account||!$place)throw new RuntimeException('Falta cuenta o lugar sintético.');
$sessionId=bin2hex(random_bytes(24));session_name('el_session');session_id($sessionId);session_start();
$_SESSION=['admin'=>true,'admin_account_id'=>$account,'business_account_id'=>$account,'last_seen'=>time(),'business_last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
AccountSessionSecurity::bind($pdo,$_SESSION,$account);session_write_close();
$sessionDirectory=explode(';',session_save_path());$sessionFile=(end($sessionDirectory)?:sys_get_temp_dir()).'/sess_'.$sessionId;
if(is_file($sessionFile)){chown($sessionFile,'www-data');chmod($sessionFile,0600);}
$checks=[];
try{
    foreach(['list'=>['',200],'create'=>['?tab=crear',200],'edit'=>['?id='.$place,200],'filtered'=>['?q=Jer&estado=published',200],'saved'=>['?id='.$place.'&saved=1',200],'missing'=>['?id=999999',404]] as $name=>[$query,$expected]){
        $context=stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>'Cookie: el_session='.$sessionId]]);
        $html=file_get_contents('http://127.0.0.1/admin/places.php'.$query,false,$context);
        if($html===false)throw new RuntimeException('No responde el panel: '.$name);
        preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);
        if((int)($status[1]??0)!==$expected||!str_contains($html,'<html lang="es"')||!str_contains($html,'Administrar lugares'))throw new RuntimeException('Respuesta privada incorrecta: '.$name.' / HTTP '.($status[1]??'unknown').' / html='.(int)str_contains($html,'<html lang="es"').' / label='.(int)str_contains($html,'Administrar lugares'));
        if(in_array($name,['create','edit','saved'],true)&&(!str_contains($html,'name="csrf"')||!str_contains($html,'name="publication_status"')&&$name!=='create'||!str_contains($html,'name="translations[es][display_name]"')))throw new RuntimeException('Formulario incompleto: '.$name);
        if(!str_contains($html,'/assets/admin.css?v=')||!str_contains($html,'/assets/admin-navigation.css?v='))throw new RuntimeException('Faltan recursos del panel.');
        $checks[]=['case'=>$name,'http'=>$expected,'spanish_private_ui'=>true];
    }
}finally{
    if(session_status()===PHP_SESSION_ACTIVE)session_abort();
    if(is_file($sessionFile)&&!unlink($sessionFile))throw new RuntimeException('No se pudo retirar la sesión de prueba.');
}
file_put_contents('/srv/state/place-admin-http-report.json',json_encode(['driver'=>'MariaDB','real_http_authorized_session'=>true,'session_destroyed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo "OK HTTP/MariaDB: seis pantallas privadas con sesión autorizada; formularios y recursos completos; sesión de prueba eliminada.\n";

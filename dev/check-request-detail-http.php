<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
require '/srv/portal/app/AccountSessionValidator.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin=(int)$pdo->query("SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status='active' AND r.role='administrator' LIMIT 1")->fetchColumn();
if(!$admin)throw new RuntimeException('No hay cuenta administrativa sintética.');
$requestId=(int)($pdo->query('SELECT id FROM leads ORDER BY id LIMIT 1')->fetchColumn()?:0);
$results=[];
foreach(['admin','guest','expired'] as $mode){
    $id=bin2hex(random_bytes(24));session_name('el_session');session_id($id);session_start();
    $_SESSION=['csrf'=>bin2hex(random_bytes(32))];
    if($mode!=='guest'){
        $_SESSION+=['admin'=>true,'admin_account_id'=>$admin,'business_account_id'=>$admin,'last_seen'=>time(),'business_last_seen'=>time()];
        AccountSessionSecurity::bind($pdo,$_SESSION,$admin);
        if($mode==='expired')$_SESSION['last_seen']=$_SESSION['business_last_seen']=time()-3600;
    }
    session_write_close();$directory=explode(';',session_save_path());$file=(end($directory)?:sys_get_temp_dir()).'/sess_'.$id;
    if(!is_file($file))throw new RuntimeException('Falta sesión sintética.');chown($file,'www-data');chmod($file,0600);
    try{
        $ctx=stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>'Cookie: el_session='.$id]]);
        $body=file_get_contents('http://127.0.0.1/admin/request-details.php?type=lead&id='.($requestId?:999999),false,$ctx);
        if($body===false)throw new RuntimeException('No responde costos.');
        preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);
        $normalized=preg_replace('/(name="csrf" value=")[^"]*(")/','$1TOKEN$2',$body);
        $results[$mode]=['status'=>(int)($status[1]??0),'body_hash'=>hash('sha256',$normalized)];
    }finally{if(is_file($file)&&!unlink($file))throw new RuntimeException('No se pudo retirar sesión sintética.');}
}
if($results['admin']['status']!==($requestId?200:404)||$results['guest']['status']!==303||$results['expired']['status']!==303)throw new RuntimeException('Cambió el control de acceso.');
$results['fixture_lead_available']=$requestId>0;
echo json_encode($results,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";

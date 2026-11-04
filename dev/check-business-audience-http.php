<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Solo base sintética local.');
require '/srv/portal/app/AccountSessionValidator.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin=(int)$pdo->query("SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status='active' AND r.role='administrator' LIMIT 1")->fetchColumn();
$membership=$pdo->query("SELECT a.id AS account_id,b.public_id FROM accounts a JOIN business_account_memberships m ON m.account_id=a.id AND m.status='active' JOIN businesses b ON b.id=m.business_id WHERE a.status='active' ORDER BY a.id,b.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$any=(string)$pdo->query('SELECT public_id FROM businesses ORDER BY id LIMIT 1')->fetchColumn();
if(!$admin||$any==='')throw new RuntimeException('Falta fixture sintética de cuenta o negocio.');
$results=[];
function audienceSession(PDO $pdo,int $account,string $mode,callable $check):void{
    $id=bin2hex(random_bytes(24));session_name('el_session');session_id($id);session_start();
    $_SESSION=['csrf'=>bin2hex(random_bytes(32))];
    if($mode!=='guest'){
        $_SESSION+=['business_account_id'=>$account,'business_last_seen'=>time()];
        if($mode==='admin')$_SESSION+=['admin'=>true,'admin_account_id'=>$account,'last_seen'=>time()];
        AccountSessionSecurity::bind($pdo,$_SESSION,$account);
        if($mode==='expired')$_SESSION['business_last_seen']=time()-3600;
    }
    session_write_close();$directory=explode(';',session_save_path());$file=(end($directory)?:sys_get_temp_dir()).'/sess_'.$id;
    if(!is_file($file))throw new RuntimeException('Falta sesión sintética.');chown($file,'www-data');chmod($file,0600);
    try{$check($id);}finally{if(is_file($file)&&!unlink($file))throw new RuntimeException('No se pudo retirar sesión sintética.');}
}
function audienceRequest(string $session,string $query):array{
    $ctx=stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>30,'header'=>'Cookie: el_session='.$session]]);
    $body=file_get_contents('http://127.0.0.1/api/entity-audience.php?'.$query,false,$ctx);
    if($body===false)throw new RuntimeException('No responde la consulta de audiencia.');
    preg_match('/^HTTP\/\S+ (\d+)/',$http_response_header[0]??'',$status);
    $decoded=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    return ['status'=>(int)($status[1]??0),'body_hash'=>hash('sha256',json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))];
}
audienceSession($pdo,$admin,'guest',function(string $session)use(&$results,$any):void{
    $results['guest_business']=audienceRequest($session,'type=business&key='.rawurlencode($any));
    $results['guest_invalid']=audienceRequest($session,'type[]=business&key[]=test');
});
audienceSession($pdo,$admin,'admin',function(string $session)use(&$results,$any):void{
    $results['admin_business']=audienceRequest($session,'type=business&key='.rawurlencode($any).'&days=90');
    $results['admin_place']=audienceRequest($session,'type=destination&key=jerico');
    $results['admin_invalid']=audienceRequest($session,'type[]=business&key[]=test');
});
audienceSession($pdo,$admin,'expired',function(string $session)use(&$results,$any):void{
    $results['expired_business']=audienceRequest($session,'type=business&key='.rawurlencode($any));
});
if($membership){
    audienceSession($pdo,(int)$membership['account_id'],'owner',function(string $session)use(&$results,$membership):void{
        $results['owner_business']=audienceRequest($session,'type=business&key='.rawurlencode((string)$membership['public_id']));
        $results['owner_other']=audienceRequest($session,'type=business&key=missing-synthetic-business');
        $results['owner_place']=audienceRequest($session,'type=destination&key=jerico');
        $results['owner_invalid']=audienceRequest($session,'type[]=business&key[]=test');
    });
}
foreach(['guest_business'=>403,'guest_invalid'=>403,'expired_business'=>403,'admin_invalid'=>400,'owner_other'=>403,'owner_place'=>403,'owner_invalid'=>400] as $case=>$expected){
    if(isset($results[$case])&&$results[$case]['status']!==$expected)throw new RuntimeException('Control de acceso falló: '.$case);
}
echo json_encode(['membership_fixture_found'=>(bool)$membership,'cases'=>$results],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";

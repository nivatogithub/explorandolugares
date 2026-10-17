<?php
declare(strict_types=1);
require_once __DIR__.'/AnalyticsService.php';
function measurement_config():array {
    $path=dirname(__DIR__,2).'/private/config.php';
    if(PHP_SAPI==='cli-server'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))$path=getenv('EL_COVERAGE_TEST_CONFIG')?:$path;
    if(!is_file($path))throw new RuntimeException('Configuración pendiente');return require $path;
}
function measurement_local_http():bool {
    if(PHP_SAPI==='cli-server')return true;
    if(getenv('EL_PREPROD_LOCAL')!=='1')return false;
    $host=parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST);
    return in_array($host,['127.0.0.1','localhost','[::1]'],true)&&!empty(measurement_config()['local_test']);
}
function measurement_db(array $config):PDO{return new PDO($config['dsn'],$config['db_user']??null,$config['db_password']??null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);}
function measurement_cookie_id(string $name,int $lifetime,string $key,bool $forceNew=false):string {
    $raw=(string)($_COOKIE[$name]??'');$parts=explode('.',$raw);
    if(!$forceNew&&count($parts)===3&&preg_match('/^[a-f0-9]{32}$/D',$parts[0])&&ctype_digit($parts[1])&&(int)$parts[1]>=time()&&hash_equals(hash_hmac('sha256',$name.'|'.$parts[0].'|'.$parts[1],$key),$parts[2]))$id=$parts[0];else $id=bin2hex(random_bytes(16));
    $expires=time()+$lifetime;$value=$id.'.'.$expires.'.'.hash_hmac('sha256',$name.'|'.$id.'|'.$expires,$key);setcookie($name,$value,['expires'=>$expires,'path'=>'/','secure'=>($_SERVER['HTTPS']??'')==='on','httponly'=>true,'samesite'=>'Lax']);return $id;
}
function measurement_account(PDO $pdo,string $key):array {
    // Reuse only an existing authenticated application session; never create an application login.
    $context=['excluded'=>false,'account_kind'=>'anonymous','account_hash'=>null];
    if(!isset($_COOKIE['el_session']))return $context;
    session_name('el_session');ini_set('session.use_strict_mode','1');session_start(['read_and_close'=>true]);
    if(!empty($_SESSION['admin']))return ['excluded'=>true,'account_kind'=>'internal','account_hash'=>null];
    if(!empty($_SESSION['business_account_id']))return ['excluded'=>true,'account_kind'=>'business','account_hash'=>null];
    $accountId=(int)($_SESSION['traveler_account_id']??$_SESSION['account_id']??0);
    if($accountId){try{$q=$pdo->prepare("SELECT id FROM accounts WHERE id=? AND account_type='traveler' AND status='active' AND email_verified_at IS NOT NULL");$q->execute([$accountId]);if($q->fetchColumn())$context=['excluded'=>false,'account_kind'=>'traveler','account_hash'=>hash_hmac('sha256','account|'.$accountId,$key)];}catch(Throwable){}}
    return $context;
}

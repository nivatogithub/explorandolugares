<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/AccountSessionValidator.php';

function session_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo=new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY,status TEXT,password_hash TEXT);
    CREATE TABLE account_roles(account_id INTEGER,role TEXT);
    CREATE TABLE account_access_events(id INTEGER PRIMARY KEY,account_id INTEGER,event_type TEXT,reason TEXT,created_at TEXT,actor_account_id INTEGER);
    INSERT INTO accounts VALUES(1,"active","synthetic-proof"),(2,"active","another-proof");
    INSERT INTO account_roles VALUES(1,"administrator")');
$session=['admin'=>true,'admin_account_id'=>1,'business_account_id'=>1,'last_seen'=>time(),'business_last_seen'=>time()];
AccountSessionSecurity::bind($pdo,$session,1);
$valid=$session;
$connection=static fn(): PDO => $pdo;
AccountSessionValidator::synchronize($connection,$session,true);
session_check(AccountSessionValidator::isActiveAdmin($session),'Valid administrator rejected.');

$unused=static function(): PDO { throw new LogicException('An anonymous/legacy session opened a connection.'); };
$guest=[];AccountSessionValidator::synchronize($unused,$guest,true);
session_check(!AccountSessionValidator::isActiveAdmin($guest),'Guest authorized.');
$legacy=['admin'=>true,'last_seen'=>time()];$old=$legacy;
AccountSessionValidator::synchronize($unused,$legacy,true);
session_check(!AccountSessionValidator::isActiveAdmin($legacy),'Legacy flag bypassed unified accounts.');
AccountSessionValidator::synchronize($unused,$old,false);
session_check(AccountSessionValidator::isActiveAdmin($old),'Explicit legacy compatibility changed.');

$expired=$valid;$expired['last_seen']=$expired['business_last_seen']=time()-3600;
AccountSessionValidator::synchronize($connection,$expired,true);
session_check(!AccountSessionValidator::isActiveAdmin($expired),'Expired session authorized.');
$mismatch=$valid;$mismatch['admin_account_id']=2;
AccountSessionValidator::synchronize($connection,$mismatch,true);
session_check(!AccountSessionValidator::isActiveAdmin($mismatch),'Mismatched administrator identity authorized.');

foreach (['disabled','role_revoked','password_reset','missing_proof'] as $case) {
    $pdo->beginTransaction();$session=$valid;
    try {
        if($case==='disabled')$pdo->exec('UPDATE accounts SET status="disabled" WHERE id=1');
        if($case==='role_revoked')$pdo->exec('DELETE FROM account_roles WHERE account_id=1');
        if($case==='password_reset')$pdo->exec('UPDATE accounts SET password_hash="new-proof" WHERE id=1');
        if($case==='missing_proof')unset($session['account_credential_proof']);
        AccountSessionValidator::synchronize($connection,$session,true);
        session_check(!AccountSessionValidator::isActiveAdmin($session),'Stale authorization accepted: '.$case);
    } finally { $pdo->rollBack(); }
}

$failed=$valid;
try {
    AccountSessionValidator::synchronize(static function(): PDO { throw new RuntimeException('Synthetic connection failure.'); },$failed,true);
    throw new LogicException('Validation failure was swallowed.');
} catch (RuntimeException $error) {
    session_check(!AccountSessionValidator::isActiveAdmin($failed)&&!isset($failed['business_account_id']),'Validation error retained credentials.');
}
echo "OK: shared session validation, expiry, role/account/password revocation, identity, legacy configuration and fail-closed errors.\n";

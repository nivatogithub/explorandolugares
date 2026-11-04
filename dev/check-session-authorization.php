<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo pruebas locales en el dev reservado.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Usar únicamente la base sintética del dev1.');
require '/srv/portal/app/AccountSessionValidator.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$account=(int)$pdo->query('SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status="active" AND r.role="administrator" LIMIT 1')->fetchColumn();
if(!$account)throw new RuntimeException('Falta la cuenta administrativa sintética.');
$snapshot=static fn():string=>hash('sha256',json_encode([
    $pdo->query('SELECT * FROM accounts ORDER BY id')->fetchAll(),
    $pdo->query('SELECT * FROM account_roles ORDER BY account_id,role')->fetchAll(),
    $pdo->query('SELECT * FROM account_access_events ORDER BY id')->fetchAll(),
],JSON_THROW_ON_ERROR));
$before=$snapshot();$checks=[];
foreach(['valid','expired','disabled','role_revoked','password_reset','identity_mismatch','flag_only'] as $case){
    $pdo->beginTransaction();
    try{
        $session=['admin'=>true,'admin_account_id'=>$account,'business_account_id'=>$account,'last_seen'=>time(),'business_last_seen'=>time()];
        AccountSessionSecurity::bind($pdo,$session,$account);
        if($case==='expired')$session['last_seen']=$session['business_last_seen']=time()-3600;
        if($case==='disabled')$pdo->prepare('UPDATE accounts SET status="disabled" WHERE id=?')->execute([$account]);
        if($case==='role_revoked')$pdo->prepare('DELETE FROM account_roles WHERE account_id=?')->execute([$account]);
        if($case==='password_reset')$pdo->prepare('UPDATE accounts SET password_hash="synthetic-changed-proof" WHERE id=?')->execute([$account]);
        if($case==='identity_mismatch')$session['admin_account_id']=$account+999999;
        if($case==='flag_only')$session=['admin'=>true,'last_seen'=>time()];
        AccountSessionValidator::synchronize(static fn():PDO=>$pdo,$session,true);
        if(AccountSessionValidator::isActiveAdmin($session)!==($case==='valid'))throw new RuntimeException('Autorización incorrecta: '.$case);
        $checks[]=['case'=>$case,'authorized'=>$case==='valid'];
    }finally{if($pdo->inTransaction())$pdo->rollBack();}
}
if($snapshot()!==$before)throw new RuntimeException('La prueba modificó datos fuera de su transacción.');
file_put_contents('/srv/state/session-authorization-report.json',json_encode(['driver'=>'MariaDB','production_accessed'=>false,'all_changes_rolled_back'=>true,'account_data_unchanged'=>true,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
echo "OK MariaDB: siete escenarios de autorización; cuentas, roles e historial intactos después de rollback.\n";

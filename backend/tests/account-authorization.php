<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/AccountAuthorization.php';
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$p->exec('CREATE TABLE accounts(id INTEGER,status TEXT);CREATE TABLE account_roles(account_id INTEGER,role TEXT);INSERT INTO accounts VALUES(1,"active"),(2,"disabled"),(3,"active");INSERT INTO account_roles VALUES(1,"administrator"),(2,"administrator")');
function check_auth(bool $value):void{if(!$value)throw new RuntimeException('Falló la validación de autorización.');}
check_auth(AccountAuthorization::isAdmin($p,1));check_auth(!AccountAuthorization::isAdmin($p,2));check_auth(!AccountAuthorization::isAdmin($p,3));
foreach([[1,1,time(),true],[1,1,time()-1801,false],[1,3,time(),false],[2,2,time(),false],[3,3,time(),false]] as [$admin,$account,$last,$expected]){
 $s=['admin'=>true,'admin_account_id'=>$admin,'business_account_id'=>$account,'business_last_seen'=>$last];AccountAuthorization::synchronize($p,$s);check_auth(!empty($s['admin'])===$expected);
}
$p->exec('DELETE FROM account_roles WHERE account_id=1');$s=['admin'=>true,'admin_account_id'=>1,'business_account_id'=>1,'business_last_seen'=>time()];AccountAuthorization::synchronize($p,$s);check_auth(empty($s['admin']));
echo "OK: permiso explícito, cuenta activa, caducidad, vínculo de sesión y revocación.\n";

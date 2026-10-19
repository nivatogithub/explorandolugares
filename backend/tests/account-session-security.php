<?php
declare(strict_types=1);
require __DIR__.'/business-account-portal.php';
require dirname(__DIR__).'/app/AccountSessionSecurity.php';
require dirname(__DIR__).'/app/AccountClosureService.php';
$pdo->exec('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status,email_verified_at) VALUES("security-fixture","traveler","Prueba privada","session@example.invalid",NULL,"active",CURRENT_TIMESTAMP)');
$id=(int)$pdo->lastInsertId();$password='PruebaSegura123';
$pdo->prepare('UPDATE accounts SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
$a=['business_account_id'=>$id,'admin'=>true,'admin_account_id'=>$id];AccountSessionSecurity::bind($pdo,$a,$id);$b=$a;
AccountSessionSecurity::synchronize($pdo,$a);portal_check(isset($a['business_account_id']),'Sesión vigente rechazada');
$reset=new AccountPasswordResetService($pdo);$token=$reset->issue($id);$reset->reset($token,'OtraClaveSegura456','OtraClaveSegura456');
foreach([&$a,&$b] as &$session){AccountSessionSecurity::synchronize($pdo,$session);portal_check(!isset($session['business_account_id'])&&!isset($session['admin']),'La recuperación no revocó todas las sesiones');}unset($session);
$session=['business_account_id'=>$id];AccountSessionSecurity::bind($pdo,$session,$id);
$closure=new AccountClosureService($pdo);
foreach([['incorrecta',true],['OtraClaveSegura456',false]] as [$pw,$confirm]){try{$closure->close($id,$pw,$confirm);throw new LogicException('Cierre sin autorización');}catch(RuntimeException $e){}}
$pdo->exec('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES('.$id.',1,"owner","active")');
$members=(int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships')->fetchColumn();
$token=$reset->issue($id);$verify=(new AccountEmailVerificationService($pdo));
$closure->close($id,'OtraClaveSegura456',true);
AccountSessionSecurity::synchronize($pdo,$session);
portal_check(!isset($session['business_account_id']),'Cuenta cerrada conserva sesión');
portal_check(!$service->current($id),'Cuenta cerrada visible como activa');
portal_check(!$reset->isValid($token),'Enlace de recuperación revive cuenta cerrada');
portal_check((int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships')->fetchColumn()===$members,'Se borraron asociaciones');
portal_check((int)$pdo->query('SELECT COUNT(*) FROM accounts WHERE id='.$id)->fetchColumn()===1,'Se borró físicamente la cuenta');
$pdo->exec('DELETE FROM account_roles');
$pdo->prepare('UPDATE accounts SET status="active" WHERE id=?')->execute([$id]);
$pdo->prepare('INSERT INTO account_roles VALUES(?,"administrator")')->execute([$id]);
try{$closure->close($id,'OtraClaveSegura456',true);throw new LogicException('Se cerró el último administrador');}catch(RuntimeException $e){}
portal_check($service->current($id)!==null,'Último administrador desactivado');
echo "OK: dos sesiones revocadas por recuperación, cierre con contraseña y confirmación, enlaces invalidados, datos preservados y último administrador protegido.\n";

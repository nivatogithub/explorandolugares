<?php
declare(strict_types=1);
require __DIR__.'/account-session-security.php';
require_once dirname(__DIR__).'/app/AccountReactivationService.php';
require_once dirname(__DIR__).'/app/AccountRecoveryDelivery.php';
$react=new AccountReactivationService($pdo);
$pdo->exec('DELETE FROM account_roles');
$closure->close($id,'OtraClaveSegura456',true);
$oldSession=['business_account_id'=>$id];AccountSessionSecurity::bind($pdo,$oldSession,$id);
$first=$react->issue($id);$token=$react->issue($id);
portal_check(!$react->isValid($first)&&$react->isValid($token),'Reenvío no invalida token anterior');
portal_check(!$reset->isValid($token),'Token de reactivación aceptado como recuperación');
$react->reset($token,'VuelvoConClave789','VuelvoConClave789');
portal_check(!$react->isValid($token)&&$service->current($id)!==null,'Reactivación sin uso único o sin acceso');
AccountSessionSecurity::synchronize($pdo,$oldSession);portal_check(!isset($oldSession['business_account_id']),'Se restauró sesión vieja');
portal_check((int)$pdo->query('SELECT COUNT(*) FROM accounts WHERE id='.$id)->fetchColumn()===1,'Se duplicó la cuenta');
portal_check((int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships')->fetchColumn()===$members,'Se alteraron asociaciones');
$closure->close($id,'VuelvoConClave789',true);$token=$react->issue($id);
$pdo->prepare('UPDATE account_reactivations SET expires_at="2000-01-01 00:00:00" WHERE token_hash=?')->execute([hash('sha256',$token)]);
portal_check(!$react->isValid($token),'Token vencido aceptado');
$pdo->exec('UPDATE account_reactivations SET created_at="2000-01-01 00:00:00"');
$token=$react->issue($id);
(new AccountAdminService($pdo))->setAccountStatus($id,'disabled',null,'Revisión de seguridad sintética');
portal_check(!$react->isValid($token),'Suspensión permite reactivación por enlace previo');
try{$react->issue($id);throw new LogicException('Suspensión admite enlace nuevo');}catch(RuntimeException $e){}
try{$react->reset($token,'ClaveDistinta123','ClaveDistinta123');throw new LogicException('Suspensión permite reactivación');}catch(RuntimeException $e){}
$admin=new AccountAdminService($pdo);$admin->setAccountStatus($id,'active',null,'Revisión terminada');
portal_check($service->current($id)!==null,'Administrador no puede restablecer suspensión');
$closure->close($id,'VuelvoConClave789',true);
try{$admin->setAccountStatus($id,'active',null,'Intento de reapertura');throw new LogicException('Administrador reabre cierre sin confirmación del titular');}catch(RuntimeException $e){}
$payload=null;$delivery=new AccountRecoveryDelivery(['enabled'=>true,'api_key'=>'synthetic','sender_email'=>'cuentas@example.invalid','sender_name'=>'Explorando Lugares','public_origin'=>'https://example.invalid'],function($p)use(&$payload){$payload=$p;return ['status'=>201,'body'=>['messageId'=>'synthetic']];});
$delivery->send('persona@example.invalid',str_repeat('a',64),false,false,true);
portal_check(str_contains($payload['htmlContent'],'Reactivar mi cuenta')&&str_contains($payload['textContent'],'reactivacion=1'),'Correo de reactivación incorrecto');
echo "OK: cierre y suspensión diferenciados, reactivación de un uso por correo, caducidad, no duplicados, datos preservados y transporte simulado.\n";

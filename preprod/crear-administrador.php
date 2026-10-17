<?php
// Explicit local provisioning, never part of a hosting release. No credentials in arguments/output.
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')exit(1);
$c=require '/srv/state/private/config.php';
if(empty($c['local_test']))exit(1);
$path='/srv/state/private/administrator-password.hash';
$hash=is_file($path)?trim(file_get_contents($path)):'';
if(!(password_get_info($hash)['algo']??null))throw new RuntimeException('Falta el hash autorizado en el área privada local.');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->beginTransaction();
try{
 $q=$p->prepare('SELECT id FROM accounts WHERE email=? FOR UPDATE');$q->execute(['admin@explorandolugares.com']);$id=$q->fetchColumn();
 if($id)throw new RuntimeException('La cuenta ya existe: revisar antes de modificar sus credenciales o permisos.');
 $q=$p->prepare('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,"traveler",?,?,?,"active")');
 $uuid=bin2hex(random_bytes(16));$uuid=substr($uuid,0,8).'-'.substr($uuid,8,4).'-4'.substr($uuid,13,3).'-8'.substr($uuid,17,3).'-'.substr($uuid,20);
 $q->execute([$uuid,'Administrador','admin@explorandolugares.com',$hash]);$id=(int)$p->lastInsertId();
 $p->prepare('INSERT INTO account_roles(account_id,role) VALUES(?,"administrator")')->execute([$id]);
 $p->commit();
}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
$c['unified_admin_only']=true;
file_put_contents('/srv/state/private/config.php',"<?php\nreturn ".var_export($c,true).";\n",LOCK_EX);chmod('/srv/state/private/config.php',0600);
unlink($path);
echo "Cuenta administradora creada con el hash autorizado; acceso anterior desactivado solo en preproducción.\n";

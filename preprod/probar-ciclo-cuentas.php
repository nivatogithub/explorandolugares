<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')exit(1);
$pdo=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_accounts_'.bin2hex(random_bytes(5));
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
try{
 $pdo->exec('USE `'.$name.'`');
 $pdo->exec('CREATE TABLE accounts(id BIGINT UNSIGNED PRIMARY KEY, password_hash VARCHAR(255),status VARCHAR(24),updated_at DATETIME, email_verified_at DATETIME) ENGINE=InnoDB');
 $pdo->exec('CREATE TABLE account_roles(account_id BIGINT UNSIGNED,role VARCHAR(32)) ENGINE=InnoDB');
 foreach(['account_password_resets','account_email_verifications'] as $table)$pdo->exec('CREATE TABLE '.$table.'(account_id BIGINT UNSIGNED,used_at DATETIME NULL) ENGINE=InnoDB');
 $pdo->exec(file_get_contents('/srv/portal/app/database/migrations/043-account-lifecycle.mysql.sql'));
 require '/srv/portal/app/AccountClosureService.php';require '/srv/portal/app/AccountReactivationService.php';require '/srv/portal/app/AccountAdminService.php';require '/srv/portal/app/AccountSessionSecurity.php';
 $password='SoloPruebaLocal123';$pdo->prepare('INSERT INTO accounts(id,password_hash,status) VALUES(1,?,"active")')->execute([password_hash($password,PASSWORD_DEFAULT)]);
 $old=['business_account_id'=>1];AccountSessionSecurity::bind($pdo,$old,1);
 (new AccountClosureService($pdo))->close(1,$password,true);
 $react=new AccountReactivationService($pdo);$token=$react->issue(1);$react->reset($token,'VueltaDePrueba456','VueltaDePrueba456');
 if($react->isValid($token))throw new RuntimeException('Token reutilizable');
 AccountSessionSecurity::synchronize($pdo,$old);if(isset($old['business_account_id']))throw new RuntimeException('Sesión vieja reactivada');
 (new AccountClosureService($pdo))->close(1,'VueltaDePrueba456',true);$token=$react->issue(1);
 $admin=new AccountAdminService($pdo);$admin->setAccountStatus(1,'disabled',null,'Prueba aislada de suspensión');
 if($react->isValid($token))throw new RuntimeException('Suspensión admite reactivación');
 $admin->setAccountStatus(1,'active',null,'Fin de prueba aislada');
 if((int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()!==1)throw new RuntimeException('Se duplicó cuenta');
 echo "OK MariaDB: migración, cierre, reactivación única, suspensión y revocación de sesiones; base de prueba aislada.\n";
}finally{$pdo->exec('DROP DATABASE `'.$name.'`');}

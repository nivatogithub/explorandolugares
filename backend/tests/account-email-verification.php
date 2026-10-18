<?php
declare(strict_types=1);
require __DIR__.'/../app/AccountEmailVerificationService.php';
require __DIR__.'/../app/AccountRecoveryDelivery.php';
require __DIR__.'/../app/BusinessAccountPortalService.php';
function throttle(...$args):bool{return true;}
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$p->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY,status TEXT,email_verified_at TEXT,updated_at TEXT,public_id TEXT,account_type TEXT,display_name TEXT,email TEXT,password_hash TEXT)');$p->exec(file_get_contents(__DIR__.'/../database/migrations/035-account-email-verifications.sqlite.sql'));
$p->exec('INSERT INTO accounts(id,status) VALUES(1,"pending_activation"),(2,"pending_activation"),(3,"disabled"),(4,"active")');
$s=new AccountEmailVerificationService($p);$checks=0;
function check(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function rejects(callable $f):bool{try{$f();return false;}catch(RuntimeException){return true;}}
$p->prepare('UPDATE accounts SET public_id=?,account_type="traveler",display_name="Persona",email=?,password_hash=? WHERE id=?')->execute(['person-2','pending@example.test',password_hash('Password-12345',PASSWORD_DEFAULT),2]);
$portal=new BusinessAccountPortalService($p);check(rejects(fn()=>$portal->authenticate('pending@example.test','Password-12345')),'pending cannot sign in');
$t=$s->issue(1);check($p->query('SELECT token_hash FROM account_email_verifications')->fetchColumn()!==$t,'only digest stored');check(rejects(fn()=>$s->verify(str_repeat('a',64))),'altered rejected');
$t2=$s->issue(1);check(rejects(fn()=>$s->verify($t)),'prior token revoked');$s->verify($t2);check($p->query('SELECT status FROM accounts WHERE id=1')->fetchColumn()==='active','activates');check((bool)$p->query('SELECT email_verified_at FROM accounts WHERE id=1')->fetchColumn(),'verified timestamp');check(rejects(fn()=>$s->verify($t2)),'replay rejected');
$t=$s->issue(2);$p->exec('UPDATE account_email_verifications SET expires_at="2000-01-01 00:00:00" WHERE account_id=2');check(rejects(fn()=>$s->verify($t)),'expired rejected');$s->issue(2);$s->issue(2);check(rejects(fn()=>$s->issue(2)),'resend limit');check(rejects(fn()=>$s->issue(3)),'disabled rejected');check(rejects(fn()=>$s->issue(4)),'legacy active not automatically marked verified');
$captured=[];$d=new AccountRecoveryDelivery(['enabled'=>true,'api_key'=>'fake','sender_email'=>'sender@example.test','sender_name'=>'Test','public_origin'=>'https://example.test'],function($payload)use(&$captured){$captured=$payload;return ['status'=>201,'body'=>['messageId'=>'fake']];});$d->send('person@example.test',str_repeat('b',64),true);check(str_contains($captured['textContent'],'https://example.test/cuenta/verificar-correo/?token='),'canonical verify URL');check(!isset($captured['listIds']),'no campaigns');echo "OK $checks activation checks\n";

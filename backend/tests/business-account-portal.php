<?php
declare(strict_types=1);
function portal_check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function throttle(string $kind,int $limit,int $seconds,string $extra=''):bool{return true;}
require dirname(__DIR__).'/app/BusinessAccountPortalService.php';
require dirname(__DIR__).'/app/AccountAdminService.php';
require dirname(__DIR__).'/app/AccountPasswordResetService.php';
require dirname(__DIR__).'/app/AccountEmailVerificationService.php';
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE account_roles(account_id INTEGER,role TEXT);CREATE TABLE business_categories(business_id INTEGER,category_code TEXT)');
$pdo->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT,account_type TEXT,display_name TEXT,email TEXT UNIQUE,password_hash TEXT,status TEXT,email_verified_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE business_translations(business_id INTEGER,locale TEXT,display_name TEXT,summary TEXT DEFAULT "",description TEXT DEFAULT "");
CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT,publication_status TEXT,display_order INTEGER,latitude REAL,longitude REAL,department TEXT,country_code TEXT,destination_type TEXT);
CREATE TABLE destination_translations(destination_id INTEGER,locale TEXT,display_name TEXT);
CREATE TABLE service_categories(code TEXT,display_order INTEGER,is_active INTEGER);
CREATE TABLE service_category_translations(category_code TEXT,locale TEXT,label TEXT);
CREATE TABLE businesses(id INTEGER PRIMARY KEY,public_id TEXT,destination_id INTEGER,category_code TEXT,information_status TEXT,claim_status TEXT,operation_status TEXT,publication_status TEXT,slug TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE business_account_memberships(account_id INTEGER,business_id INTEGER,role TEXT,status TEXT);
CREATE TABLE subscription_plans(code TEXT PRIMARY KEY);
CREATE TABLE business_subscriptions(id INTEGER PRIMARY KEY,business_id INTEGER,plan_code TEXT,status TEXT,starts_at TEXT,ends_at TEXT);
CREATE TABLE business_claim_requests(id INTEGER PRIMARY KEY,account_id INTEGER,business_id INTEGER,status TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);');
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/019-business-account-applications.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/020-account-password-resets.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/023-business-representation-evidence.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/035-account-email-verifications.sqlite.sql'));
$pdo->exec("ALTER TABLE business_account_applications ADD COLUMN business_details_json TEXT NOT NULL DEFAULT '{}'");
$pdo->exec("INSERT INTO destinations(id,slug,publication_status,display_order) VALUES(1,'jerico','published',1);INSERT INTO destination_translations VALUES(1,'es','Jericó');INSERT INTO service_categories VALUES('food',1,1);INSERT INTO service_category_translations VALUES('food','es','Alimentación');INSERT INTO businesses(id,public_id,destination_id,category_code,information_status,claim_status,operation_status,publication_status,slug,updated_at) VALUES(1,'public-id',1,'food','PUBLICO','NO_RECLAMADO','DESCONOCIDO','published','cafe-prueba',CURRENT_TIMESTAMP);INSERT INTO business_translations VALUES(1,'es','Café de prueba','','');INSERT INTO businesses(id,public_id,destination_id,category_code,information_status,claim_status,operation_status,publication_status,slug,updated_at) VALUES(2,'hidden-id',1,'food','PUBLICO','NO_RECLAMADO','DESCONOCIDO','draft','hotel-oculto',CURRENT_TIMESTAMP);INSERT INTO business_translations VALUES(2,'es','Hotel que aún no aparece','','')");
$pdo->exec('ALTER TABLE businesses ADD COLUMN record_origin TEXT DEFAULT "public_import";CREATE TABLE business_sources(business_id INTEGER,source_url TEXT,checked_at TEXT)');
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/043-account-lifecycle.sqlite.sql'));
$service=new BusinessAccountPortalService($pdo);
$matches=$service->searchRegistrationBusinesses(1,'food','Hotel que');
portal_check(count($matches)===1&&$matches[0]['display_name']==='Hotel que aún no aparece'&&$matches[0]['can_request'],'Registration search can find an unpublished business without exposing its private fields.');
$accountId=$service->register('Responsable local','responsable@example.test','ClaveDePrueba123',['application_type'=>'existing_business','business_id'=>'1','destination_id'=>'1','category_code'=>'food','relationship_code'=>'owner','phone'=>'3230000000','message'=>'Soy su propietario','consent'=>'1']);
$pendingRejected=false;try{$service->authenticate('responsable@example.test','ClaveDePrueba123');}catch(RuntimeException){$pendingRejected=true;}portal_check($pendingRejected,'Pending account must not authenticate before mailbox verification.');
$verification=new AccountEmailVerificationService($pdo);$activation=$verification->issue($accountId);$verification->verify($activation);
$account=$service->authenticate('RESPONSABLE@example.test','ClaveDePrueba123');
portal_check((int)$account['id']===$accountId&&$account['account_type']==='traveler'&&$account['status']==='active','Registration creates a personal account, activated by email, with a separate business request.');
portal_check($service->current($accountId)!==null,'An authenticated business account can load its profile.');
portal_check(count($service->businesses($accountId))===1,'An unowned existing business is assigned automatically after account verification.');
$rejected=false;try{$service->authenticate('responsable@example.test','incorrecta');}catch(RuntimeException){$rejected=true;}
portal_check($rejected,'Invalid credentials never authenticate.');
$apps=$service->applications($accountId);
portal_check(count($apps)===1&&$apps[0]['business_name']==='Café de prueba'&&$apps[0]['application_type']==='claim_existing','The account portal can follow its business association request.');
portal_check((string)$pdo->query('SELECT claim_status FROM businesses WHERE id=1')->fetchColumn()==='RECLAMADO','An unowned business becomes reclaimed without changing publication or activating a paid subscription.');
$accountsBefore=(int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();$failed=false;
try{$service->register('Persona sin negocio','sin-negocio@example.test','ClaveDePrueba123',[]);}catch(RuntimeException){$failed=true;}
portal_check($failed&&(int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()===$accountsBefore,'An account cannot be created without a matched business or a new-business request.');
$newId=$service->register('Nueva responsable','nuevo@example.test','ClaveDePrueba456',['application_type'=>'new_business','destination_id'=>'1','category_code'=>'food','business_name'=>'Restaurante pendiente','relationship_code'=>'authorized_representative','phone'=>'6040000000','message'=>'Puedo aportar el registro mercantil','consent'=>'1']);
portal_check(count($service->applications($newId))===1&&(int)$pdo->query('SELECT COUNT(*) FROM businesses WHERE slug="restaurante-pendiente"')->fetchColumn()===0,'A missing business creates a private validation request, not a public business record.');
$service->reviewApplication((int)$pdo->query('SELECT id FROM business_account_applications WHERE account_id='.$newId)->fetchColumn(),'in_review','Solicitar soporte mercantil');
portal_check((string)$pdo->query('SELECT status FROM business_account_applications WHERE account_id='.$newId)->fetchColumn()==='in_review','The administration queue can track review without creating a business or granting access.');
$newApplicationId=(int)$pdo->query('SELECT id FROM business_account_applications WHERE account_id='.$newId)->fetchColumn();
$pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$newApplicationId,'identity_document',str_repeat('c',48).'.png','identidad.png','image/png',128]);$newIdentityId=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$newApplicationId,'business_registration',str_repeat('d',48).'.pdf','registro.pdf','application/pdf',256]);$newBusinessDocId=(int)$pdo->lastInsertId();
$authorityRequired=false;try{$service->reviewApplication($newApplicationId,'approved','Identidad y registro oficial revisados',[$newIdentityId,$newBusinessDocId]);}catch(RuntimeException $error){$authorityRequired=str_contains($error->getMessage(),'autorización');}
portal_check($authorityRequired,'An administrator acting on behalf of a legal representative must provide an authorization letter before account approval.');
$existingApplicationId=(int)$pdo->query('SELECT id FROM business_account_applications WHERE account_id='.$accountId)->fetchColumn();
// Keep exercising review of legacy pending applications separately from the new direct claim path.
$pdo->exec('DELETE FROM business_account_memberships WHERE account_id='.(int)$accountId.' AND business_id=1');$pdo->exec("UPDATE business_account_applications SET status='pending' WHERE id=".$existingApplicationId);$pdo->exec("UPDATE businesses SET claim_status='EN_TRAMITE',information_status='PUBLICO' WHERE id=1");
$pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$existingApplicationId,'identity_document',str_repeat('a',48).'.png','identidad.png','image/png',128]);$identityEvidenceId=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$existingApplicationId,'business_registration',str_repeat('b',48).'.pdf','matricula.pdf','application/pdf',256]);$businessEvidenceId=(int)$pdo->lastInsertId();
$evidenceRequired=false;try{$service->reviewApplication($existingApplicationId,'approved','Identidad y matrícula mercantil revisadas');}catch(RuntimeException $error){$evidenceRequired=str_contains($error->getMessage(),'Marca como revisados');}
portal_check($evidenceRequired,'Approval is blocked until the administrator explicitly marks identity and business documents as reviewed.');
$service->reviewApplication($existingApplicationId,'approved','Identidad y matrícula mercantil revisadas',[$identityEvidenceId,$businessEvidenceId],'admin-test');
$verifiedCount=(int)$pdo->query('SELECT COUNT(*) FROM business_account_application_evidence WHERE application_id='.$existingApplicationId.' AND verified_at IS NOT NULL AND verified_by="admin-test"')->fetchColumn();
portal_check($verifiedCount===2,'A successful approval records which private identity/business documents were reviewed.');
$approvedExisting=$pdo->query('SELECT r.status,b.information_status,b.claim_status,m.role FROM business_account_applications r JOIN businesses b ON b.id=r.business_id JOIN business_account_memberships m ON m.business_id=b.id AND m.account_id=r.account_id WHERE r.id='.$existingApplicationId)->fetch(PDO::FETCH_ASSOC);
portal_check($approvedExisting['status']==='approved'&&$approvedExisting['information_status']==='ACTIVO'&&$approvedExisting['claim_status']==='RECLAMADO'&&$approvedExisting['role']==='owner','Approving an existing-business request authorizes and links the account without publishing.');
$pdo->exec("INSERT INTO businesses(id,public_id,destination_id,category_code,information_status,claim_status,operation_status,publication_status,slug,updated_at) VALUES(3,'another-id',1,'food','ACTIVO','RECLAMADO','ABIERTO','draft','otro-negocio',CURRENT_TIMESTAMP);INSERT INTO business_translations VALUES(3,'es','Otro negocio verificado','','')");
$linkRejected=false;try{(new AccountAdminService($pdo))->linkBusiness(['account_id'=>$accountId,'business_id'=>3,'membership_role'=>'manager']);}catch(RuntimeException $error){$linkRejected=str_contains($error->getMessage(),'solicitud aprobada');}
portal_check($linkRejected,'A business marked reclaimed cannot be linked without its own approved, evidence-reviewed account application.');
$pdo->prepare('INSERT INTO business_account_applications(account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,consent_version,status,reviewed_at) VALUES(?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')->execute([$accountId,'claim_existing',3,1,'food','Otro negocio verificado','owner','test-v1','approved']);$linkApplicationId=(int)$pdo->lastInsertId();
foreach([['identity_document','c'],['business_registration','d']] as [$type,$prefix]){$pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size,verified_by,verified_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')->execute([$linkApplicationId,$type,str_repeat($prefix,48).'.pdf',$type.'.pdf','application/pdf',128,'admin-test']);}
(new AccountAdminService($pdo))->linkBusiness(['account_id'=>$accountId,'business_id'=>3,'membership_role'=>'manager']);
portal_check((int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships WHERE account_id='.$accountId.' AND business_id=3 AND role="manager"')->fetchColumn()===1,'An administrator can assign a verified business to an authorized account.');
$businessAccountRejected=false;try{(new AccountAdminService($pdo))->save(['account_type'=>'business','display_name'=>'Atajo administrativo','email'=>'atajo@example.test','business_ids'=>[3]]);}catch(RuntimeException $error){$businessAccountRejected=str_contains($error->getMessage(),'únicamente desde el registro empresarial');}
portal_check($businessAccountRejected&&(int)$pdo->query("SELECT COUNT(*) FROM accounts WHERE email='atajo@example.test'")->fetchColumn()===0,'The admin cannot create a business account or grant membership outside the documentary registration flow.');
$weak=false;try{$service->register('Otra persona','otra@example.test','123',[]);}catch(RuntimeException){$weak=true;}
portal_check($weak,'Short passwords are rejected.');
$resetService=new AccountPasswordResetService($pdo);$resetToken=$resetService->issue($accountId,'admin-local');
portal_check(strlen($resetToken)===64&&$resetService->isValid($resetToken),'An administrator can issue a valid temporary password link.');
$storedHash=$pdo->query('SELECT token_hash FROM account_password_resets ORDER BY id DESC LIMIT 1')->fetchColumn();
portal_check($storedHash===hash('sha256',$resetToken)&&$storedHash!==$resetToken,'Password reset tokens are stored as hashes, not readable secrets.');
$resetService->reset($resetToken,'NuevaClavePortal123','NuevaClavePortal123');
portal_check(!$resetService->isValid($resetToken),'A password reset token is single-use.');
$resetAccount=$service->authenticate('responsable@example.test','NuevaClavePortal123');
portal_check((int)$resetAccount['id']===$accountId,'The new password works after the one-time reset link is redeemed.');
$reuseRejected=false;try{$resetService->reset($resetToken,'OtraClavePortal123','OtraClavePortal123');}catch(RuntimeException){$reuseRejected=true;}
portal_check($reuseRejected,'A redeemed password reset link cannot be reused.');
$expiredToken=$resetService->issue($accountId,'admin-local');$pdo->prepare('UPDATE account_password_resets SET expires_at=? WHERE token_hash=?')->execute([gmdate('Y-m-d H:i:s',time()-60),hash('sha256',$expiredToken)]);
portal_check(!$resetService->isValid($expiredToken),'Expired password reset links are rejected.');
$accountAdmin=new AccountAdminService($pdo);$accountAdmin->setAccountStatus($accountId,'disabled',null,'Suspensión sintética de prueba');
portal_check($service->current($accountId)===null,'A disabled account cannot keep using an authenticated session.');
$disabledLoginRejected=false;try{$service->authenticate('responsable@example.test','NuevaClavePortal123');}catch(RuntimeException){$disabledLoginRejected=true;}
portal_check($disabledLoginRejected,'A disabled account cannot start a new session.');
$accountAdmin->setAccountStatus($accountId,'active');
portal_check($service->current($accountId)!==null,'An administrator can re-enable a disabled account.');
echo "OK: solicitud vinculada, evidencia de representación requerida, aprobación revisada, autenticación y aislamiento de negocios.\n";

$pdo->exec("INSERT INTO subscription_plans(code) VALUES('free'),('featured');INSERT INTO business_subscriptions(id,business_id,plan_code,status,starts_at,ends_at) VALUES(100,1,'free','active',datetime('now','-1 day'),NULL),(101,1,'featured','pending_payment',NULL,NULL)");
$owned=$service->businesses($accountId);$owned=array_values(array_filter($owned,static fn($row)=>(int)$row['id']===1));
portal_check($owned[0]['active_plan_code']==='free'&&$owned[0]['plan_code']==='featured'&&$owned[0]['subscription_status']==='pending_payment','A pending paid request must not replace the active free subscription in the customer portal.');

// Fail after the account INSERT: the account and application must roll back together.
$beforeAccounts=(int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
$beforeApplications=(int)$pdo->query('SELECT COUNT(*) FROM business_account_applications')->fetchColumn();
$pdo->exec("CREATE TRIGGER fail_application BEFORE INSERT ON business_account_applications BEGIN SELECT RAISE(ABORT,'injected failure'); END");
$input=['application_type'=>'new_business','destination_id'=>'1','category_code'=>'food','business_name'=>'Negocio de fallo sintético','relationship_code'=>'owner','phone'=>'3230000000','message'=>'','consent'=>'1'];
$failed=false;try{$service->register('Responsable sintético','rollback@example.test','ClaveSintetica123',$input);}catch(PDOException){$failed=true;}
portal_check($failed&&!$pdo->inTransaction(),'Injected failure must leave no active transaction.');
portal_check((int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()===$beforeAccounts,'Failed combined registration must not leave an orphan account.');
portal_check((int)$pdo->query('SELECT COUNT(*) FROM business_account_applications')->fetchColumn()===$beforeApplications,'Failed combined registration must not leave an application.');
$failed=false;try{$service->register('','','',$input,[],null,null,null,$accountId);}catch(PDOException){$failed=true;}
portal_check($failed&&$service->current($accountId)!==null,'A failed business request must preserve an existing active account.');
$pdo->exec('DROP TRIGGER fail_application');
$service->register('','','',$input,[],null,null,null,$accountId);
$repeatRejected=false;try{$service->register('','','',$input,[],null,null,null,$accountId);}catch(RuntimeException){$repeatRejected=true;}
portal_check($repeatRejected&&(int)$pdo->query('SELECT COUNT(*) FROM business_account_applications')->fetchColumn()===$beforeApplications+1,'Repeated submission must not duplicate the pending request.');
echo "OK: injected INSERT failure rolls back new account and application; existing account survives; retry creates exactly one request.\n";

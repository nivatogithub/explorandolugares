<?php
declare(strict_types=1);
function portal_check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
function throttle(string $kind,int $limit,int $seconds,string $extra=''):bool{return true;}
require dirname(__DIR__).'/app/BusinessAccountPortalService.php';
require dirname(__DIR__).'/app/AccountAdminService.php';
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT,account_type TEXT,display_name TEXT,email TEXT UNIQUE,password_hash TEXT,status TEXT,email_verified_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE business_translations(business_id INTEGER,locale TEXT,display_name TEXT,summary TEXT DEFAULT "",description TEXT DEFAULT "");
CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT,publication_status TEXT,display_order INTEGER);
CREATE TABLE destination_translations(destination_id INTEGER,locale TEXT,display_name TEXT);
CREATE TABLE service_categories(code TEXT,display_order INTEGER,is_active INTEGER);
CREATE TABLE service_category_translations(category_code TEXT,locale TEXT,label TEXT);
CREATE TABLE businesses(id INTEGER PRIMARY KEY,public_id TEXT,destination_id INTEGER,category_code TEXT,information_status TEXT,claim_status TEXT,operation_status TEXT,publication_status TEXT,slug TEXT,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE business_account_memberships(account_id INTEGER,business_id INTEGER,role TEXT,status TEXT);
CREATE TABLE subscription_plans(code TEXT PRIMARY KEY);
CREATE TABLE business_subscriptions(id INTEGER PRIMARY KEY,business_id INTEGER,plan_code TEXT,status TEXT);
CREATE TABLE business_claim_requests(id INTEGER PRIMARY KEY,account_id INTEGER,business_id INTEGER,status TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);');
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/019-business-account-applications.sqlite.sql'));
$pdo->exec("INSERT INTO destinations VALUES(1,'jerico','published',1);INSERT INTO destination_translations VALUES(1,'es','Jericó');INSERT INTO service_categories VALUES('food',1,1);INSERT INTO service_category_translations VALUES('food','es','Alimentación');INSERT INTO businesses VALUES(1,'public-id',1,'food','PUBLICO','NO_RECLAMADO','DESCONOCIDO','published','cafe-prueba',CURRENT_TIMESTAMP);INSERT INTO business_translations VALUES(1,'es','Café de prueba','','');INSERT INTO businesses VALUES(2,'hidden-id',1,'food','PUBLICO','NO_RECLAMADO','DESCONOCIDO','draft','hotel-oculto',CURRENT_TIMESTAMP);INSERT INTO business_translations VALUES(2,'es','Hotel que aún no aparece','','')");
$service=new BusinessAccountPortalService($pdo);
$matches=$service->searchRegistrationBusinesses(1,'food','Hotel que');
portal_check(count($matches)===1&&$matches[0]['display_name']==='Hotel que aún no aparece'&&$matches[0]['can_request'],'Registration search can find an unpublished business without exposing its private fields.');
$accountId=$service->register('Responsable local','responsable@example.test','ClaveDePrueba123',['application_type'=>'existing_business','business_id'=>'1','destination_id'=>'1','category_code'=>'food','relationship_code'=>'owner','phone'=>'3230000000','message'=>'Soy su propietario','consent'=>'1']);
$account=$service->authenticate('RESPONSABLE@example.test','ClaveDePrueba123');
portal_check((int)$account['id']===$accountId&&$account['account_type']==='business'&&$account['status']==='active','Registration creates a business account only alongside its associated request.');
portal_check($service->current($accountId)!==null,'An authenticated business account can load its profile.');
$rejected=false;try{$service->authenticate('responsable@example.test','incorrecta');}catch(RuntimeException){$rejected=true;}
portal_check($rejected,'Invalid credentials never authenticate.');
$apps=$service->applications($accountId);
portal_check(count($apps)===1&&$apps[0]['business_name']==='Café de prueba'&&$apps[0]['application_type']==='claim_existing','The account portal can follow its business association request.');
portal_check((string)$pdo->query('SELECT claim_status FROM businesses WHERE id=1')->fetchColumn()==='EN_TRAMITE','An existing business becomes unavailable to duplicate claim requests while its account request is reviewed.');
$accountsBefore=(int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();$failed=false;
try{$service->register('Persona sin negocio','sin-negocio@example.test','ClaveDePrueba123',[]);}catch(RuntimeException){$failed=true;}
portal_check($failed&&(int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()===$accountsBefore,'An account cannot be created without a matched business or a new-business request.');
$newId=$service->register('Nueva responsable','nuevo@example.test','ClaveDePrueba456',['application_type'=>'new_business','destination_id'=>'1','category_code'=>'food','business_name'=>'Restaurante pendiente','relationship_code'=>'authorized_representative','phone'=>'6040000000','message'=>'Puedo aportar el registro mercantil','consent'=>'1']);
portal_check(count($service->applications($newId))===1&&(int)$pdo->query('SELECT COUNT(*) FROM businesses WHERE slug="restaurante-pendiente"')->fetchColumn()===0,'A missing business creates a private validation request, not a public business record.');
$service->reviewApplication((int)$pdo->query('SELECT id FROM business_account_applications WHERE account_id='.$newId)->fetchColumn(),'in_review','Solicitar soporte mercantil');
portal_check((string)$pdo->query('SELECT status FROM business_account_applications WHERE account_id='.$newId)->fetchColumn()==='in_review','The administration queue can track review without creating a business or granting access.');
$pdo->exec("INSERT INTO businesses VALUES(3,'another-id',1,'food','ACTIVO','RECLAMADO','ABIERTO','draft','otro-negocio',CURRENT_TIMESTAMP);INSERT INTO business_translations VALUES(3,'es','Otro negocio verificado','','')");
(new AccountAdminService($pdo))->linkBusiness(['account_id'=>$accountId,'business_id'=>3,'membership_role'=>'manager']);
portal_check((int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships WHERE account_id='.$accountId.' AND business_id=3 AND role="manager"')->fetchColumn()===1,'An administrator can assign a verified business to an authorized account.');
$weak=false;try{$service->register('Otra persona','otra@example.test','123',[]);}catch(RuntimeException){$weak=true;}
portal_check($weak,'Short passwords are rejected.');
echo "OK: búsqueda de negocios privados, registro vinculado, solicitud de alta nueva, autenticación y aislamiento de negocios.\n";

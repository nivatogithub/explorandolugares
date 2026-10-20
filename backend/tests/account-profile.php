<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/AccountProfileService.php';
require dirname(__DIR__).'/app/BusinessChannelImporter.php';
require dirname(__DIR__).'/app/BusinessCrmRepository.php';
require dirname(__DIR__).'/app/BusinessAccountPortalService.php';
require_once dirname(__DIR__).'/app/AutomaticBusinessClaim.php';
require dirname(__DIR__).'/app/AccountAdminService.php';
require dirname(__DIR__).'/app/BusinessBulkEditService.php';
require dirname(__DIR__).'/app/DatabaseMaintenance.php';
function throttle(string $kind,int $limit,int $seconds,string $extra=''):bool{return true;}
function profile_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
// Keep the disposable fixture aligned with the current schema (including 030 categories).
$schemaMigrations=glob(dirname(__DIR__).'/database/migrations/*.sqlite.sql');sort($schemaMigrations,SORT_STRING);
foreach($schemaMigrations as $file){if((int)basename($file)>=8)$pdo->exec(file_get_contents($file));}
$pdo->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"jerico","published"); INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Jericó","",""); INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1); INSERT INTO service_category_translations(category_code,locale,label) VALUES("hospedaje","es","Hospedaje");');
for($i=1;$i<=3;$i++){$pdo->prepare('INSERT INTO businesses(id,public_id,destination_id,category_code,slug) VALUES(?,?,1,"hospedaje",?)')->execute([$i,'public-'.$i,'negocio-'.$i]);$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")')->execute([$i,'Negocio '.$i]);}
$channel=['channel'=>'email','value'=>'info@example.invalid','source_url'=>'https://example.invalid/business','consulted_at'=>'2026-10-15','evidence_status'=>'PUBLICADO_PARA_CLIENTES','legal_basis_status'=>'Pendiente de revisión','recipient_label'=>'Administración'];
$package=['schema_version'=>'business-channels-import-v1','destination_slug'=>'jerico','businesses'=>[['name'=>'NEGOCIO 1','channels'=>[$channel]]]];
BusinessChannelImporter::import($pdo,$package);
profile_check((int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()===0,'Importing public-directory channels and their recipient labels does not create an account.');
profile_check((int)$pdo->query('SELECT COUNT(*) FROM sqlite_master WHERE name IN ("people","person_channels","account_person_links")')->fetchColumn()===0,'The model creates no separate person tables or channel registry for people.');
$id=(int)$pdo->query('SELECT id FROM business_research_contacts')->fetchColumn();$pdo->exec('UPDATE business_research_contacts SET suppressed=1');
$noRecipient=$package;unset($noRecipient['businesses'][0]['channels'][0]['recipient_label']);BusinessChannelImporter::import($pdo,$noRecipient);
profile_check($pdo->query('SELECT recipient_label FROM business_research_contacts')->fetchColumn()==='Administración'&&(int)$pdo->query('SELECT suppressed FROM business_research_contacts')->fetchColumn()===1,'Reimporting without recipient preserves its annotation and exclusion.');
BusinessChannelImporter::saveRecipient($pdo,1,$id,'A la atención de Ana');
$failed=false;try{BusinessChannelImporter::saveRecipient($pdo,2,$id,'Otra área');}catch(RuntimeException){$failed=true;}
profile_check($failed&&$pdo->query('SELECT recipient_label FROM business_research_contacts')->fetchColumn()==='A la atención de Ana','A recipient can be edited only under the channel’s existing business.');
$bulk=new BusinessBulkEditService($pdo);$export=$bulk->export('jerico');
profile_check($export['businesses'][0]['research_contacts'][0]['recipient_label']==='A la atención de Ana','Private JSON exports include recipient annotations.');
$export['businesses'][0]['research_contacts'][0]['recipient_label']='Recepción';$bulk->apply($export,'test-admin');
profile_check($pdo->query('SELECT recipient_label FROM business_research_contacts')->fetchColumn()==='Recepción','A reviewed bulk update can edit recipient labels without creating identities.');
$profile=new AccountProfileService($pdo);$portal=new BusinessAccountPortalService($pdo);
$account=$portal->register('Responsable de prueba','acceso@example.invalid','ClaveDePrueba123',['application_type'=>'existing_business','business_id'=>'1','destination_id'=>'1','category_code'=>'hospedaje','relationship_code'=>'owner','phone'=>'+573230000001','consent'=>'1']);
profile_check($pdo->query('SELECT contact_phone FROM accounts WHERE id='.$account)->fetchColumn()==='+573230000001','The registering person’s phone lives in their account.');
$pdo->exec('UPDATE accounts SET status="active" WHERE id='.(int)$account);$pdo->beginTransaction();AutomaticBusinessClaim::afterActivation($pdo,(int)$account);$pdo->commit();
profile_check(count($portal->businesses($account))===1,'An unowned business is associated directly without waiting for documentary approval.');
$app=(int)$pdo->query('SELECT id FROM business_account_applications WHERE account_id='.$account)->fetchColumn();
// Legacy pending applications retain their transactional documentary review path.
$pdo->exec('DELETE FROM business_account_memberships WHERE account_id='.(int)$account.' AND business_id=1');$pdo->exec("UPDATE business_account_applications SET status='pending' WHERE id=".$app);$pdo->exec("UPDATE businesses SET claim_status='EN_TRAMITE' WHERE id=1");
function evidence(PDO $pdo,int $app):array{$ids=[];foreach(['identity_document','business_registration'] as $type){$pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size,verified_by,verified_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP)')->execute([$app,$type,$type.'.pdf','prueba.pdf','application/pdf',128,'test']);$ids[]=(int)$pdo->lastInsertId();}return $ids;}
$documents=evidence($pdo,$app);$pdo->exec('UPDATE accounts SET contact_phone=""');
$pdo->exec('CREATE TRIGGER stop_profile BEFORE UPDATE OF contact_phone ON accounts WHEN NEW.contact_phone<>"" BEGIN SELECT RAISE(ABORT,"synthetic failure"); END');
$failed=false;try{$portal->reviewApplication($app,'approved','Documentos ficticios revisados para la prueba.',$documents,'test');}catch(PDOException){$failed=true;}
profile_check($failed&&(int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships')->fetchColumn()===0&&$pdo->query('SELECT status FROM business_account_applications WHERE id='.$app)->fetchColumn()==='pending','Failure to persist the approved account profile rolls back approval and access.');
$pdo->exec('DROP TRIGGER stop_profile');$portal->reviewApplication($app,'approved','Documentos ficticios revisados para la prueba.',$documents,'test');
profile_check(count($profile->forBusiness(1))===1&&$profile->forBusiness(1)[0]['display_name']==='Responsable de prueba','An approved business has a single responsible account with name, email and phone.');
$pdo->exec('UPDATE businesses SET information_status="ACTIVO",claim_status="RECLAMADO" WHERE id=2');
$pdo->prepare('INSERT INTO business_account_applications(account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,consent_version,status) VALUES(?,"claim_existing",2,1,"hospedaje","Negocio 2","owner","test","approved")')->execute([$account]);$app2=(int)$pdo->lastInsertId();evidence($pdo,$app2);
(new AccountAdminService($pdo))->linkBusiness(['account_id'=>$account,'business_id'=>2,'membership_role'=>'owner']);
$profile->savePhoneForBusiness(1,'+573230000002');
profile_check($profile->forBusiness(2)[0]['contact_phone']==='+573230000002'&&(int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()===1,'One personal account owns several business associations and one shared phone profile.');
profile_check((new BusinessCrmRepository($pdo))->overview(['query'=>'+573230000002'])['total']===2,'CRM can search the responsible account’s phone across its businesses.');
$other=$portal->register('Otra responsable','otra@example.invalid','ClaveDePrueba456',['application_type'=>'existing_business','business_id'=>'3','destination_id'=>'1','category_code'=>'hospedaje','relationship_code'=>'owner','consent'=>'1']);
$pdo->exec('UPDATE accounts SET status="active" WHERE id='.(int)$other);$pdo->beginTransaction();AutomaticBusinessClaim::afterActivation($pdo,(int)$other);$pdo->commit();
$failed=false;try{$pdo->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,1,"owner","active")')->execute([$other]);}catch(PDOException){$failed=true;}
profile_check($failed&&count($profile->forBusiness(1))===1,'The database rejects a second active account for a business.');
$failed=false;try{$profile->savePhoneForBusiness(3,'+573230000003');}catch(RuntimeException){$failed=true;}
profile_check(!$failed&&$pdo->query('SELECT contact_phone FROM accounts WHERE id='.$other)->fetchColumn()==='+573230000003','A directly reclaimed business has its associated personal profile.');
profile_check((int)$pdo->query('SELECT COUNT(*) FROM business_contacts')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM business_research_contacts WHERE campaign_allowed<>0 OR marketing_consent<>0')->fetchColumn()===0,'Responsible-account data and recipient annotations are private and do not enable campaigns.');
$report=(new DatabaseMaintenance($pdo,dirname(__DIR__).'/app',dirname(__DIR__).'/public'))->inspect();profile_check($report['migrations']['028-accounts-business-channels']==='applied','Diagnostics recognize the final simple model.');
echo "OK: personal account profile without person tables; one responsible account/business; several businesses/account; private recipient annotations, bulk editing and approval rollback.\n";

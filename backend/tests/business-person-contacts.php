<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessPersonContactService.php';
require dirname(__DIR__).'/app/BusinessChannelImporter.php';
require dirname(__DIR__).'/app/BusinessCrmRepository.php';
require dirname(__DIR__).'/app/BusinessAccountPortalService.php';
require dirname(__DIR__).'/app/DatabaseMaintenance.php';
function throttle(string $kind,int $limit,int $seconds,string $extra=''):bool{return true;}
function person_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
for($i=8;$i<=28;$i++){
    $files=glob(dirname(__DIR__).'/database/migrations/'.sprintf('%03d',$i).'-*.sqlite.sql');
    person_check(count($files)===1,'Every migration has one SQLite counterpart.');$pdo->exec(file_get_contents($files[0]));
}
$pdo->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"jerico","published");
    INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Jericó",""," ");
    INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1);
    INSERT INTO service_category_translations(category_code,locale,label) VALUES("hospedaje","es","Hospedaje");');
for($i=1;$i<=3;$i++){
    $pdo->prepare('INSERT INTO businesses(id,public_id,destination_id,category_code,slug,publication_status) VALUES(?,?,1,"hospedaje",?,"draft")')->execute([$i,'public-'.$i,'negocio-'.$i]);
    $pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")')->execute([$i,'Negocio '.$i]);
}
$people=new BusinessPersonContactService($pdo);
person_check($people->ready(),'The complete additive person model is available.');
person_check((int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn()===0,'Installing the model creates no people from public businesses or old channels.');
$channel=['channel'=>'email','value'=>'info@example.invalid','source_url'=>'https://example.invalid/business','consulted_at'=>'2026-10-15','evidence_status'=>'PUBLICADO_PARA_CLIENTES','legal_basis_status'=>'Pendiente de revisión'];
BusinessChannelImporter::import($pdo,['schema_version'=>'business-channels-import-v1','destination_slug'=>'jerico','businesses'=>[['name'=>'NEGOCIO 1','channels'=>[$channel]]]]);
person_check((int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn()===0,'General channels imported by name never fabricate a contact person.');
$contact1=$people->saveContact(1,['display_name'=>'Persona de prueba','roles'=>['owner','legal_representative'],'email'=>'responsable@example.invalid','source_note'=>'Datos ficticios facilitados por la persona.']);
$person1=(int)$pdo->query('SELECT person_id FROM business_person_contacts WHERE id='.$contact1)->fetchColumn();
person_check((int)$pdo->query('SELECT COUNT(*) FROM business_person_contact_roles WHERE contact_id='.$contact1)->fetchColumn()===2,'One person can have two roles for the same business.');
person_check((int)$pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships')->fetchColumn()===0,'Saving a contact does not create a login or grant business permissions.');
$contact2=$people->saveContact(1,['display_name'=>'Otra persona de prueba','roles'=>['administrator'],'phone'=>'+573230000000','channel_scope'=>'personal','source_note'=>'Datos ficticios recibidos directamente.']);
$person2=(int)$pdo->query('SELECT person_id FROM business_person_contacts WHERE id='.$contact2)->fetchColumn();
$people->saveContact(2,['person_id'=>$person1,'roles'=>['administrator'],'email'=>'sede-dos@example.invalid','source_note'=>'Persona identificada que administra esta otra sede.']);
$people->saveContact(2,['person_id'=>$person2,'roles'=>['employee'],'source_note'=>'La persona también trabaja para este negocio.']);
person_check((int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn()===2,'The same person can be reused across businesses without a new personal record.');
$first=$people->forBusiness(1);$second=$people->forBusiness(2);
$firstChannels=array_merge(...array_column($first,'channels'));$secondChannels=array_merge(...array_column($second,'channels'));
person_check(!in_array('sede-dos@example.invalid',array_column($firstChannels,'channel_value'),true)&&!in_array('responsable@example.invalid',array_column($secondChannels,'channel_value'),true),'A person’s business-specific channel stays scoped to that business.');
person_check(in_array('+573230000000',array_column($firstChannels,'channel_value'),true)&&in_array('+573230000000',array_column($secondChannels,'channel_value'),true),'A declared personal channel is available through the person in both active business relationships.');
$pdo->exec('UPDATE person_channels SET suppressed=1 WHERE person_id='.$person1.' AND business_id=1');
$people->saveContact(1,['person_id'=>$person1,'roles'=>['owner','legal_representative'],'email'=>'responsable@example.invalid','source_note'=>'Se actualizan los cargos manteniendo el mismo canal.']);
person_check((int)$pdo->query('SELECT COUNT(*) FROM business_person_contacts WHERE business_id=1 AND status="active" AND is_primary=1')->fetchColumn()===1,'Updating the primary contact retains exactly one primary.');
person_check((int)$pdo->query('SELECT COUNT(*) FROM person_channels WHERE person_id='.$person1.' AND business_id=1')->fetchColumn()===1&&(int)$pdo->query('SELECT suppressed FROM person_channels WHERE person_id='.$person1.' AND business_id=1')->fetchColumn()===1,'Readding a channel preserves suppression and does not duplicate it.');
$crm=new BusinessCrmRepository($pdo);
$found=$crm->overview(['query'=>'sede-dos@example.invalid']);
person_check($found['total']===1&&(int)$found['rows'][0]['id']===2,'CRM search finds the person’s scoped channel under the correct business.');
person_check($crm->overview(['query'=>'Persona de prueba'])['total']===2,'CRM can search identified people without multiplying business rows.');
person_check((int)$crm->find(1)['person_contact_count']===2,'CRM reports people separately from general channels.');
$before=(int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn();$rejected=false;
try{$people->saveContact(1,['display_name'=>'No guardar','roles'=>['owner'],'email'=>'mal','source_note'=>'Prueba de rechazo']);}catch(RuntimeException){$rejected=true;}
person_check($rejected&&(int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn()===$before,'Invalid person channels leave no person or relationship behind.');
$rejected=false;try{$people->deactivateContact(2,$contact1);}catch(RuntimeException){$rejected=true;}
person_check($rejected,'A contact cannot be deactivated using another business ID.');
$people->deactivateContact(1,$contact1);
person_check((int)$pdo->query('SELECT is_primary FROM business_person_contacts WHERE id='.$contact2)->fetchColumn()===1,'Ending the primary relationship promotes another active contact.');
person_check((int)$crm->find(1)['person_contact_count']===1&&(int)$crm->find(2)['person_contact_count']===2,'Ending one relationship does not remove the person from another business.');

$portal=new BusinessAccountPortalService($pdo);
$accountId=$portal->register('Responsable que se registra','acceso@example.invalid','ClaveDePrueba123',['application_type'=>'existing_business','business_id'=>'3','destination_id'=>'1','category_code'=>'hospedaje','relationship_code'=>'owner','phone'=>'+573230000001','consent'=>'1']);
$accountPerson=$people->ensureAccountPerson($accountId);
person_check((int)$pdo->query('SELECT COUNT(*) FROM account_person_links WHERE account_id='.$accountId)->fetchColumn()===1,'A newly registered account is associated with a single person.');
person_check($people->forBusiness(3)===[]&&$portal->businesses($accountId)===[],'Registration does not establish an approved business contact or business access before review.');
$appId=(int)$pdo->query('SELECT id FROM business_account_applications WHERE account_id='.$accountId)->fetchColumn();
$evidence=[];
foreach(['identity_document','business_registration'] as $type){$pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$appId,$type,$type.'.pdf','prueba.pdf','application/pdf',128]);$evidence[]=(int)$pdo->lastInsertId();}
$pdo->exec('CREATE TRIGGER stop_person_channel BEFORE INSERT ON person_channels BEGIN SELECT RAISE(ABORT,"synthetic channel failure"); END');
$rejected=false;try{$portal->reviewApplication($appId,'approved','Documentos ficticios revisados para esta prueba.',$evidence,'test-admin');}catch(PDOException){$rejected=true;}
person_check($rejected&&(int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships WHERE account_id='.$accountId)->fetchColumn()===0&&$pdo->query('SELECT status FROM business_account_applications WHERE id='.$appId)->fetchColumn()==='pending','Failure to save the approved contact rolls back the whole approval and permissions.');
person_check($people->forBusiness(3)===[],'A failed approval leaves no partially approved contact.');
$pdo->exec('DROP TRIGGER stop_person_channel');
$portal->reviewApplication($appId,'approved','Documentos ficticios revisados para esta prueba.',$evidence,'test-admin');
$approved=$people->forBusiness(3);
person_check(count($approved)===1&&(int)$approved[0]['person_id']===$accountPerson&&$approved[0]['roles']===['owner']&&(int)$approved[0]['is_primary']===1,'Approval links the existing account person as the business contact with the declared role.');
person_check(count($approved[0]['channels'])===2&&(int)$pdo->query('SELECT COUNT(*) FROM business_contacts')->fetchColumn()===0,'Approved personal email and phone remain private and separate from general business channels.');
$people->saveContact(3,['person_id'=>$accountPerson,'roles'=>['owner'],'email'=>'otro-correo@example.invalid','source_note'=>'La persona proporciona otro correo para comunicaciones.']);
person_check($pdo->query('SELECT email FROM accounts WHERE id='.$accountId)->fetchColumn()==='acceso@example.invalid','Adding a person’s contact email does not change their login credentials.');
person_check((int)$pdo->query('SELECT COUNT(*) FROM person_channels WHERE marketing_consent<>0 OR campaign_allowed<>0')->fetchColumn()===0,'Manual contacts and account approval never imply campaign permission.');
person_check($pdo->query('SELECT publication_status FROM businesses WHERE id=3')->fetchColumn()==='draft','People and account linkage do not publish a business.');
$pdo->exec('DELETE FROM business_person_contacts WHERE business_id=3');
person_check($people->syncMissingApprovedPeople(3)===1&&count($people->forBusiness(3))===1,'Previously approved accounts can be reconciled explicitly using their existing application.');
person_check($people->syncMissingApprovedPeople(3)===0,'Reconciling old approved accounts is idempotent.');
$reconciled=$people->forBusiness(3);$people->deactivateContact(3,(int)$reconciled[0]['id']);
person_check($people->syncMissingApprovedPeople(3)===0&&$people->forBusiness(3)[0]['status']==='inactive','Reconciliation preserves a manually deactivated contact instead of reactivating it.');
$report=(new DatabaseMaintenance($pdo,dirname(__DIR__).'/app',dirname(__DIR__).'/public'))->inspect();
person_check($report['migrations']['028-people-business-contacts']==='applied','Database diagnostics recognize the complete additive migration.');
echo "OK: people, multiple business roles, private/scoped channels, name-based bulk loading, independent accounts, approval rollback and no fabricated public-directory people.\n";

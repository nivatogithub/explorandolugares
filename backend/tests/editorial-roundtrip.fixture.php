<?php
declare(strict_types=1);
// Test server only; this file is never a public release input.
if(PHP_SAPI!=='cli-server'||getenv('EL_EDITORIAL_TEST')!=='1'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/BusinessEditorialService.php';
require dirname(__DIR__).'/app/BusinessEditorialPackage.php';
require dirname(__DIR__).'/app/BusinessEditorialMail.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$p->exec('PRAGMA foreign_keys=ON');$base=dirname(__DIR__).'/database';
$p->exec(file_get_contents($base.'/sqlite.sql'));$p->exec(file_get_contents($base.'/destinations.sqlite.sql'));
$files=glob($base.'/migrations/*.sqlite.sql');sort($files);foreach($files as $file)if((int)basename($file)>=8)$p->exec(file_get_contents($file));
$p->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"fixture","published");INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Lugar ficticio","","");INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1);INSERT INTO service_category_translations(category_code,locale,label) VALUES("hospedaje","es","Hospedaje");INSERT INTO accounts(id,public_id,account_type,display_name,email,status,email_verified_at) VALUES(1,"fixture-account","traveler","Persona ficticia","persona@example.invalid","active",CURRENT_TIMESTAMP);INSERT INTO businesses(id,public_id,destination_id,category_code,slug,information_status,record_origin,publication_status,latitude,longitude,location_confirmed,location_map_url) VALUES(1,"fixture-business",1,"hospedaje","fixture","VERIFICADO","owner_submitted","draft",5.791,-75.786,1,"https://www.google.com/maps/search/?api=1&query=5.791,-75.786");INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(1,1,"owner","active");INSERT INTO business_subscriptions(id,business_id,plan_code,billing_frequency,status,quoted_amount,reviewed_at) VALUES(1,1,"page_premium","annual","paid_pending_publication",100,CURRENT_TIMESTAMP);INSERT INTO business_account_applications(id,account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,consent_version,status) VALUES(1,1,"request_new",1,1,"hospedaje","Fixture","owner","test","approved");INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size,verified_at) VALUES(1,"business_registration","private.pdf","private.pdf","application/pdf",1,CURRENT_TIMESTAMP);INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,status,reviewed_at) VALUES(1,1,"payment.pdf","payment.pdf","application/pdf",1,"approved",CURRENT_TIMESTAMP)');
foreach(['es','en','pt-BR'] as $lang)$p->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(1,?,?,?,?)')->execute([$lang,'Fixture','Breve','Descripción']);
$p->exec('INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(1,"phone","+573001234567",1);UPDATE businesses SET information_status="ACTIVO" WHERE id=1');
$root=(string)getenv('EL_EDITORIAL_TEST_ROOT');$service=new BusinessEditorialService($p,$root.'/references');$package=new BusinessEditorialPackage($p,$root.'/references');
$service->save(1,1,0,[],true);
try{
    $first=$package->importPrepared(1,$_FILES['package']??[],$root,'fixture-admin');
    $second=$package->importPrepared(1,$_FILES['package']??[],$root,'fixture-admin');
    if($first===$second||$p->inTransaction())throw new RuntimeException('Reimport did not finish its own transaction.');
    $v=$service->version($second);$payload=$service->payload($v,1);
    if(count($payload['showcase']['products'])!==5)throw new RuntimeException('Lost Premium products.');
    foreach(['es','en','pt-BR'] as $locale)if(($payload['translations'][$locale]['summary']??'')!==str_repeat('á',1200-mb_strlen($locale)).$locale)throw new RuntimeException('Lost the 1200-character registration description.');
    $service->transition($second,'prepare',0,'fixture-admin','',$v['payload_hash'],['translations'=>'1','images'=>'1','layout'=>'1']);
    $mail=(new BusinessEditorialMail($p,['public_origin'=>'http://127.0.0.1'],null,true))->send($second,'fixture-admin');
    $service->transition($second,'approve',1,'','',$v['payload_hash']);$service->publishByOwner($second,1,$v['payload_hash']);
    if($service->latest(1)['status']!=='published')throw new RuntimeException('Owner publication failed.');
    header('Content-Type: application/json');echo json_encode(['ok'=>true,'products'=>count($payload['showcase']['products']),'mail'=>$mail,'status'=>$service->latest(1)['status']]);
}catch(Throwable $e){http_response_code(500);echo get_class($e).': '.$e->getMessage();}

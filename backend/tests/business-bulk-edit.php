<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessBulkEditService.php';
function bulk_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function bulk_rejected(callable $action,string $label):void{$failed=false;try{$action();}catch(Throwable $error){$failed=true;}bulk_check($failed,$label);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
foreach(['008-business-subscriptions','009-business-origin','010-business-location-subscription-lifecycle','011-premium-business-story','012-business-image-references','013-accounts-location-map','014-tiered-business-description','015-business-media-quotas','016-plan-content-limits','026-business-research-contacts','027-business-bulk-update-audit'] as $name)$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/'.$name.'.sqlite.sql'));
$pdo->exec('INSERT INTO destinations(id,slug) VALUES(1,"jerico"),(2,"jardin"); INSERT INTO service_categories(code) VALUES("hospedaje"),("alimentacion");');
$insert=$pdo->prepare('INSERT INTO businesses(id,public_id,destination_id,category_code,slug,publication_status) VALUES(?,?,?,?,?,?)');
foreach([[1,'00000000-0000-4000-8000-000000000001',1,'hospedaje','hotel-uno','published'],[2,'00000000-0000-4000-8000-000000000002',1,'alimentacion','cafe-dos','draft'],[3,'00000000-0000-4000-8000-000000000003',2,'hospedaje','otro-destino','draft']] as $row)$insert->execute($row);
$q=$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,"","")');foreach([1=>'Hotel de prueba',2=>'Café de prueba',3=>'Hotel de otro destino'] as $id=>$name)foreach(['es','en','pt-BR'] as $locale)$q->execute([$id,$locale,$name]);
$pdo->exec('INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(1,"free","active"),(2,"free","pending_publication"); INSERT INTO business_contacts(id,business_id,channel,contact_value,is_public) VALUES(1,1,"email","publico@example.invalid",1); INSERT INTO business_sources(business_id,source_url,checked_at,note) VALUES(1,"https://example.invalid/hotel","2026-10-15 10:00:00","Fuente de prueba"),(1,"https://example.invalid/hotel","2026-10-15 10:00:00","Fuente de prueba");');
$pdo->exec('INSERT INTO business_research_contacts(id,business_id,channel,contact_value,source_url,consulted_at,evidence_status,source_note,legal_basis_status,record_hash,suppressed) VALUES(1,1,"email","privado@example.invalid","https://example.invalid/contacto","2026-10-15","PUBLICADO_PARA_CLIENTES","Nota privada","PENDIENTE_REVISION","hash-de-prueba",1);');
$service=new BusinessBulkEditService($pdo);$export=$service->export('jerico');
bulk_check(count($export['businesses'])===2&&$export['businesses'][0]['public_id']==='00000000-0000-4000-8000-000000000001','Export uses persistent IDs and filters by destination.');
bulk_check($service->preview($export)['businesses_changed']===0,'An unchanged export is a no-op, including existing duplicate source observations.');
bulk_check($service->apply($export,'admin-test')['updated']===0&&(int)$pdo->query('SELECT COUNT(*) FROM business_bulk_update_audit')->fetchColumn()===0,'A no-op does not create data or audit changes.');
$package=$export;$package['businesses']=[$package['businesses'][0]];$row=&$package['businesses'][0];
$row['fields']['address_text']='Dirección ficticia de prueba';$row['translations']['es']['display_name']='Hotel de prueba actualizado';
$row['research_contacts'][0]['contact_value']='actualizado@example.invalid';
$row['contacts'][]=['channel'=>'instagram','contact_value'=>'https://instagram.com/example_test'];
$row['sources'][]=['source_url'=>'https://example.invalid/nueva-fuente','note'=>'Fuente añadida sin fecha'];unset($row);
$preview=$service->preview($package);
bulk_check($preview['businesses_changed']===1&&count($preview['changes'][0]['changes'])===5,'Preview reports exact changed fields and additions.');
bulk_check($pdo->query('SELECT address_text FROM businesses WHERE id=1')->fetchColumn()==='','Preview is read-only.');
$result=$service->apply($package,'admin-test');
bulk_check($result['updated']===1&&(int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===3,'Update never inserts a business.');
$business=$pdo->query('SELECT * FROM businesses WHERE id=1')->fetch();
bulk_check($business['publication_status']==='published'&&$business['slug']==='hotel-uno'&&$business['public_id']==='00000000-0000-4000-8000-000000000001','Publication, route and persistent IDs preserved.');
bulk_check((int)$pdo->query('SELECT is_public FROM business_contacts WHERE channel="instagram"')->fetchColumn()===0&&(int)$pdo->query('SELECT is_public FROM business_contacts WHERE id=1')->fetchColumn()===1,'New contacts stay private; existing publication permission is unchanged.');
bulk_check($pdo->query('SELECT checked_at FROM business_sources WHERE note="Fuente añadida sin fecha"')->fetchColumn()===null,'A new source without date uses SQL NULL.');
bulk_check($pdo->query('SELECT checked_at FROM business_sources WHERE id=1')->fetchColumn()==='2026-10-15 10:00:00','Existing source timestamps are preserved.');
bulk_check($pdo->query('SELECT contact_value FROM business_research_contacts WHERE id=1')->fetchColumn()==='actualizado@example.invalid'&&(int)$pdo->query('SELECT suppressed FROM business_research_contacts WHERE id=1')->fetchColumn()===1,'Private research updates by its own ID and preserves suppression.');
$audit=$pdo->query('SELECT * FROM business_bulk_update_audit')->fetch();
bulk_check(json_decode($audit['before_json'],true)['translations']['es']['display_name']==='Hotel de prueba'&&json_decode($audit['after_json'],true)['translations']['es']['display_name']==='Hotel de prueba actualizado','Audit stores recoverable before/after values.');
bulk_rejected(fn()=>$service->apply($package,'admin-test'),'The old export is rejected after the record changes.');
$fresh=$service->export('jerico');$patch=['schema_version'=>$fresh['schema_version'],'destination_slug'=>'jerico','businesses'=>[['public_id'=>$fresh['businesses'][0]['public_id'],'version'=>$fresh['businesses'][0]['version'],'fields'=>['address_text'=>'','location_reference'=>'Referencia ficticia']]]];
$service->apply($patch,'admin-test');
bulk_check($pdo->query('SELECT address_text FROM businesses WHERE id=1')->fetchColumn()==='Dirección ficticia de prueba','Blank fields never erase existing values.');
bulk_check((int)$pdo->query('SELECT COUNT(*) FROM business_contacts WHERE business_id=1')->fetchColumn()===2,'Omitted contacts and sources remain stored.');
$base=$service->export('jerico');
foreach(['unknown','cross-destination','duplicate-id','wrong-internal-id','invalid-category','public-contact-permission','marketing-permission','duplicate-name','invalid-source-date'] as $case){
    $bad=$base;
    switch($case){
        case 'unknown':$bad['businesses'][0]['public_id']='unknown-id';break;
        case 'cross-destination':$bad['businesses'][0]['public_id']='00000000-0000-4000-8000-000000000003';break;
        case 'duplicate-id':$bad['businesses'][]=$bad['businesses'][0];break;
        case 'wrong-internal-id':$bad['businesses'][0]['id']=2;break;
        case 'invalid-category':$bad['businesses'][0]['fields']['category_code']='hotel-no-existe';break;
        case 'public-contact-permission':$bad['businesses'][0]['contacts'][1]['is_public']=1;break;
        case 'marketing-permission':$bad['businesses'][0]['research_contacts'][0]['campaign_allowed']=1;break;
        case 'duplicate-name':$bad['businesses'][1]['translations']['es']['display_name']=$bad['businesses'][0]['translations']['es']['display_name'];break;
        case 'invalid-source-date':$bad['businesses'][0]['sources'][0]['checked_at']='2026-02-30';break;
    }
    bulk_rejected(fn()=>$service->apply($bad,'admin-test'),'Reject '.$case);
}
$stale=$service->export('jerico');$pdo->exec('UPDATE business_research_contacts SET source_note="Revisión concurrente" WHERE id=1');
bulk_rejected(fn()=>$service->apply($stale,'admin-test'),'Concurrent private-contact changes invalidate the export.');
$before=$service->export('jerico');$bad=$before;$bad['businesses'][0]['fields']['address_text']='No debe guardarse';$bad['businesses'][1]['translations']['es']['display_name']='Fallo de prueba';
$pdo->exec('CREATE TRIGGER fail_second BEFORE UPDATE ON business_translations WHEN NEW.display_name="Fallo de prueba" BEGIN SELECT RAISE(ABORT,"Fallo simulado"); END;');
$auditCount=(int)$pdo->query('SELECT COUNT(*) FROM business_bulk_update_audit')->fetchColumn();
bulk_rejected(fn()=>$service->apply($bad,'admin-test'),'A late database failure rejects the entire batch.');
bulk_check($service->export('jerico')===$before&&(int)$pdo->query('SELECT COUNT(*) FROM business_bulk_update_audit')->fetchColumn()===$auditCount,'No earlier row or audit is committed after a late failure.');
echo "OK: JSON export, persistent IDs, sparse updates, preview, private contacts, source provenance, no business inserts, stale/conflicting-input protection, transaction rollback and before/after audit.\n";

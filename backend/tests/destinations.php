<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/DestinationImporter.php';
require dirname(__DIR__).'/app/DestinationRepository.php';
require dirname(__DIR__).'/app/BusinessRepository.php';
require dirname(__DIR__).'/app/BusinessAdminService.php';
require dirname(__DIR__).'/app/BusinessSubscriptionAdminService.php';
require dirname(__DIR__).'/app/BusinessPublicImporter.php';
require dirname(__DIR__).'/app/BusinessBulkPublicationService.php';
require dirname(__DIR__).'/app/AccountAdminService.php';

function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/006-poi-experience-links.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/008-business-subscriptions.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/009-business-origin.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/010-business-location-subscription-lifecycle.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/011-premium-business-story.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/012-business-image-references.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/013-accounts-location-map.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/014-tiered-business-description.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/015-business-media-quotas.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/016-plan-content-limits.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/021-business-payment-evidence.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/025-subscription-renewal-history.sqlite.sql'));
$root=dirname(__DIR__,2);
$catalog=json_decode(file_get_contents($root.'/content/destinos-dinamicos.json'),true,512,JSON_THROW_ON_ERROR);
$options=json_decode(file_get_contents(dirname(__DIR__).'/app/contact-options.json'),true,512,JSON_THROW_ON_ERROR);
$counts=DestinationImporter::import($pdo,$catalog,$options);
check($counts['destinations']===21,'Expected 21 destinations');
check($counts['translations']===63,'Expected 3 destination translations each');
check($counts['points_of_interest']===70,'Expected 70 points of interest, including Jericó attractions and activities');
check((int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===0,'Importer must not create businesses');
$repo=new DestinationRepository($pdo);
$jerico=$repo->findPublished('jerico','es');
check($jerico!==null && $jerico['display_name']==='Jericó','Spanish destination loads');
check(!array_key_exists('internal_research_json',$jerico),'Internal local market research is excluded from public destination data');
check($jerico['history_media']['path']==='assets/destinos/1/monumento-santa-laura-ilustrado-v2.webp','Jericó history section uses the selected close-cropped monument illustration');
check(!array_key_exists('provenance_json',$jerico['history_media']),'Private image provenance is excluded from public render data');
$historyProvenance=json_decode((string)$pdo->query("SELECT provenance_json FROM media_assets WHERE asset_path='assets/destinos/1/monumento-santa-laura-ilustrado-v2.webp'")->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
check(($historyProvenance['reference_asset_path']??'')==='assets/jerico/monumento-santa-laura.webp'&&($historyProvenance['reference_source_url']??'')==='https://commons.wikimedia.org/wiki/File:Santa_Laura_Montoya_Upegui%2C_Jeric%C3%B3_Antioquia.jpg','Private image provenance records the source photograph, origin and licence');
check((int)$pdo->query("SELECT COUNT(*) FROM media_assets WHERE origin='ai_generated' AND provenance_json IS NOT NULL")->fetchColumn()===11,'All selected Jericó AI illustrations retain private provenance or factual-basis notes');
foreach ([
    'assets/destinos/1/cerro-de-las-nubes-sendero-ilustrado-v2.webp',
    'assets/destinos/1/chorros-quebradona-ilustrado-v2.webp',
] as $approvedImage) {
    $query=$pdo->prepare('SELECT p.slug,m.provenance_json FROM points_of_interest p JOIN point_of_interest_media pm ON pm.point_of_interest_id=p.id JOIN media_assets m ON m.id=pm.media_asset_id WHERE m.asset_path=?');
    $query->execute([$approvedImage]);$row=$query->fetch();
    check($row!==false && in_array($row['slug'],['subida-al-cerro-de-las-nubes','chorros-de-quebradona'],true),'Approved nature-attraction image is connected to its Jericó card');
    $provenance=json_decode((string)$row['provenance_json'],true,512,JSON_THROW_ON_ERROR);
    check(($provenance['human_approval']??'')!=='','Approved illustration retains its private approval note');
}
check(count($jerico['history'])===2 && count($jerico['places'])===7,'Public Jericó page shows seven published attractions');
$publishedExperienceCount=(int)$pdo->query("SELECT COUNT(*) FROM points_of_interest WHERE destination_id=".(int)$jerico['id']." AND poi_type='experience' AND publication_status='published'")->fetchColumn();
check($publishedExperienceCount===0,'Operator experiences are kept out of public listings until approved');
$jericoPreview=$repo->findPublished('jerico','es',true);
check($jericoPreview!==null && count($jericoPreview['places'])===10,'Private preview can review draft operator experiences alongside attractions');
foreach(array_slice($jericoPreview['places'],7) as $experience)check($experience['type']==='experience'&&$experience['external_url']==='','Draft experiences have no external operator link before an agreement');
check($jerico['places'][1]['media'][0]['creator']!=='','POI image attribution returned');
check(count(array_filter($jerico['sources'],static fn($source)=>$source['section_key']==='places'))===2,'Attractions sources include the Antioquia government tourism guide');
$jericoPt=$repo->findPublished('jerico','pt-BR');
check($jericoPt!==null && str_contains($jericoPt['places'][1]['media'][0]['alt'],'visto de perto sobre Jericó') && str_contains($jericoPt['history_media']['alt'],'Santa Madre Laura'),'Portuguese alternative text stays localized for the updated illustrations');
$jericoVisuals=array_column($catalog['destinations'][0]['translations']['es']['places'],'slug');
$expectedJericoMedia=['centro-historico'=>'assets/destinos/1/parque-principal-ilustrado.webp','historic-centre'=>'assets/destinos/1/parque-principal-ilustrado.webp','cerro-el-salvador'=>'assets/destinos/1/cristo-cerro-el-salvador-ilustrado-v2.webp','colina-de-el-salvador'=>'assets/destinos/1/cristo-cerro-el-salvador-ilustrado-v2.webp','santuario-de-santa-laura'=>'assets/destinos/1/santuario-santa-laura-ilustrado-v2.webp','saint-laura-sanctuary'=>'assets/destinos/1/santuario-santa-laura-ilustrado-v2.webp','casa-natal-de-santa-laura'=>'assets/destinos/1/casa-santa-laura-ilustrada-v2.webp','birthplace-of-saint-laura'=>'assets/destinos/1/casa-santa-laura-ilustrada-v2.webp','jardin-botanico-los-balsos'=>'assets/destinos/1/jardin-botanico-los-balsos-ilustrado.webp','los-balsos-botanical-garden'=>'assets/destinos/1/jardin-botanico-los-balsos-ilustrado.webp','subida-al-cerro-de-las-nubes'=>'assets/destinos/1/cerro-de-las-nubes-sendero-ilustrado-v2.webp','cerro-de-las-nubes-hike'=>'assets/destinos/1/cerro-de-las-nubes-sendero-ilustrado-v2.webp','subida-ao-cerro-de-las-nubes'=>'assets/destinos/1/cerro-de-las-nubes-sendero-ilustrado-v2.webp','chorros-de-quebradona'=>'assets/destinos/1/chorros-quebradona-ilustrado-v2.webp'];
foreach($catalog['destinations'][0]['translations'] as $locale=>$translation)foreach($translation['places'] as $place)if(isset($expectedJericoMedia[$place['slug']]))check(($place['media']['path']??null)===$expectedJericoMedia[$place['slug']],'Selected Jericó illustration is stable in every locale: '.$locale.' / '.$place['slug']);
$jericoCatalog=$catalog['destinations'][0];foreach($jericoCatalog['translations'] as $locale=>$translation)foreach(array_slice($translation['places'],-3) as $experience)check($experience['publication_status']==='draft'&&$experience['external_url']==='','Jericó operator activities stay unlinked drafts after catalog generation: '.$locale.' / '.$experience['slug']);
$jardin=$repo->findPublished('jardin','en');
check($jardin===null,'Other pilot destinations remain hidden until approved');
$jardinPreview=$repo->findPublished('jardin','en',true);
check($jardinPreview!==null && $jardinPreview['locale']==='en' && $jardinPreview['publication_status']==='draft' && count($jardinPreview['places'])===3,'A draft destination remains available to authenticated preview');
check($repo->findPublished('not-a-place','es')===null,'Missing destination does not load');
$pdo->exec("INSERT INTO destinations(slug,destination_type,country_code,department,publication_status) VALUES('destino-borrador','municipality','CO','Antioquia','draft')");
$draftId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(?,?,?,?,?)')->execute([$draftId,'es','Destino borrador','Aún no publicado','Texto de prueba']);
$pdo->prepare('INSERT INTO points_of_interest(destination_id,slug,publication_status) VALUES(?,?,?)')->execute([$draftId,'atractivo-borrador','draft']);$draftPoi=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description) VALUES(?,?,?,?)')->execute([$draftPoi,'es','Atractivo de prueba','Solo visible en previsualización.']);
check($repo->findPublished('destino-borrador','es')===null,'An unpublished destination stays invisible on public routes');
$privateDraft=$repo->findPublished('destino-borrador','es',true);check($privateDraft!==null&&$privateDraft['publication_status']==='draft'&&count($privateDraft['places'])===1,'Private preview can render draft destination and child attractions');
foreach ($catalog['destinations'] as $record) foreach ($catalog['locales'] as $locale) {
    $page=$repo->findPublished($record['slug'],$locale);
    if($record['slug']==='jerico')check($page!==null && $page['slug']===$record['slug'] && $page['locale']===$locale,'Jericó is public in every locale');
    else check($page===null,'Draft is hidden in every locale: '.$record['slug'].' / '.$locale);
}
$businessInsert=$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?)');
$destinationId=(int)$pdo->query("SELECT id FROM destinations WHERE slug='jerico'")->fetchColumn();
$businessInsert->execute(['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',$destinationId,'alimentacion','sitio-de-prueba','PUBLICO','NO_RECLAMADO','DESCONOCIDO','published']);
$businessInsert->execute(['test-draft',$destinationId,'alimentacion','sitio-borrador','PUBLICO','NO_RECLAMADO','DESCONOCIDO','draft']);
$businessInsert->execute(['test-closed',$destinationId,'alimentacion','sitio-cerrado','PUBLICO','NO_RECLAMADO','CERRADO_DEFINITIVO','published']);
$businessId=(int)$pdo->query("SELECT id FROM businesses WHERE public_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'")->fetchColumn();
$pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status) VALUES(?,?,?,?)')->execute([$businessId,'featured','monthly','active']);
$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description,brand_story,featured_services) VALUES(?,?,?,?,?,?,?)')->execute([$businessId,'es','Prueba','Descripción breve','Descripción',null,null]);
$pdo->prepare('INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(?,?,?,?)')->execute([$businessId,'whatsapp','+57 300 123 4567',1]);
$pdo->prepare('INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(?,?,?,?)')->execute([$businessId,'email','privado@example.test',0]);
$mediaId=(int)$pdo->query('SELECT id FROM media_assets LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO business_media(business_id,media_asset_id,display_order,role) VALUES(?,?,?,?)')->execute([$businessId,$mediaId,1,'logo']);
$visible=$repo->findPublished('jerico','es')['businesses'];
check(count($visible)===1 && $visible[0]['public_id']==='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','Only published, non-closed businesses appear');
check($visible[0]['plan_code']==='featured'&&isset($visible[0]['features']['highlighted_card']),'The active subscription determines directory card entitlements');
check($visible[0]['image_path']!==null,'Published business card can load its image');
$contacts=$repo->findPublished('jerico','es')['public_contacts'][$businessId]??[];
check(count($contacts)===1 && $contacts[0]['channel']==='whatsapp','Private business contacts stay hidden');
$pageBusinessId=(int)$pdo->query("SELECT id FROM businesses WHERE public_id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'")->fetchColumn();
$pdo->prepare('UPDATE business_subscriptions SET plan_code=?,status=? WHERE business_id=?')->execute(['page_premium','active',$pageBusinessId]);
 $businessPage=(new BusinessRepository($pdo))->findPublicPage('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','es');
check($businessPage!==null&&$businessPage['plan_code']==='page_premium'&&isset($businessPage['features']['business_page']),'Page-tier businesses get a dedicated profile rendered from the subscription catalog');
check((new BusinessRepository($pdo))->findPublicPage('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','pt-BR')['plan_name']==='Página Premium','The public profile subscription label is localized');
$pdo->exec("UPDATE subscription_plan_prices SET amount=25000 WHERE plan_code='featured' AND billing_frequency='monthly'");
$adminService=new BusinessAdminService($pdo,sys_get_temp_dir().'/explorando-lugares-image-references-test');$newBusiness=$adminService->save(['destination_id'=>$destinationId,'category_code'=>'alimentacion','slug'=>'url-que-no-se-debe-usar','display_order'=>'999','publication_status'=>'published','information_status'=>'VERIFICADO','claim_status'=>'RECLAMADO','operation_status'=>'ABIERTO','plan_code'=>'featured','billing_frequency'=>'monthly','address_text'=>'Calle 10, Jericó','location_reference'=>'Frente al parque','location_map_url'=>'https://www.google.com/maps/place/Negocio/@5.7921,-75.7844,18z','map_workflow'=>'1','location_confirmed'=>'1','image_assistance_requested'=>'1','translations'=>['es'=>['display_name'=>'Negocio administrado','summary'=>'Resumen','description'=>'Descripción'],'en'=>['display_name'=>'Managed business','summary'=>'Summary','description'=>'Description'],'pt-BR'=>['display_name'=>'Negócio administrado','summary'=>'Resumo','description'=>'Descrição']],'contacts'=>['whatsapp'=>['value'=>'+573001234567','public'=>1],'phone_fixed'=>['value'=>'+5741234567','public'=>1],'instagram'=>['value'=>'https://instagram.com/negocio','public'=>1]]]);
check((int)$pdo->query('SELECT image_assistance_requested FROM businesses WHERE id='.$newBusiness)->fetchColumn()===1,'Image generation support preference is recorded separately from public media.');
$pdo->prepare('INSERT INTO business_media(business_id,media_asset_id,display_order,role) VALUES(?,?,?,?)')->execute([$newBusiness,$mediaId,0,'logo']);
$pdo->prepare('INSERT INTO business_image_references(business_id,storage_name,original_name,mime_type,file_size,width,height) VALUES(?,?,?,?,?,?,?)')->execute([$newBusiness,str_repeat('a',40).'.jpg','foto-local.jpg','image/jpeg',12345,640,480]);
$newBusinessRecord=$adminService->find($newBusiness);
check(count($newBusinessRecord['image_references'])===1&&$newBusinessRecord['image_references'][0]['original_name']==='foto-local.jpg','Uploaded reference media is retrievable as private review material, independently of published media.');
check($newBusinessRecord!==null&&$newBusinessRecord['publication_status']==='draft'&&$newBusinessRecord['information_status']==='PENDIENTE_REVISION'&&$newBusinessRecord['claim_status']==='NO_RECLAMADO'&&$newBusinessRecord['operation_status']==='DESCONOCIDO'&&$newBusinessRecord['record_origin']==='admin_created','Admin-created business ignores requested statuses and always starts as a private, unverified draft');
check($newBusinessRecord['slug']==='negocio-administrado'&&count($newBusinessRecord['translations'])===1&&$newBusinessRecord['subscription']['plan_code']==='featured'&&$newBusinessRecord['subscription']['status']==='pending','URL is generated, Spanish is the only initial locale, and the selected tier is attached as unpaid/pending on creation');
check($newBusinessRecord['address_text']==='Calle 10, Jericó'&&(float)$newBusinessRecord['latitude']===5.7921&&(float)$newBusinessRecord['longitude']===-75.7844&&str_contains($newBusinessRecord['location_map_url'],'openstreetmap.org/?mlat=5.7921')&&(int)$newBusinessRecord['location_confirmed']===1,'Address, destination confirmation and coordinates are tied to the selected map point');
$tooLong=$adminService->find($newBusiness);$tooLong['id']=$newBusiness;$tooLong['plan_code']='basic';$tooLong['translations']['es']['summary']=str_repeat('á',301);$limitRejected=false;try{$adminService->save($tooLong);}catch(RuntimeException $error){$limitRejected=str_contains($error->getMessage(),'300 caracteres');}check($limitRejected,'The server rejects Basic summaries above 300 Unicode characters.');
$mapUpdate=$newBusinessRecord;unset($mapUpdate['latitude'],$mapUpdate['longitude']);$mapUpdate['location_map_url']='https://maps.app.goo.gl/example-pin';$mapUpdate['location_map_changed']='1';$mapUpdate['location_confirmed']='1';$adminService->save($mapUpdate);$mapUpdatedRecord=$adminService->find($newBusiness);
check($mapUpdatedRecord['latitude']===null&&$mapUpdatedRecord['longitude']===null,'Changing to a map link without coordinates clears the old point instead of retaining a mismatched location');
check($newBusinessRecord['opening_hours']===''&&$newBusinessRecord['capacity_count']===null&&$newBusinessRecord['menu_url']===''&&$newBusinessRecord['reservations_url']==='','Featured-tier creation does not request page-only schedule, capacity, menu or booking details');
check(!isset($newBusinessRecord['contacts']['phone_fixed']),'A featured-card form does not collect contact channels reserved for own-page tiers.');
$pdo->prepare('UPDATE businesses SET publication_status="published" WHERE id=?')->execute([$newBusiness]);
check(count($repo->findPublished('jerico','es')['businesses'])===1,'A pending-review admin record stays hidden even if the publication flag is changed directly');
$pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(?,?,?)')->execute([$newBusiness,'page','active']);
check((new BusinessRepository($pdo))->findPublicPage($newBusinessRecord['public_id'],'es')===null,'A pending-review record cannot render its own public business page');
$pdo->prepare('DELETE FROM business_subscriptions WHERE business_id=?')->execute([$newBusiness]);
$pdo->prepare('UPDATE businesses SET publication_status="draft" WHERE id=?')->execute([$newBusiness]);
$newBusinessRecord['translations']['es']['summary']='Resumen actualizado';$newBusinessRecord['translations']['en']=['display_name'=>'Managed business','summary'=>'Summary','description'=>'Description'];
$adminService->save($newBusinessRecord);
check($adminService->find($newBusiness)['publication_status']==='draft'&&count($adminService->find($newBusiness)['translations'])===2,'Content edits remain private and preserve only submitted translations');
check((int)$pdo->query("SELECT COUNT(*) FROM business_media WHERE business_id={$newBusiness} AND role='logo'")->fetchColumn()===1,'Saving the business form does not reassign or remove its approved public logo.');
$subscriptionService=new BusinessSubscriptionAdminService($pdo);
check((int)$pdo->query("SELECT COUNT(*) FROM business_subscriptions WHERE business_id={$newBusiness}")->fetchColumn()===1,'The selected subscription is created together with the business form.');
$pdo->exec("UPDATE subscription_plan_prices SET amount=120000 WHERE plan_code='page' AND billing_frequency='quarterly'");
$subscriptionService->save(['business_id'=>$newBusiness,'plan_code'=>'page','billing_frequency'=>'quarterly']);
$subscriptionHistory=$pdo->query("SELECT plan_code,status FROM business_subscriptions WHERE business_id={$newBusiness} ORDER BY id")->fetchAll();
check(count($subscriptionHistory)===2&&$subscriptionHistory[0]['status']==='cancelled'&&$subscriptionHistory[1]['plan_code']==='page'&&$subscriptionHistory[1]['status']==='pending','A new paid choice keeps history and waits for payment review before enabling entitlements');
$imported=BusinessPublicImporter::import($pdo,['schema_version'=>'business-import-v1','destination_slug'=>'destino-borrador','businesses'=>[['category_code'=>'alimentacion','name'=>'Importado de prueba','summary'=>'Resumen confirmado por fuente','description'=>'Texto basado en información pública.','sources'=>[['url'=>'https://example.org/negocio','note'=>'Página oficial','checked_at'=>'2026-10-14']]]]]);
$importedRecord=$pdo->query("SELECT b.id,b.slug,b.record_origin,b.information_status,b.claim_status,b.operation_status,b.publication_status FROM businesses b JOIN business_translations t ON t.business_id=b.id WHERE t.display_name='Importado de prueba'")->fetch();
check($imported===1&&$importedRecord!==false&&$importedRecord['information_status']==='PUBLICO'&&$importedRecord['publication_status']==='draft'&&$importedRecord['claim_status']==='NO_RECLAMADO'&&$importedRecord['operation_status']==='DESCONOCIDO','JSON public-business import records source status but remains a private draft');
$importedPlan=$pdo->query('SELECT plan_code,status FROM business_subscriptions WHERE business_id='.(int)$importedRecord['id'])->fetch();
check($importedPlan['plan_code']==='free'&&$importedPlan['status']==='pending_publication','Every imported public listing is explicitly assigned the Free tier and waits for approval');
$importedLocales=$pdo->query('SELECT locale,display_name FROM business_translations WHERE business_id='.(int)$importedRecord['id'].' ORDER BY locale')->fetchAll();
check(count($importedLocales)===3&&count(array_unique(array_column($importedLocales,'display_name')))==1,'A proper business name is available in all portal languages without inventing translations');
$privateImportPreview=$repo->findPublished('destino-borrador','es',true);
check(count($privateImportPreview['businesses'])===1&&$privateImportPreview['businesses'][0]['display_name']==='Importado de prueba','Draft imported business cards appear in an authenticated destination preview only');
$publicImportCheck=$repo->findPublished('destino-borrador','es');
check($publicImportCheck===null,'A draft destination containing imported business cards never leaks into the public directory');
$importedRecord=$adminService->find((int)$importedRecord['id']);
$minimalFree=['id'=>(int)$importedRecord['id'],'destination_id'=>(int)$importedRecord['destination_id'],'category_code'=>$importedRecord['category_code'],'plan_code'=>'free','map_workflow'=>'1','location_confirmed'=>'0','translations'=>['es'=>['display_name'=>$importedRecord['translations']['es']['display_name']]]];
$freeEditId=$adminService->save($minimalFree);$freeEdited=$adminService->find($freeEditId);
check($freeEditId===(int)$importedRecord['id']&&$freeEdited['public_id']===$importedRecord['public_id']&&$freeEdited['record_origin']==='public_import','Editing a free imported record uses the same business and origin.');
check($freeEdited['address_text']===''&&$freeEdited['latitude']===null&&$freeEdited['location_map_url']===''&&$freeEdited['subscription']['plan_code']==='free','Free imported business can be saved with just name, category and destination, without a map, phone or description.');
$preserveId=$adminService->save(['destination_id'=>$destinationId,'category_code'=>'alimentacion','plan_code'=>'free','translations'=>['es'=>['display_name'=>'Gratis con datos privados']],'address_text'=>'Referencia conocida','latitude'=>'5.79','longitude'=>'-75.78','location_confirmed'=>'1','image_assistance_requested'=>'1']);
$preserveBefore=$adminService->find($preserveId);$adminService->save(['id'=>$preserveId,'destination_id'=>$destinationId,'category_code'=>'alimentacion','plan_code'=>'free','map_workflow'=>'1','translations'=>['es'=>['display_name'=>'Gratis con datos privados']]]);$preserveAfter=$adminService->find($preserveId);
foreach(['address_text','latitude','longitude','location_source','location_confirmed','image_assistance_requested'] as $field)check($preserveBefore[$field]===$preserveAfter[$field],'Hidden free fields preserve existing private data: '.$field);
$subscriptionService->publish((int)$importedRecord['id']);
$publishedImport=$pdo->query('SELECT b.publication_status,s.plan_code,s.status FROM businesses b JOIN business_subscriptions s ON s.business_id=b.id WHERE b.id='.(int)$importedRecord['id'])->fetch();
check($publishedImport['publication_status']==='published'&&$publishedImport['plan_code']==='free'&&$publishedImport['status']==='active','A reviewed imported Free listing can publish without exposing contact details or requiring an exact street address');
$sourceCount=(int)$pdo->query("SELECT COUNT(*) FROM business_sources WHERE business_id=".(int)$importedRecord['id'])->fetchColumn();
check($sourceCount===1,'Public import preserves its source URL and review date');
$accounts=new AccountAdminService($pdo);
$travelerId=$accounts->save(['account_type'=>'traveler','display_name'=>'Viajera de prueba','email'=>'traveler@example.test']);
$account=$pdo->query('SELECT account_type,status,password_hash FROM accounts WHERE id='.(int)$travelerId)->fetch();
check($account['account_type']==='traveler'&&$account['status']==='pending_activation'&&$account['password_hash']===null,'Traveler accounts can be created without issuing a password or enabling login');
$emptyBusinessAccountRejected=false;try{$accounts->save(['account_type'=>'business','display_name'=>'Responsable de prueba','email'=>'owner@example.test']);}catch(RuntimeException){$emptyBusinessAccountRejected=true;}
check($emptyBusinessAccountRejected,'The admin cannot create a business account outside the application workflow');
$pdo->prepare('UPDATE businesses SET information_status="ACTIVO",claim_status="RECLAMADO" WHERE id=?')->execute([$businessId]);
$businessAccountRejected=false;try{$accounts->save(['account_type'=>'business','display_name'=>'Responsable de prueba','email'=>'owner@example.test','business_ids'=>[$businessId]]);}catch(RuntimeException){$businessAccountRejected=true;}
check($businessAccountRejected&&(int)$pdo->query("SELECT COUNT(*) FROM accounts WHERE email='owner@example.test'")->fetchColumn()===0,'Even a reclaimed business cannot bypass the business registration and document review workflow');
$bulkPackage=['schema_version'=>'business-import-v1','destination_slug'=>'jerico','businesses'=>[]];
for($index=1;$index<=200;$index++)$bulkPackage['businesses'][]=['category_code'=>'alimentacion','information_status'=>'PUBLICO','name'=>'Establecimiento de prueba '.$index,'sources'=>[['url'=>'https://example.org/jerico/'.$index,'note'=>'Fuente validada','checked_at'=>'2026-10-15']]];
$bulkCount=BusinessPublicImporter::import($pdo,$bulkPackage);
$bulkStats=$pdo->query("SELECT COUNT(*) AS total,SUM(b.publication_status='draft') AS drafts,SUM(s.plan_code='free' AND s.status='pending_publication') AS free_pending FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale='es' JOIN business_subscriptions s ON s.business_id=b.id WHERE b.destination_id=".$destinationId." AND t.display_name LIKE 'Establecimiento de prueba %'")->fetch();
check($bulkCount===200&&(int)$bulkStats['total']===200&&(int)$bulkStats['drafts']===200&&(int)$bulkStats['free_pending']===200,'A batch of 200 Jericó listings imports atomically as private, unclaimed Free drafts');
$jericoBulkPreview=$repo->findPublished('jerico','es',true);$jericoPublicAfterBulk=$repo->findPublished('jerico','es');
check(count($jericoBulkPreview['businesses'])===201&&count($jericoPublicAfterBulk['businesses'])===1,'Private preview shows all 200 Free cards while public directory remains unchanged');
$bulkPublish=(new BusinessBulkPublicationService($pdo))->publish(array_column($pdo->query("SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale='es' WHERE t.display_name LIKE 'Establecimiento de prueba %'")->fetchAll(),'id'));
check(count($bulkPublish['published'])===200&&count($bulkPublish['skipped'])===0,'Admin can approve and publish 200 eligible imported Free businesses in a single batch');
$publishedBulk=(int)$pdo->query("SELECT COUNT(*) FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale='es' WHERE t.display_name LIKE 'Establecimiento de prueba %' AND b.publication_status='published'")->fetchColumn();
check($publishedBulk===200,'Bulk approval publishes all eligible selected records');
$duplicatePackage=['schema_version'=>'business-import-v1','destination_slug'=>'jerico','businesses'=>[
 ['category_code'=>'hospedaje','information_status'=>'PUBLICO','name'=>'Hotel La Palma','sources'=>[['url'=>'https://example.org/hotel-la-palma']]],
 ['category_code'=>'hospedaje','information_status'=>'PUBLICO','name'=>'Hótel La-Palma','sources'=>[['url'=>'https://example.org/hotel-la-palma-2']]],
]];
BusinessPublicImporter::import($pdo,$duplicatePackage);
$duplicateIds=array_column($pdo->query("SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale='es' WHERE t.display_name LIKE '%La%Palma'")->fetchAll(),'id');
$duplicateMap=BusinessBulkPublicationService::duplicateMap($pdo->query("SELECT b.id,b.destination_id,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale='es'")->fetchAll());
check(count($duplicateIds)===2&&count($duplicateMap)>=2,'Duplicate review detects names with accent and punctuation variations in the same destination');
$duplicatePublish=(new BusinessBulkPublicationService($pdo))->publish($duplicateIds);
check(count($duplicatePublish['published'])===0&&count($duplicatePublish['skipped'])===2,'Bulk publication blocks possible duplicate records and returns individual review reasons');
$beforeImportCount=(int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn();$batchRejected=false;
try{BusinessPublicImporter::import($pdo,['schema_version'=>'business-import-v1','destination_slug'=>'destino-borrador','businesses'=>[
 ['category_code'=>'alimentacion','name'=>'Lote válido provisional','summary'=>'','description'=>'','sources'=>[['url'=>'https://example.org/source']]],
 ['category_code'=>'no-existe','name'=>'Lote inválido','sources'=>[['url'=>'https://example.org/source']]]
]]);}catch(RuntimeException){$batchRejected=true;}
check($batchRejected&&(int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===$beforeImportCount,'Invalid JSON batches roll back all inserts');
$failed=false;try{DestinationImporter::import($pdo,$catalog,$options);}catch(RuntimeException $error){$failed=true;}
check($failed,'Repeat import into non-empty catalog must be refused');
check((int)$pdo->query('SELECT COUNT(*) FROM destinations')->fetchColumn()===22,'Refused repeat import leaves data intact, including the private-preview fixture');
$upgrade=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$upgrade->exec('CREATE TABLE media_assets (id INTEGER PRIMARY KEY, asset_path TEXT NOT NULL)');
$upgrade->exec(file_get_contents(dirname(__DIR__).'/database/migrations/007-media-provenance.sqlite.sql'));
$columns=$upgrade->query('PRAGMA table_info(media_assets)')->fetchAll(PDO::FETCH_COLUMN,1);
check(in_array('provenance_json',$columns,true),'Migration 007 adds a private provenance field to existing catalogs');
echo "OK: destination import, locale queries, source metadata, publication filtering, and repeat-import protection.\n";

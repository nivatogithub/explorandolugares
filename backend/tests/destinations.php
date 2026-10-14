<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/DestinationImporter.php';
require dirname(__DIR__).'/app/DestinationRepository.php';

function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/006-poi-experience-links.sqlite.sql'));
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
$expectedJericoMedia=['centro-historico'=>'assets/destinos/1/parque-principal-ilustrado.webp','cerro-el-salvador'=>'assets/destinos/1/cristo-cerro-el-salvador-ilustrado-v2.webp','santuario-de-santa-laura'=>'assets/destinos/1/santuario-santa-laura-ilustrado-v2.webp','casa-natal-de-santa-laura'=>'assets/destinos/1/casa-santa-laura-ilustrada-v2.webp','jardin-botanico-los-balsos'=>'assets/destinos/1/jardin-botanico-los-balsos-ilustrado.webp'];
foreach($catalog['destinations'][0]['translations'] as $locale=>$translation)foreach($translation['places'] as $place){if(isset($expectedJericoMedia[$place['slug']]))check($place['media']['path']===$expectedJericoMedia[$place['slug']],'Selected Jericó illustration is stable in every locale: '.$locale.' / '.$place['slug']);if(in_array($place['slug'],['subida-al-cerro-de-las-nubes','chorros-de-quebradona'],true))check($place['media']===null,'Unverified attraction art is omitted until a local reference is approved: '.$locale.' / '.$place['slug']);}
$jericoCatalog=$catalog['destinations'][0];foreach($jericoCatalog['translations'] as $locale=>$translation)foreach(array_slice($translation['places'],-3) as $experience)check($experience['publication_status']==='draft'&&$experience['external_url']==='','Jericó operator activities stay unlinked drafts after catalog generation: '.$locale.' / '.$experience['slug']);
$jardin=$repo->findPublished('jardin','en');
check($jardin!==null && $jardin['locale']==='en' && count($jardin['places'])===3,'Translated destination loads');
check($repo->findPublished('not-a-place','es')===null,'Missing destination does not load');
$pdo->exec("INSERT INTO destinations(slug,destination_type,country_code,department,publication_status) VALUES('destino-borrador','municipality','CO','Antioquia','draft')");
$draftId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(?,?,?,?,?)')->execute([$draftId,'es','Destino borrador','Aún no publicado','Texto de prueba']);
$pdo->prepare('INSERT INTO points_of_interest(destination_id,slug,publication_status) VALUES(?,?,?)')->execute([$draftId,'atractivo-borrador','draft']);$draftPoi=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description) VALUES(?,?,?,?)')->execute([$draftPoi,'es','Atractivo de prueba','Solo visible en previsualización.']);
check($repo->findPublished('destino-borrador','es')===null,'An unpublished destination stays invisible on public routes');
$privateDraft=$repo->findPublished('destino-borrador','es',true);check($privateDraft!==null&&$privateDraft['publication_status']==='draft'&&count($privateDraft['places'])===1,'Private preview can render draft destination and child attractions');
foreach ($catalog['destinations'] as $record) foreach ($catalog['locales'] as $locale) {
    $page=$repo->findPublished($record['slug'],$locale);
    check($page!==null && $page['slug']===$record['slug'] && $page['locale']===$locale,'All locale and slug combinations load');
}
$businessInsert=$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,?,?,?,?,?)');
$destinationId=(int)$pdo->query("SELECT id FROM destinations WHERE slug='jerico'")->fetchColumn();
$businessInsert->execute(['test-published',$destinationId,'alimentacion','sitio-de-prueba','PUBLICO','NO_RECLAMADO','DESCONOCIDO','published']);
$businessInsert->execute(['test-draft',$destinationId,'alimentacion','sitio-borrador','PUBLICO','NO_RECLAMADO','DESCONOCIDO','draft']);
$businessInsert->execute(['test-closed',$destinationId,'alimentacion','sitio-cerrado','PUBLICO','NO_RECLAMADO','CERRADO_DEFINITIVO','published']);
$businessId=(int)$pdo->query("SELECT id FROM businesses WHERE public_id='test-published'")->fetchColumn();
$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)')->execute([$businessId,'es','Prueba','Descripción breve','Descripción']);
$pdo->prepare('INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(?,?,?,?)')->execute([$businessId,'whatsapp','+57 300 123 4567',1]);
$pdo->prepare('INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(?,?,?,?)')->execute([$businessId,'email','privado@example.test',0]);
$mediaId=(int)$pdo->query('SELECT id FROM media_assets LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO business_media(business_id,media_asset_id,display_order) VALUES(?,?,?)')->execute([$businessId,$mediaId,1]);
$visible=$repo->findPublished('jerico','es')['businesses'];
check(count($visible)===1 && $visible[0]['public_id']==='test-published','Only published, non-closed businesses appear');
check($visible[0]['image_path']!==null,'Published business card can load its image');
$contacts=$repo->findPublished('jerico','es')['public_contacts'][$businessId]??[];
check(count($contacts)===1 && $contacts[0]['channel']==='whatsapp','Private business contacts stay hidden');
$failed=false;try{DestinationImporter::import($pdo,$catalog,$options);}catch(RuntimeException $error){$failed=true;}
check($failed,'Repeat import into non-empty catalog must be refused');
check((int)$pdo->query('SELECT COUNT(*) FROM destinations')->fetchColumn()===22,'Refused repeat import leaves data intact, including the private-preview fixture');
echo "OK: destination import, locale queries, source metadata, publication filtering, and repeat-import protection.\n";

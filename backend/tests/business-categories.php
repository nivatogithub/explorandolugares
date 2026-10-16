<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessAdminService.php';
require dirname(__DIR__).'/app/BusinessSubscriptionAdminService.php';
require dirname(__DIR__).'/app/BusinessPublicImporter.php';
require dirname(__DIR__).'/app/BusinessBulkEditService.php';
require dirname(__DIR__).'/app/DestinationRepository.php';
function categories_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/006-poi-experience-links.sqlite.sql'));
for($n=8;$n<=30;$n++)foreach(glob(dirname(__DIR__).'/database/migrations/'.sprintf('%03d',$n).'-*.sqlite.sql') as $file)$pdo->exec(file_get_contents($file));
$pdo->exec("INSERT INTO destinations(slug,publication_status) VALUES('jerico','published'); INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,'es','Destino de prueba','','')");
foreach(['hospedaje','alimentacion','experiencias','transporte'] as $code){$pdo->prepare('INSERT INTO service_categories(code) VALUES(?)')->execute([$code]);foreach(['es','en','pt-BR'] as $locale)$pdo->prepare('INSERT INTO service_category_translations(category_code,locale,label) VALUES(?,?,?)')->execute([$code,$locale,$code]);}
$package=['schema_version'=>'business-import-v1','destination_slug'=>'jerico','businesses'=>[['name'=>'Negocio de prueba','category_code'=>'hospedaje','category_codes'=>['alimentacion','experiencias','transporte','transporte'],'sources'=>[['url'=>'https://example.test/business','checked_at'=>'2026-10-16']]]]];
BusinessPublicImporter::import($pdo,$package);
$id=(int)$pdo->query('SELECT id FROM businesses')->fetchColumn();$storage=sys_get_temp_dir().'/el-categories-'.bin2hex(random_bytes(6));$admin=new BusinessAdminService($pdo,$storage);
categories_check(count($admin->find($id)['category_codes'])===4,'Import keeps four unique categories on one ID');
categories_check(count($admin->all())===1,'Admin must not duplicate a business per category');
$input=['id'=>$id,'destination_id'=>1,'category_code'=>'hospedaje','plan_code'=>'free','translations'=>['es'=>['display_name'=>'Negocio de prueba']],'category_codes'=>['alimentacion']];
$admin->save($input);categories_check(count($admin->find($id)['category_codes'])===2,'Explicit replacement removes extra categories');
unset($input['category_codes']);$admin->save($input);categories_check(count($admin->find($id)['category_codes'])===2,'Omitted category list preserves extras');
$input['category_codes']=['inventada'];$failed=false;try{$admin->save($input);}catch(RuntimeException $e){$failed=true;}categories_check($failed&&count($admin->find($id)['category_codes'])===2,'Unknown category is rejected without partial changes');
$bulk=new BusinessBulkEditService($pdo);$export=$bulk->export('jerico','alimentacion');categories_check(count($export['businesses'])===1,'Export matches secondary category');
$export['businesses'][0]['category_codes']=['experiencias','transporte'];$preview=$bulk->preview($export);categories_check($preview['businesses_changed']===1,'Bulk preview includes category changes');$bulk->apply($export,'test');
categories_check(count($admin->find($id)['category_codes'])===3,'Bulk replacement retains principal category');
$pdo->exec('UPDATE businesses SET publication_status="published"');$destination=(new DestinationRepository($pdo))->findPublished('jerico','es');
categories_check(count($destination['businesses'])===1&&count($destination['categories'])===3&&count($destination['businesses'][0]['category_codes'])===3,'Public directory exposes all category filters and exactly one business');
$admin->delete($id,true);categories_check((int)$pdo->query('SELECT COUNT(*) FROM business_categories')->fetchColumn()===0,'Deleting a business removes only its category relationships');
rmdir($storage);
echo "OK: multiple catalog categories, stable ID, no duplicate cards, sparse updates, JSON preview/apply/export filters, public category filters and deletion cascade.\n";

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessAdminService.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
for($n=8;$n<=48;$n++){foreach(glob(dirname(__DIR__).'/database/migrations/'.sprintf('%03d',$n).'-*.sqlite.sql') as $file)$pdo->exec(file_get_contents($file));}
$storage=sys_get_temp_dir().'/el-delete-'.bin2hex(random_bytes(6));
$service=new BusinessAdminService($pdo,$storage);
function assertDelete(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo->exec("INSERT INTO destinations(slug) VALUES('test'); INSERT INTO accounts(public_id,account_type,display_name,email) VALUES('person','business','Persona','person@example.test')");
$category=$pdo->query('SELECT code FROM service_categories LIMIT 1')->fetchColumn();
// Catalog categories are normally seeded by the destination importer.
if(!$category){$pdo->exec("INSERT INTO service_categories(code,is_active,display_order) VALUES('test',1,0)");$category='test';}
$insert=$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug) VALUES(?,1,?,?)');
foreach([1,2,3,4,5,6] as $id){$insert->execute(['business-'.$id,$category,'test-'.$id]);$pdo->exec("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES($id,'es','Negocio $id','','')");}
$sub=$pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,status,quoted_amount) VALUES(?,?,?,?)');
$sub->execute([1,'free','active',null]);
foreach(['pending','cancelled','expired'] as $i=>$status){$id=$i+2;$sub->execute([$id,'basic',$status,19900]);$sub->execute([$id,'free','active',null]);assertDelete(!$service->canDelete($id),'Any historical paid tier must block deletion: '.$status);try{$service->delete($id,true);throw new LogicException('Deletion unexpectedly allowed');}catch(RuntimeException $e){assertDelete(str_contains($e->getMessage(),'historial'),'History rejection expected');}}
$sub->execute([5,'free','active',null]);$subscription=(int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size) VALUES($subscription,1,'proof','proof.pdf','application/pdf',10)");
assertDelete(!$service->canDelete(5),'Evidence must protect even an inconsistent Free record');
assertDelete($service->canDelete(6),'Never subscribed business is eligible');
$pdo->exec("INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(1,1,'owner','active'); INSERT INTO business_contacts(business_id,channel,contact_value,is_public,display_order) VALUES(1,'email','shop@example.test',0,0)");
try{$service->delete(1,false);throw new LogicException('Confirmation was skipped');}catch(RuntimeException $e){assertDelete(str_contains($e->getMessage(),'Confirma'),'Explicit confirmation required');}
$service->delete(1,true);
assertDelete((int)$pdo->query('SELECT COUNT(*) FROM businesses WHERE id=1')->fetchColumn()===0,'Free business is deleted');
assertDelete((int)$pdo->query('SELECT COUNT(*) FROM business_contacts WHERE business_id=1')->fetchColumn()===0,'Its channels cascade');
assertDelete((int)$pdo->query('SELECT COUNT(*) FROM accounts WHERE id=1')->fetchColumn()===1,'Responsible account survives');
assertDelete((int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===5,'Other businesses survive');
$sharedName=str_repeat('a',40).'.png';file_put_contents($storage.'/'.$sharedName,'synthetic');
$pdo->prepare('INSERT INTO business_account_applications(account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,consent_version) VALUES(1,"claim_existing",6,1,?,"Negocio 6","owner","test")')->execute([$category]);$application=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO business_application_images(application_id,storage_name,original_name,mime_type,file_size,width,height) VALUES(?,?,"ficticio.png","image/png",9,1,1)')->execute([$application,$sharedName]);
$pdo->prepare('INSERT INTO business_image_references(business_id,storage_name,original_name,mime_type,file_size,width,height) VALUES(6,?,"ficticio.png","image/png",9,1,1)')->execute([$sharedName]);
$service->delete(6,true);
assertDelete(is_file($storage.'/'.$sharedName),'Historical application image survives business deletion');unlink($storage.'/'.$sharedName);
rmdir($storage);
echo "OK: confirmed Free deletion, cascade isolation, responsible account preservation, historical paid tiers and payment evidence protection.\n";

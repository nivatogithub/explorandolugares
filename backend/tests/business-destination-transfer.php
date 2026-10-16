<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessDestinationTransfer.php';
function transfer_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function transfer_rejected(callable $action,string $message):void{$failed=false;try{$action();}catch(RuntimeException|PDOException $e){$failed=true;}transfer_check($failed,$message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/006-poi-experience-links.sqlite.sql'));
for($n=8;$n<=30;$n++)foreach(glob(dirname(__DIR__).'/database/migrations/'.sprintf('%03d',$n).'-*.sqlite.sql') as $file)$pdo->exec(file_get_contents($file));
$pdo->exec("INSERT INTO destinations(slug,country_code,department,publication_status) VALUES('jerico','CO','Antioquia','published');INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,'es','Jericó','','');INSERT INTO service_categories(code) VALUES('hospedaje'),('alimentacion');INSERT INTO accounts(public_id,account_type,display_name,email) VALUES('account','business','Persona','test@example.invalid')");
$config=json_decode(file_get_contents(dirname(__DIR__).'/app/cauca-viejo-business-transfer.json'),true,512,JSON_THROW_ON_ERROR);
$insert=$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug,publication_status,latitude,longitude,location_confirmed) VALUES(?,1,"hospedaje",?,"published",5.87,-75.75,1)');
$names=[...array_column($config['businesses'],'name'),...array_column($config['excluded'],'name')];
foreach($names as $i=>$name){$id=$i+1;$insert->execute(['business-'.$id,'business-'.$id]);$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")')->execute([$id,$name]);}
foreach($config['businesses'] as $i=>$business)$pdo->prepare('INSERT INTO business_sources(business_id,source_url,note) VALUES(?,"https://example.invalid/rnt",?)')->execute([$i+1,'MinCIT RNT '.$business['rnt']]);
$pdo->exec('INSERT INTO business_subscriptions(business_id,plan_code,status,quoted_amount) VALUES(1,"basic","active",19900);INSERT INTO business_contacts(business_id,channel,contact_value,is_public) VALUES(1,"email","business@example.invalid",0);INSERT INTO business_categories(business_id,category_code) VALUES(1,"alimentacion");INSERT INTO business_account_memberships(business_id,account_id,role,status) VALUES(1,1,"owner","active")');
$service=new BusinessDestinationTransfer($pdo);$plan=$service->preview();transfer_check(count($plan['ready'])===14,'Only the fourteen reviewed names are eligible');
$before=$pdo->query('SELECT * FROM businesses WHERE id=1')->fetch();
transfer_rejected(fn()=>$service->apply([1],$plan['hash'],false,'test',sys_get_temp_dir()),'Confirmation required');
transfer_rejected(fn()=>$service->apply([19],$plan['hash'],true,'test',sys_get_temp_dir()),'Unreviewed IDs are rejected');
$pdo->exec("CREATE TRIGGER stop_second BEFORE UPDATE OF destination_id ON businesses WHEN OLD.id=2 BEGIN SELECT RAISE(ABORT,'test rollback');END");
transfer_rejected(fn()=>$service->apply([1,2],$plan['hash'],true,'test',sys_get_temp_dir()),'A failed row rolls the entire transfer back');
transfer_check((int)$pdo->query('SELECT destination_id FROM businesses WHERE id=1')->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM business_bulk_update_audit')->fetchColumn()===0,'Failed batch leaves businesses and audit unchanged');
$pdo->exec('DROP TRIGGER stop_second');$plan=$service->preview();$result=$service->apply([1,2],$plan['hash'],true,'test',sys_get_temp_dir());
$after=$pdo->query('SELECT * FROM businesses WHERE id=1')->fetch();unset($before['destination_id'],$before['updated_at'],$after['destination_id'],$after['updated_at']);transfer_check($before===$after,'Transfer preserves UUID, slug, state, map, coordinates and all other business fields');
foreach(['business_contacts','business_categories','business_account_memberships','business_subscriptions'] as $table)transfer_check((int)$pdo->query('SELECT COUNT(*) FROM '.$table.' WHERE business_id=1')->fetchColumn()===1,'Transfer preserves '.$table);
transfer_check(count($result['moved'])===2&&(int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===19,'Two IDs move without inserting or deleting businesses');
transfer_check((int)$pdo->query('SELECT COUNT(*) FROM business_bulk_update_audit')->fetchColumn()===2,'Every transfer has before/after audit and evidence');
transfer_rejected(fn()=>$service->apply([1,2],$plan['hash'],true,'test',sys_get_temp_dir()),'Reusing old preview cannot repeat a transfer');
$plan=$service->preview();transfer_check(count($plan['already'])===2&&count($plan['ready'])===12,'Repeated preview reports already transferred IDs');
$pdo->exec('UPDATE businesses SET updated_at="2000-01-01" WHERE id=3');transfer_rejected(fn()=>$service->apply([3],$plan['hash'],true,'test',sys_get_temp_dir()),'Stale previews are rejected');
$target=(int)$result['target_id'];$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug) VALUES("collision",?,"hospedaje","business-3")')->execute([$target]);$collision=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es","Otro negocio","","")')->execute([$collision]);
$plan=$service->preview();transfer_check(count($plan['omitted'])===1,'Target slug collisions are omitted instead of overwriting');
transfer_check((int)$pdo->query('SELECT destination_id FROM businesses WHERE id=15')->fetchColumn()===1,'Posada Dulcinea stays in Jericó');
$pdo->exec('UPDATE business_sources SET note="MinCIT RNT 999999" WHERE business_id=4');
$plan=$service->preview();transfer_check(count($plan['omitted'])===2,'Matching a name with a different RNT must not authorize relocation');
echo "OK: reviewed destination transfers, stable IDs and relationships, atomic rollback, audit, stale/unauthorized-ID rejection, replay protection and slug collisions.\n";

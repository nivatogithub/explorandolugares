<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessSubscriptionAdminService.php';
function subscription_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE businesses(id INTEGER PRIMARY KEY,address_text TEXT,latitude NUMERIC,longitude NUMERIC,information_status TEXT,operation_status TEXT,publication_status TEXT,updated_at TEXT,record_origin TEXT,location_map_url TEXT,location_confirmed INTEGER);
CREATE TABLE subscription_plans(code TEXT PRIMARY KEY,display_order INTEGER,is_active INTEGER,max_gallery_photos INTEGER,has_business_page INTEGER,max_summary_chars INTEGER,max_description_chars INTEGER,max_brand_story_chars INTEGER,max_featured_services_chars INTEGER);
CREATE TABLE subscription_plan_translations(plan_code TEXT,locale TEXT,name TEXT,summary TEXT);
CREATE TABLE subscription_plan_prices(plan_code TEXT,billing_frequency TEXT,amount NUMERIC,currency TEXT);
CREATE TABLE subscription_plan_features(plan_code TEXT,feature_code TEXT,included INTEGER);
CREATE TABLE subscription_features(code TEXT PRIMARY KEY,display_order INTEGER);
CREATE TABLE subscription_feature_translations(feature_code TEXT,locale TEXT,name TEXT,description TEXT);
CREATE TABLE business_subscriptions(id INTEGER PRIMARY KEY AUTOINCREMENT,business_id INTEGER,plan_code TEXT,billing_frequency TEXT,status TEXT,starts_at TEXT,ends_at TEXT,reviewed_at TEXT,publish_not_before TEXT,quoted_amount NUMERIC,currency TEXT,published_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE business_media(business_id INTEGER,media_asset_id INTEGER,display_order INTEGER,role TEXT);
CREATE TABLE business_translations(business_id INTEGER,locale TEXT,display_name TEXT,summary TEXT,description TEXT,brand_story TEXT,featured_services TEXT);
CREATE TABLE business_contacts(business_id INTEGER,channel TEXT,contact_value TEXT,is_public INTEGER);');
$pdo->exec("INSERT INTO businesses VALUES(1,'Calle 10 # 5-12',6.1223,-75.7869,'ACTIVO','ABIERTO','draft',CURRENT_TIMESTAMP,'owner_submitted','https://www.google.com/maps/place/Cafe/@6.1223,-75.7869,18z',1);
INSERT INTO subscription_plans VALUES('free',1,1,0,0,0,0,0,0),('basic',2,1,0,0,300,0,0,0),('featured',3,1,0,0,500,1000,0,0),('page',4,1,0,1,750,2000,0,0),('page_premium',5,1,2,1,1000,3500,1500,1500);
INSERT INTO subscription_plan_translations VALUES('free','es','Gratis','Directorio'),('basic','es','Básico','Resumen'),('featured','es','Destacado','Tarjeta'),('page','es','Página','Sitio'),('page_premium','es','Página Premium','Sitio ampliado');
INSERT INTO subscription_plan_prices VALUES('page','quarterly',120000,'COP'),('page_premium','quarterly',NULL,'COP');
INSERT INTO subscription_plan_features VALUES('basic','expanded_details',1),('featured','expanded_details',1),('page','business_page',1),('page','photo_gallery',1),('page_premium','business_page',1),('page_premium','photo_gallery',1);
INSERT INTO subscription_features VALUES('expanded_details',1),('photo_gallery',2),('business_page',3),('extended_description',9);
INSERT INTO subscription_plan_features VALUES('free','extended_description',0),('basic','extended_description',0),('featured','extended_description',1),('page','extended_description',1),('page_premium','extended_description',1);
INSERT INTO business_media VALUES(1,9,0,'cover');
INSERT INTO business_translations VALUES(1,'es','Café de Prueba','Café local','Texto en español',NULL,NULL),(1,'en','Test Café','Local café','English text',NULL,NULL),(1,'pt-BR','Café Teste','Café da região','Texto em português',NULL,NULL);
INSERT INTO business_contacts VALUES(1,'whatsapp','+573001112233',1);");
$service=new BusinessSubscriptionAdminService($pdo);$plans=array_column($service->options(),null,'code');
subscription_check($plans['page']['max_gallery_photos']===0&&$plans['page_premium']['max_gallery_photos']===2,'Page includes one cover, while Premium allows up to three total public photos.');
subscription_check($plans['basic']['text_limits']['summary']===300&&$plans['featured']['text_limits']['description']===1000&&$plans['page']['text_limits']['summary']===750&&$plans['page_premium']['text_limits']['brand_story']===1500,'Text character quotas grow by subscription tier and are returned to the editor.');
subscription_check(!in_array('extended_description',$plans['basic']['features'],true)&&in_array('extended_description',$plans['featured']['features'],true),'Basic collects only the short summary while Featured unlocks the longer directory description.');
subscription_check(str_contains(implode(' ',$plans['free']['requirements']),'solo nombre, categoría y ubicación'),'Free exposes only the minimum public listing.');
subscription_check(str_contains(implode(' ',$plans['page']['requirements']),'dos referencias privadas')&&str_contains(implode(' ',$plans['page']['requirements']),'una imagen para preparar la portada')&&str_contains(implode(' ',$plans['page']['requirements']),'no se publican directamente'),'Page asks for a logo and one visual reference while keeping the published image review separate.');
subscription_check(str_contains(implode(' ',$plans['page_premium']['requirements']),'hasta 2 fotos adicionales')&&str_contains(implode(' ',$plans['page_premium']['requirements']),'historia o propuesta')&&str_contains(implode(' ',$plans['page_premium']['requirements']),'referencias son privadas'),'Premium lists up to three public photos total, private references and dedicated story content.');
$id=$service->save(['business_id'=>1,'plan_code'=>'page','billing_frequency'=>'quarterly','publish_not_before'=>'2026-10-15','payment_reviewed'=>'1']);
$q=$pdo->prepare('SELECT * FROM business_subscriptions WHERE id=?');$q->execute([$id]);$sub=$q->fetch(PDO::FETCH_ASSOC);
subscription_check($sub['status']==='paid_pending_publication'&&$sub['starts_at']===null&&$sub['quoted_amount']==120000&&$sub['publish_not_before']==='2026-10-15','Payment confirmation stores the quote and waits for publication without starting the term.');
$rejected=false;try{$service->publish(1,new DateTimeImmutable('2026-10-14 12:00:00',new DateTimeZone('America/Bogota')));}catch(RuntimeException $error){$rejected=str_contains($error->getMessage(),'fecha elegida');}
subscription_check($rejected,'A scheduled listing cannot publish early.');
$pdo->exec("INSERT INTO business_media VALUES(1,11,2,'gallery'),(1,12,3,'gallery'),(1,13,4,'gallery'),(1,14,5,'gallery'),(1,15,6,'gallery')");
$quotaRejected=false;try{$service->publish(1,new DateTimeImmutable('2026-10-16 12:00:00',new DateTimeZone('America/Bogota')));}catch(RuntimeException $error){$quotaRejected=str_contains($error->getMessage(),'galería supera el máximo');}
subscription_check($quotaRejected,'A page cannot publish more gallery images than its purchased tier includes.');
$pdo->exec("DELETE FROM business_media WHERE business_id=1 AND role='gallery' AND media_asset_id BETWEEN 11 AND 15");
$service->publish(1,new DateTimeImmutable('2026-10-16 12:00:00',new DateTimeZone('America/Bogota')));
$q->execute([$id]);$sub=$q->fetch(PDO::FETCH_ASSOC);
subscription_check($sub['status']==='active'&&$sub['published_at']==='2026-10-16 17:00:00'&&$sub['starts_at']===$sub['published_at']&&$sub['ends_at']==='2027-01-16 17:00:00','A quarterly subscription expires three calendar months after its actual publication date.');
subscription_check($pdo->query('SELECT publication_status FROM businesses WHERE id=1')->fetchColumn()==='published','Publication and benefit activation occur together.');
$pdo->exec("UPDATE business_contacts SET is_public=0 WHERE business_id=1");
$pdo->exec("UPDATE business_subscriptions SET status='paid_pending_publication',reviewed_at='2026-10-14 12:00:00' WHERE id=".(int)$id);
$pdo->exec("UPDATE business_subscriptions SET publish_not_before=NULL WHERE id=".(int)$id);
$contactRejected=false;try{$service->publish(1,new DateTimeImmutable('2026-10-17 12:00:00',new DateTimeZone('America/Bogota')));}catch(RuntimeException $error){$contactRejected=str_contains($error->getMessage(),'contacto directo');}
subscription_check($contactRejected,'Paid direct-contact tiers require a publicly authorized phone or WhatsApp.');
$pdo->exec("UPDATE business_contacts SET is_public=1 WHERE business_id=1");
$pdo->exec("UPDATE subscription_plan_prices SET amount=240000 WHERE plan_code='page_premium' AND billing_frequency='quarterly'");
$pdo->exec("UPDATE business_subscriptions SET plan_code='page_premium',billing_frequency='quarterly',status='paid_pending_publication',quoted_amount=240000,publish_not_before=NULL WHERE id=".(int)$id);
$premiumRejected=false;try{$service->publish(1,new DateTimeImmutable('2026-10-18 12:00:00',new DateTimeZone('America/Bogota')));}catch(RuntimeException $error){$premiumRejected=str_contains($error->getMessage(),'información adicional');}
subscription_check($premiumRejected,'Premium publication requires its two additional content sections.');
$pdo->exec("UPDATE business_translations SET brand_story=CASE locale WHEN 'es' THEN 'Historia del café' WHEN 'en' THEN 'The cafe story' ELSE 'História do café' END,featured_services=CASE locale WHEN 'es' THEN 'Café de origen' WHEN 'en' THEN 'Origin coffee' ELSE 'Café de origem' END WHERE business_id=1");
$service->publish(1,new DateTimeImmutable('2026-10-18 12:00:00',new DateTimeZone('America/Bogota')));
$q->execute([$id]);$sub=$q->fetch(PDO::FETCH_ASSOC);
subscription_check($sub['status']==='active'&&$sub['ends_at']==='2027-01-18 17:00:00','Premium renews from the actual publication date after its extra content is ready.');
$pdo->exec("UPDATE subscription_plan_prices SET amount=NULL WHERE plan_code='page_premium' AND billing_frequency='quarterly'");
$badPayment=false;try{$service->save(['business_id'=>1,'plan_code'=>'page_premium','billing_frequency'=>'quarterly','payment_reviewed'=>'1']);}catch(RuntimeException $error){$badPayment=str_contains($error->getMessage(),'tarifa definida');}
subscription_check($badPayment,'A payment cannot be confirmed until its plan price exists.');
echo "OK: tier photo quotas, configured-price snapshot, scheduling gate, actual-publication start and calendar expiry.\n";

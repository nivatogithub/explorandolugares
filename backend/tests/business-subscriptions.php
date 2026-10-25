<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessSubscriptionAdminService.php';
require dirname(__DIR__).'/app/BusinessPaymentEvidenceService.php';
function subscription_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function throttle(string $kind,int $limit,int $seconds,string $extra=''):bool{return true;}
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE businesses(id INTEGER PRIMARY KEY,address_text TEXT,latitude NUMERIC,longitude NUMERIC,information_status TEXT,operation_status TEXT,publication_status TEXT,updated_at TEXT,record_origin TEXT,location_map_url TEXT,location_confirmed INTEGER);
CREATE TABLE subscription_plans(code TEXT PRIMARY KEY,display_order INTEGER,is_active INTEGER,max_gallery_photos INTEGER,has_business_page INTEGER,max_summary_chars INTEGER,max_description_chars INTEGER,max_brand_story_chars INTEGER,max_featured_services_chars INTEGER);
CREATE TABLE subscription_plan_translations(plan_code TEXT,locale TEXT,name TEXT,summary TEXT);
CREATE TABLE subscription_plan_prices(plan_code TEXT,billing_frequency TEXT,amount NUMERIC,currency TEXT);
CREATE TABLE subscription_plan_features(plan_code TEXT,feature_code TEXT,included INTEGER);
CREATE TABLE subscription_features(code TEXT PRIMARY KEY,display_order INTEGER);
CREATE TABLE subscription_feature_translations(feature_code TEXT,locale TEXT,name TEXT,description TEXT);
CREATE TABLE business_subscriptions(id INTEGER PRIMARY KEY AUTOINCREMENT,business_id INTEGER,plan_code TEXT,billing_frequency TEXT,status TEXT,starts_at TEXT,ends_at TEXT,reviewed_at TEXT,publish_not_before TEXT,quoted_amount NUMERIC,currency TEXT,published_at TEXT,renewal_of_subscription_id INTEGER NULL UNIQUE REFERENCES business_subscriptions(id) ON DELETE SET NULL,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE business_media(business_id INTEGER,media_asset_id INTEGER,display_order INTEGER,role TEXT);
CREATE TABLE business_translations(business_id INTEGER,locale TEXT,display_name TEXT,summary TEXT,description TEXT,brand_story TEXT,featured_services TEXT);
CREATE TABLE business_contacts(business_id INTEGER,channel TEXT,contact_value TEXT,is_public INTEGER);
CREATE TABLE accounts(id INTEGER PRIMARY KEY,account_type TEXT,status TEXT);
CREATE TABLE business_account_memberships(account_id INTEGER,business_id INTEGER,role TEXT,status TEXT);
CREATE TABLE business_account_applications(id INTEGER PRIMARY KEY,account_id INTEGER,business_id INTEGER,status TEXT,business_details_json TEXT);
CREATE TABLE business_subscription_payment_evidence(id INTEGER PRIMARY KEY AUTOINCREMENT,subscription_id INTEGER,account_id INTEGER,storage_name TEXT,original_name TEXT,mime_type TEXT,file_size INTEGER,payment_reference TEXT,status TEXT,admin_note TEXT,customer_message TEXT,reviewed_by TEXT,reviewed_at TEXT,submitted_at TEXT DEFAULT CURRENT_TIMESTAMP);');
$pdo->exec(file_get_contents(__DIR__.'/../database/migrations/044-subscription-changes.sqlite.sql'));
$pdo->exec(file_get_contents(__DIR__.'/../database/migrations/040-payment-transaction-details.sqlite.sql'));
$pdo->exec(file_get_contents(__DIR__.'/../database/migrations/041-experience-operator-addon.sqlite.sql'));
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
subscription_check($plans['featured']['text_limits']===['summary'=>100,'description'=>0,'brand_story'=>0,'featured_services'=>0]&&$plans['page']['text_limits']['summary']===300&&$plans['page_premium']['text_limits']===['summary'=>600,'description'=>0,'brand_story'=>0,'featured_services'=>0],'One description grows 100/300/600, without legacy allowances or extra text sections.');
subscription_check(!isset($plans['basic'])&&count($plans)===4&&in_array('extended_description',$plans['featured']['features'],true),'New choices offer Free, Featured, Page and Premium; legacy Basic is preserved only in storage.');
subscription_check(str_contains(implode(' ',$plans['free']['requirements']),'NIT o RNT'),'Free retains the identification and private support requirement.');
subscription_check(str_contains(implode(' ',$plans['page']['requirements']),'portada, página propia y correo')&&!str_contains(implode(' ',$plans['page']['requirements']),'horarios'),'Page adds cover, page and email without retired operational fields.');
subscription_check(str_contains(implode(' ',$plans['page_premium']['requirements']),'hasta cinco productos')&&!str_contains(implode(' ',$plans['page_premium']['requirements']),'historia o propuesta'),'Premium adds five products, not mandatory extra story blocks.');
$pdo->exec("UPDATE subscription_plan_prices SET currency='CO' WHERE plan_code='page' AND billing_frequency='quarterly'");
$invalidCatalog=$service->options();$invalidCatalogPrices=array_column($invalidCatalog,null,'code');
subscription_check($invalidCatalogPrices['page']['prices']['quarterly']['amount']===null,'A malformed currency makes a catalog price unavailable to the form.');
$badCurrency=false;try{$service->save(['business_id'=>1,'plan_code'=>'page','billing_frequency'=>'quarterly']);}catch(RuntimeException $error){$badCurrency=str_contains($error->getMessage(),'moneda');}
subscription_check($badCurrency,'The admin subscription handler refuses a configured price with an invalid currency.');
$pdo->exec("UPDATE subscription_plan_prices SET currency='COP' WHERE plan_code='page' AND billing_frequency='quarterly'");
$testBase=new DateTimeImmutable('first day of next month',new DateTimeZone('America/Bogota'));
$testDate=static fn(int $days):DateTimeImmutable=>$testBase->modify(($days>=0?'+':'').$days.' days')->setTime(12,0);
$scheduledDay=$testBase->format('Y-m-d');
$id=$service->save(['business_id'=>1,'plan_code'=>'page','billing_frequency'=>'quarterly','publish_not_before'=>$scheduledDay,'payment_reviewed'=>'1']);
$q=$pdo->prepare('SELECT * FROM business_subscriptions WHERE id=?');$q->execute([$id]);$sub=$q->fetch(PDO::FETCH_ASSOC);
subscription_check($sub['status']==='pending'&&$sub['starts_at']===null&&$sub['quoted_amount']==120000&&$sub['publish_not_before']===$scheduledDay,'An admin checkbox cannot mark an unverified subscription paid; the quote and publication date are still stored.');
$pdo->exec("INSERT INTO accounts VALUES(1,'business','active');INSERT INTO business_account_memberships VALUES(1,1,'owner','active');INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,payment_reference,status,admin_note,reviewed_by) VALUES($id,1,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png','soporte.png','image/png',68,'TX-1','pending','','')");$evidenceId=(int)$pdo->lastInsertId();
$paymentStorage=sys_get_temp_dir().'/el-payment-test-'.bin2hex(random_bytes(4));$payments=new BusinessPaymentEvidenceService($pdo,$paymentStorage);
$pdo->prepare('UPDATE business_subscriptions SET quoted_amount=NULL WHERE id=?')->execute([$id]);$invalidQuoteRejected=false;try{$payments->review($evidenceId,'approved','','admin');}catch(RuntimeException $error){$invalidQuoteRejected=str_contains($error->getMessage(),'importe cotizado');}
subscription_check($invalidQuoteRejected&&$pdo->query('SELECT status FROM business_subscription_payment_evidence WHERE id='.$evidenceId)->fetchColumn()==='pending','Payment approval refuses a missing quote and keeps the evidence pending for correction.');
$pdo->prepare('UPDATE business_subscriptions SET quoted_amount=120000 WHERE id=?')->execute([$id]);$pdo->exec('UPDATE business_subscription_payment_evidence SET paid_amount=120000');$payments->review($evidenceId,'approved','','admin');$q->execute([$id]);$sub=$q->fetch(PDO::FETCH_ASSOC);rmdir($paymentStorage);
subscription_check($sub['status']==='paid_pending_publication'&&$sub['reviewed_at']!==null&&$pdo->query('SELECT publication_status FROM businesses WHERE id=1')->fetchColumn()==='draft','Only an administrator-approved payment evidence marks the subscription paid, and it remains unpublished.');
$rejected=false;try{$service->publish(1,$testDate(-1));}catch(RuntimeException $error){$rejected=str_contains($error->getMessage(),'fecha elegida');}
subscription_check($rejected,'A scheduled listing cannot publish early.');
$pdo->exec("INSERT INTO business_media VALUES(1,11,2,'gallery'),(1,12,3,'gallery'),(1,13,4,'gallery'),(1,14,5,'gallery'),(1,15,6,'gallery')");
$quotaRejected=false;try{$service->publish(1,$testDate(0));}catch(RuntimeException $error){$quotaRejected=str_contains($error->getMessage(),'galería supera el máximo');}
subscription_check($quotaRejected,'A page cannot publish more gallery images than its purchased tier includes.');
$pdo->exec("DELETE FROM business_media WHERE business_id=1 AND role='gallery' AND media_asset_id BETWEEN 11 AND 15");
$summaryUpdate=$pdo->prepare('UPDATE business_translations SET summary=? WHERE business_id=1 AND locale="es"');$summaryUpdate->execute([str_repeat('á',301)]);
$limitRejected=false;try{$service->publish(1,$testDate(0));}catch(RuntimeException $error){$limitRejected=str_contains($error->getMessage(),'300 caracteres');}
subscription_check($limitRejected,'Publication must enforce the advertised character limit, including content imported before review.');
subscription_check($pdo->query('SELECT starts_at FROM business_subscriptions WHERE id='.(int)$id)->fetchColumn()===null,'A failed content review must not start the paid term.');
$summaryUpdate->execute([str_repeat('á',300)]);
$service->publish(1,$testDate(0));
$q->execute([$id]);$sub=$q->fetch(PDO::FETCH_ASSOC);
subscription_check($sub['status']==='active'&&$sub['published_at']===$testDate(0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')&&$sub['starts_at']===$sub['published_at']&&$sub['ends_at']===$testDate(0)->modify('+3 months')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'A quarterly subscription expires three calendar months after its actual publication date.');
subscription_check($pdo->query('SELECT publication_status FROM businesses WHERE id=1')->fetchColumn()==='published','Publication and benefit activation occur together.');
$pdo->exec("UPDATE business_contacts SET is_public=0 WHERE business_id=1");
$pdo->exec("UPDATE business_subscriptions SET status='paid_pending_publication',reviewed_at='2026-10-14 12:00:00' WHERE id=".(int)$id);
$pdo->exec("UPDATE business_subscriptions SET publish_not_before=NULL WHERE id=".(int)$id);
$contactRejected=false;try{$service->publish(1,$testDate(1));}catch(RuntimeException $error){$contactRejected=str_contains($error->getMessage(),'contacto directo');}
subscription_check($contactRejected,'Paid direct-contact tiers require a publicly authorized phone or WhatsApp.');
$pdo->exec("UPDATE business_contacts SET is_public=1 WHERE business_id=1");
$pdo->exec("UPDATE subscription_plan_prices SET amount=240000 WHERE plan_code='page_premium' AND billing_frequency='quarterly'");
$pdo->exec("UPDATE business_subscriptions SET plan_code='page_premium',billing_frequency='quarterly',status='paid_pending_publication',quoted_amount=240000,publish_not_before=NULL WHERE id=".(int)$id);
$service->validateContent(1,'page_premium');
subscription_check(true,'Premium accepts a page without an optional history or general services narrative.');
$pdo->exec("UPDATE business_translations SET brand_story='Historia opcional' WHERE business_id=1 AND locale='es'");
$service->validateContent(1,'page_premium');
subscription_check(true,'Legacy history is not public content and does not block publication.');
$pdo->exec("UPDATE business_translations SET brand_story=CASE locale WHEN 'es' THEN 'Historia del café' WHEN 'en' THEN 'The cafe story' ELSE 'História do café' END,featured_services=CASE locale WHEN 'es' THEN 'Café de origen' WHEN 'en' THEN 'Origin coffee' ELSE 'Café de origem' END WHERE business_id=1");
$service->publish(1,$testDate(2));
$q->execute([$id]);$sub=$q->fetch(PDO::FETCH_ASSOC);
subscription_check($sub['status']==='active'&&$sub['ends_at']===$testDate(2)->modify('+3 months')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'Premium renews from the actual publication date after its extra content is ready.');
$previousEnd=$sub['ends_at'];
$renewalService=new BusinessSubscriptionAdminService($pdo,$testDate(4));
$renewalId=$renewalService->save(['business_id'=>1,'plan_code'=>'page','billing_frequency'=>'quarterly']);
$pdo->prepare('UPDATE business_subscriptions SET status="paid_pending_publication",reviewed_at="2026-10-19 12:00:00" WHERE id=?')->execute([$renewalId]);
$early=false;try{$service->publish(1,$testDate(4));}catch(RuntimeException $e){$early=str_contains($e->getMessage(),'vencimiento');}subscription_check($early,'A lower tier cannot activate before the paid term ends.');
$service->publish(1,new DateTimeImmutable($previousEnd,new DateTimeZone('UTC')));
$q->execute([$id]);$previous=$q->fetch(PDO::FETCH_ASSOC);$q->execute([$renewalId]);$renewed=$q->fetch(PDO::FETCH_ASSOC);
subscription_check($previous['status']==='expired'&&$previous['ends_at']===$previousEnd&&(int)$renewed['renewal_of_subscription_id']===(int)$id,'Publishing a new paid term preserves the prior expiry and links the renewal to its predecessor.');
$renewalWindowStart=$testDate(2)->modify('+3 months')->setTime(0,0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');$renewalWindowEnd=$testDate(3)->modify('+3 months')->setTime(0,0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$renewals=$pdo->query("SELECT COUNT(DISTINCT due.business_id) FROM business_subscriptions due WHERE due.plan_code<>'free' AND due.status IN ('active','expired') AND due.starts_at IS NOT NULL AND due.ends_at>='$renewalWindowStart' AND due.ends_at<'$renewalWindowEnd' AND EXISTS (SELECT 1 FROM business_subscriptions successor WHERE successor.renewal_of_subscription_id=due.id AND successor.starts_at<=due.ends_at AND successor.plan_code<>'free')")->fetchColumn();
subscription_check((int)$renewals===1,'A renewal published before the previous term expires is counted for the due business.');
$pdo->exec("UPDATE subscription_plan_prices SET amount=NULL WHERE plan_code='page_premium' AND billing_frequency='quarterly'");
$badPayment=false;try{$service->save(['business_id'=>1,'plan_code'=>'page_premium','billing_frequency'=>'quarterly','payment_reviewed'=>'1']);}catch(RuntimeException $error){$badPayment=str_contains($error->getMessage(),'tarifa definida');}
subscription_check($badPayment,'A paid subscription cannot be selected until its plan price exists.');
$before=(int)$pdo->query('SELECT COUNT(*) FROM business_subscriptions')->fetchColumn();
foreach(['basic','forged'] as $badPlan){$rejected=false;try{$payments->submit(1,1,$badPlan,'monthly','',[]);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'vigente');}subscription_check($rejected,'Forged or legacy plan cannot create a new payment.');}
foreach(['monthly','quarterly','semiannual','annual'] as $period){$pdo->prepare('DELETE FROM subscription_plan_prices WHERE plan_code="featured" AND billing_frequency=?')->execute([$period]);$rejected=false;try{$payments->submit(1,1,'featured',$period,'',[]);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'tarifa válida');}subscription_check($rejected,'Every supported period rejects payment without a configured price.');}
$rejected=false;try{$service->save(['business_id'=>1,'plan_code'=>'basic','billing_frequency'=>'monthly']);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'vigente');}subscription_check($rejected,'Admin cannot create new Basic subscription.');
subscription_check((int)$pdo->query('SELECT COUNT(*) FROM business_subscriptions')->fetchColumn()===$before,'Rejected payment/selection does not mutate subscription history.');
subscription_check((bool)$pdo->query('SELECT code FROM subscription_plans WHERE code="basic"')->fetchColumn(),'Legacy Basic catalog remains present.');
// Failed replacement must preserve an existing request, including inside a caller transaction.
$pending=$service->save(['business_id'=>1,'plan_code'=>'free']);
$pdo->exec("CREATE TRIGGER reject_subscription_insert BEFORE INSERT ON business_subscriptions BEGIN SELECT RAISE(ABORT,'injected failure');END");
foreach([false,true] as $nested){
 if($nested)$pdo->beginTransaction();
 $failed=false;try{$service->save(['business_id'=>1,'plan_code'=>'free']);}catch(PDOException){$failed=true;}
 subscription_check($failed,'Injected INSERT failure reached');
 subscription_check($pdo->query('SELECT status FROM business_subscriptions WHERE id='.$pending)->fetchColumn()==='pending_publication','Failed replacement never cancels previous request');
 if($nested){subscription_check($pdo->inTransaction(),'Caller transaction remains usable');$pdo->commit();}
}
$pdo->exec('DROP TRIGGER reject_subscription_insert');
$beforePublication=$pdo->query('SELECT publication_status FROM businesses WHERE id=1')->fetchColumn();
try{$service->publish(1,null,static function():void{throw new RuntimeException('stale-review');});throw new RuntimeException('Missing version guard');}catch(RuntimeException $e){subscription_check($e->getMessage()==='stale-review','Version precondition rejects stale review');}
subscription_check($pdo->query('SELECT publication_status FROM businesses WHERE id=1')->fetchColumn()===$beforePublication,'Rejected review leaves publication unchanged');
subscription_check(!$pdo->inTransaction(),'Rejected review rolls back transaction');
@rmdir($paymentStorage);
echo "OK: tier photo quotas, configured-price snapshot, scheduling gate, actual-publication start and calendar expiry.\n";

<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessPromotions.php';
require __DIR__.'/../app/BusinessSelfOnboarding.php';
require __DIR__.'/../app/BusinessEditorialPackage.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$p->exec('PRAGMA foreign_keys=ON');
$p->exec(file_get_contents(__DIR__.'/../database/sqlite.sql'));$p->exec(file_get_contents(__DIR__.'/../database/destinations.sqlite.sql'));
$migrations=glob(__DIR__.'/../database/migrations/*.sqlite.sql');sort($migrations);foreach($migrations as $file)if((int)basename($file)>=8)$p->exec(file_get_contents($file));
$p->exec(file_get_contents(__DIR__.'/../database/migrations/056-business-promotions.sqlite.sql'));
$p->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"test","published");INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Lugar ficticio","","");INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1);INSERT INTO service_category_translations(category_code,locale,label) VALUES("hospedaje","es","Hospedaje");INSERT INTO accounts(id,public_id,account_type,display_name,email,status,email_verified_at) VALUES(1,"test-account","traveler","Persona ficticia","persona@example.invalid","active",CURRENT_TIMESTAMP)');
$s=new BusinessPromotions($p);$checks=0;
function ck(bool $value,string $message):void{global $checks;if(!$value)throw new RuntimeException($message);$checks++;}
function rejects(callable $fn,string $message):void{try{$fn();}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;ck(true,$message);return;}throw new RuntimeException($message);}
function settings(string $code,int $percentage=100):array{return ['code'=>$code,'percentage'=>$percentage,'plans'=>['page'],'periods'=>['monthly'],'destinations'=>[1],'enabled'=>1];}
$s->save(settings('pagina1mes'),'Administrador ficticio');$quote=$s->quote(' pagina1mes ','page','monthly',1);
ck($quote['code']==='PAGINA1MES'&&$quote['due_cents']===0&&$quote['discount_cents']===$quote['list_cents'],'100% normalized and exact zero');
foreach([['free','monthly'],['featured','monthly'],['page_premium','monthly'],['page','annual']] as [$plan,$period])rejects(fn()=>$s->quote('PAGINA1MES',$plan,$period,1),'wrong plan/period');
rejects(fn()=>$s->save(settings('BAD',101),'admin'),'over 100 rejected');rejects(fn()=>$s->save(settings('BAD',0),'admin'),'zero rejected');rejects(fn()=>$s->save(settings('BAD'),' '),'admin required');
$s->save([...settings('VENCIDO'),'expires_on'=>'2020-01-01'],'admin');rejects(fn()=>$s->quote('VENCIDO','page','monthly',1),'expired rejected');
$s->save([...settings('APAGADO'),'enabled'=>0],'admin');rejects(fn()=>$s->quote('APAGADO','page','monthly',1),'disabled rejected');
$q=$s->quote('PAGINA1MES','page','monthly',1,10000);ck($q['due_cents']===10000,'addon never free');
$s->save(settings('MITAD',50),'admin');$half=$s->quote('MITAD','page','monthly',1);ck($half['due_cents']===(int)round($half['list_cents']/2),'partial discount');
function onboarding(PDO $p,BusinessPromotions $s,string $code,string $name,?int $existing=null):array{
    $offer=$s->quote($code,'page','monthly',1);
    $details=['onboarding_policy'=>BusinessSelfOnboarding::POLICY,'responsibility_declared'=>true,'categories'=>['hospedaje'],'location'=>['address_text'=>'Calle de prueba 123','location_reference'=>'','latitude'=>5.791,'longitude'=>-75.786,'location_source'=>'map','location_map_url'=>'https://www.google.com/maps/search/?api=1&query=5.791,-75.786','location_confirmed'=>1,'opening_hours'=>'','price_range'=>'','capacity_count'=>null,'menu_url'=>'','reservations_url'=>''],'text'=>['summary'=>'Negocio ficticio'],'requested_subscription'=>['plan_code'=>'page','billing_frequency'=>'monthly','promotion'=>$offer]];
    $p->prepare('INSERT INTO business_account_applications(account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,consent_version,business_details_json) VALUES(1,?,?,1,"hospedaje",?,"owner","test",?)')->execute([$existing?'claim_existing':'request_new',$existing,$name,json_encode($details)]);$application=(int)$p->lastInsertId();
    $p->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,"business_registration","test.pdf","Ficticio.pdf","application/pdf",100)')->execute([$application]);
    $p->beginTransaction();try{BusinessSelfOnboarding::complete($p,$application);$p->commit();}catch(Throwable $e){$p->rollBack();throw $e;}
    $id=(int)$p->query('SELECT business_id FROM business_account_applications WHERE id='.$application)->fetchColumn();
    $sub=$p->query('SELECT * FROM business_subscriptions WHERE business_id='.$id.' ORDER BY id DESC LIMIT 1')->fetch();return [$id,$sub,$application];
}
[$id,$sub,$app]=onboarding($p,$s,'PAGINA1MES','Hotel Promoción');
ck($sub['quoted_amount']==0&&$sub['status']==='paid_pending_publication'&&$sub['starts_at']===null&&$sub['ends_at']===null,'zero bypasses only payment, no term started');
ck($s->covered((int)$sub['id']),'audited coverage');
ck((int)$p->query('SELECT COUNT(*) FROM business_subscription_payment_evidence')->fetchColumn()===0,'no invented receipts');
ck($p->query('SELECT publication_status FROM businesses WHERE id='.$id)->fetchColumn()==='draft','not auto published');
$ready=(new BusinessEditorialPackage($p,''))->readiness($id);ck($ready['payment_ready']&&!$ready['title_ready']&&!$ready['ready'],'ownership still blocks export');
$p->prepare('UPDATE business_account_application_evidence SET verified_at=CURRENT_TIMESTAMP WHERE application_id=?')->execute([$app]);
ck((new BusinessEditorialPackage($p,''))->readiness($id)['ready'],'validated title opens editorial without payment');
$p->beginTransaction();BusinessSelfOnboarding::complete($p,$app);$s->redeem((int)$sub['id'],$id,1,'PAGINA1MES');$p->commit();ck((int)$p->query('SELECT COUNT(*) FROM business_promotion_redemptions')->fetchColumn()===1,'retry idempotent');
$s->save([...settings('PAGINA1MES',20),'enabled'=>0],'admin');ck($s->covered((int)$sub['id']),'editing/disabling does not revoke redeemed conditions');
[$id2,$sub2]=onboarding($p,$s,'MITAD','Casa Descuento Parcial');
$q=(new SubscriptionChangeService($p))->quote($id2,'page','monthly');$discounted=$s->checkout($sub2,$q);
ck($discounted['due_cents']===$half['due_cents']&&!$s->covered((int)$sub2['id']),'partial discount still owes payment');
$s->save([...settings('MITAD',80),'enabled'=>0],'admin');ck($s->checkout($sub2,$q)===$discounted,'redeemed net survives rule edits');
$p->exec('UPDATE subscription_plan_prices SET amount=123456 WHERE plan_code="page" AND billing_frequency="monthly"');
ck($s->checkout($sub2,(new SubscriptionChangeService($p))->quote($id2,'page','monthly'))['due_cents']===$half['due_cents'],'redeemed net survives price edits');
$p->prepare('UPDATE business_subscriptions SET quoted_amount=0 WHERE id=?')->execute([$sub2['id']]);rejects(fn()=>$s->checkout([...$sub2,'quoted_amount'=>0],$q),'tampered net rejected');ck(!$s->covered((int)$sub2['id']),'zero without 100% snapshot not covered');
$s->save([...settings('UNUSO'),'max_uses'=>1],'admin');onboarding($p,$s,'UNUSO','Casa Único Uso');rejects(fn()=>$s->quote('UNUSO','page','monthly',1),'usage limit enforced');
$s->save(settings('RECLAMAR'),'admin');$p->exec('INSERT INTO businesses(id,public_id,destination_id,category_code,slug) VALUES(90,"claim-test",1,"hospedaje","claim-test");INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(90,"es","Hotel por reclamar","","");INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(90,"free","pending_publication")');
[$claim,$claimSub]=onboarding($p,$s,'RECLAMAR','Hotel por reclamar',90);ck($claim===90&&$claimSub['plan_code']==='page'&&$s->covered((int)$claimSub['id']),'claim retains business identity and coupon upgrades previous free entry');
ck(count($s->redemptions())===4&&count($s->listing())===6,'administrator sees redemption and code lists');
rejects(fn()=>$s->save([...settings('SINLUGAR'),'destinations'=>[]],'admin'),'empty scope forbidden');
rejects(fn()=>$s->save([...settings('INEXISTENTE'),'destinations'=>[9999]],'admin'),'unknown place forbidden');
$p->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(2,"otro","published"),(3,"nuevo","published");INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(2,"es","Otro lugar","","")');
$s->save([...settings('VARIOS'),'destinations'=>[1,2]],'admin');
ck($s->quote('VARIOS','page','monthly',1)['destination_id']===1&&$s->quote('VARIOS','page','monthly',2)['destination_id']===2,'one or many explicit places');
rejects(fn()=>$s->quote('VARIOS','page','monthly',3),'unlisted place forbidden');
rejects(fn()=>$s->quote('VARIOS','page','monthly',0),'missing place fails closed');
$s->save([...settings('VARIOS'),'destinations'=>[2]],'admin');
$p->exec(file_get_contents(__DIR__.'/../database/migrations/057-business-promotion-places.sqlite.sql'));
$p->exec(file_get_contents(__DIR__.'/../database/migrations/057-business-promotion-places.sqlite.sql'));
rejects(fn()=>$s->quote('VARIOS','page','monthly',1),'migration rerun does not broaden edited scope');
rejects(fn()=>$s->quote('VARIOS','page','monthly',3),'newly published places not automatic');
$s->save([...settings('PAGINA1MES'),'destinations'=>[2],'enabled'=>0],'admin');
ck($s->covered((int)$sub['id']),'removing original place or disabling keeps redeemed entitlement');
ck($p->query('SELECT destination_id FROM business_promotion_redemption_places WHERE subscription_id='.(int)$sub['id'])->fetchColumn()===1,'original redeemed place preserved');
$deleted=false;try{$p->exec('DELETE FROM business_promotion_codes WHERE code="PAGINA1MES"');$deleted=true;}catch(PDOException $e){if($e->getCode()!=='23000')throw $e;}
ck(!$deleted&&$s->covered((int)$sub['id']),'database forbids deleting a used coupon');
echo "OK $checks promotion checks: rules, immutable snapshots, idempotency, 100% without fake payment, partial checkout, title gate, claim and migrations twice.\n";

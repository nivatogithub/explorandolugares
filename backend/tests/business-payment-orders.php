<?php
declare(strict_types=1);
require_once __DIR__.'/../app/BusinessPaymentOrderService.php';
require_once __DIR__.'/../app/BusinessWorkspace.php';
require_once __DIR__.'/../app/BusinessEditorialPackage.php';
if(!function_exists('throttle')){function throttle(...$args):bool{return true;}}
final class OrderTestStorage implements PaymentEvidenceStorage
{
    public int $stores=0;public int $discarded=0;
    public function __construct(public string $root){if(!is_dir($root))mkdir($root,0700,true);}
    public function store(array $upload):array{$this->stores++;$name=bin2hex(random_bytes(24)).'.pdf';$body="%PDF-1.4\nSynthetic test proof\n%%EOF";file_put_contents($this->root.'/'.$name,$body);return ['storage_name'=>$name,'path'=>$this->root.'/'.$name,'original_name'=>'Prueba.pdf','mime_type'=>'application/pdf','file_size'=>strlen($body)];}
    public function discard(array $file):void{$this->discarded++;unlink($file['path']);}
}
function order_test_suite(PDO $p,string $root):array
{
    $checks=0;$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
    $reject=static function(callable $work,string $label)use($check){try{$work();}catch(RuntimeException $e){if($e instanceof PDOException)throw $e;$check(true,$label);return;}throw new RuntimeException('Accepted: '.$label);};
    $put=static function(string $table,array $row,string $key)use($p){$q=$p->prepare('SELECT 1 FROM '.$table.' WHERE '.$key.'=?');$q->execute([$row[$key]]);if(!$q->fetchColumn()){$names=array_keys($row);$p->prepare('INSERT INTO '.$table.' ('.implode(',',$names).') VALUES('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));}};
    foreach([1,2] as $id)$put('accounts',['id'=>$id,'public_id'=>'order-test-account-'.$id,'account_type'=>'traveler','display_name'=>'Persona de prueba '.$id,'password_hash'=>password_hash('Synthetic-order-test-123!',PASSWORD_DEFAULT),'email'=>'order'.$id.'@example.invalid','status'=>'active','email_verified_at'=>'2026-01-01 00:00:00'],'id');
    $put('destinations',['id'=>1,'slug'=>'order-fixture','publication_status'=>'published'],'id');
    $p->exec('INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Lugar de prueba","","")');
    foreach(['hospedaje','comercio-local','experiencias'] as $category){$put('service_categories',['code'=>$category,'is_active'=>1],'code');$q=$p->prepare('SELECT 1 FROM service_category_translations WHERE category_code=? AND locale="es"');$q->execute([$category]);if(!$q->fetchColumn())$p->prepare('INSERT INTO service_category_translations(category_code,locale,label) VALUES(?,"es",?)')->execute([$category,$category]);}
    $experience=$p->prepare('INSERT INTO points_of_interest(destination_id,slug,poi_type,publication_status) VALUES(1,?,"experience","published")');$experience->execute(['order-experience']);$experienceId=(int)$p->lastInsertId();
    $p->prepare('INSERT INTO point_of_interest_translations(point_of_interest_id,locale,name,description) VALUES(?,"es","Experiencia de prueba","")')->execute([$experienceId]);
    $p->exec('UPDATE subscription_plan_prices SET currency="COP"');
    $storage=new OrderTestStorage($root);$service=new BusinessPaymentOrderService($p,$storage);$read=new BusinessPaymentOrderSelector($p);
    $business=static function(string $plan='page',string $period='monthly',string $category='hospedaje',int $owner=1)use($p):array{
        $key='order-'.bin2hex(random_bytes(6));$p->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug,publication_status) VALUES(?,1,?,?,"draft")')->execute([$key,$category,$key]);$id=(int)$p->lastInsertId();
        $p->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")')->execute([$id,'Negocio <seguro> '.$key]);
        $p->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")')->execute([$owner,$id]);
        $price=(new BusinessSubscriptionChoice($p))->quote($plan,$period);$p->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,quoted_amount,currency) VALUES(?,?,?,"pending",?,"COP")')->execute([$id,$plan,$period,$price['quoted_amount']]);$p->prepare('INSERT INTO business_editorial_versions(business_id,account_id,plan_code,base_hash,payload_json,payload_hash) VALUES(?,?,?,"fixture","{}","fixture")')->execute([$id,$owner,$plan]);return ['business_id'=>$id,'plan_code'=>$plan,'billing_frequency'=>$period,'experience_id'=>0];
    };
    $key=static fn()=>bin2hex(random_bytes(24));$details=static fn(array $o)=>['transaction_date'=>date('Y-m-d'),'paid_amount'=>SubscriptionChangeService::money((int)$o['total_cents']),'payment_method'=>'transfer','source_bank'=>'Banco ficticio'];
    $a=$business();$b=$business('featured','annual','comercio-local');$c=$business('page_premium','quarterly','experiencias');$c['experience_id']=$experienceId;
    $request=$key();$order=$service->create(1,[$a,$b,$c],$request);$items=$read->items((int)$order['id']);
    $check(count($items)===3&&array_sum(array_column($items,'due_cents'))==(int)$order['total_cents'],'Three mixed plans, periods and addon total exactly');
    $check($service->create(1,[$c,$b,$a],$request)['id']===$order['id'],'Prepare retries, including reordered choices, are idempotent');
    $reject(fn()=>$service->create(1,[$a],$request),'request key cannot change selection');$reject(fn()=>$service->create(1,[$a,$a],$key()),'duplicate business rejected');
    $reject(fn()=>$service->create(1,[$a],$key()),'overlapping order rejected');$reject(fn()=>$service->get(2,$order['public_id']),'foreign order private');
    $reject(fn()=>$service->submit(2,$order['public_id'],'TEST',[], $details($order)),'foreign submission');
    $reject(fn()=>$service->submit(1,$order['public_id'],'TEST',[],[...$details($order),'paid_amount'=>'1']),'insufficient payment');$check($storage->stores===0,'Rejected amount stores no receipt');
    $beforeTerms=$p->query('SELECT id,starts_at,ends_at FROM business_subscriptions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $sent=$service->submit(1,$order['public_id'],'TEST-ONE-PAYMENT',[],$details($order));$check($sent['status']==='under_review'&&$storage->stores===1,'One proof for entire order');
    $service->submit(1,$order['public_id'],'DUPLICATE',[],$details($order));$check($storage->stores===1,'Submit retry does not store again');
    $receipt=$read->evidence((int)$order['id']);$check((int)$p->query('SELECT COUNT(*) FROM business_subscription_payment_evidence')->fetchColumn()===1,'One evidence row, not three copies');
    $history=(new BusinessPaymentEvidenceService($p,$root))->history(1);$check(count($history)===1&&count($history[0]['order_items'])===3,'History displays total once with allocations');
    $rows=(new BusinessWorkspace($p))->rows(1);foreach($rows as $row)if(in_array((int)$row['id'],array_column([$a,$b,$c],'business_id'),true))$check($row['state']['code']==='validation','Every business sees pending validation');
    $service->review((int)$receipt['id'],'rejected','Documento de prueba ilegible','tester','Adjunta un comprobante legible.');
    $check($read->byId((int)$order['id'])['status']==='rejected','Rejection applies to order');
    $service->submit(1,$order['public_id'],'TEST-ONE-PAYMENT',[],$details($order));$newReceipt=$read->evidence((int)$order['id']);$check($newReceipt['id']!==$receipt['id']&&$storage->stores===2,'Correction keeps immutable rejected proof and stores one new proof');
    $reject(fn()=>$service->review((int)$receipt['id'],'approved','','tester'),'old rejected proof cannot approve new attempt');
    (new BusinessPaymentEvidenceService($p,$root))->review((int)$newReceipt['id'],'approved','','tester');
    foreach($read->items((int)$order['id']) as $item){$sub=$read->subscription((int)$item['subscription_id']);$check($sub['status']==='paid_pending_publication'&&$sub['starts_at']===null&&$sub['ends_at']===null,'All allocations approved without publication or term start');$ready=(new BusinessEditorialPackage($p,''))->readiness((int)$item['business_id']);$check($ready['payment_ready']&&!$ready['title_ready'],'Non-anchor businesses can continue editorial but ownership remains required');}
    $service->review((int)$newReceipt['id'],'approved','','tester');$check($read->byId((int)$order['id'])['status']==='approved','Approval retry is idempotent');
    foreach($beforeTerms as $row){$sub=$read->subscription((int)$row['id']);$check($sub['starts_at']===$row['starts_at']&&$sub['ends_at']===$row['ends_at'],'Original terms preserved');}
    $x=$business();$single=$service->create(1,[$x],$key());$check(count($read->items((int)$single['id']))===1,'One-business order uses same flow');$service->cancel(1,$single['public_id']);$check(!$read->openForBusiness($x['business_id']),'Cancellation releases selection without deleting history');
    $foreign=$business('page','monthly','hospedaje',2);$reject(fn()=>$service->create(1,[$foreign],$key()),'foreign business excluded');
    $plain=$business();$invalid=$plain;$invalid['experience_id']=$experienceId;$reject(fn()=>$service->create(1,[$invalid],$key()),'ineligible operator excluded');
    $dup=$plain;$dup['plan_code']=[];$reject(fn()=>$service->create(1,[$dup],$key()),'malformed plan rejected');
    // A late failure must undo all newly prepared subscriptions and discard the stored proof.
    $f1=$business();$f2=$business('featured');$fail=$service->create(1,[$f1,$f2],$key());$before=(int)$p->query('SELECT COUNT(*) FROM business_subscriptions')->fetchColumn();
    $mysql=$p->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    $p->exec($mysql?"CREATE TRIGGER reject_order_evidence BEFORE INSERT ON business_payment_order_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic late failure'":"CREATE TRIGGER reject_order_evidence BEFORE INSERT ON business_payment_order_evidence BEGIN SELECT RAISE(ABORT,'Synthetic late failure');END");
    $failed=false;try{$service->submit(1,$fail['public_id'],'FAIL',[],$details($fail));}catch(PDOException){$failed=true;}$p->exec('DROP TRIGGER reject_order_evidence');
    $check($failed&&!$p->inTransaction()&&(int)$p->query('SELECT COUNT(*) FROM business_subscriptions')->fetchColumn()===$before&&$read->evidence((int)$fail['id'])===null&&$storage->discarded===1,'Late failure rolls back all allocations and cleans proof');
    $service->submit(1,$fail['public_id'],'RETRY',[],$details($fail));$e=$read->evidence((int)$fail['id']);$targets=$read->items((int)$fail['id']);$p->prepare('UPDATE business_subscriptions SET quoted_amount=quoted_amount+1 WHERE id=?')->execute([$targets[1]['subscription_id']]);
    $reject(fn()=>$service->review((int)$e['id'],'approved','','tester'),'changed second line rejects entire confirmation');$check($read->subscription((int)$targets[0]['subscription_id'])['status']==='pending','Earlier line approval rolled back');
    // Active subscriptions renew independently and keep every remaining paid day.
    $renew=$business();$sub=$read->latestSubscription($renew['business_id']);$p->prepare('UPDATE business_subscriptions SET status="active",starts_at="2026-01-01 00:00:00",ends_at="2035-01-01 00:00:00",reviewed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$sub['id']]);
    $renewOrder=$service->create(1,[$renew],$key());$check($read->items((int)$renewOrder['id'])[0]['quote']['kind']==='renewal','Renewal starts at individual current expiry');$service->submit(1,$renewOrder['public_id'],'RENEW',[],$details($renewOrder));$service->review((int)$read->evidence((int)$renewOrder['id'])['id'],'approved','','tester');$check($read->subscription((int)$sub['id'])['ends_at']==='2035-01-01 00:00:00','Renewal never shortens current paid term');
    $coupon=$business();$promotions=new BusinessPromotions($p);$promotions->save(['code'=>'ORDERHALF','percentage'=>50,'plans'=>['page'],'periods'=>['monthly'],'destinations'=>[1],'enabled'=>1],'tester');
    $couponSub=$read->latestSubscription($coupon['business_id']);$offer=$promotions->applyAtPayment(1,$coupon['business_id'],(int)$couponSub['id'],'ORDERHALF');$discountOrder=$service->create(1,[$coupon],$key());
    $check((int)$discountOrder['total_cents']===(int)$offer['due_cents'],'Partial coupon belongs only to its item and preserves captured net');
    $service->submit(1,$discountOrder['public_id'],'DISCOUNT',[],$details($discountOrder));$check((int)$read->items((int)$discountOrder['id'])[0]['subscription_id']===(int)$couponSub['id'],'Coupon redemption target is preserved, not recreated');
    $zero=$business();$promotions->save(['code'=>'ORDERFREE','percentage'=>100,'plans'=>['page'],'periods'=>['monthly'],'destinations'=>[1],'enabled'=>1],'tester');$zeroSub=$read->latestSubscription($zero['business_id']);$promotions->applyAtPayment(1,$zero['business_id'],(int)$zeroSub['id'],'ORDERFREE');$reject(fn()=>$service->create(1,[$zero],$key()),'Covered business never creates fake payment');
    $frozen=$business();$frozenOrder=$service->create(1,[$frozen],$key());$p->exec('UPDATE subscription_plan_prices SET amount=amount+100 WHERE plan_code="page" AND billing_frequency="monthly"');$service->submit(1,$frozenOrder['public_id'],'FROZEN',[],$details($frozenOrder));$check((int)$read->byId((int)$frozenOrder['id'])['total_cents']===(int)$frozenOrder['total_cents'],'Issued order honors its immutable total after catalogue changes');$p->exec('UPDATE subscription_plan_prices SET amount=amount-100 WHERE plan_code="page" AND billing_frequency="monthly"');
    $httpChoices=[$business(),$business('featured','annual','comercio-local'),$business('page_premium','quarterly','experiencias')];$httpChoices[2]['experience_id']=$experienceId;
    return ['http_choices'=>$httpChoices,'checks'=>$checks,'orders'=>(int)$p->query('SELECT COUNT(*) FROM business_payment_orders')->fetchColumn(),'one_proof_per_attempt'=>true,'grouped_and_single'=>true,'rollback'=>true,'renewal_days_preserved'=>true];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../database/sqlite.sql'));$p->exec(file_get_contents(__DIR__.'/../database/destinations.sqlite.sql'));$p->exec(file_get_contents(__DIR__.'/../database/migrations/006-poi-experience-links.sqlite.sql'));foreach(glob(__DIR__.'/../database/migrations/*.sqlite.sql') as $migration)if((int)basename($migration)>=8)$p->exec(file_get_contents($migration));$p->exec(file_get_contents(__DIR__.'/../database/migrations/061-business-payment-orders.sqlite.sql'));
    $root=sys_get_temp_dir().'/order-test-'.bin2hex(random_bytes(8));try{echo json_encode(order_test_suite($p,$root),JSON_THROW_ON_ERROR)."\n";}finally{foreach(glob($root.'/*') as $f)unlink($f);if(is_dir($root))rmdir($root);}
}

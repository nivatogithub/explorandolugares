<?php
declare(strict_types=1);
require __DIR__.'/business-subscriptions.php';
function change_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function must_reject(callable $action,string $message):void{$failed=false;try{$action();}catch(RuntimeException){$failed=true;}change_check($failed,$message);}
$pdo->exec('DELETE FROM subscription_changes;DELETE FROM business_subscription_payment_evidence;DELETE FROM business_subscriptions;DELETE FROM subscription_plan_prices');
foreach(['featured'=>49900,'page'=>69900,'page_premium'=>119900] as $plan=>$price)foreach(['monthly'=>1,'quarterly'=>2.5,'semiannual'=>5.2,'annual'=>10] as $period=>$months)$pdo->prepare('INSERT INTO subscription_plan_prices VALUES(?,?,?,"COP")')->execute([$plan,$period,(string)($price*$months)]);
$changes=new SubscriptionChangeService($pdo);
$insert=$pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,reviewed_at,quoted_amount,currency,published_at) VALUES(1,?,?,"active",?,?,?, ?,"COP",?)');
$from='2028-04-01 00:00:00';$end='2028-05-01 00:00:00';$at=new DateTimeImmutable('2028-04-16 00:00:00',new DateTimeZone('UTC'));
$insert->execute(['featured','monthly',$from,$end,$from,49900,$from]);$source=(int)$pdo->lastInsertId();
$quote=$changes->quote(1,'page','monthly',null,$at);
change_check($quote['credit_cents']===2495000&&$quote['target_remaining_cents']===3495000&&$quote['due_cents']===1000000,'Half a 30-day term: credit 24950, target 34950, charge 10000.');
change_check($quote['ends_at']===$end&&$pdo->query('SELECT status FROM business_subscriptions WHERE id='.$source)->fetchColumn()==='active','Quoting does not replace the active tier or change expiry.');
$admin=new BusinessSubscriptionAdminService($pdo,$at);$upgrade=$admin->save(['business_id'=>1,'plan_code'=>'page','billing_frequency'=>'monthly']);
$sub=$pdo->query('SELECT * FROM business_subscriptions WHERE id='.$upgrade)->fetch(PDO::FETCH_ASSOC);
change_check((float)$sub['quoted_amount']===10000.0&&$changes->record($upgrade)['source_subscription_id']==$source,'The actual payable quote and its source are persisted.');
must_reject(fn()=>$admin->publish(1,$at),'Cannot grant an unpaid upgrade.');
$pdo->prepare('UPDATE business_subscriptions SET status="paid_pending_publication",reviewed_at=? WHERE id=?')->execute([$from,$upgrade]);
must_reject(fn()=>$admin->save(['business_id'=>1,'plan_code'=>'free']),'A paid request cannot be cancelled by a new selection.');
$admin->publish(1,$at);$up=$pdo->query('SELECT * FROM business_subscriptions WHERE id='.$upgrade)->fetch(PDO::FETCH_ASSOC);
change_check($up['ends_at']===$end&&$up['status']==='active'&&$pdo->query('SELECT status FROM business_subscriptions WHERE id='.$source)->fetchColumn()==='expired','Approval activates the new tier only through the original expiry.');
$second=$changes->quote(1,'page_premium','monthly',null,$at);
change_check($second['credit_cents']===3495000&&$second['due_cents']===2500000,'A second upgrade credits Page entitlement, not just the 10000 upgrade receipt.');
must_reject(fn()=>$changes->quote(1,'page_premium','annual',null,$at),'An immediate upgrade cannot silently change billing frequency.');
$down=$changes->quote(1,'featured','annual',null,$at);change_check($down['kind']==='renewal'&&$down['effective_at']===$end&&$down['credit_cents']===0,'A lower tier is a future renewal, never a refund or immediate downgrade.');
$downId=$admin->save(['business_id'=>1,'plan_code'=>'free']);
must_reject(fn()=>$admin->publish(1,$at),'Free also cannot downgrade an unexpired paid term.');
$pdo->exec('DELETE FROM business_media');
$admin->publish(1,new DateTimeImmutable($end,new DateTimeZone('UTC')));
change_check($pdo->query('SELECT status FROM business_subscriptions WHERE id='.$downId)->fetchColumn()==='active','The requested free tier may activate at expiry.');
// All period discounts use the contracted full-term price, with leap/calendar duration.
foreach(['monthly'=>[1,'2028-02-01','2028-03-01'],'quarterly'=>[2.5,'2028-01-01','2028-04-01'],'semiannual'=>[5.2,'2028-01-01','2028-07-01'],'annual'=>[10,'2028-01-01','2029-01-01']] as $period=>[$factor,$start,$finish]){
 $pdo->exec('UPDATE business_subscriptions SET status="expired"');$insert->execute(['featured',$period,$start.' 00:00:00',$finish.' 00:00:00',$start.' 00:00:00',(string)(49900*$factor),$start.' 00:00:00']);
 $startDate=new DateTimeImmutable($start,new DateTimeZone('UTC'));$finishDate=new DateTimeImmutable($finish,new DateTimeZone('UTC'));$half=$startDate->setTimestamp(intdiv($startDate->getTimestamp()+$finishDate->getTimestamp(),2));
 $q=$changes->quote(1,'page',$period,null,$half);
 change_check($q['due_cents']===(int)round(20000*$factor*100/2),'Discount and actual calendar duration respected for '.$period);
 $addon=$changes->quote(1,'page',$period,['amount'=>(string)(29900*$factor),'experience_id'=>7],$half);
 change_check($addon['due_cents']===(int)round(49900*$factor*100/2),'Operator supplement uses the same unconsumed fraction for '.$period);
}
// A pending paid upgrade cannot be granted after its source term has already expired.
$active=$pdo->query('SELECT id FROM business_subscriptions WHERE status="active"')->fetchColumn();$lateQuote=$changes->quote(1,'page','annual',null,new DateTimeImmutable('2028-07-02',new DateTimeZone('UTC')));
$pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,quoted_amount,currency,reviewed_at) VALUES(1,"page","annual","paid_pending_publication",?,"COP","2028-07-02")')->execute([SubscriptionChangeService::money($lateQuote['due_cents'])]);$late=(int)$pdo->lastInsertId();$changes->capture($late,$lateQuote);
$lateSub=$pdo->query('SELECT * FROM business_subscriptions WHERE id='.$late)->fetch(PDO::FETCH_ASSOC);
must_reject(fn()=>$changes->activation($lateSub,new DateTimeImmutable('2029-01-02',new DateTimeZone('UTC'))),'Expired quotes require reconciliation; no silent free new term.');
change_check($changes->record($late)!==null,'The expired upgrade still retains the payment reconciliation ledger.');
$pdo->exec('UPDATE business_subscriptions SET status="pending" WHERE id='.$late);
$pdo->prepare('INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,payment_reference,status,paid_amount) VALUES(?,1,"fixture.png","fixture.png","image/png",68,"TX-UPGRADE-LOW","pending",?)')->execute([$late,SubscriptionChangeService::money($lateQuote['due_cents']-1)]);
$lowEvidence=(int)$pdo->lastInsertId();$storage=sys_get_temp_dir().'/el-upgrade-proof-'.bin2hex(random_bytes(4));$reviewer=new BusinessPaymentEvidenceService($pdo,$storage);
must_reject(fn()=>$reviewer->review($lowEvidence,'approved','','admin'),'An underpaid difference cannot be approved.');
change_check($pdo->query('SELECT status FROM business_subscription_payment_evidence WHERE id='.$lowEvidence)->fetchColumn()==='pending','Failed approval preserves evidence and payment history.');rmdir($storage);
echo "OK: prorated upgrade credit, discounted periods, leap year, original expiry, consecutive upgrades, no unpaid activation, no early downgrade, addon and expired-quote safeguards.\n";

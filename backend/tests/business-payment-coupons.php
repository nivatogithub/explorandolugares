<?php
declare(strict_types=1);
require __DIR__.'/business-promotions.php';
// A fresh registration has no redemption, receipt, review or started term.
function pendingCouponBusiness(PDO $p,string $plan='page',string $period='monthly',int $place=1):array{
    $key='coupon-'.bin2hex(random_bytes(6));
    $p->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug) VALUES(?,?,"hospedaje",?)')->execute([$key,$place,$key]);$id=(int)$p->lastInsertId();
    $p->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(1,?,"owner","active")')->execute([$id]);
    $p->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,quoted_amount,currency) VALUES(?,?,?,"pending",80000,"COP")')->execute([$id,$plan,$period]);$sub=(int)$p->lastInsertId();
    $p->prepare('INSERT INTO business_editorial_versions(business_id,account_id,plan_code,base_hash,payload_json,payload_hash) VALUES(?,1,?,"test","{}","test")')->execute([$id,$plan]);
    return [$id,$sub];
}
$s->save(settings('PAGOAQUI'),'admin');$s->save(settings('PAGOMITAD',50),'admin');
[$business,$subscription]=pendingCouponBusiness($p);
rejects(fn()=>$s->applyAtPayment(999,$business,$subscription,'PAGOAQUI'),'Foreign account cannot redeem');
rejects(fn()=>$s->applyAtPayment(1,$business,$subscription+1,'PAGOAQUI'),'Stale subscription rejected');
$offer=$s->applyAtPayment(1,$business,$subscription,' pagoaqui ');
ck($offer['due_cents']===0&&$offer['list_cents']===8000000&&$s->covered($subscription),'100% uses captured price and covers payment');
ck($p->query('SELECT publication_status FROM businesses WHERE id='.$business)->fetchColumn()==='draft','Coupon never publishes');
ck($p->query('SELECT starts_at FROM business_subscriptions WHERE id='.$subscription)->fetchColumn()===null,'Coupon never starts term');
ck((int)$p->query('SELECT COUNT(*) FROM business_subscription_payment_evidence WHERE subscription_id='.$subscription)->fetchColumn()===0,'No fake receipt');
$s->save([...settings('PAGOAQUI',5),'enabled'=>0],'admin');
ck((int)$s->applyAtPayment(1,$business,$subscription,'PAGOAQUI')['due_cents']===0,'Retry keeps redeemed terms even after disabling');
rejects(fn()=>$s->applyAtPayment(1,$business,$subscription,'PAGOMITAD'),'Cannot replace redeemed coupon');
[$business,$subscription]=pendingCouponBusiness($p);
$half=$s->applyAtPayment(1,$business,$subscription,'PAGOMITAD');
ck($half['due_cents']===4000000&&$half['list_cents']===8000000,'Partial discount preserves original quote, not changed tariff');
ck(!$s->covered($subscription)&&$p->query('SELECT status FROM business_subscriptions WHERE id='.$subscription)->fetchColumn()==='pending','Partial remains payable');
foreach([['featured','monthly',1],['page','annual',1],['page','monthly',2]] as [$plan,$period,$place]){
    [$business,$subscription]=pendingCouponBusiness($p,$plan,$period,$place);
    rejects(fn()=>$s->applyAtPayment(1,$business,$subscription,'PAGOMITAD'),'Actual plan/period/place governs eligibility');
    ck($s->forSubscription($subscription)===null,'Invalid coupon leaves no redemption');
}
foreach(['receipt','review','published','renewal','suspended'] as $state){
    [$business,$subscription]=pendingCouponBusiness($p);
    if($state==='receipt')$p->prepare('INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,status) VALUES(?,1,"test.pdf","test.pdf","application/pdf",10,"rejected")')->execute([$subscription]);
    if($state==='review')$p->exec('UPDATE business_editorial_versions SET status="in_editorial" WHERE business_id='.$business);
    if($state==='published')$p->exec('UPDATE businesses SET publication_status="published" WHERE id='.$business);
    if($state==='renewal'){$p->exec('UPDATE business_subscriptions SET status="cancelled" WHERE id='.$subscription);$p->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,quoted_amount,currency) VALUES(?,"page","monthly","pending",80000,"COP")')->execute([$business]);$subscription=(int)$p->lastInsertId();}
    if($state==='suspended')$p->exec('UPDATE accounts SET status="disabled" WHERE id=1');
    rejects(fn()=>$s->applyAtPayment(1,$business,$subscription,'PAGOMITAD'),'Reject after '.$state);
    ck(!$p->inTransaction(),'Failure rolls back transaction');
    if($state==='suspended')$p->exec('UPDATE accounts SET status="active" WHERE id=1');
}
$s->save([...settings('SOLOPREMIUM'),'plans'=>['page_premium'],'destinations'=>[3]],'admin');
[$premium,$premiumSubscription]=pendingCouponBusiness($p,'page_premium','monthly',3);
[$page,$pageSubscription]=pendingCouponBusiness($p,'page','monthly',3);
ck($s->hasApplicableOffer($premium,'page_premium','monthly'),'Compatible Premium offer shows code field');
ck(!$s->hasApplicableOffer($page,'page','monthly')&&!$s->hasApplicableOffer($premium,'featured','monthly')&&!$s->hasApplicableOffer($premium,'free','monthly'),'Other plans never show that offer');
ck(!$s->hasApplicableOffer($premium,'page_premium','annual'),'Other period never shows that offer');
$s->save([...settings('SOLOPREMIUM'),'plans'=>['page_premium'],'destinations'=>[3],'enabled'=>0],'admin');
ck(!$s->hasApplicableOffer($premium,'page_premium','monthly'),'Inactive offer hidden');
$s->save([...settings('SOLOPREMIUM'),'plans'=>['page_premium'],'destinations'=>[3],'expires_on'=>'2020-01-01'],'admin');
ck(!$s->hasApplicableOffer($premium,'page_premium','monthly'),'Expired offer hidden');
$s->save([...settings('SOLOPREMIUM'),'plans'=>['page_premium'],'destinations'=>[3],'max_uses'=>1],'admin');
$s->applyAtPayment(1,$premium,$premiumSubscription,'SOLOPREMIUM');
ck(!$s->hasApplicableOffer($premium,'page_premium','monthly'),'Exhausted offer hidden');
echo "OK: checkout-only coupons; eligible plan/period/place visibility, immutable retry, scope, permissions, review/receipt/renewal gates and no publication or fake payments.\n";

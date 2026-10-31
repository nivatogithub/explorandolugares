<?php
declare(strict_types=1);
require __DIR__.'/business-promotions.php';
require_once __DIR__.'/../app/SubscriptionPricing.php';
require_once __DIR__.'/../app/DatabaseMaintenance.php';
$checks=0;
function pricing_check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function protectedPricingSnapshot(PDO $p):array{
    $snapshot=[];foreach($p->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table){
        if(in_array($table,['subscription_plan_prices','subscription_pricing_policy','experience_operator_prices'],true))continue;
        $snapshot[$table]=$p->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
    }return $snapshot;
}
$snapshot=protectedPricingSnapshot($p);
$migration=file_get_contents(__DIR__.'/../database/migrations/058-rounded-subscription-prices.sqlite.sql');
for($run=0;$run<2;$run++){
    $p->beginTransaction();$p->exec($migration);$p->commit();
    pricing_check(protectedPricingSnapshot($p)===$snapshot,'No changes to existing payments, quotes, coupon snapshots, applications, users or metrics');
}
$expected=['featured'=>[25000,67500,127500,240000],'page'=>[40000,108000,204000,384000],'page_premium'=>[60000,162000,306000,576000]];
$periods=['monthly','quarterly','semiannual','annual'];$months=[1,3,6,12];$discounts=[0,10,15,20];$service=new BusinessSubscriptionChoice($p);$presentation=new SubscriptionPricing($p);
foreach($expected as $plan=>$amounts)foreach($amounts as $i=>$amount){
    $quote=$service->quote($plan,$periods[$i]);pricing_check($quote['quoted_amount']===number_format($amount,2,'.','')&&$quote['currency']==='COP','Exact approved quote '.$plan.'/'.$periods[$i]);
    $checkout=(new SubscriptionChangeService($p))->quote(987654,$plan,$periods[$i]);pricing_check($checkout['due_cents']===$amount*100,'Checkout and selection agree');
    if($i>0){$saving=$presentation->savings($plan,$periods[$i]);pricing_check($saving['percentage']===$discounts[$i]*1.0&&$saving['total_cents']===$amount*100,'Exact discount and savings '.$plan.'/'.$periods[$i]);}
}
$addonPrices=(new ExperienceOperatorAddon($p))->prices();
foreach([20000,54000,102000,192000] as $i=>$amount){
    pricing_check((float)$addonPrices[$periods[$i]]['amount']===$amount*1.0,'Approved experience add-on');
    $quote=(new SubscriptionChangeService($p))->quote(987654,'page',$periods[$i],['experience_id'=>1,'amount'=>(string)$amount]);
    pricing_check($quote['due_cents']===($expected['page'][$i]+$amount)*100,'Combined plan and experience checkout');
    if($i>0)pricing_check($presentation->savings('page',$periods[$i],true)['percentage']===$discounts[$i]*1.0,'Combined savings exact');
}
foreach(['es','en','pt-BR'] as $locale){
    $html=$presentation->publicTable($locale);pricing_check(substr_count($html,'<tr>')===6,'Four plans and experience row '.$locale);
    foreach($expected as $amounts)foreach($amounts as $amount)pricing_check(str_contains($html,SubscriptionPricing::money($amount,$locale)),'Public table matches catalogue');
    foreach([10,15,20] as $percent)pricing_check(str_contains($html,$percent.' %'),'Explicit discount in public table');
    pricing_check(!str_contains($presentation->periodLabel('monthly',$locale),'%'),'No false monthly discount');
}
pricing_check($presentation->savings('free','annual')===null,'No discount advertised on Free');
$states=(new DatabaseMaintenance($p,__DIR__.'/../app',__DIR__.'/../public'))->inspect()['migrations'];
foreach(['022-subscription-prices','037-subscription-pricing-policy','041-experience-operator-addon','050-commercial-page-price','058-rounded-subscription-prices'] as $id)pricing_check($states[$id]==='applied','Superseded prices do not make maintenance revert newer ones: '.$id);
// Fail after the first catalogue changes: a transaction restores the entire table.
$p->exec("CREATE TRIGGER price_failure BEFORE UPDATE ON experience_operator_prices BEGIN SELECT RAISE(ABORT,'synthetic storage failure');END");
$before=$p->query('SELECT * FROM subscription_plan_prices')->fetchAll();$p->beginTransaction();$failed=false;
try{$p->exec('UPDATE subscription_plan_prices SET amount=123 WHERE plan_code="page"');$p->exec($migration);$p->commit();}catch(Throwable){$failed=true;$p->rollBack();}
pricing_check($failed&&$before===$p->query('SELECT * FROM subscription_plan_prices')->fetchAll(),'Failure rolls back all prices');
echo "OK $checks rounded price checks: 16 prices, 10/15/20% savings, combined checkout, three languages, idempotency, immutable financial history and rollback.\n";

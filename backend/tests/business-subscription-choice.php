<?php
declare(strict_types=1);
require __DIR__.'/../app/BusinessSubscriptionChoice.php';
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->exec('CREATE TABLE subscription_plans(code TEXT PRIMARY KEY,is_active INT,display_order INT);CREATE TABLE subscription_plan_translations(plan_code TEXT,locale TEXT,name TEXT,summary TEXT);CREATE TABLE subscription_plan_prices(plan_code TEXT,billing_frequency TEXT,amount DECIMAL(12,2),currency TEXT)');
foreach(['free','basic','featured','page','page_premium'] as $n=>$code){$p->prepare('INSERT INTO subscription_plans VALUES(?,1,?)')->execute([$code,$n]);$p->prepare('INSERT INTO subscription_plan_translations VALUES(?,"es",?,?)')->execute([$code,$code,'Resumen '.$code]);}
$p->exec('INSERT INTO subscription_plan_prices VALUES("featured","monthly",49900,"COP"),("featured","annual",499000,"COP"),("page","quarterly",NULL,"COP")');
$s=new BusinessSubscriptionChoice($p);$checks=0;function ck(bool $v,string $m):void{global $checks;if(!$v)throw new RuntimeException($m);$checks++;}function rejects(callable $f):bool{try{$f();return false;}catch(RuntimeException){return true;}}
ck(array_column($s->options(),'code')===['free','featured','page','page_premium'],'four public choices without basic');
ck($s->quote('free','annual')['quoted_amount']==='0.00'&&$s->quote('free','monthly')['billing_frequency']===null,'free no billing');
$q=$s->quote('featured','monthly');ck($q['quoted_amount']==='49900.00'&&$q['quote_status']==='quoted','configured monthly total');ck($s->quote('featured','annual')['quoted_amount']==='499000.00','configured annual total, no invented discount');
foreach(['quarterly','semiannual'] as $period){$q=$s->quote('featured',$period);ck($q['quoted_amount']===null&&$q['quote_status']==='pending_price'&&$q['billing_frequency']===$period,'missing tariff preserves choice without multiplication');}
ck($s->quote('page','quarterly')['quote_status']==='pending_price','explicit null pending');ck(rejects(fn()=>$s->quote('basic','monthly')),'legacy basic not offered');ck(rejects(fn()=>$s->quote('page','weekly')),'invalid frequency rejected');ck(rejects(fn()=>$s->quote('administrator','monthly')),'forged plan rejected');
$p->exec('UPDATE subscription_plans SET is_active=0 WHERE code="page_premium"');ck(rejects(fn()=>$s->quote('page_premium','monthly')),'inactive rejected');
$p->exec('UPDATE subscription_plan_prices SET amount=-20 WHERE plan_code="featured" AND billing_frequency="monthly"');ck(rejects(fn()=>$s->quote('featured','monthly')),'invalid catalog amount rejected');
$p->exec('UPDATE subscription_plan_prices SET amount=49900,currency="bad-value" WHERE plan_code="featured" AND billing_frequency="monthly"');ck(rejects(fn()=>$s->quote('featured','monthly')),'invalid currency rejected');
$p->exec('UPDATE subscription_plan_prices SET currency="COP" WHERE plan_code="featured" AND billing_frequency="monthly"');
$subscriptionOptions=$s->options();$_POST=['plan_code'=>'featured','billing_frequency'=>'semiannual'];function esc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}ob_start();require __DIR__.'/../public/business/subscription-choice.php';$markup=ob_get_clean();ck(str_contains($markup,'name="plan_code" value="featured" checked'),'form preserves selected radio');ck(str_contains($markup,'value="semiannual" selected'),'form preserves period');ck(str_contains($markup,'Por cotizar'),'pending price explicit');
echo "OK $checks subscription choice checks\n";

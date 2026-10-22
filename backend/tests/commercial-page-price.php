<?php
declare(strict_types=1);
require __DIR__.'/business-self-onboarding.php';
$before=$p->query('SELECT * FROM business_subscriptions ORDER BY id')->fetchAll();$migration=file_get_contents(__DIR__.'/../database/migrations/050-commercial-page-price.sqlite.sql');$p->exec($migration);$p->exec($migration);
$q=$p->query('SELECT billing_frequency,amount FROM subscription_plan_prices WHERE plan_code="page"');$prices=$q->fetchAll(PDO::FETCH_KEY_PAIR);foreach(['monthly'=>79900,'quarterly'=>199750,'semiannual'=>415480,'annual'=>799000] as $period=>$amount)check((float)$prices[$period]===$amount*1.0,'price '.$period);
check($before===$p->query('SELECT * FROM business_subscriptions ORDER BY id')->fetchAll(),'historic subscriptions unchanged');echo "OK: four catalogue prices; repeat safe; existing subscriptions unchanged\n";

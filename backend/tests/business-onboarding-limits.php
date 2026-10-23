<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessOnboardingDetails.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec('CREATE TABLE service_categories(code TEXT,is_active INTEGER);INSERT INTO service_categories VALUES("hospedaje",1)');
function onboardingFails(PDO $pdo,array $input):bool{try{BusinessOnboardingDetails::capture($pdo,$input,'hospedaje',sys_get_temp_dir());return false;}catch(RuntimeException){return true;}}
$page=BusinessOnboardingDetails::capture($p,['plan_code'=>'page','summary'=>str_repeat('á',450)],'hospedaje',sys_get_temp_dir());
if(mb_strlen($page['text']['summary'])!==450||!onboardingFails($p,['plan_code'=>'page','summary'=>str_repeat('á',451)]))throw new RuntimeException('Short summary limit is not enforced.');
$premium=BusinessOnboardingDetails::capture($p,['plan_code'=>'page_premium','summary'=>str_repeat('á',450),'description'=>str_repeat('é',1200),'products'=>[['title'=>'Servicio uno','summary'=>str_repeat('x',180)],['title'=>'Servicio dos','summary'=>'Breve']]],'hospedaje',sys_get_temp_dir());
if(mb_strlen($premium['text']['description'])!==1200||count($premium['products'])!==2||!onboardingFails($p,['plan_code'=>'page_premium','description'=>str_repeat('é',1201)])||!onboardingFails($p,['plan_code'=>'featured','description'=>'No corresponde'])||!onboardingFails($p,['plan_code'=>'page_premium','products'=>[['title'=>'A','summary'=>''],['title'=>'B','summary'=>''],['title'=>'C','summary'=>''],['title'=>'D','summary'=>''],['title'=>'E','summary'=>''],['title'=>'F','summary'=>'']]]))throw new RuntimeException('Onboarding editorial boundaries are not enforced.');
if(!onboardingFails($p,['plan_code'=>'free','summary'=>'Texto'])||!onboardingFails($p,['plan_code'=>'page','brand_story'=>'Texto']))throw new RuntimeException('Unavailable onboarding fields were accepted.');
echo "OK: concise onboarding limits, Premium product cards and unavailable fields are enforced.\n";

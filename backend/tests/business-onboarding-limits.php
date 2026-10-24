<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessOnboardingDetails.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec('CREATE TABLE service_categories(code TEXT,is_active INTEGER);INSERT INTO service_categories VALUES("hospedaje",1)');
function captureInput(PDO $pdo,array $input):array{return BusinessOnboardingDetails::capture($pdo,$input,'hospedaje',sys_get_temp_dir());}
function onboardingFails(PDO $pdo,array $input):bool{try{captureInput($pdo,$input);return false;}catch(RuntimeException){return true;}}
$base=['address_text'=>'Calle de prueba 10','channels'=>['phone'=>'+573001234567','phone_fixed'=>'+576041234567','whatsapp'=>'+573001234567','email'=>'fixture@example.invalid','website'=>'https://example.invalid','instagram'=>'https://example.invalid/ig','facebook'=>'https://example.invalid/fb','linkedin'=>'https://example.invalid/in']];
$previous=[];
foreach(BusinessRegistrationPlan::all() as $code=>$rules){
    $limit=$rules['description_limit'];
    $input=$code==='free'?['plan_code'=>$code,'summary'=>'']:array_merge($base,['plan_code'=>$code,'summary'=>str_repeat('á',$limit)]);
    $details=captureInput($p,$input);
    if(mb_strlen($details['text']['summary'])!==$limit||$details['text']['description']!=='')throw new RuntimeException('One description must respect each plan limit.');
    if(!onboardingFails($p,array_replace($input,['summary'=>str_repeat('á',$limit+1)])))throw new RuntimeException('Reject over-limit description: '.$code);
    if(!onboardingFails($p,array_replace($input,['description'=>'Un segundo campo no corresponde'])))throw new RuntimeException('Reject second description: '.$code);
    if(array_diff($previous,$rules['channels']))throw new RuntimeException('Paid channels must be cumulative.');
    if($code!=='free'){
        if(array_diff(array_keys($details['channels']),$rules['channels'])||array_diff($rules['channels'],array_keys($details['channels'])))throw new RuntimeException('Only permitted channels may be captured.');
        foreach(['summary','address_text','channels'] as $required){if(!onboardingFails($p,array_replace($input,[$required=>$required==='channels'?[]:''])))throw new RuntimeException('Required in all paid plans: '.$required);}
    }
    $previous=$rules['channels'];
}
$premium=array_merge($base,['plan_code'=>'page_premium','summary'=>'Descripción única']);
foreach([0,2,4,5] as $count){$products=[];for($i=0;$i<$count;$i++)$products[$i]=['title'=>'Servicio '.$i,'summary'=>str_repeat('x',180)];$result=captureInput($p,$premium+['products'=>$products]);if(count($result['products'])!==$count)throw new RuntimeException('Preserve configured product count.');}
$products=[4=>['title'=>'Quinto producto','summary'=>'Breve']];
if(!isset(captureInput($p,$premium+['products'=>$products])['products'][4]))throw new RuntimeException('Preserve sparse image/product position.');
if(!onboardingFails($p,$premium+['products'=>[5=>['title'=>'Sexto producto','summary'=>'Breve']]]))throw new RuntimeException('Reject sixth product.');
foreach(['free','featured','page'] as $code){if(!onboardingFails($p,array_replace($premium,['plan_code'=>$code,'products'=>$products])))throw new RuntimeException('Products are Premium only.');}
if(!onboardingFails($p,$premium+['brand_story'=>'Campo retirado']))throw new RuntimeException('Do not capture retired fields.');
echo "OK: one description 0/300/450/1200, cumulative channels and paid requirements, Premium 0/2/4/5 products, sparse positions, sixth and retired fields rejected.\n";

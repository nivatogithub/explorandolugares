<?php
declare(strict_types=1);require __DIR__.'/../app/ContactPhone.php';
function cp($in,$expected){if(ContactPhone::capture($in)!==$expected)throw new RuntimeException('Unexpected phone');}
cp(['phone_kind'=>'mobile','phone_country_code'=>'57','phone_national'=>'300 123 4567'],'+573001234567');
cp(['phone_kind'=>'fixed','phone_country_code'=>'57','phone_area'=>'601','phone_national'=>'4441234','phone'=>'+999999999'],'+576014441234');
cp(['phone_kind'=>'fixed','phone_country_code'=>'57','phone_area'=>'604','phone_national'=>'4441234'],'+576044441234');
cp(['phone_kind'=>'mobile','phone_country_code'=>'1','phone_national'=>'2025550123'],'+12025550123');
cp(['phone_kind'=>'fixed','phone_country_code'=>'55','phone_foreign_area'=>'11','phone_national'=>'23456789'],'+551123456789');
cp(['phone_national'=>''],'');
foreach([['phone_kind'=>'fixed','phone_country_code'=>'57','phone_area'=>'999','phone_national'=>'4441234'],['phone_kind'=>'mobile','phone_country_code'=>'57','phone_national'=>'123'],['phone_kind'=>'mobile','phone_country_code'=>'0','phone_national'=>'3001234567']] as $in){try{ContactPhone::capture($in);throw new LogicException('Invalid accepted');}catch(RuntimeException $e){if($e instanceof LogicException)throw $e;}}
echo "OK: person phones, international prefixes, fixed/mobile, optional empty and server recomposition\n";

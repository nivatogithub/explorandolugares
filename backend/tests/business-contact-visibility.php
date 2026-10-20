<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/views/directory-helpers.php';
function visibility_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$contacts=[];
foreach(['phone'=>'+573001234567','phone_fixed'=>'6041234567','whatsapp'=>'+573001234568','email'=>'public@example.test','instagram'=>'https://instagram.com/example','website'=>'https://example.test','facebook'=>'https://facebook.com/example','linkedin'=>'https://linkedin.com/company/example'] as $channel=>$value)$contacts[]=['channel'=>$channel,'contact_value'=>$value,'is_public'=>1];
$contacts[]=['channel'=>'phone','contact_value'=>'+573009999999','is_public'=>0];
$expected=['free'=>[],'featured'=>['phone','phone_fixed','whatsapp'],'page'=>['phone','phone_fixed','whatsapp','email','instagram'],'page_premium'=>['phone','phone_fixed','whatsapp','email','instagram','website','facebook','linkedin']];
foreach($expected as $plan=>$allowed){
 $filtered=BusinessContactVisibility::filter($plan,$contacts);
 visibility_check(array_column($filtered,'channel')===$allowed,$plan.' channel policy mismatch');
 foreach(['es','en','pt-BR'] as $locale){
  $html=directoryPlanContacts(['plan_code'=>$plan],$contacts,$locale);
  foreach($contacts as $row){$href=contactHref($row);$visible=in_array($row['channel'],$allowed,true)&&$row['is_public']===1;visibility_check(str_contains($html,e($href))===$visible,$plan.' exposed/omitted '.$row['channel']);}
 }
}
visibility_check(BusinessContactVisibility::filter('unknown',$contacts)===[],'Unknown tier must fail closed');
visibility_check(directoryPlanContacts(['plan_code'=>'page_premium'],[['channel'=>'website','contact_value'=>'javascript:alert(1)','is_public'=>1]],'es')==='','Unsafe URLs must not render');
echo "OK: all four tiers, private channels excluded, explicit labels in three languages, unknown tiers and unsafe links.\n";

<?php
declare(strict_types=1);
require __DIR__.'/business-self-onboarding.php';
require __DIR__.'/../app/BusinessAccountPortalService.php';
function throttle(...$args):bool{return true;}
$service=new BusinessAccountPortalService($p);
foreach(['page','page_premium'] as $plan){$before=$p->query('SELECT COUNT(*) FROM business_account_applications')->fetchColumn();$rejected=false;try{$service->register('Persona','persona1@example.invalid','', ['application_type'=>'new_business','destination_id'=>1,'category_code'=>'hospedaje','business_name'=>'Nuevo hotel de prueba','relationship_code'=>'owner','consent'=>'1','self_onboarding_policy'=>BusinessSelfOnboarding::POLICY,'responsibility_declared'=>'1','business_details'=>['requested_subscription'=>['plan_code'=>$plan]]],['business_registration'=>['error'=>UPLOAD_ERR_OK]],sys_get_temp_dir(),null,sys_get_temp_dir(),1);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'fotografía');}check($rejected,'missing photos rejected for '.$plan);check($before===$p->query('SELECT COUNT(*) FROM business_account_applications')->fetchColumn(),'no partial application');}
echo "OK: Commercial/Premium require photo on server; no partial registration\n";

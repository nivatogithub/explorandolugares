<?php
declare(strict_types=1);
require __DIR__.'/business-self-onboarding.php';
require __DIR__.'/../app/BusinessOwnershipEvidence.php';
$dir=sys_get_temp_dir().'/el-evidence-'.bin2hex(random_bytes(6));mkdir($dir,0700);$name=str_repeat('a',48).'.pdf';file_put_contents($dir.'/'.$name,'synthetic ownership document');
try{
 $p->prepare('UPDATE business_account_applications SET business_details_json=? WHERE id=?')->execute([json_encode(['document_retention_policy'=>'delete-after-verification-v1']),$id]);
 $p->prepare('UPDATE business_account_application_evidence SET storage_name=? WHERE application_id=?')->execute([$name,$id]);$evidence=(int)$p->query('SELECT id FROM business_account_application_evidence WHERE application_id='.$id)->fetchColumn();
 $blocked=false;try{BusinessOwnershipEvidence::requireVerified($p,$business);}catch(RuntimeException){$blocked=true;}check($blocked,'unreviewed proof prevents publication');
 $service=new BusinessOwnershipEvidence($p,$dir);$rejected=false;try{$service->verifyAndDelete(5,$evidence,'admin');}catch(RuntimeException){$rejected=true;}check($rejected&&is_file($dir.'/'.$name),'another business cannot delete proof');
 $service->verifyAndDelete($business,$evidence,'admin');check(!file_exists($dir.'/'.$name),'document physically removed');BusinessOwnershipEvidence::requireVerified($p,$business);
 $service->verifyAndDelete($business,$evidence,'admin');check((int)$p->query('SELECT COUNT(*) FROM business_evidence_retirement')->fetchColumn()===1,'idempotent review');check((int)$p->query('SELECT COUNT(*) FROM business_account_applications WHERE id='.$id)->fetchColumn()===1,'application remains');
 $p->prepare('UPDATE business_account_applications SET business_details_json=? WHERE id=?')->execute([json_encode(['document_retention_policy'=>'delete-after-verification-v1']),$dispute]);BusinessOwnershipEvidence::requireVerified($p,$business);check(count($service->documents($business))===1,'disputed claimant cannot block or replace owner proof');
 echo "OK: publication gate, scoped deletion, physical retirement, audit and idempotency\n";
}finally{if(is_file($dir.'/'.$name))unlink($dir.'/'.$name);rmdir($dir);}

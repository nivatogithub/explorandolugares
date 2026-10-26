<?php
declare(strict_types=1);
require __DIR__.'/business-self-onboarding.php';
require_once __DIR__.'/../app/BusinessOwnershipEvidence.php';
$business=(int)$p->query('SELECT business_id FROM business_account_applications WHERE id='.$id)->fetchColumn();
$dir=sys_get_temp_dir().'/el-evidence-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$name=str_repeat('a',48).'.pdf';file_put_contents($dir.'/'.$name,'synthetic ownership document');
try{
 $p->prepare('UPDATE business_account_applications SET business_details_json=? WHERE id=?')->execute([json_encode(['document_retention_policy'=>'delete-after-verification-v1']),$id]);
 $p->prepare('UPDATE business_account_application_evidence SET storage_name=? WHERE application_id=?')->execute([$name,$id]);
 $evidence=(int)$p->query('SELECT id FROM business_account_application_evidence WHERE application_id='.$id)->fetchColumn();
 $blocked=false;try{BusinessOwnershipEvidence::requireVerified($p,$business);}catch(RuntimeException){$blocked=true;}check($blocked,'unreviewed proof prevents publication');
 $service=new BusinessOwnershipEvidence($p,$dir);$rejected=false;try{$service->verify(5,$evidence,'admin');}catch(RuntimeException){$rejected=true;}check($rejected&&is_file($dir.'/'.$name),'another business cannot verify proof');
 $service->verify($business,$evidence,'admin');
 check(is_file($dir.'/'.$name),'verification must retain the private document');BusinessOwnershipEvidence::requireVerified($p,$business);
 $service->verify($business,$evidence,'different-admin');
 check((int)$p->query('SELECT COUNT(*) FROM business_evidence_retirement')->fetchColumn()===1,'idempotent review');
 check($p->query('SELECT reviewer FROM business_evidence_retirement')->fetchColumn()==='admin','original reviewer preserved');
 $blocked=false;try{$service->retirePublished($business);}catch(RuntimeException){$blocked=true;}check($blocked&&is_file($dir.'/'.$name),'cannot retire before publication');
 $p->beginTransaction();$p->prepare('UPDATE businesses SET publication_status="published" WHERE id=?')->execute([$business]);
 $blocked=false;try{$service->retirePublished($business);}catch(LogicException){$blocked=true;}check($blocked&&is_file($dir.'/'.$name),'cannot delete before the publication commit');$p->rollBack();
 $p->prepare('UPDATE businesses SET publication_status="published" WHERE id=?')->execute([$business]);
 // Simulate an unusable file path without permissions assumptions (tests may run as root).
 unlink($dir.'/'.$name);mkdir($dir.'/'.$name);
 check($service->retirePublished($business)===[$evidence],'failed retirement is retryable');
 check($p->query('SELECT deleted_at FROM business_evidence_retirement')->fetchColumn()===null,'failed retirement cannot claim deletion');
 check($p->query('SELECT publication_status FROM businesses WHERE id='.$business)->fetchColumn()==='published','failure does not undo publication');
 rmdir($dir.'/'.$name);file_put_contents($dir.'/'.$name,'synthetic ownership document');
 $subscriptions=$p->query('SELECT * FROM business_subscriptions')->fetchAll();
 check($service->retirePublished($business)===[]&&!file_exists($dir.'/'.$name),'post-publication physical retirement');
 check($service->retirePublished($business)===[],'idempotent retirement');
 check($p->query('SELECT * FROM business_subscriptions')->fetchAll()===$subscriptions,'retry cannot restart a paid term');
 BusinessOwnershipEvidence::requireVerified($p,$business);
 $p->prepare('UPDATE business_account_applications SET business_details_json=? WHERE id=?')->execute([json_encode(['document_retention_policy'=>BusinessOwnershipEvidence::RETENTION_POLICY]),$dispute]);
 BusinessOwnershipEvidence::requireVerified($p,$business);check(count($service->documents($business))===1,'disputed claimant cannot block or replace owner proof');
 check((int)$p->query('SELECT COUNT(*) FROM business_account_applications WHERE id='.$id)->fetchColumn()===1,'application and audit remain');
 echo "OK: retained verification, old-policy gate, private scope, commit boundary, deletion failure/retry, audit and unchanged subscriptions\n";
}finally{if(is_file($dir.'/'.$name))unlink($dir.'/'.$name);if(is_dir($dir.'/'.$name))rmdir($dir.'/'.$name);rmdir($dir);}

<?php
declare(strict_types=1);
require __DIR__.'/business-self-onboarding.php';
require_once dirname(__DIR__).'/app/BusinessAdminWorkspace.php';
require_once dirname(__DIR__).'/app/AdminReturnContext.php';
$base=['has_owner'=>true,'title_verified'=>true,'publication_status'=>'draft','subscription_status'=>'paid_pending_publication'];
foreach([
 ['ownership',['title_verified'=>false]],['payment',['subscription_status'=>'pending']],
 ['payment_review',['subscription_status'=>'pending','payment_status'=>'pending']],
 ['payment_clarification',['subscription_status'=>'pending','payment_status'=>'clarification_requested']],
 ['payment_rejected',['subscription_status'=>'pending','payment_status'=>'rejected']],
 ['editorial',['editorial_status'=>'in_editorial']],['awaiting_owner',['editorial_status'=>'awaiting_owner']],
 ['awaiting_owner',['editorial_status'=>'owner_approved']],['changes',['editorial_status'=>'changes_requested']],
 ['published',['publication_status'=>'published','editorial_status'=>'published']],
 ['expired',['subscription_status'=>'expired']],
] as [$expected,$overrides])check(BusinessAdminWorkspace::state(array_replace($base,$overrides))===$expected,'administrative status '.$expected);
$return='atencion-comercios.php?query=Casa&plan=page&status=awaiting_owner&page=2';
check(AdminReturnContext::safe($return,'fallback')===$return,'return preserves filters and page');
foreach(['https://example.invalid/','//example.invalid/','atencion-comercios.php#bad','/admin/../business/'] as $unsafe)check(AdminReturnContext::safe($unsafe,'fallback')==='fallback','unsafe return rejected');
$p->exec('INSERT INTO service_category_translations(category_code,locale,label) VALUES("hospedaje","es","Hospedaje")');
$dir=sys_get_temp_dir().'/el-admin-workspace-'.bin2hex(random_bytes(6));
try{
 $rows=(new BusinessAdminWorkspace($p,$dir))->rows();check(count($rows)>0,'workspace returns real joined fixture rows');
 foreach($rows as $row){check(isset(BusinessAdminWorkspace::STATES[$row['workflow_state']]),'valid status code');foreach($row['accounts'] as $account){check(!isset($account['password_hash']),'account overlay excludes credentials');check((int)$account['business_count']>0,'associated business count');}}
}finally{if(is_dir($dir))rmdir($dir);}
echo "OK: administrative status, scoped account read model, counts, filter return and unsafe URL rejection\n";

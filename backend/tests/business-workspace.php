<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessWorkspace.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$base=dirname(__DIR__).'/database';
$p->exec(file_get_contents($base.'/sqlite.sql'));$p->exec(file_get_contents($base.'/destinations.sqlite.sql'));
foreach(glob($base.'/migrations/*.sqlite.sql') as $file)if((int)basename($file)>=8)$p->exec(file_get_contents($file));
$p->exec(file_get_contents($base.'/migrations/055-business-registration-drafts.sqlite.sql'));
$p->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"fixture","published");INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Jericó","","");INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1)');
foreach([1,2] as $id)$p->prepare('INSERT INTO accounts(id,public_id,account_type,display_name,email,status,email_verified_at) VALUES(?,?,"traveler","Fixture",?,"active",CURRENT_TIMESTAMP)')->execute([$id,'account-'.$id,'fixture'.$id.'@example.invalid']);
$d=new BusinessRegistrationDrafts($p);$id=str_repeat('a',48);
$payload=['application_type'=>'new_business','business_name'=>'Borrador privado','destination_id'=>'1','category_code'=>'hospedaje','plan_code'=>'page_premium','summary'=>'Sin terminar','products'=>[4=>['title'=>'Quinto','summary'=>'Conservar posición']],'csrf'=>'never-store','password'=>'never-store'];
$saved=$d->save(1,$id,0,3,$payload);
if($saved['revision']!==1||$d->load(1,$id)['wizard_step']!==3)throw new RuntimeException('Draft step not saved.');
if(isset($d->load(1,$id)['payload']['password'])||isset($d->load(1,$id)['payload']['csrf']))throw new RuntimeException('Secrets in capture payload.');
if((int)$p->query('SELECT COUNT(*) FROM businesses')->fetchColumn()!==0)throw new RuntimeException('Partial capture created a business.');
$denied=false;try{$d->load(2,$id);}catch(RuntimeException){$denied=true;}if(!$denied)throw new RuntimeException('Other account read a draft.');
$denied=false;try{$d->save(2,$id,1,4,$payload);}catch(RuntimeException){$denied=true;}if(!$denied)throw new RuntimeException('Other account changed a draft.');
$conflict=false;try{$d->save(1,$id,0,4,$payload);}catch(RuntimeException){$conflict=true;}if(!$conflict)throw new RuntimeException('Stale tab overwrote a draft.');
$d->save(1,$id,1,4,$payload);
$rows=(new BusinessWorkspace($p))->rows(1);
if(count($rows)!==1||$rows[0]['state']['code']!=='draft')throw new RuntimeException('Draft not visible once in workspace.');
if($d->load(1,$id)['payload']['products'][4]['title']!=='Quinto')throw new RuntimeException('Lost sparse product.');
$d->finish(1,$id,null);if($d->listing(1))throw new RuntimeException('Submitted draft still offered for editing.');
$states=[
 [[],null,null,'validation'],
 [['plan_code'=>'page'],null,null,'payment'],
 [['plan_code'=>'page'],null,['status'=>'pending'],'validation'],
 [['plan_code'=>'page'],null,['status'=>'approved'],'editorial'],
 [['plan_code'=>'page'],null,['status'=>'clarification_requested'],'clarification'],
 [[],['status'=>'awaiting_owner'],['status'=>'approved'],'awaiting_owner'],
 [[],['status'=>'owner_approved'],['status'=>'approved'],'awaiting_owner'],
 [[],['status'=>'changes_requested'],null,'changes'],
 [['publication_status'=>'published'],['status'=>'published'],['status'=>'approved'],'published'],
];
foreach($states as [$business,$version,$payment,$expected])if(BusinessWorkspace::state($business,$version,$payment)['code']!==$expected)throw new RuntimeException('Incorrect workspace state: '.$expected);
echo "OK: private step drafts, ownership, stale-tab conflicts, sparse products, no premature businesses, submission locks and customer states.\n";

<?php
declare(strict_types=1);
function throttle(string $kind,int $limit,int $seconds,string $extra=''):bool{return true;}
require dirname(__DIR__).'/app/BusinessAccountPortalService.php';
function disputed_check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach(['sqlite.sql','destinations.sqlite.sql','migrations/006-poi-experience-links.sqlite.sql'] as $file)$pdo->exec(file_get_contents(dirname(__DIR__).'/database/'.$file));
for($n=8;$n<=35;$n++){ $files=glob(dirname(__DIR__).'/database/migrations/'.sprintf('%03d',$n).'-*.sqlite.sql');if(count($files)===1)$pdo->exec(file_get_contents($files[0])); }
$pdo->exec('INSERT INTO destinations(slug,publication_status) VALUES("prueba","published");INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Prueba","Prueba","Prueba");INSERT INTO service_categories(code,display_order,is_active) VALUES("test",1,1);INSERT INTO service_category_translations(category_code,locale,label) VALUES("test","es","Prueba")');
foreach([1,2] as $id)$pdo->prepare('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,"traveler",?,?,?,"active")')->execute(['00000000-0000-4000-8000-'.sprintf('%012d',$id),'Persona '.$id,'persona'.$id.'@example.invalid',password_hash('ClavePrueba123456',PASSWORD_DEFAULT)]);
foreach([1=>'RECLAMADO',2=>'EN_TRAMITE',3=>'NO_RECLAMADO',4=>'NO_RECLAMADO'] as $id=>$status){$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,1,"test",?,"PUBLICO",?,"ABIERTO","published")')->execute(['00000000-0000-4000-8001-'.sprintf('%012d',$id),'negocio-'.$id,$status]);$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")')->execute([$id,'Negocio '.$id]);}
$pdo->exec('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(1,1,"owner","active"),(1,2,"owner","active"),(1,4,"owner","active")');
$service=new BusinessAccountPortalService($pdo);
$membershipsBefore=$pdo->query('SELECT * FROM business_account_memberships')->fetchAll();
$businessesBefore=$pdo->query('SELECT * FROM businesses WHERE id IN(1,2,4)')->fetchAll();
$matches=$service->searchRegistrationBusinesses(1,'test','Negocio');
disputed_check(count($matches)===4&&$matches[0]['can_request']&&$matches[0]['requires_review'],'Associated businesses remain selectable with explicit review notice.');
$base=['application_type'=>'existing_business','destination_id'=>1,'category_code'=>'test','relationship_code'=>'owner','consent'=>'1','message'=>'Solicito que se revise mi representación.'];
foreach([1,2,4] as $businessId){
 $service->register('Ignored','ignored@example.invalid','',$base+['business_id'=>$businessId],[],null,[],null,2);
 $row=$pdo->query('SELECT * FROM business_account_applications ORDER BY id DESC LIMIT 1')->fetch();
 disputed_check($row['application_type']==='claim_existing'&&$row['status']==='in_review'&&json_decode($row['business_details_json'],true)['claim_review_kind']==='disputed_existing','Contested claim stored separately for existing business.');
 $blocked=false;try{$service->reviewApplication((int)$row['id'],'approved','Documento y representación supuestamente comprobados');}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'conciliación manual');}
 disputed_check($blocked,'Contested approval cannot transfer ownership even through admin action.');
 $service->reviewApplication((int)$row['id'],'not_approved','No se pudo resolver la representación.');
}
disputed_check($pdo->query('SELECT * FROM businesses WHERE id IN(1,2,4)')->fetchAll()===$businessesBefore,'Creating/rejecting disputes preserves complete original business records.');
disputed_check($pdo->query('SELECT * FROM business_account_memberships')->fetchAll()===$membershipsBefore,'All memberships preserved; no claimant access granted.');
$service->register('Ignored','ignored@example.invalid','',$base+['business_id'=>1],[],null,[],null,2);
$duplicate=false;try{$service->register('Ignored','ignored@example.invalid','',$base+['business_id'=>1],[],null,[],null,2);}catch(RuntimeException $e){$duplicate=str_contains($e->getMessage(),'pendiente');}
disputed_check($duplicate,'Same person cannot create duplicate open disputes.');
$self=false;try{$service->register('Ignored','ignored@example.invalid','',$base+['business_id'=>1],[],null,[],null,1);}catch(RuntimeException $e){$self=str_contains($e->getMessage(),'asociado a tu cuenta');}
disputed_check($self,'Existing responsible is directed to their own business.');
$apps=$service->applications(2);disputed_check(count(array_filter($apps,fn($r)=>$r['review_kind']==='disputed_existing'&&$r['review_message']!==''))===4,'Applicant receives explicit status explanation.');
$service->register('Nueva Persona','nueva@example.invalid','ClavePrueba123456',$base+['business_id'=>3]);
$new=$pdo->query('SELECT status,account_type FROM accounts WHERE email="nueva@example.invalid"')->fetch();
disputed_check($new['status']==='pending_activation'&&$new['account_type']==='traveler','Anonymous registration still requires activation and creates a personal account.');
disputed_check($pdo->query('SELECT claim_status FROM businesses WHERE id=3')->fetchColumn()==='NO_RECLAMADO','An unverified account cannot reserve or take an unowned business before activating its email.');
// Another verified account may claim while the first applicant has not verified their email.
$service->register('Ignored','ignored@example.invalid','',$base+['business_id'=>3],[],null,[],null,2);
require_once dirname(__DIR__).'/app/AccountEmailVerificationService.php';$newId=(int)$pdo->query('SELECT id FROM accounts WHERE email="nueva@example.invalid"')->fetchColumn();$verifier=new AccountEmailVerificationService($pdo);$token=$verifier->issue($newId);$verifier->verify($token);
disputed_check((int)$pdo->query('SELECT account_id FROM business_account_memberships WHERE business_id=3 AND status="active"')->fetchColumn()===2,'Pending email must not reserve a business.');
disputed_check($pdo->query('SELECT status FROM business_account_applications WHERE account_id='.$newId)->fetchColumn()==='in_review','Activation rechecks ownership and turns a competing claim into a dispute.');
$pending=(int)$pdo->query('SELECT id FROM business_account_applications WHERE business_id=1 AND account_id=2 AND status="in_review"')->fetchColumn();
$pdo->exec('CREATE TRIGGER stop_resolution BEFORE INSERT ON business_account_memberships WHEN NEW.account_id=2 BEGIN SELECT RAISE(ABORT,"synthetic resolution failure"); END');
$failed=false;try{$service->reviewApplication($pending,'approved','Conciliación con ambas partes documentada y representación confirmada.',[],'admin-test',true);}catch(PDOException){$failed=true;}
disputed_check($failed&&(int)$pdo->query('SELECT account_id FROM business_account_memberships WHERE business_id=1 AND status="active"')->fetchColumn()===1,'Failed manual resolution must restore the original owner.');
$pdo->exec('DROP TRIGGER stop_resolution');$service->reviewApplication($pending,'approved','Conciliación con ambas partes documentada y representación confirmada.',[],'admin-test',true);
disputed_check((int)$pdo->query('SELECT account_id FROM business_account_memberships WHERE business_id=1 AND status="active"')->fetchColumn()===2,'Only explicit manual reconciliation transfers representation.');
disputed_check($pdo->query('SELECT status FROM business_account_memberships WHERE account_id=1 AND business_id=1')->fetchColumn()==='revoked','Previous membership is preserved as revoked history.');
echo "OK: disputed claims saved; ownership and prior claim untouched; duplicates blocked; admin takeover prevented; applicant message; activation preserved.\n";

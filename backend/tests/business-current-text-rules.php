<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessRegistrationPlan.php';
require dirname(__DIR__).'/app/DatabaseMaintenance.php';
require dirname(__DIR__).'/app/BusinessEditorialPackage.php';
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec(file_get_contents(dirname(__DIR__).'/database/sqlite.sql'));
$p->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
foreach(glob(dirname(__DIR__).'/database/migrations/*.sqlite.sql') as $file)if((int)basename($file)>=8&&(int)basename($file)<54)$p->exec(file_get_contents($file));
$p->exec('CREATE TABLE protected_fixture(id INTEGER PRIMARY KEY,value TEXT);INSERT INTO protected_fixture VALUES(1,"unchanged")');
$p->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"preserved","draft");INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1);INSERT INTO businesses(id,public_id,destination_id,category_code,slug) VALUES(1,"preserved",1,"hospedaje","preserved")');
$p->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(1,"es","Preserved",?,"")')->execute([str_repeat('á',1200)]);
$protected=$p->query('SELECT * FROM protected_fixture')->fetchAll(PDO::FETCH_ASSOC);
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/054-business-description-budgets.sqlite.sql');
$p->exec($sql);$first=$p->query('SELECT * FROM subscription_plans ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);$p->exec($sql);
if($first!==$p->query('SELECT * FROM subscription_plans ORDER BY code')->fetchAll(PDO::FETCH_ASSOC))throw new RuntimeException('Migration must be idempotent.');
foreach(BusinessRegistrationPlan::all() as $code=>$rules){$q=$p->prepare('SELECT max_summary_chars,max_description_chars,max_brand_story_chars,max_featured_services_chars FROM subscription_plans WHERE code=?');$q->execute([$code]);if(array_values($q->fetch(PDO::FETCH_ASSOC))!==array_values(BusinessRegistrationPlan::textLimits($code)))throw new RuntimeException('Database and policy disagree: '.$code);}
if($p->query('SELECT * FROM protected_fixture')->fetchAll(PDO::FETCH_ASSOC)!==$protected)throw new RuntimeException('Migration changed unrelated data.');
if(mb_strlen($p->query('SELECT summary FROM business_translations WHERE business_id=1')->fetchColumn())!==1200)throw new RuntimeException('New quotas must not truncate stored text.');
$tool=new DatabaseMaintenance($p,dirname(__DIR__).'/app',dirname(__DIR__,2).'/ensayos/v1');$report=$tool->inspect();
foreach(['016-plan-content-limits','053-current-business-text-rules','054-business-description-budgets'] as $migration)if($report['migrations'][$migration]!=='applied')throw new RuntimeException('Current metadata must not trigger historical migration reapplication: '.$migration);
$package=new BusinessEditorialPackage($p,sys_get_temp_dir());$validate=(new ReflectionClass($package))->getMethod('translations');
foreach(BusinessRegistrationPlan::all() as $code=>$rules){
    $translations=[];foreach(['es','en','pt-BR'] as $locale)$translations[$locale]=['display_name'=>'Fixture','summary'=>str_repeat('á',$rules['description_limit']),'description'=>''];
    $clean=$validate->invoke($package,$translations,$code);
    if(mb_strlen($clean['es']['summary'])!==$rules['description_limit'])throw new RuntimeException('ZIP must use the exact plan limit.');
    $translations['es']['summary'].='x';$failed=false;try{$validate->invoke($package,$translations,$code);}catch(RuntimeException){$failed=true;}
    if(!$failed)throw new RuntimeException('ZIP allowed historical/excess capacity: '.$code);
    $translations['es']['summary']='';$translations['es']['description']='Campo retirado';$failed=false;try{$validate->invoke($package,$translations,$code);}catch(RuntimeException){$failed=true;}
    if(!$failed)throw new RuntimeException('ZIP allowed a second business description.');
}
echo "OK: current plan metadata equals shared policy, migration twice unchanged, unrelated data intact, historical migrations remain applied.\n";

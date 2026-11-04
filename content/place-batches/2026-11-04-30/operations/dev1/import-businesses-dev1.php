<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Only local dev1');
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Wrong database');
$pdo=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach(['BusinessPublicImporter','BusinessSubscriptionAdminService','BusinessBulkPublicationService'] as $class)require_once '/srv/portal/app/'.$class.'.php';
$existing=$pdo->query('SELECT id FROM businesses')->fetchAll(PDO::FETCH_COLUMN);
$protected=[];
foreach(['businesses'=>'id','business_translations'=>'business_id','business_subscriptions'=>'business_id','business_contacts'=>'business_id'] as $table=>$column){$query='SELECT * FROM '.$table.($existing?' WHERE '.$column.' IN ('.implode(',',$existing).')':' WHERE 1=0');$protected[$table]=[$query,hash('sha256',json_encode($pdo->query($query)->fetchAll()))];}
$report=['environment'=>'dev1','production_touched'=>false,'destinations'=>[]];
foreach(glob(__DIR__.'/businesses/*.json') as $file){
 $package=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);if(($package['schema_version']??'')!=='business-import-v1')continue;$slug=$package['destination_slug'];$ids=[];$new=[];
 foreach($package['businesses'] as $b){$q=$pdo->prepare('SELECT b.id,b.publication_status FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? AND LOWER(TRIM(t.display_name))=LOWER(TRIM(?))');$q->execute([$slug,$b['name']]);$row=$q->fetch();if(!$row)$new[]=$b;}
 if($new){$package['businesses']=$new;BusinessPublicImporter::import($pdo,$package);}
 foreach($new as $b){$q=$pdo->prepare('SELECT b.id FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? AND t.display_name=?');$q->execute([$slug,$b['name']]);$ids[]=(int)$q->fetchColumn();}
 $result=$ids?(new BusinessBulkPublicationService($pdo))->publish($ids):['published'=>[],'skipped'=>[]];
 $report['destinations'][]=['slug'=>$slug,'imported'=>count($new),...$result];
 file_put_contents(__DIR__.'/business-publication-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
foreach($protected as $table=>[$query,$hash])if(!hash_equals($hash,hash('sha256',json_encode($pdo->query($query)->fetchAll()))))throw new RuntimeException('Existing rows changed: '.$table);
$report['existing_businesses_preserved']=true;file_put_contents(__DIR__.'/business-publication-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['destinations'=>count($report['destinations']),'published'=>array_sum(array_map(fn($r)=>count($r['published']),$report['destinations'])),'skipped'=>array_merge(...array_column($report['destinations'],'skipped')),'existing_preserved'=>true],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

<?php
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local only');
$c=require '/srv/state/private/config.php';if(empty($c['local_test'])||!str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Wrong environment');
$pdo=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach(['BusinessAdminService','BusinessSubscriptionAdminService','BusinessBulkPublicationService'] as $cl)require_once '/srv/portal/app/'.$cl.'.php';
$svc=new BusinessAdminService($pdo,'/srv/state/private/business-references');$report=[];
foreach(json_decode(file_get_contents(__DIR__.'/businesses/verified-geolocation.json'),true) as $g){
 try{$q=$pdo->prepare('SELECT b.id FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? AND t.display_name=?');$q->execute([$g['destination_slug'],$g['name']]);$id=(int)$q->fetchColumn();$b=$svc->find($id);if(!$b||$b['record_origin']!=='public_import'||$b['claim_status']!=='NO_RECLAMADO'||$b['subscription']['plan_code']!=='free')throw new RuntimeException('Protected identity');
 if($b['publication_status']!=='published'||(!empty($g['requires_location_update'])&&(abs((float)$b['latitude']-$g['latitude'])>0.000001||abs((float)$b['longitude']-$g['longitude'])>0.000001))){$input=array_replace($b,['latitude'=>$g['latitude'],'longitude'=>$g['longitude'],'location_map_url'=>$g['map_url'],'location_confirmed'=>1,'plan_code'=>'free']);$svc->save($input);$result=(new BusinessBulkPublicationService($pdo))->publish([$id]);}else $result=['already_published'=>true];
 $report[]=['name'=>$g['name'],'id'=>$id,'result'=>$result];}catch(Throwable $e){$report[]=['name'=>$g['name'],'error'=>$e->getMessage()];}
}
file_put_contents(__DIR__.'/located-businesses-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

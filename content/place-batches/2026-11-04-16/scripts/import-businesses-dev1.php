<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local dev1 only.');
$c=require '/srv/state/private/config.php';if(empty($c['local_test'])||!str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Wrong database.');
$root=dirname(__DIR__);$mode=$argv[1]??'';if(!in_array($mode,['prepare','publish'],true))throw new RuntimeException('Choose prepare or publish.');
foreach(['BusinessPublicImporter','BusinessAdminService','BusinessSubscriptionAdminService','BusinessBulkPublicationService','BusinessResearchContactImporter'] as $cl)require_once '/srv/portal/app/'.$cl.'.php';
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$svc=new BusinessAdminService($p,'/srv/state/private/business-references');
$payload=json_decode(file_get_contents($root.'/release-input.json'),true,512,JSON_THROW_ON_ERROR);$exceptions=json_decode(file_get_contents($root.'/business-location-exceptions.json'),true,512,JSON_THROW_ON_ERROR);$allowed=[];foreach($exceptions['records'] as $x)$allowed[$x['place'].'|'.$x['name']]=true;
$reportPath=$root.'/dev1-business-receipt.json';$report=is_file($reportPath)?json_decode(file_get_contents($reportPath),true,512,JSON_THROW_ON_ERROR):['environment'=>'dev1','production_touched'=>false,'businesses'=>[]];
$find=$p->prepare('SELECT b.id,b.public_id,b.publication_status,b.record_origin,b.claim_status FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? AND t.display_name=?');
$exceptionIds=[];
foreach($payload['places'] as $d){$slug=$d['destination']['slug'];$research=json_decode(file_get_contents($root.'/'.$d['research_file']),true,512,JSON_THROW_ON_ERROR);$known=[];foreach($research['businesses'] as $b)$known[$b['name']]=$b;
 if($mode==='prepare'){
  $package=$d['business_package'];$new=[];foreach($package['businesses'] as $b){$find->execute([$slug,$b['name']]);$existing=$find->fetch();if(!$existing)$new[]=$b;elseif(!isset($report['businesses'][$slug.'|'.$b['name']]))throw new RuntimeException('Business exists outside batch receipt: '.$slug.' '.$b['name']);}
  if($new){$package['businesses']=$new;BusinessPublicImporter::import($p,$package);foreach($new as $b){$find->execute([$slug,$b['name']]);$row=$find->fetch();$report['businesses'][$slug.'|'.$b['name']]=['id'=>(int)$row['id'],'public_id'=>$row['public_id'],'place'=>$slug,'name'=>$b['name'],'state'=>'imported'];}file_put_contents($reportPath,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
 }
 foreach($d['business_package']['businesses'] as $b){$key=$slug.'|'.$b['name'];$find->execute([$slug,$b['name']]);$row=$find->fetch();if(!$row||($report['businesses'][$key]['id']??0)!==(int)$row['id']||$row['record_origin']!=='public_import'||$row['claim_status']!=='NO_RECLAMADO')throw new RuntimeException('Batch business identity not preserved.');
  $raw=$known[$b['name']];$g=$raw['geolocation']??null;$exempt=isset($allowed[$research['name'].'|'.$b['name']]);if($exempt)$exceptionIds[]=['public_id'=>$row['public_id'],'destination_slug'=>$slug,'name'=>$b['name'],'reason'=>'Owner approved no coordinates for this named business on 2026-11-04'];
  if($mode==='prepare'){
   try{$existing=$svc->find((int)$row['id']);$input=array_replace($existing,['address_text'=>$raw['address']??'','latitude'=>$g['latitude']??null,'longitude'=>$g['longitude']??null,'location_map_url'=>'','location_confirmed'=>0,'plan_code'=>'free']);$svc->save($input);$report['businesses'][$key]['state']='prepared';$report['businesses'][$key]['has_coordinates']=$g!==null;$report['businesses'][$key]['location_exception']=$exempt;unset($report['businesses'][$key]['error']);}
   catch(Throwable $e){$report['businesses'][$key]['state']='location_review_required';$report['businesses'][$key]['error']=$e->getMessage();}
  }elseif($row['publication_status']==='published'){$report['businesses'][$key]['state']='published';}
  elseif(($report['businesses'][$key]['state']??'')!=='location_review_required'){
   $result=(new BusinessBulkPublicationService($p))->publish([(int)$row['id']]);$report['businesses'][$key]['publication_result']=$result;$report['businesses'][$key]['state']=$result['published']?'published':'publication_review_required';
  }
  file_put_contents($reportPath,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
 }
 echo $slug.' '.$mode." complete\n";
}
if(count($exceptionIds)!==47)throw new RuntimeException('Expected exactly 47 approved business exceptions.');
file_put_contents($root.'/dev1-business-exception-ids.json',json_encode(['environment'=>'dev1','exceptions'=>$exceptionIds],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
$counts=array_count_values(array_column($report['businesses'],'state'));echo json_encode($counts,JSON_UNESCAPED_UNICODE)."\n";

<?php
declare(strict_types=1);
// Explicitly local, synthetic presentation fixture. Never included in a release package.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1'||getenv('EL_DEMO_ENV')!=='dev1')throw new RuntimeException('dev1 CLI only');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic dev database required');
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tables=['el_basic_page_daily','el_basic_entity_daily','el_basic_country_daily'];
foreach($tables as $table){
 $exists=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$exists->execute([$table]);
 if($exists->fetchColumn()&&(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()>0)throw new RuntimeException('Existing metrics preserved; refusing to mix fixture: '.$table);
}
require '/srv/portal/app/analytics/BasicAudienceService.php';
(new BasicAudienceService($pdo,'/srv/portal/public'))->install();
$today=new DateTimeImmutable('today',new DateTimeZone('America/Bogota'));
$start=$today->modify('-182 days');
$departments=['05','08','11','13','15','17','20','23','25','41','44','47','54','66','68','70','73','76','85'];
$pdo->beginTransaction();
try{
 $page=$pdo->prepare('INSERT INTO el_basic_page_daily(page_day,scope_code,page_views) VALUES(?,?,?)');
 $country=$pdo->prepare('INSERT INTO el_basic_country_daily(page_day,scope_code,destination_slug,country_code,page_views) VALUES(?,?,?,?,?)');
 $total=0;
 for($i=0;$i<=182;$i++){
  $date=$start->modify('+'.$i.' days');$day=$date->format('Y-m-d');$national=['CO'=>0,'BR'=>0,'US'=>0];
  foreach($departments as $index=>$dep){
   $n=8+(int)floor($i*.18)+(($i*17+$index*13)%29)+((int)$date->format('N')>=6?18:0);
   if($i===182)$n=(int)ceil($n*.4);
   $br=(int)floor($n*.16);$us=(int)floor($n*.12);$countries=['CO'=>$n-$br-$us,'BR'=>$br,'US'=>$us];
   $page->execute([$day,$dep,$n]);
   foreach($countries as $code=>$count){$country->execute([$day,$dep,'',$code,$count]);$national[$code]+=$count;}
  }
  $count=array_sum($national);$page->execute([$day,'CO',$count]);$total+=$count;
  foreach($national as $code=>$count)$country->execute([$day,'CO','',$code,$count]);
 }
 $setting=$pdo->prepare('INSERT INTO el_basic_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
 foreach(['started_at'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'entities_started_at'=>$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'demo_fixture'=>'coverage-20261104','enabled'=>'1'] as $k=>$v)$setting->execute([$k,$v]);
 $pdo->commit();
}catch(Throwable $e){$pdo->rollBack();throw $e;}
// Public release is mounted read-only; add the visible demo banner on the host release, not here.
echo json_encode(['fixture'=>'SIMULATED','days'=>183,'countries'=>['CO','BR','US'],'national_page_views'=>$total,'production_touched'=>false],JSON_PRETTY_PRINT)."\n";

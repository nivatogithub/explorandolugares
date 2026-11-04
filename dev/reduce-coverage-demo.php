<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1'||getenv('EL_DEMO_ENV')!=='dev1')throw new RuntimeException('dev1 only');
$c=require '/srv/state/private/config.php';
if(empty($c['local_test'])||!str_contains($c['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Local synthetic database required');
$p=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$marker=$p->query("SELECT setting_value FROM el_basic_settings WHERE setting_key='demo_fixture'")->fetchColumn();
if($marker==='coverage-20261104-small'){echo "Already reduced\n";exit;}
if($marker!=='coverage-20261104')throw new RuntimeException('Expected known fixture not found');
$start=(new DateTimeImmutable('2026-11-04',new DateTimeZone('America/Bogota')))->modify('-182 days');
$deps=['05','08','11','13','15','17','20','23','25','41','44','47','54','66','68','70','73','76','85'];
$p->beginTransaction();
try{
 $page=$p->prepare('UPDATE el_basic_page_daily SET page_views=page_views-?+? WHERE page_day=? AND scope_code=? AND page_views>=?');
 $country=$p->prepare("UPDATE el_basic_country_daily SET page_views=page_views-?+? WHERE page_day=? AND scope_code=? AND destination_slug='' AND country_code=? AND page_views>=?");
 $apply=function($day,$dep,$old,$new)use($page,$country){
  $a=array_sum($old);$b=array_sum($new);$page->execute([$a,$b,$day,$dep,$a]);if($page->rowCount()!==1)throw new RuntimeException('Page fixture changed; stop');
  foreach($old as $code=>$n){$country->execute([$n,$new[$code],$day,$dep,$code,$n]);if($country->rowCount()!==1&&$n!==$new[$code])throw new RuntimeException('Country fixture changed; stop');}
 };
 for($i=0;$i<=182;$i++){
  $date=$start->modify('+'.$i.' days');$day=$date->format('Y-m-d');$oldTotal=$newTotal=['CO'=>0,'BR'=>0,'US'=>0];
  foreach($deps as $j=>$dep){
   $n=8+(int)floor($i*.18)+(($i*17+$j*13)%29)+((int)$date->format('N')>=6?18:0);if($i===182)$n=(int)ceil($n*.4);
   $br=(int)floor($n*.16);$us=(int)floor($n*.12);$old=['CO'=>$n-$br-$us,'BR'=>$br,'US'=>$us];$new=['CO'=>0,'BR'=>0,'US'=>0];
   for($v=0;$v<max(1,(int)floor($n/19));$v++){$bucket=($i*7+$j*13+$v*31)%100;$new[$bucket<72?'CO':($bucket<88?'BR':'US')]++;}
   $apply($day,$dep,$old,$new);foreach($old as $code=>$n){$oldTotal[$code]+=$n;$newTotal[$code]+=$new[$code];}
  }
  $apply($day,'CO',$oldTotal,$newTotal);
 }
 $p->exec("UPDATE el_basic_settings SET setting_value='coverage-20261104-small' WHERE setting_key='demo_fixture'");$p->commit();echo "Reduced known simulated contribution only; other counts preserved.\n";
}catch(Throwable $e){$p->rollBack();throw $e;}

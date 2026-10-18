<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/DatabaseHealthPeriod.php';
function periodCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$dir=sys_get_temp_dir().'/database-health-'.bin2hex(random_bytes(8));
$reading=['checked_at'=>'2026-10-18T12:00:00+00:00','db'=>['Uptime'=>'1000','Connections'=>'612562','Aborted_connects'=>'12','Threads_connected'=>'5']];
try{
 periodCheck(DatabaseHealthPeriod::read($dir,$reading,'db')['status']==='none','No reference must remain unavailable');
 DatabaseHealthPeriod::start($dir,$reading,'db');
 $same=DatabaseHealthPeriod::read($dir,$reading,'db');periodCheck($same['status']==='active'&&$same['deltas']['Connections']===0,'New period must start at zero');
 periodCheck(!isset($same['deltas']['Threads_connected']),'Instantaneous gauges cannot be subtracted');
 $next=['checked_at'=>'2026-10-18T12:01:00+00:00','db'=>['Uptime'=>'1060','Connections'=>'612572','Aborted_connects'=>'14']];
 $delta=DatabaseHealthPeriod::read($dir,$next,'db');periodCheck($delta['deltas']['Connections']===10&&$delta['deltas']['Aborted_connects']===2&&$delta['seconds']===60,'Compute elapsed deltas, not historical totals');
 periodCheck(!isset($delta['deltas']['Slow_queries']),'Missing counters must not pretend to be zero');
 periodCheck(DatabaseHealthPeriod::read($dir,$next,'other-db')['status']==='none','Do not reuse another database reference');
 $restart=$next;$restart['db']['Uptime']='5';periodCheck(DatabaseHealthPeriod::read($dir,$restart,'db')['status']==='reset','Restart must invalidate period');
 $flush=$next;$flush['db']['Aborted_connects']='0';periodCheck(DatabaseHealthPeriod::read($dir,$flush,'db')['status']==='reset','Flushed counter must invalidate comparison');
 $late=$next;$late['checked_at']='2026-10-19T12:01:00+00:00';periodCheck(DatabaseHealthPeriod::read($dir,$late,'db')['status']==='reset','Detect restart even if uptime is higher than initial reference');
 DatabaseHealthPeriod::start($dir,$next,'db');periodCheck(DatabaseHealthPeriod::read($dir,$next,'db')['deltas']['Connections']===0,'Replace period without accumulating snapshots');
 periodCheck(count(glob($dir.'/*'))===1&&(fileperms($dir.'/period.json')&0777)===0600,'Keep one private snapshot');
 file_put_contents($dir.'/period.json','broken');periodCheck(DatabaseHealthPeriod::read($dir,$next,'db')['status']==='none','Corrupt reference must not produce statistics');
 $rejected=false;try{DatabaseHealthPeriod::start($dir,['checked_at'=>$next['checked_at'],'db'=>[]],'db');}catch(RuntimeException){$rejected=true;}periodCheck($rejected,'Unavailable global metrics cannot start a period');
}finally{if(is_file($dir.'/period.json'))unlink($dir.'/period.json');if(is_dir($dir))rmdir($dir);}
echo "PASS: private observation periods, deltas, unavailable metrics, restart/flush detection and isolation\n";

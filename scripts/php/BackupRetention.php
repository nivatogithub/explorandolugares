<?php
declare(strict_types=1);
/** Retention of tool-owned snapshot files only. This class never opens a database. */
final class BackupRetention
{
 public static function run(string $directory,string $remoteDirectory,bool $apply=false,?int $now=null):array {
  $now??=time();$directory=rtrim($directory,'/');$remoteDirectory=rtrim($remoteDirectory,'/');
  if(is_link($directory)||!is_dir($directory))return ['policy'=>['hourly'=>48,'daily'=>30,'monthly'=>12],'retained'=>0,'eligible'=>0,'deleted'=>0,'ignored'=>0,'applied'=>false];
  $lockPath=$directory.'/.retention.lock';if(is_link($lockPath))throw new RuntimeException('Bloqueo de respaldo inválido.');
  $lock=fopen($lockPath,'c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('La conservación ya está en ejecución.');chmod($lockPath,0600);
  try{
   $valid=[];$ignored=0;
   foreach(glob($directory.'/db-*.sql.gz')?:[] as $file){
    $name=basename($file);if(!preg_match('/^db-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.sql\.gz$/D',$name)||is_link($file)||!is_file($file)){$ignored++;continue;}
    $prefix=substr($file,0,-7);$metas=array_values(array_filter([$prefix.'.json',substr($file,0,-3).'.json'],'is_file'));
    if(count($metas)!==1||is_link($metas[0])){$ignored++;continue;}$meta=$metas[0];
    try{$data=json_decode(file_get_contents($meta),true,32,JSON_THROW_ON_ERROR);}catch(Throwable){$ignored++;continue;}
    $stamp=strtotime((string)($data['created_at']??''));
    if(!$stamp||$stamp>$now+300||($data['remote_path']??'')!==$remoteDirectory.'/'.$name||($data['transactional']??false)!==true||!is_int($data['tables']??null)||$data['tables']<1||!preg_match('/^[a-f0-9]{64}$/D',(string)($data['sha256']??''))||filesize($file)!==($data['bytes']??-1)||!hash_equals($data['sha256'],hash_file('sha256',$file))){$ignored++;continue;}
    $valid[]=['file'=>$file,'meta'=>$meta,'time'=>$stamp,'hash'=>$data['sha256']];
   }
   usort($valid,static fn($a,$b)=>[$b['time'],$b['file']]<=>[$a['time'],$a['file']]);
   $keep=[];$hours=[];$days=[];$months=[];
   $hourStart=intdiv($now,3600)*3600-47*3600;$dayStart=strtotime(gmdate('Y-m-d',$now).' UTC')-29*86400;
   $monthStart=(new DateTimeImmutable(gmdate('Y-m-01',$now).'T00:00:00Z'))->modify('-11 months')->getTimestamp();
   foreach($valid as $v){
    $h=gmdate('Y-m-d-H',$v['time']);$d=gmdate('Y-m-d',$v['time']);$m=gmdate('Y-m',$v['time']);$retain=false;
    if($v['time']>=$hourStart&&count($hours)<48&&!isset($hours[$h])){$hours[$h]=true;$retain=true;}
    if($v['time']>=$dayStart&&count($days)<30&&!isset($days[$d])){$days[$d]=true;$retain=true;}
    if($v['time']>=$monthStart&&count($months)<12&&!isset($months[$m])){$months[$m]=true;$retain=true;}
    if($retain)$keep[$v['file']]=true;
   }
   // Always preserve the newest verified copy, even if the scheduler stopped.
   if($valid)$keep[$valid[0]['file']]=true;
   $remove=array_values(array_filter($valid,static fn($v)=>!isset($keep[$v['file']])));
   $fresh=$valid&&$valid[0]['time']>=$now-7200;
   $result=['policy'=>['hourly'=>48,'daily'=>30,'monthly'=>12],'timezone'=>'UTC','retained'=>count($keep),'eligible'=>count($remove),'deleted'=>0,'ignored'=>$ignored,'recent_verified_backup'=>(bool)$fresh,'applied'=>false];
   if(!$apply)return $result;
   if($remove&&!$fresh)throw new RuntimeException('Sin respaldo reciente verificado: no se elimina ninguna copia.');
   if($remove){
    $gz=gzopen($valid[0]['file'],'rb');if(!$gz)throw new RuntimeException('No se pudo verificar la copia reciente.');
    $crc=hash_init('crc32b');$size=0;
    try{while(!gzeof($gz)){$chunk=gzread($gz,1048576);if($chunk===false)throw new RuntimeException('Copia reciente ilegible.');hash_update($crc,$chunk);$size+=strlen($chunk);}}finally{gzclose($gz);}
    $raw=fopen($valid[0]['file'],'rb');if(!$raw)throw new RuntimeException('Copia reciente no accesible.');
    try{if(fread($raw,2)!=="\x1f\x8b"||fseek($raw,-8,SEEK_END)!==0)throw new RuntimeException('Formato gzip no válido.');$footer=unpack('Vcrc/Vsize',fread($raw,8));}finally{fclose($raw);}
    if(hash_final($crc)!==sprintf('%08x',$footer['crc'])||($size&0xffffffff)!==$footer['size'])throw new RuntimeException('La integridad gzip de la copia reciente no coincide.');
   }
   foreach($remove as $v){
    if(is_link($v['file'])||is_link($v['meta'])||!hash_equals($v['hash'],hash_file('sha256',$v['file'])))throw new RuntimeException('El respaldo cambió durante la limpieza.');
    if(!unlink($v['file']))throw new RuntimeException('No se pudo retirar una copia vencida.');
    if(!unlink($v['meta']))throw new RuntimeException('Se retiró la copia, pero quedó su metadato pendiente.');
    $result['deleted']++;
   }
   $result['applied']=true;return $result;
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
}

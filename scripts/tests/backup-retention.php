<?php
declare(strict_types=1);
require dirname(__DIR__).'/php/BackupRetention.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$base=sys_get_temp_dir().'/el-retention-'.bin2hex(random_bytes(8));mkdir($base,0700);$now=strtotime('2026-10-19T17:00:00Z');
function fixture(string $dir,int $time,int $id):string {
 $prefix=$dir.'/db-'.gmdate('Ymd-His',$time).'-'.sprintf('%08x',$id);$file=$prefix.'.sql.gz';$data=gzencode('-- prueba aislada '.$id);file_put_contents($file,$data);
 file_put_contents($prefix.'.json',json_encode(['created_at'=>gmdate('c',$time),'remote_path'=>$file,'transactional'=>true,'tables'=>69,'bytes'=>strlen($data),'sha256'=>hash('sha256',$data)]));return $file;
}
try{
 $dir=$base.'/generations';mkdir($dir,0700);$latest=fixture($dir,$now,1);
 for($i=1;$i<=1000;$i++)fixture($dir,$now-$i*86400,$i+1);
 for($i=1;$i<=72;$i++)fixture($dir,$now-$i*3600,$i+2000);
 file_put_contents($dir.'/ajeno.txt','no tocar');symlink($dir.'/ajeno.txt',$dir.'/db-20261019-160000-abcdef01.sql.gz');
 $preview=BackupRetention::run($dir,$dir,false,$now);check($preview['retained']<=90&&$preview['eligible']>900,'No limita generaciones');
 $done=BackupRetention::run($dir,$dir,true,$now);check($done['deleted']===$preview['eligible']&&is_file($latest),'Limpieza incorrecta');
 check(file_get_contents($dir.'/ajeno.txt')==='no tocar'&&is_link($dir.'/db-20261019-160000-abcdef01.sql.gz'),'Tocó un archivo ajeno');
 check(BackupRetention::run($dir,$dir,true,$now)['deleted']===0,'No es idempotente');
 $dir=$base.'/stale';mkdir($dir,0700);fixture($dir,$now-100*86400,1);fixture($dir,$now-100*86400-10,2);
 try{BackupRetention::run($dir,$dir,true,$now);throw new LogicException('Limpió sin copia reciente');}catch(RuntimeException $e){}
 check(count(glob($dir.'/*.sql.gz'))===2,'No conservó copias antiguas');
 $dir=$base.'/unknown';mkdir($dir,0700);$fresh=fixture($dir,$now,1);$older=fixture($dir,$now-400*86400,2);file_put_contents(substr($older,0,-7).'.json','{}');
 check(BackupRetention::run($dir,$dir,true,$now)['ignored']===1&&is_file($older),'Borró metadatos no verificados');
 $dir=$base.'/corrupt';mkdir($dir,0700);$broken=fixture($dir,$now,1);fixture($dir,$now-400*86400,2);
 $data=file_get_contents($broken);$data=substr($data,0,-4).pack('V',9999);file_put_contents($broken,$data);$meta=substr($broken,0,-7).'.json';$m=json_decode(file_get_contents($meta),true);$m['sha256']=hash('sha256',$data);file_put_contents($meta,json_encode($m));
 try{@BackupRetention::run($dir,$dir,true,$now);throw new LogicException('Limpió con gzip inválido');}catch(RuntimeException $e){}
 check(count(glob($dir.'/*.sql.gz'))===2,'Eliminó sin integridad gzip');
 echo "OK: generaciones 48/30/12, máximo 90 sin duplicados, idempotencia, seguridad ante copias antiguas/corruptas, metadatos ajenos y enlaces simbólicos.\n";
}finally{
 foreach(glob($base.'/*')?:[] as $dir){foreach(glob($dir.'/{*,.*}',GLOB_BRACE)?:[] as $f){if(in_array(basename($f),['.','..'],true))continue;if(is_file($f)||is_link($f))unlink($f);}rmdir($dir);}rmdir($base);
}

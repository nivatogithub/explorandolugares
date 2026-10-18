<?php
declare(strict_types=1);
/** A private reference reading, never a reset of MariaDB's shared counters. */
final class DatabaseHealthPeriod
{
    public const COUNTERS=['Connections','Connection_errors_max_connections','Aborted_connects','Aborted_clients','Slow_queries','Innodb_row_lock_waits','Innodb_row_lock_time','Questions'];

    private static function values(array $health): array
    {
        $values=[];
        foreach(array_merge(['Uptime'],self::COUNTERS) as $key){
            $v=filter_var($health['db'][$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if($v!==false)$values[$key]=$v;
        }
        return $values;
    }
    public static function start(string $directory,array $health,string $databaseIdentity): void
    {
        $values=self::values($health);
        if(!isset($values['Uptime'],$values['Connections']))throw new RuntimeException('El servidor no permite leer los contadores necesarios para iniciar un período.');
        if(is_link($directory)||is_link($directory.'/period.json'))throw new RuntimeException('Ruta de diagnóstico no válida.');
        if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('No se pudo guardar el período.');
        $data=['checked_at'=>$health['checked_at'],'database'=>$databaseIdentity,'values'=>$values];
        $tmp=tempnam($directory,'.period-');if($tmp===false)throw new RuntimeException('No se pudo guardar el período.');
        try{chmod($tmp,0600);$json=json_encode($data,JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$json,LOCK_EX)!==strlen($json)||!rename($tmp,$directory.'/period.json'))throw new RuntimeException('No se pudo guardar el período.');}
        finally{if(is_file($tmp))unlink($tmp);}
    }
    public static function read(string $directory,array $health,string $databaseIdentity): array
    {
        $none=['status'=>'none','started_at'=>null,'seconds'=>null,'deltas'=>[]];$path=$directory.'/period.json';
        if(is_link($directory)||is_link($path)||!is_file($path))return $none;
        try{
            if(filesize($path)>16384)return $none;
            $v=json_decode(file_get_contents($path),true,8,JSON_THROW_ON_ERROR);
            if(!is_array($v)||($v['database']??null)!==$databaseIdentity||!is_array($v['values']??null))return $none;
            $start=is_string($v['checked_at']??null)?strtotime($v['checked_at']):false;$now=strtotime($health['checked_at']);
            if($start===false||$now===false||$start>$now)return $none;
            $current=self::values($health);$old=self::values(['db'=>$v['values']]);
            if(!isset($current['Uptime'],$old['Uptime'],$old['Connections']))return $none;
            $result=['status'=>'active','started_at'=>$v['checked_at'],'seconds'=>$now-$start,'deltas'=>[]];
            if(abs(($current['Uptime']-$old['Uptime'])-($now-$start))>120||$current['Uptime']<$old['Uptime']){$result['status']='reset';return $result;}
            foreach(self::COUNTERS as $key){
                if(!isset($current[$key],$old[$key]))continue;
                if($current[$key]<$old[$key]){$result['status']='reset';$result['deltas']=[];return $result;}
                $result['deltas'][$key]=$current[$key]-$old[$key];
            }
            return $result;
        }catch(Throwable){return $none;}
    }
}

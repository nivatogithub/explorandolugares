<?php
declare(strict_types=1);
/** Read-only operational diagnostics. Unavailable hosting metrics are never invented. */
final class SystemHealth
{
    public static function inspect(PDO $pdo, array $config, string $path): array
    {
        $start=hrtime(true);$pdo->query('SELECT 1')->fetchColumn();
        $result=['checked_at'=>gmdate('c'),'latency_ms'=>round((hrtime(true)-$start)/1e6,2),'php'=>PHP_VERSION,'storage'=>null,'db'=>[],'variables'=>[],'unavailable'=>[]];
        $quota=$config['hosting_storage']['quota_bytes']??null;$used=$config['hosting_storage']['used_bytes']??null;$date=$config['hosting_storage']['measured_at']??null;
        if(is_numeric($quota)&&$quota>0&&is_numeric($used)&&$used>=0&&is_string($date)&&strtotime($date)!==false){$result['storage']=['quota'=>(float)$quota,'used'=>(float)$used,'percent'=>round(100*$used/$quota,1),'date'=>$date,'stale'=>time()-strtotime($date)>86400];}
        $free=@disk_free_space($path);$result['filesystem_free']=$free===false?null:$free;
        if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){
            foreach(['status'=>"SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Threads_running','Max_used_connections','Connections','Aborted_connects','Aborted_clients','Connection_errors_max_connections','Slow_queries','Innodb_row_lock_waits','Innodb_row_lock_time','Questions')",'variables'=>"SHOW GLOBAL VARIABLES WHERE Variable_name IN ('max_connections','max_user_connections','long_query_time','slow_query_log','innodb_buffer_pool_size')"] as $key=>$sql){try{$values=$pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);$result[$key==='status'?'db':$key]=$values;}catch(Throwable){$result['unavailable'][]=$key;}}
        }
        return $result;
    }
    public static function bytes(?float $bytes): string {if($bytes===null)return 'No disponible';foreach([1073741824=>'GB',1048576=>'MB',1024=>'KB'] as $unit=>$label)if($bytes>=$unit)return number_format($bytes/$unit,2,',','.').' '.$label;return number_format($bytes,0,',','.').' bytes';}
}

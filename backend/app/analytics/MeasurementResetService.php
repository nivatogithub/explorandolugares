<?php
declare(strict_types=1);
/** Explicit operator action. Never resets accounts, catalogue, subscriptions or consent. */
final class MeasurementResetService
{
    public function __construct(private PDO $db) {}
    private function exists(string $table):bool {
        try{$this->db->query('SELECT 1 FROM '.$table.' LIMIT 0');return true;}
        catch(PDOException $e){if(($e->errorInfo[0]??'')==='42S02'||str_contains($e->getMessage(),'no such table:'))return false;throw $e;}
    }
    public function reset(string $reason,string $actor):array {
        if(!preg_match('/^[a-z0-9_-]{1,64}$/D',$reason)||!preg_match('/^[a-zA-Z0-9_@.:-]{1,64}$/D',$actor))throw new InvalidArgumentException('Identifica el motivo y el responsable del reinicio.');
        $counters=['el_measurement_events','el_measurement_sessions','el_measurement_limits','el_basic_page_daily','el_basic_entity_daily'];
        $settings=['el_measurement_settings','el_basic_settings'];$protected=['accounts','businesses','business_subscriptions','business_account_memberships','el_coverage_places','el_coverage_departments','el_coverage_units'];
        $existing=[];foreach(array_merge($counters,$settings,$protected) as $t)if($this->exists($t))$existing[$t]=true;
        $now=gmdate('Y-m-d H:i:s');$report=['reset_at'=>$now,'reason'=>$reason,'counters'=>[],'protected_counts'=>[]];
        $this->db->beginTransaction();
        try{
            foreach($protected as $t)if(isset($existing[$t]))$report['protected_counts'][$t]=(int)$this->db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();
            foreach($counters as $t)if(isset($existing[$t])){$before=(int)$this->db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();$this->db->exec('DELETE FROM '.$t);$after=(int)$this->db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();if($after!==0)throw new RuntimeException('No se pudo reiniciar el contador.');$report['counters'][$t]=['before'=>$before,'after'=>$after];}
            $mysql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
            foreach($settings as $t)if(isset($existing[$t])){
                $sql=$mysql?'INSERT INTO '.$t.'(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)':'INSERT INTO '.$t.'(setting_key,setting_value) VALUES(?,?) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value';
                $q=$this->db->prepare($sql);$values=['started_at'=>$now,'last_reset_at'=>$now,'last_reset_reason'=>$reason,'last_reset_actor'=>$actor];if($t==='el_basic_settings')$values['entities_started_at']=$now;foreach($values as $k=>$v)$q->execute([$k,$v]);
            }
            foreach($report['protected_counts'] as $t=>$before)if((int)$this->db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn()!==$before)throw new RuntimeException('Cambió el catálogo durante el reinicio; vuelve a intentarlo.');
            $this->db->commit();return $report;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}

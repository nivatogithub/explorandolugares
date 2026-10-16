<?php
declare(strict_types=1);
/** Additive catalog sync. Does not change publication or analytics events/settings. */
final class CoverageCatalogService {
    public function __construct(private PDO $pdo) {}
    private function upsert(string $table,array $row,string $key):void {
        $columns=array_keys($row);$updates=array_values(array_filter($columns,fn($c)=>$c!==$key));
        $mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
        $tail=$mysql?' ON DUPLICATE KEY UPDATE '.implode(',',array_map(fn($c)=>"$c=VALUES($c)",$updates)):' ON CONFLICT('.$key.') DO UPDATE SET '.implode(',',array_map(fn($c)=>"$c=excluded.$c",$updates));
        $q=$this->pdo->prepare('INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')'.$tail);$q->execute(array_values($row));
    }
    public function synchronize(array $plan):void {
        $mysql=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$suffix=$mysql?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS el_coverage_scope (place_id VARCHAR(200) PRIMARY KEY,in_work_scope INTEGER NOT NULL,original_priority INTEGER NULL,prepared_percent DECIMAL(6,2) NOT NULL,depth_percent DECIMAL(6,2) NOT NULL,publishable INTEGER NOT NULL,scope_version VARCHAR(100) NOT NULL)'.$suffix);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS el_coverage_registry (code VARCHAR(5) PRIMARY KEY,department_code VARCHAR(2) NOT NULL,name VARCHAR(180) NOT NULL,original_priority INTEGER NULL,in_work_scope INTEGER NOT NULL,tracked_place_count INTEGER NOT NULL)'.$suffix);
        $ownsTransaction=!$this->pdo->inTransaction();if($ownsTransaction)$this->pdo->beginTransaction();
        try {
        if(isset($plan['scope_version'])){
            $this->pdo->exec('UPDATE el_coverage_scope SET in_work_scope=0');
            $this->pdo->exec('UPDATE el_coverage_registry SET in_work_scope=0');
        }
        foreach($plan['departments'] as $d){
            $this->upsert('el_coverage_departments',['code'=>$d['code'],'name'=>$d['name'],'priority'=>$d['priority']??4-$d['weight'],'weight'=>$d['weight']],'code');
            foreach($d['places'] as $p){
                if(($p['original_priority']??null)===4 && ($p['in_work_scope']??false))throw new InvalidArgumentException('Categoría 4 conservada, fuera de trabajos');

                $this->upsert('el_coverage_places',['id'=>$p['id'],'department_code'=>$d['code'],'name'=>$p['name'],'slug'=>$p['slug'],'municipality_code'=>$p['municipality_code'],'parent_place_id'=>$p['parent_place_id'],'place_type'=>$p['type'],'priority'=>$p['priority'],'weight'=>$p['weight'],'preparing'=>(int)$p['preparing']],'id');
                if(array_key_exists('in_work_scope',$p))$this->upsert('el_coverage_scope',['place_id'=>$p['id'],'in_work_scope'=>(int)$p['in_work_scope'],'original_priority'=>$p['original_priority']??null,'prepared_percent'=>$p['prepared_percent']??0,'depth_percent'=>$p['depth_percent']??0,'publishable'=>(int)($p['publishable']??false),'scope_version'=>$plan['scope_version']??'unknown'],'place_id');
            }
        }
        foreach($plan['measurement_inventory']??[] as $g)$this->upsert('el_coverage_registry',['code'=>$g['code'],'department_code'=>$g['department_code'],'name'=>$g['name'],'original_priority'=>$g['original_priority']??null,'in_work_scope'=>(int)$g['in_work_scope'],'tracked_place_count'=>$g['tracked_place_count']],'code');
        // Keep rows absent from a newer file, but remove them from the target rather than deleting data.
        if(isset($plan['scope_version'])){
            $this->pdo->prepare('UPDATE el_coverage_scope SET in_work_scope=0 WHERE scope_version<>?')->execute([$plan['scope_version']]);
            $this->upsert('el_measurement_settings',['setting_key'=>'catalog_version','setting_value'=>$plan['universe_version']],'setting_key');
            $this->upsert('el_measurement_settings',['setting_key'=>'coverage_scope_version','setting_value'=>$plan['scope_version']],'setting_key');
        }
        if($ownsTransaction)$this->pdo->commit();
        }catch(Throwable $e){if($ownsTransaction && $this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function enrich(array $plan):array {
        try{
            $rows=$this->pdo->query('SELECT * FROM el_coverage_scope')->fetchAll(PDO::FETCH_ASSOC);$byid=[];foreach($rows as $r)$byid[$r['place_id']]=$r;
            if(!$rows)return $plan;
            foreach($plan['departments'] as &$d)foreach($d['places'] as &$p)if(isset($byid[$p['id']])){
                $r=$byid[$p['id']];$p['in_work_scope']=(bool)$r['in_work_scope'];$p['original_priority']=$r['original_priority']===null?null:(int)$r['original_priority'];$p['prepared_percent']=(float)$r['prepared_percent'];$p['depth_percent']=(float)$r['depth_percent'];$p['publishable']=(bool)$r['publishable'];
            }unset($d,$p);
            $plan['scope_version']=(string)$this->pdo->query("SELECT setting_value FROM el_measurement_settings WHERE setting_key='coverage_scope_version'")->fetchColumn();
            $plan['measurement_inventory']=$this->pdo->query('SELECT code,department_code,name,original_priority,in_work_scope,tracked_place_count FROM el_coverage_registry ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
            $version=$this->pdo->query("SELECT setting_value FROM el_measurement_settings WHERE setting_key='catalog_version'")->fetchColumn();if($version)$plan['universe_version']=$version;
        }catch(Throwable){ /* Existing installations remain readable; unknown scope is not a zero target. */ }
        return $plan;
    }
}

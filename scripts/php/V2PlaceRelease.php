<?php
declare(strict_types=1);

/** Private deployment operation; never installed as a public application route. */
final class V2PlaceRelease
{
    public function __construct(private PDO $db,private string $app,private string $public,private string $bundle,private string $journal) {}

    private function demand(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
    private function rows(string $table,string $where=''):array {
        $rows=$this->db->query('SELECT * FROM `'.$table.'` '.$where)->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){if($table==='media_assets'){unset($row['responsive_srcset'],$row['responsive_sizes']);}ksort($row);}unset($row);
        return $rows;
    }
    private function fingerprint(array $rows):string {$rows=array_map(static fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$rows);sort($rows);return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));}
    private function protectedSnapshot(array $ids):array {
        $in=implode(',',array_map('intval',$ids['businesses']))?:'0';$media=implode(',',array_map('intval',$ids['media']))?:'0';$accounts=implode(',',array_map('intval',$ids['accounts']))?:'0';
        $rules=['accounts'=>'WHERE id IN ('.$accounts.')','account_roles'=>'WHERE account_id IN ('.$accounts.')','businesses'=>'WHERE id IN ('.$in.')','business_account_memberships'=>'WHERE business_id IN ('.$in.')','media_assets'=>'WHERE id IN ('.$media.')'];
        foreach(['business_translations','business_contacts','business_categories','business_media','business_sources','business_subscriptions'] as $t)$rules[$t]='WHERE business_id IN ('.$in.')';
        $rules['business_subscription_payment_evidence']='WHERE subscription_id IN (SELECT id FROM business_subscriptions WHERE business_id IN ('.$in.'))';
        $out=[];foreach($rules as $table=>$where)$out[$table]=$this->fingerprint($this->rows($table,$where));return $out;
    }
    private function statistics():array {
        $out=[];
        foreach($this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
            if(!str_starts_with($table,'el_basic_')&&!str_starts_with($table,'el_measurement_'))continue;
            $pk=array_column(array_filter($this->db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC),static fn($c)=>$c['Key']==='PRI'),'Field');
            $out[$table]=[];
            foreach($this->rows($table) as $row){$key=json_encode(array_intersect_key($row,array_flip($pk)),JSON_THROW_ON_ERROR);$out[$table][$key]=$row;}
        }return $out;
    }
    private function verifyStatistics(array $before):void {
        $now=$this->statistics();foreach($before as $table=>$rows)foreach($rows as $key=>$row){$this->demand(isset($now[$table][$key]),'Existing statistics row disappeared');foreach($row as $column=>$value){$actual=$now[$table][$key][$column];if(is_numeric($value)&&is_numeric($actual))$this->demand((float)$actual>=(float)$value,'Statistics decreased');else $this->demand($actual===$value,'Statistics identity changed');}}
    }
    private function save(array $state):void {
        $temp=$this->journal.'.writing';file_put_contents($temp,json_encode($state,JSON_THROW_ON_ERROR),LOCK_EX);chmod($temp,0600);rename($temp,$this->journal);
    }
    public function run(string $mode):array {
        $this->demand(in_array($mode,['probe','migrate','apply','verify'],true),'Invalid release operation');
        require_once $this->app.'/ProductionContentTransfer.php';require_once $this->app.'/DatabaseMaintenance.php';
        $manifest=json_decode(file_get_contents($this->bundle.'/manifest.json'),true,128,JSON_THROW_ON_ERROR);
        $hash=hash_file('sha256',$this->bundle.'/public-content.json');$this->demand(hash_equals($manifest['sha256'],$hash),'Content hash mismatch');
        $pack=json_decode(file_get_contents($this->bundle.'/public-content.json'),true,512,JSON_THROW_ON_ERROR);
        $scope=json_decode(file_get_contents($this->bundle.'/scope.json'),true,128,JSON_THROW_ON_ERROR);$slugs=array_merge(...array_column($scope,'slugs'));sort($slugs);$actual=array_keys($pack['editorial']);sort($actual);
        $this->demand($actual===$slugs&&count($actual)===87&&$pack['schema_version']===2&&!in_array('sonson',$slugs,true),'Unexpected release scope');
        $this->demand(count($pack['tables']['businesses'])===2699&&count($manifest['media'])===322,'Unexpected release size');
        foreach($pack['editorial'] as $slug=>$p)$this->demand($p['slug']===$slug&&$p['publication_status']==='published','Unexpected place status');
        foreach($pack['tables']['businesses'] as $b)$this->demand($b['record_origin']==='public_import'&&$b['claim_status']==='NO_RECLAMADO'&&$b['publication_status']==='published','Unexpected business scope');
        $state=is_file($this->journal)?json_decode(file_get_contents($this->journal),true,512,JSON_THROW_ON_ERROR):null;
        if($state)$this->demand(hash_equals($state['content_sha256'],$hash),'A different content release owns this journal');
        $existing=$this->db->query('SELECT id,slug,publication_status FROM destinations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        $sonson=array_values(array_filter($existing,static fn($p)=>$p['slug']==='sonson'));$this->demand(count($sonson)===1&&$sonson[0]['publication_status']==='draft','Sonson must remain an existing draft');
        if(!$state){
            $this->demand(count(array_filter($existing,static fn($p)=>$p['publication_status']==='published'))===20,'Unexpected published baseline; reconcile before import');
            foreach($existing as $p)if(in_array($p['slug'],$slugs,true))$this->demand($p['publication_status']==='draft','Incoming place already published without release journal');
        }
        $ids=$state['ids']??['businesses'=>$this->db->query('SELECT id FROM businesses')->fetchAll(PDO::FETCH_COLUMN),'accounts'=>$this->db->query('SELECT id FROM accounts')->fetchAll(PDO::FETCH_COLUMN),'media'=>$this->db->query('SELECT id FROM media_assets')->fetchAll(PDO::FETCH_COLUMN)];
        $incomingNames=[];foreach($pack['tables']['business_translations'] as $t)if($t['locale']==='es')$incomingNames[(int)$t['business_id']]=$t['display_name'];
        $destinationSlugs=array_column($pack['editorial'],'slug','id');
        foreach($pack['tables']['businesses'] as $b){
            $q=$this->db->prepare('SELECT b.id FROM businesses b WHERE b.public_id=? UNION SELECT b.id FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE d.slug=? AND LOWER(TRIM(t.display_name))=LOWER(TRIM(?))');
            $q->execute([$b['public_id'],$destinationSlugs[$b['destination_id']],$incomingNames[$b['id']]]);$matches=$q->fetchAll(PDO::FETCH_COLUMN);
            $this->demand(count($matches)<=1,'Ambiguous business collision');
            foreach($matches as $id){$this->demand(!in_array($id,$ids['businesses']),'Incoming content collides with an existing production business');$q=$this->db->prepare('SELECT COUNT(*) FROM business_account_memberships WHERE business_id=? AND status="active"');$q->execute([$id]);$this->demand((int)$q->fetchColumn()===0,'Incoming business has acquired an owner; reconcile before resume');}
        }
        $maintenance=new DatabaseMaintenance($this->db,$this->app,$this->public);$migrations=['060-destination-content','061-business-payment-orders','062-place-labels'];
        if($mode==='probe')return ['preflight_passed'=>true,'content_sha256'=>$hash,'existing_businesses'=>count($ids['businesses']),'existing_accounts'=>count($ids['accounts']),'sonson_preserved'=>true,'migration_states'=>array_intersect_key($maintenance->inspect()['migrations'],array_flip($migrations))];
        if(!$state){
            $places=[];foreach($this->rows('destinations') as $p)if(!in_array($p['slug'],$slugs,true)){$places[$p['id']]=$p;}
            $state=['content_sha256'=>$hash,'ids'=>$ids,'protected'=>$this->protectedSnapshot($ids),'statistics'=>$this->statistics(),'places'=>$places,'original_identities'=>$existing,'status'=>'prepared'];$this->save($state);
        }
        $this->demand($this->protectedSnapshot($ids)===$state['protected'],'Existing protected production records changed; do not overwrite');$this->verifyStatistics($state['statistics']);
        if($mode==='migrate'){$maintenance->applyMigrations($migrations);$state['status']='migrated';$this->save($state);}
        if($mode==='apply'){
            foreach($migrations as $m)$this->demand(($maintenance->inspect()['migrations'][$m]??'')==='applied','Missing migration before content import');
            foreach($manifest['media'] as $m)$this->demand($m['path']==='assets/destinos/lote-87/'.$m['sha256'].'.webp'&&is_file($this->public.'/'.$m['path'])&&hash_equals($m['sha256'],hash_file('sha256',$this->public.'/'.$m['path'])),'Missing or different approved image');
            $state['status']='importing';$this->save($state);
            $transfer=new class($this->db,$slugs) extends ProductionContentTransfer {
                public function __construct(PDO $db,private array $scope){parent::__construct($db);}
                public function validate(array $pack):array {$slugs=array_keys($pack['editorial']);sort($slugs);if($slugs!==$this->scope||$pack['schema_version']!==2)throw new RuntimeException('Scope mismatch');return ['places'=>$slugs];}
            };
            $transfer->apply($pack,$this->public);$this->db->exec(file_get_contents($this->bundle.'/heritage.sql'));
        }
        $this->demand($this->protectedSnapshot($ids)===$state['protected'],'Protected business/account records changed');$this->verifyStatistics($state['statistics']);
        foreach($state['places'] as $id=>$before){$q=$this->db->prepare('SELECT * FROM destinations WHERE id=?');$q->execute([$id]);$now=$q->fetch(PDO::FETCH_ASSOC);$this->demand((bool)$now,'Existing place disappeared');
            $regions=['barranquilla'=>'Atlántico','riohacha'=>'La Guajira','valledupar'=>'Cesar','san-bernardo-del-viento'=>'Córdoba'];
            if($before['country_code']==='CO'&&($regions[$before['slug']]??null)===$before['department']&&trim($before['subregion']??'')===''){$this->demand($now['subregion']==='Caribe','Expected reviewed region fill');$before['subregion']=$now['subregion'];$before['updated_at']=$now['updated_at'];}
            ksort($before);ksort($now);$this->demand($before===$now,'Existing place changed outside approved scope');
        }
        if(in_array($mode,['apply','verify'],true)){
            $this->demand((int)$this->db->query("SELECT COUNT(*) FROM destinations WHERE publication_status='published'")->fetchColumn()===107,'Expected 107 published places');
            foreach($state['original_identities'] as $p){$q=$this->db->prepare('SELECT slug FROM destinations WHERE id=?');$q->execute([$p['id']]);$this->demand($q->fetchColumn()===$p['slug'],'Original place identity changed');}
            $state['status']='complete';$this->save($state);
        }
        return ['success'=>true,'operation'=>$mode,'status'=>$state['status'],'protected_businesses'=>count($ids['businesses']),'protected_accounts'=>count($ids['accounts']),'accounts_businesses_preserved'=>true,'statistics_non_decreasing'=>true,'sonson_preserved'=>true,'published_places'=>(int)$this->db->query("SELECT COUNT(*) FROM destinations WHERE publication_status='published'")->fetchColumn()];
    }
}

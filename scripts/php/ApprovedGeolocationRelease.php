<?php
declare(strict_types=1);
/** Fixed approved catalog migration. No schema changes, deletion, account or statistics writes. */
final class ApprovedGeolocationRelease
{
    public function __construct(private PDO $db) {}
    private function hashProtected(): array {
        $out=[];
        foreach($this->db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t){
            if(in_array($t,['destinations','points_of_interest','businesses','business_translations','point_of_interest_translations'],true))continue;
            if(!preg_match('/^[a-zA-Z0-9_]+$/D',$t))throw new RuntimeException('Unexpected table');
            $hash=hash_init('sha256');$n=0;
            foreach($this->db->query('SELECT * FROM `'.$t.'` ORDER BY '.implode(',',array_map(static fn($c)=>'`'.$c.'`',array_column($this->db->query('SHOW COLUMNS FROM `'.$t.'`')->fetchAll(PDO::FETCH_ASSOC),'Field')))) as $row){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR));$n++;}
            $out[$t]=['rows'=>$n,'sha256'=>hash_final($hash)];
        }return $out;
    }
    private function same($a,$b): bool {return $a===null||$b===null ? $a===$b : abs((float)$a-(float)$b)<0.00000005;}
    private function categories(): array {
        $sql="SELECT DISTINCT b.destination_id, c.category_code, b.id FROM businesses b JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale='es' JOIN (SELECT id AS business_id,category_code FROM businesses UNION SELECT business_id,category_code FROM business_categories) c ON c.business_id=b.id WHERE d.publication_status='published' AND b.publication_status='published' AND b.information_status<>'PENDIENTE_REVISION' AND b.operation_status<>'CERRADO_DEFINITIVO'";
        $groups=[];foreach($this->db->query($sql) as $r)$groups[$r['destination_id'].':'.$r['category_code']][(int)$r['id']]=true;return array_map('count',$groups);
    }
    public function run(array $p,bool $apply=false,?string $expectedBefore=null): array {
        if(($p['schema_version']??0)!==1||($p['release']??'')!=='geolocalizacion-20261024')throw new RuntimeException('Unapproved payload');
        $this->db->exec('SET SESSION innodb_lock_wait_timeout=5');$this->db->beginTransaction();
        try{
            $protected=$this->hashProtected();$beforeGroups=$this->categories();$beforeRows=[];$changed=0;$created=0;$hidden=0;$maps=0;
            $read=[];foreach(['destinations','points_of_interest','businesses'] as $t)$read[$t]=$this->db->prepare('SELECT * FROM `'.$t.'` WHERE id=? FOR UPDATE');
            foreach($p['coordinate_updates'] as $v){
                $t=$v['table'];if(!isset($read[$t]))throw new RuntimeException('Table outside scope');$read[$t]->execute([$v['id']]);$row=$read[$t]->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Missing ID');
                foreach($v['identity'] as $k=>$wanted)if((string)$row[$k]!== (string)$wanted)throw new RuntimeException('Identity changed: '.$t.':'.$v['id'].':'.$k);
                $beforeRows['coordinates'][$t.':'.$v['id']]=[$row['latitude'],$row['longitude']];
                $done=true;foreach(['latitude','longitude'] as $k){$new=$v['after'][$k];if($new!==null&&(!is_numeric($new)||!is_finite((float)$new)))throw new RuntimeException('Invalid coordinate');if(!$this->same($row[$k],$new)){$done=false;if(!$this->same($row[$k],$v['before'][$k]))throw new RuntimeException('Coordinate changed since preparation: '.$t.':'.$v['id']);}}
                if($done)continue;
                if($v['after']['latitude']!==null&&($v['after']['latitude']< -4.5||$v['after']['latitude']>13.5||$v['after']['longitude']< -82||$v['after']['longitude']> -66))throw new RuntimeException('Point outside Colombia range');
                $q=$this->db->prepare('UPDATE `'.$t.'` SET latitude=?,longitude=? WHERE id=?');$q->execute([$v['after']['latitude'],$v['after']['longitude'],$v['id']]);$changed++;
            }
            $membership=$this->db->prepare("SELECT COUNT(*) FROM business_account_memberships WHERE business_id=?");
            $paid=$this->db->prepare("SELECT COUNT(*) FROM business_subscriptions WHERE business_id=? AND plan_code<>'free'");
            foreach($p['archive'] as $v){
                $read['businesses']->execute([$v['id']]);$r=$read['businesses']->fetch(PDO::FETCH_ASSOC);
                if(!$r||$r['public_id']!==$v['public_id']||(int)$r['destination_id']!==$v['destination_id']||$r['slug']!==$v['slug']||$r['record_origin']!=='public_import'||$r['claim_status']!=='NO_RECLAMADO')throw new RuntimeException('Imported business identity/claim changed: '.$v['id']);
                $membership->execute([$v['id']]);$paid->execute([$v['id']]);if($membership->fetchColumn()||$paid->fetchColumn())throw new RuntimeException('Owned or paid business protected: '.$v['id']);
                $beforeRows['archive'][$v['id']]=[$r['publication_status'],$r['information_status'],$r['claim_status']];
                if($r['publication_status']===$v['after_status'])continue;
                if($r['publication_status']!==$v['before_status'])throw new RuntimeException('Publication changed since review');
                $q=$this->db->prepare('UPDATE businesses SET publication_status=? WHERE id=?');$q->execute([$v['after_status'],$v['id']]);$hidden++;
            }
            $findNew=$this->db->prepare('SELECT id,public_id,destination_id,category_code,slug,latitude,longitude FROM businesses WHERE public_id=? OR (destination_id=? AND slug=?) FOR UPDATE');
            $newIds=[];
            foreach($p['new_businesses'] as $v){
                $findNew->execute([$v['public_id'],$v['destination_id'],$v['slug']]);$rows=$findNew->fetchAll(PDO::FETCH_ASSOC);$beforeRows['new'][$v['public_id']]=$rows;
                if($rows){if(count($rows)!==1||$rows[0]['public_id']!==$v['public_id']||(int)$rows[0]['destination_id']!==$v['destination_id']||$rows[0]['slug']!==$v['slug']||$rows[0]['category_code']!==$v['category_code']||!$this->same($rows[0]['latitude'],$v['latitude'])||!$this->same($rows[0]['longitude'],$v['longitude']))throw new RuntimeException('New-business identity collision');$newIds[$v['public_id']]=(int)$rows[0]['id'];continue;}
                $q=$this->db->prepare("INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status,address_text,latitude,longitude,location_source,location_map_url,location_reference) VALUES(?,?,?,'public_import',?,'PUBLICO','NO_RECLAMADO','DESCONOCIDO','published',?,?,?,'research',?,?)");
                $q->execute([$v['public_id'],$v['destination_id'],$v['category_code'],$v['slug'],$v['address'],$v['latitude'],$v['longitude'],$v['map_url'],$v['reference']]);$id=(int)$this->db->lastInsertId();$newIds[$v['public_id']]=$id;
                $tr=$this->db->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)');foreach(['es','en','pt-BR'] as $locale)$tr->execute([$id,$locale,$v['name'],'','']);$created++;
            }
            $mapRead=$this->db->prepare('SELECT locale,map_url FROM point_of_interest_translations WHERE point_of_interest_id=? ORDER BY locale FOR UPDATE');
            foreach($p['reviewed_maps'] as $v){
                $read['points_of_interest']->execute([$v['id']]);$r=$read['points_of_interest']->fetch(PDO::FETCH_ASSOC);if(!$r||$r['slug']!==$v['slug']||(int)$r['destination_id']!==$v['destination_id'])throw new RuntimeException('Reviewed point identity changed');
                $mapRead->execute([$v['id']]);$trs=$mapRead->fetchAll(PDO::FETCH_ASSOC);if(count($trs)!==3||array_diff(['es','en','pt-BR'],array_column($trs,'locale')))throw new RuntimeException('Missing reviewed translations');
                $beforeRows['maps'][$v['id']]=$trs;
                foreach($trs as $tr){if($tr['locale']==='es'&&$tr['map_url']!==$v['before_es']&&$tr['map_url']!==$v['url'])throw new RuntimeException('Map changed since review');if($tr['map_url']===$v['url'])continue;$q=$this->db->prepare('UPDATE point_of_interest_translations SET map_url=? WHERE point_of_interest_id=? AND locale=?');$q->execute([$v['url'],$v['id'],$tr['locale']]);$maps++;}
            }
            $beforeHash=hash('sha256',json_encode($beforeRows,JSON_THROW_ON_ERROR));if($apply&&($expectedBefore===null||!hash_equals($expectedBefore,$beforeHash)))throw new RuntimeException('Production changed after preflight; all changes rolled back');
            $afterGroups=$this->categories();foreach($beforeGroups as $g=>$count)if(($afterGroups[$g]??0)<1)throw new RuntimeException('Category would become empty: '.$g);
            foreach(['3:servicios'=>2,'3:licores'=>3,'1:licores'=>2] as $g=>$min)if(($afterGroups[$g]??0)<$min)throw new RuntimeException('Replacement category below approved minimum');
            $afterProtected=$this->hashProtected();if($afterProtected!==$protected)throw new RuntimeException('Protected table changed within release transaction');
            if((int)$this->db->query('SELECT COUNT(*) FROM destinations')->fetchColumn()!==$p['expected_destinations'])throw new RuntimeException('Destination scope changed');
            $report=['release'=>$p['release'],'applied'=>$apply,'before_hash'=>$beforeHash,'coordinate_rows_changed'=>$changed,'businesses_hidden_reversibly'=>$hidden,'businesses_created'=>$created,'map_translations_changed'=>$maps,'new_business_ids'=>$newIds,'category_groups_before'=>count($beforeGroups),'category_groups_after'=>count($afterGroups),'no_previously_populated_category_empty'=>true,'replacement_groups'=>array_intersect_key($afterGroups,array_flip(['3:servicios','3:licores','1:licores'])),'protected_tables_unchanged'=>count($protected),'protected'=>$protected,'before_rows'=>$beforeRows];
            if($apply)$this->db->commit();else $this->db->rollBack();return $report;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}

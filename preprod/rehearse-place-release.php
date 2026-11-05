<?php
declare(strict_types=1);
// Synthetic MariaDB rehearsal only. No HTTP, mail, or production transport.
if (PHP_SAPI !== 'cli' || getenv('EL_PREPROD_LOCAL') !== '1') exit(80);
$local=require '/srv/state/private/config.php';
if (empty($local['local_test']) || !str_contains($local['dsn'],'dbname=explorando_preprod;')) exit(81);
$folder='/srv/state/private/rehearsal87';
$input=json_decode(file_get_contents($folder.'/input.json'),true,512,JSON_THROW_ON_ERROR);
// SQLite presentation fixtures use negative synthetic media IDs; MySQL IDs are unsigned.
$mediaIds=array_column($input['baseline']['media_assets'],'id');$next=max($mediaIds)+1;$remap=[];
foreach($mediaIds as $id)if($id<0)$remap[$id]=$next++;
foreach($input['baseline'] as $table=>&$rows)foreach($rows as &$row){
    if($table==='media_assets')$row['id']=$remap[$row['id']]??$row['id'];
    if(isset($row['media_asset_id']))$row['media_asset_id']=$remap[$row['media_asset_id']]??$row['media_asset_id'];
}unset($row,$rows);
require_once '/srv/portal/app/ProductionContentTransfer.php';
final class RehearsalPlaceTransfer extends ProductionContentTransfer {
    public function __construct(PDO $db,private array $scope){parent::__construct($db);}
    public function validate(array $pack):array {
        $actual=array_keys($pack['editorial']);$expected=$this->scope;sort($actual);sort($expected);
        if(count($expected)!==87 || $actual!==$expected || $pack['schema_version']!==2)throw new RuntimeException('Invalid 87-place scope');
        foreach($pack['tables']['businesses'] as $b)if($b['record_origin']!=='public_import'||$b['claim_status']!=='NO_RECLAMADO'||$b['publication_status']!=='published')throw new RuntimeException('Unexpected imported business');
        return ['places'=>$expected,'businesses'=>count($pack['tables']['businesses'])];
    }
}
function demand(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);}
function digestRows(PDO $db,string $table,string $where=''):string {
    $rows=$db->query('SELECT * FROM `'.$table.'` '.$where)->fetchAll(PDO::FETCH_ASSOC);
    $rows=array_map(static function($r){ksort($r);return json_encode($r,JSON_THROW_ON_ERROR);},$rows);sort($rows);
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
$root=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$suffix=bin2hex(random_bytes(7));$name='el_rehearse_release_'.$suffix;$user='el_rehearse_'.$suffix;$password=bin2hex(random_bytes(24));
$created=false;$userCreated=false;$report=['production_accessed'=>false,'data_origin'=>'versioned 20-place content and synthetic accounts/visits','success'=>false];
try {
    $tables=$root->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='explorando_preprod' AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $definitions=[];
    foreach($tables as $t){demand((bool)preg_match('/^[a-z0-9_]+$/D',$t),'Unsafe table');$definitions[$t]=$root->query('SHOW CREATE TABLE `explorando_preprod`.`'.$t.'`')->fetch(PDO::FETCH_NUM)[1];}
    $root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    file_put_contents($folder.'/owner.json',json_encode(['database'=>$name,'user'=>$user,'owner'=>'release-rehearsal-87'],JSON_THROW_ON_ERROR));
    $root->exec('USE `'.$name.'`');$root->exec('SET FOREIGN_KEY_CHECKS=0');
    try{foreach($definitions as $sql)$root->exec(str_replace('`explorando_preprod`.','`'.$name.'`.',$sql));}finally{$root->exec('SET FOREIGN_KEY_CHECKS=1');}
    // Recreate the pre-060 shape only inside this new, empty disposable schema.
    foreach(['business_payment_order_evidence','business_payment_order_items','business_payment_orders','place_labels','destination_content_migrations'] as $t){
        $root->exec('DROP TABLE IF EXISTS `'.$t.'`');unset($definitions[$t]);
    }
    $root->exec('ALTER TABLE media_assets DROP COLUMN responsive_srcset, DROP COLUMN responsive_sizes');
    $root->exec('CREATE TABLE rehearsal_owner(token VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB');
    $root->exec("INSERT INTO rehearsal_owner VALUES('release-rehearsal-87')");
    $account=$root->quote($user)."@'localhost'";$root->exec('CREATE USER '.$account.' IDENTIFIED BY '.$root->quote($password));$userCreated=true;
    $root->exec('GRANT ALL ON `'.$name.'`.* TO '.$account);
    // This connection can write only to its randomly named disposable database.
    $db=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach($input['baseline'] as $table=>$rows){
            if(!$rows || !isset($definitions[$table]))continue;
            $columns=$db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll();
            $allowed=array_column(array_filter($columns,static fn($c)=>!str_contains($c['Extra'],'GENERATED')),'Field');
            foreach($rows as $row){$row=array_intersect_key($row,array_flip($allowed));if(!$row)continue;$keys=array_keys($row);
                $sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$keys).'`) VALUES('.implode(',',array_fill(0,count($keys),'?')).')';
                try{$db->prepare($sql)->execute(array_values($row));}
                catch(PDOException $e){throw new RuntimeException('Synthetic baseline table '.$table.': '.$e->getMessage(),0,$e);}
            }
        }
    }finally{$db->exec('SET FOREIGN_KEY_CHECKS=1');}
    $foreign=$db->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
    foreach($foreign as $fk){demand($fk['REFERENCED_TABLE_SCHEMA']===$name,'Foreign key escapes rehearsal');
        $count=$db->query('SELECT COUNT(*) FROM `'.$fk['TABLE_NAME'].'` c LEFT JOIN `'.$fk['REFERENCED_TABLE_NAME'].'` p ON c.`'.$fk['COLUMN_NAME'].'`=p.`'.$fk['REFERENCED_COLUMN_NAME'].'` WHERE c.`'.$fk['COLUMN_NAME'].'` IS NOT NULL AND p.`'.$fk['REFERENCED_COLUMN_NAME'].'` IS NULL')->fetchColumn();
        demand((int)$count===0,'Orphaned fixture: '.$fk['TABLE_NAME']);
    }
    $report['foreign_keys_checked']=count($foreign);
    $oldBusinessIds=$db->query('SELECT id FROM businesses ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);$oldPlaceIds=$db->query('SELECT id FROM destinations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    demand(count($oldPlaceIds)===20,'Baseline must have 20 places');
    $owner=(int)$db->query('SELECT id FROM accounts ORDER BY id LIMIT 1')->fetchColumn();
    $business=(int)$db->query("SELECT id FROM businesses WHERE publication_status='published' ORDER BY id LIMIT 1")->fetchColumn();demand($owner>0&&$business>0,'Missing protected account/business');
    $db->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")')->execute([$owner,$business]);
    $db->prepare('INSERT INTO business_subscriptions(business_id,plan_code,status,starts_at,ends_at,quoted_amount,currency) VALUES(?,"page_premium","active","2026-01-01 00:00:00","2030-01-01 00:00:00",123000,"COP")')->execute([$business]);
    $db->exec("INSERT INTO el_basic_page_daily VALUES('2026-11-01','CO',123)");
    $db->exec("INSERT INTO el_basic_country_daily VALUES('2026-11-01','CO','jerico','CO',41)");
    $db->exec("INSERT INTO el_basic_entity_daily VALUES('2026-11-01','place','jerico','jerico','page_view','page',67)");
    $businessList=implode(',',array_map('intval',$oldBusinessIds));$placeList=implode(',',array_map('intval',$oldPlaceIds));
    $protected=[];
    foreach($tables as $t){
        if(!isset($definitions[$t]))continue;
        if(str_starts_with($t,'account')||str_starts_with($t,'el_basic_')||str_starts_with($t,'el_measurement_')||str_starts_with($t,'business_subscription')||str_starts_with($t,'business_payment')||$t==='business_account_memberships')$protected[$t]='';
    }
    $protected['businesses']='WHERE id IN ('.$businessList.')';
    foreach(['business_translations','business_contacts','business_categories','business_media','business_sources'] as $t)$protected[$t]='WHERE business_id IN ('.$businessList.')';
    $protected['destinations']='WHERE id IN ('.$placeList.')';$protected['destination_translations']='WHERE destination_id IN ('.$placeList.')';
    $oldMedia=$db->query('SELECT id FROM media_assets')->fetchAll(PDO::FETCH_COLUMN);
    if($oldMedia)$protected['media_assets']='WHERE id IN ('.implode(',',array_map('intval',$oldMedia)).')';
    $snapshot=static function()use($db,$protected){$h=[];foreach($protected as $t=>$where)$h[$t]=digestRows($db,$t,$where);return $h;};
    $before=$snapshot();$pack=$input['pack'];
    // Preserve existing subscription rows while allowing subscriptions for NEW businesses.
    $protected['business_subscriptions']='WHERE business_id IN ('.$businessList.')';
    $snapshot=static function()use($db,&$protected){$h=[];foreach($protected as $t=>$where)$h[$t]=digestRows($db,$t,$where);return $h;};
    require_once '/srv/portal/app/DatabaseMaintenance.php';
    $maintenance=new DatabaseMaintenance($db,'/srv/portal/app','/srv/portal/public');
    $schemaProtected=$snapshot();unset($schemaProtected['media_assets'],$schemaProtected['destinations']);
    $migrations=['060-destination-content','061-business-payment-orders','062-place-labels'];
    $maintenance->applyMigrations($migrations);
    $afterUpgrade=$snapshot();unset($afterUpgrade['media_assets'],$afterUpgrade['destinations']);
    demand($schemaProtected===$afterUpgrade,'Schema upgrade changed protected business/account/visit data');
    $upgradeSnapshot=$snapshot();$maintenance->applyMigrations($migrations);
    demand($snapshot()===$upgradeSnapshot,'Repeated schema upgrade changed protected records');
    $report['migrations_060_061_062_applied_twice']=true;
    $report['schema_upgrade_preserved_accounts_businesses_and_visits']=true;
    $before=$snapshot();
    foreach($input['media'] as $m)demand(hash_equals($m['sha256'],hash_file('sha256','/srv/portal/public/'.$m['path'])),'Media hash mismatch');
    $transfer=new RehearsalPlaceTransfer($db,$input['scope']);
    $first=$transfer->apply($pack,'/srv/portal/public');
    $db->exec($input['heritage_sql']);
    $afterFirst=$snapshot();$changed=array_keys(array_diff_assoc($afterFirst,$before));
    demand(!$changed,'Protected records changed on first import: '.implode(', ',$changed));
    $counts=static function()use($db){$v=[];foreach(['destinations','points_of_interest','businesses','media_assets','business_subscriptions'] as $t)$v[$t]=(int)$db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();return $v;};
    $firstCounts=$counts();demand($firstCounts['destinations']===107,'Expected 107 places');
    $identity=static function()use($db){return ['places'=>$db->query('SELECT id,slug FROM destinations ORDER BY id')->fetchAll(),'businesses'=>$db->query('SELECT id,public_id FROM businesses ORDER BY id')->fetchAll(),'attractions'=>$db->query('SELECT id,destination_id,slug FROM points_of_interest ORDER BY id')->fetchAll()];};
    $ids=$identity();
    // Model legitimate traffic arriving between operations; the second import must keep it.
    $db->exec("UPDATE el_basic_page_daily SET page_views=page_views+7 WHERE page_day='2026-11-01' AND scope_code='CO'");$afterTraffic=$snapshot();
    $second=$transfer->apply($pack,'/srv/portal/public');$db->exec($input['heritage_sql']);
    demand($snapshot()===$afterTraffic,'Protected records or new visits changed on repeat');
    demand($counts()===$firstCounts,'Repeat inserted duplicate content/subscriptions');demand($identity()===$ids,'Repeat changed stable IDs');
    $seen=0;$poi=0;
    foreach($pack['editorial'] as $slug=>$guide){
        $q=$db->prepare("SELECT id FROM destinations WHERE slug=? AND publication_status='published'");$q->execute([$slug]);$id=(int)$q->fetchColumn();demand($id>0,'Unpublished imported place');
        $q=$db->prepare('SELECT locale FROM destination_translations WHERE destination_id=? ORDER BY locale');$q->execute([$id]);demand($q->fetchAll(PDO::FETCH_COLUMN)===['en','es','pt-BR'],'Missing locale');
        $q=$db->prepare("SELECT slug FROM points_of_interest WHERE destination_id=? AND publication_status='published'");$q->execute([$id]);$actual=$q->fetchAll(PDO::FETCH_COLUMN);$expected=array_column($guide['places'],'slug');sort($actual);sort($expected);demand($actual===$expected,'Attraction mismatch');$poi+=count($actual);$seen++;
    }
    foreach($pack['tables']['businesses'] as $b){$q=$db->prepare("SELECT COUNT(*) FROM businesses WHERE public_id=? AND publication_status='published'");$q->execute([$b['public_id']]);demand((int)$q->fetchColumn()===1,'Imported business identity mismatch');}
    // A conflicting incoming identity must not overwrite a business owned by an account.
    $collision=$pack;$collision['tables']['businesses'][0]['public_id']=$db->query('SELECT public_id FROM businesses WHERE id='.$business)->fetchColumn();
    $rejected=false;
    try{$transfer->apply($collision,'/srv/portal/public');}
    catch(RuntimeException $e){if($e->getMessage()!=='An owned business needs individual reconciliation')throw $e;$rejected=true;}
    demand($rejected,'Owned-business collision was accepted');
    demand($snapshot()===$afterTraffic,'Rejected collision changed protected records');
    demand($counts()===$firstCounts && $identity()===$ids,'Rejected collision changed identities/counts');
    $report['owned_business_collision_rejected_without_changing_protected_data']=true;
    foreach($input['media'] as $m)demand(hash_equals($m['sha256'],hash_file('sha256','/srv/portal/public/'.$m['path'])),'Media bytes changed');
    $report+=['places_imported'=>$seen,'published_places_total'=>107,'new_businesses'=>count($pack['tables']['businesses']),'new_attractions'=>$poi,'media_hashes_verified'=>count($input['media']),'protected_existing_businesses'=>count($oldBusinessIds),'protected_accounts'=>(int)$db->query('SELECT COUNT(*) FROM accounts')->fetchColumn(),'protected_table_checks'=>count($protected),'protected_published_owned_business_unchanged'=>true,'accounts_and_credentials_unchanged'=>true,'statistics_unchanged_by_import'=>true,'visits_added_between_passes_preserved'=>7,'stable_ids_after_repeat'=>true,'no_duplicate_rows_after_repeat'=>true,'passes'=>2];
    $report['success']=true;
} catch(Throwable $e){$report['error']=get_class($e).': '.$e->getMessage();}
finally {
    $db=null;
    if($created){demand((bool)preg_match('/^el_rehearse_release_[a-f0-9]{14}$/D',$name),'Unsafe cleanup');$root->exec('DROP DATABASE `'.$name.'`');}
    if($userCreated)$root->exec('DROP USER '.$root->quote($user)."@'localhost'");
    @unlink($folder.'/owner.json');$report['disposable_database_and_user_removed']=true;
    file_put_contents($folder.'/result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($report['success']?0:1);

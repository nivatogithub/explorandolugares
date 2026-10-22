<?php
declare(strict_types=1);
if((PHP_SAPI!=='cli'&&!(defined('EL_PRIVATE_SNAPSHOT')&&EL_PRIVATE_SNAPSHOT))||!isset($options))exit(1);
try{
 $selected=$options['migrations']??['043-account-lifecycle'];
 if(!is_array($selected)||!$selected||array_diff($selected,['043-account-lifecycle','044-subscription-changes','045-business-service-sections','046-premium-page-aliases','047-lead-business-links','048-individual-lead-replies','049-business-editorial','050-commercial-page-price','051-business-evidence-retirement'])||count(array_unique($selected))!==count($selected))throw new RuntimeException('Migraciones no permitidas.');
 $c=require $options['config'];$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 require $options['app_root'].'/DatabaseMaintenance.php';
 $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
 $fingerprints=static function()use($db,$tables):array{
  $out=[];foreach(['accounts','leads','businesses','business_translations','business_contacts','business_account_memberships','business_subscriptions','business_subscription_payment_evidence'] as $table){
   if(!in_array($table,$tables,true))continue;$rows=$db->query('SELECT * FROM `'.$table.'`')->fetchAll();$hashes=[];foreach($rows as $row)$hashes[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));sort($hashes);$out[$table]=['rows'=>count($rows),'sha256'=>hash('sha256',implode('', $hashes))];
  }return $out;
 };
 $protectedBefore=$fingerprints();
 $tableCounts=[];foreach($tables as $table){if(preg_match('/^[a-zA-Z0-9_]+$/D',$table))$tableCounts[$table]=(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();}
 $before=$db->query('SELECT id,status FROM accounts ORDER BY id')->fetchAll();
 $out=['accounts_before'=>count($before),'status_counts'=>[],'statistics_modified'=>false,'account_rows_updated'=>false,'migration'=>'043-account-lifecycle','selected_migrations'=>$selected];foreach($before as $a)$out['status_counts'][$a['status']]=($out['status_counts'][$a['status']]??0)+1;
 if(array_intersect($selected,['045-business-service-sections','046-premium-page-aliases'])){
  $parent=$db->query("SELECT t.ENGINE,c.COLUMN_TYPE FROM information_schema.TABLES t JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME AND c.COLUMN_NAME='id' WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_NAME='businesses'")->fetch();
  $out['business_parent_compatible']=$parent&&strtolower((string)$parent['ENGINE'])==='innodb'&&preg_match('/^bigint(?:\([0-9]+\))? unsigned$/i',(string)$parent['COLUMN_TYPE'])===1;
  if(!$out['business_parent_compatible'])throw new RuntimeException('Revisar compatibilidad de clave de negocios antes de migrar.');
 }
 if(($options['operation']??'')==='apply'){
  $m=new DatabaseMaintenance($db,$options['app_root'],$options['public_root']);$out['result']=$m->applyMigrations($selected);
  if(in_array('043-account-lifecycle',$selected,true)){$q=$db->prepare('SELECT COUNT(*) FROM account_access_events WHERE account_id=?');foreach($before as $a){$q->execute([$a['id']]);if((int)$q->fetchColumn()<1)throw new RuntimeException('Falta un estado inicial.');}$out['existing_accounts_have_state']=true;}$out['accounts_after']=(int)$db->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
 }else{$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$out['new_tables_present']=array_values(array_intersect(['account_access_events','account_reactivations','subscription_changes','business_service_sections','business_page_aliases'],$tables));$out['applied']=false;}
 $maintenance=new DatabaseMaintenance($db,$options['app_root'],$options['public_root']);$inspection=$maintenance->inspect();
 $out['migration_states']=array_intersect_key($inspection['migrations'],array_flip($selected));
 $out['subscription_rows']=(int)$db->query('SELECT COUNT(*) FROM business_subscriptions')->fetchColumn();
 $out['protected_tables_before']=$protectedBefore;$out['protected_tables_after']=$fingerprints();$out['protected_tables_unchanged']=$out['protected_tables_before']===$out['protected_tables_after'];$out['table_counts']=$tableCounts;
 $out['storage_parent_writable']=is_writable(dirname($options['config']));
 if(!$out['protected_tables_unchanged'])throw new RuntimeException('Cambió una tabla protegida durante la operación; revisar concurrencia antes de publicar.');
 echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'No se completó la preparación de cuentas; revisar antes de continuar.','code'=>(string)$e->getCode()]);exit(1);}

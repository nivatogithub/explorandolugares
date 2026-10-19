<?php
declare(strict_types=1);
if((PHP_SAPI!=='cli'&&!(defined('EL_PRIVATE_SNAPSHOT')&&EL_PRIVATE_SNAPSHOT))||!isset($options))exit(1);
try{
 $c=require $options['config'];$db=new PDO($c['dsn'],$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 require $options['app_root'].'/DatabaseMaintenance.php';
 $before=$db->query('SELECT id,status FROM accounts ORDER BY id')->fetchAll();
 $out=['accounts_before'=>count($before),'status_counts'=>[],'statistics_modified'=>false,'account_rows_updated'=>false,'migration'=>'043-account-lifecycle'];foreach($before as $a)$out['status_counts'][$a['status']]=($out['status_counts'][$a['status']]??0)+1;
 if(($options['operation']??'')==='apply'){
  $m=new DatabaseMaintenance($db,$options['app_root'],$options['public_root']);$out['result']=$m->applyMigrations(['043-account-lifecycle']);
  $q=$db->prepare('SELECT COUNT(*) FROM account_access_events WHERE account_id=?');foreach($before as $a){$q->execute([$a['id']]);if((int)$q->fetchColumn()<1)throw new RuntimeException('Falta un estado inicial.');}
  $out['existing_accounts_have_state']=true;$out['accounts_after']=(int)$db->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
 }else{$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$out['new_tables_present']=array_values(array_intersect(['account_access_events','account_reactivations'],$tables));$out['applied']=false;}
 echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'No se completó la preparación de cuentas; revisar antes de continuar.','code'=>(string)$e->getCode()]);exit(1);}

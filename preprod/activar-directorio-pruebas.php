<?php
declare(strict_types=1);
// Never packaged for hosting. Explicit local CLI operation, not an automatic startup step.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo preproducción local.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!preg_match('/(?:^|;)dbname=explorando_preprod(?:;|$)/',(string)($config['dsn']??'')))throw new RuntimeException('La base no es preproducción.');
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$fixtures=['000010'=>'free','000011'=>'featured','000012'=>'page','000013'=>'page_premium'];
$prefix='f00d0000-0000-4000-8000-000000';
$pdo->beginTransaction();
try{
 $destination=$pdo->query('SELECT id,publication_status FROM destinations WHERE slug="jerico" FOR UPDATE')->fetch();
 if(!$destination||$destination['publication_status']!=='published')throw new RuntimeException('Jericó debe existir y estar publicado; este script no publica lugares.');
 $account=$pdo->query('SELECT id FROM accounts WHERE public_id="f00d0000-0000-4000-8000-000000000002" AND email="negocio@pruebas.invalid"')->fetchColumn();
 if(!$account)throw new RuntimeException('Falta la cuenta ficticia de negocios. Ejecuta cuentas-prueba.sh antes.');
 $query=$pdo->prepare('SELECT b.* FROM businesses b WHERE b.destination_id=? AND b.record_origin="public_import" AND b.publication_status IN ("draft","published") AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO" AND NOT EXISTS(SELECT 1 FROM business_subscriptions s WHERE s.business_id=b.id AND s.plan_code<>"free") AND NOT EXISTS(SELECT 1 FROM business_account_memberships m WHERE m.business_id=b.id) FOR UPDATE');
 $query->execute([$destination['id']]);$imports=$query->fetchAll();$fixtureRows=[];
 foreach($fixtures as $suffix=>$plan){
  $uuid=$prefix.$suffix;
  $q=$pdo->prepare('SELECT b.* FROM businesses b WHERE b.public_id=? AND b.destination_id=? AND b.record_origin="owner_submitted" FOR UPDATE');$q->execute([$uuid,$destination['id']]);$row=$q->fetch();
  if(!$row)throw new RuntimeException('Falta el negocio ficticio '.$suffix.'. Ejecuta cuentas-prueba.sh antes.');
  $q=$pdo->prepare('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"');$q->execute([$row['id']]);$owners=$q->fetchAll(PDO::FETCH_COLUMN);
  if(count($owners)!==1||(int)$owners[0]!== (int)$account)throw new RuntimeException('La propiedad del negocio ficticio no coincide.');
  $fixtureRows[$suffix]=$row;
 }
 // Lock and snapshot every affected row before any update. No credentials in backup.
 $ids=array_map('intval',array_column([...$imports,...array_values($fixtureRows)],'id'));
 $marks=implode(',',array_fill(0,count($ids),'?'));
 $snapshot=['version'=>1,'environment'=>'preprod-local','created_at'=>gmdate(DATE_ATOM),'reason'=>'Directorio y suscripciones sintéticas para revisión visual','tables'=>[]];
 foreach(['businesses'=>'id','business_translations'=>'business_id','business_subscriptions'=>'business_id','business_account_memberships'=>'business_id'] as $table=>$field){$q=$pdo->prepare("SELECT * FROM $table WHERE $field IN ($marks) FOR UPDATE");$q->execute($ids);$snapshot['tables'][$table]=$q->fetchAll();}
 $backupDir='/srv/state/private/directorio-pruebas';if(!is_dir($backupDir)&&!mkdir($backupDir,0700,true))throw new RuntimeException('No se pudo crear respaldo.');
 $backup=$backupDir.'/antes-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
 $file=fopen($backup,'x');if(!$file)throw new RuntimeException('No se pudo abrir respaldo.');chmod($backup,0600);
 $encoded=json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);$written=fwrite($file,$encoded);fflush($file);fclose($file);if($written!==strlen($encoded))throw new RuntimeException('Respaldo incompleto; sin cambios.');
 $before=['imports_eligible'=>count($imports),'imports_published'=>count(array_filter($imports,fn($r)=>$r['publication_status']==='published')),'fixtures_published'=>count(array_filter($fixtureRows,fn($r)=>$r['publication_status']==='published'))];
 foreach($imports as $row){
  // Preserve factual status; public imports pending review become PUBLICO, never VERIFICADO.
  $pdo->prepare('UPDATE businesses SET publication_status="published",information_status=CASE WHEN information_status="PENDIENTE_REVISION" THEN "PUBLICO" ELSE information_status END WHERE id=?')->execute([$row['id']]);
 }
 $results=[];
 foreach($fixtures as $suffix=>$plan){
  $row=$fixtureRows[$suffix];$id=(int)$row['id'];
  $pdo->prepare('UPDATE businesses SET publication_status="published",information_status="PUBLICO",operation_status="ABIERTO" WHERE id=?')->execute([$id]);
  $summary='NEGOCIO FICTICIO. Prueba visual local de suscripción '.$plan.'. No admite reservas ni pagos.';
  $description='Este establecimiento no existe. Se utiliza exclusivamente en preproducción para revisar el directorio y las diferencias de presentación entre suscripciones. La activación es sintética: no representa un pago, una validación comercial ni una contratación real.';
  $pdo->prepare('UPDATE business_translations SET summary=?,description=?,brand_story=?,featured_services=? WHERE business_id=?')->execute([$summary,$description,'Historia ficticia de demostración; no corresponde a un comercio real.','Servicios ficticios para revisar la presentación Premium.',$id]);
  $q=$pdo->prepare('SELECT * FROM business_subscriptions WHERE business_id=? ORDER BY id DESC FOR UPDATE');$q->execute([$id]);$subscriptions=$q->fetchAll();
  $subscription=$subscriptions[0]??null;
  if(!$subscription||$subscription['plan_code']!==$plan)throw new RuntimeException('La suscripción ficticia cambió; revisar antes de activar '.$suffix.'.');
  // Preserve a current active term on rerun: do not extend it on every execution.
  if($subscription['status']!=='active'||!$subscription['ends_at']||strtotime($subscription['ends_at'])<=time()){
   $pdo->prepare('UPDATE business_subscriptions SET status="expired" WHERE business_id=? AND status="active" AND id<>?')->execute([$id,$subscription['id']]);
   $pdo->prepare('UPDATE business_subscriptions SET status="active",starts_at=CURRENT_TIMESTAMP,ends_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 1 YEAR),reviewed_at=CURRENT_TIMESTAMP,published_at=CURRENT_TIMESTAMP WHERE id=? AND business_id=?')->execute([$subscription['id'],$id]);
  }
  $pdo->prepare('UPDATE business_subscriptions SET starts_at=LEAST(starts_at,CURRENT_TIMESTAMP) WHERE id=? AND business_id=?')->execute([$subscription['id'],$id]);
  $results[]=['public_id'=>$row['public_id'],'plan'=>$plan,'directory_url'=>'http://127.0.0.1:8088/destinos/jerico/','profile_url'=>in_array($plan,['page','page_premium'],true)?'http://127.0.0.1:8088/negocios/'.$row['public_id'].'/':null,'synthetic_subscription'=>true];
 }
 $pdo->commit();
 echo json_encode(['before'=>$before,'after'=>['imports_published'=>count($imports),'fixtures_published'=>count($fixtures)],'private_snapshot'=>$backup,'fixtures'=>$results,'payment_evidence_created'=>0,'imported_businesses_assigned'=>0],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

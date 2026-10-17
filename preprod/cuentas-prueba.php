<?php
declare(strict_types=1);
// Local CLI only. This file is never included in a hosting package.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Solo se permite el entorno local de preproducción.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn']??'','dbname=explorando_preprod;'))throw new RuntimeException('La base no es la de preproducción local.');
$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
if(($input['version']??0)!==1||!is_array($input['profiles']??null))throw new RuntimeException('Formato local de cuentas no válido.');
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require_once '/srv/portal/app/BusinessSubscriptionAdminService.php';
$definitions=[
 'viajero'=>['email'=>'viajero@pruebas.invalid','name'=>'Viajero De Prueba','type'=>'traveler','uuid'=>'f00d0000-0000-4000-8000-000000000001'],
 'negocio'=>['email'=>'negocio@pruebas.invalid','name'=>'Responsable Del Negocio De Prueba','type'=>'business','uuid'=>'f00d0000-0000-4000-8000-000000000002'],
 'administrador'=>['email'=>'administrador@pruebas.invalid','name'=>'Administrador De Prueba','type'=>'traveler','uuid'=>'f00d0000-0000-4000-8000-000000000003'],
];
$result=[];$reset=($input['reset']??false)===true;
$pdo->beginTransaction();
try{
 foreach($definitions as $key=>$definition){
  $password=$input['profiles'][$key]['password']??'';
  if(!is_string($password)||strlen($password)<20||strlen($password)>200)throw new RuntimeException('La credencial local no tiene un formato válido.');
  $q=$pdo->prepare('SELECT id,email,display_name,account_type,status FROM accounts WHERE public_id=? FOR UPDATE');$q->execute([$definition['uuid']]);$account=$q->fetch(PDO::FETCH_ASSOC);$created=!$account;
  if($created||$reset){
   $collision=$pdo->prepare('SELECT public_id FROM accounts WHERE email=? AND public_id<>?');$collision->execute([$definition['email'],$definition['uuid']]);
   if($collision->fetchColumn())throw new RuntimeException('Un correo de pruebas pertenece a otro registro. No se modificó ese registro.');
   $hash=password_hash($password,PASSWORD_DEFAULT);
   if($created){$q=$pdo->prepare('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,?,?,?,?,"active")');$q->execute([$definition['uuid'],$definition['type'],$definition['name'],$definition['email'],$hash]);$id=(int)$pdo->lastInsertId();}
   else{$id=(int)$account['id'];$pdo->prepare('UPDATE accounts SET account_type=?,display_name=?,email=?,password_hash=?,status="active",updated_at=CURRENT_TIMESTAMP WHERE id=? AND public_id=?')->execute([$definition['type'],$definition['name'],$definition['email'],$hash,$id,$definition['uuid']]);$pdo->prepare('UPDATE account_password_resets SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$id]);}
   $pdo->prepare('DELETE FROM account_roles WHERE account_id=?')->execute([$id]);
   if($key==='administrador')$pdo->prepare('INSERT INTO account_roles(account_id,role) VALUES(?,"administrator")')->execute([$id]);
   $account=['id'=>$id,'email'=>$definition['email'],'display_name'=>$definition['name'],'account_type'=>$definition['type'],'status'=>'active'];
  }
  $id=(int)$account['id'];
  if($key==='viajero'&&($created||$reset)){$q=$pdo->prepare('SELECT 1 FROM account_consents WHERE account_id=? AND consent_version="preprod-synthetic-fixture-v1"');$q->execute([$id]);if(!$q->fetchColumn())$pdo->prepare('INSERT INTO account_consents(account_id,consent_version) VALUES(?,"preprod-synthetic-fixture-v1")')->execute([$id]);}
  $result[$key]=$account;
  if($key!=='negocio')continue;
  $businessDefinitions=[
   ['uuid'=>'f00d0000-0000-4000-8000-000000000010','suffix'=>'000010','plan'=>'free','label'=>'Gratis','name'=>'Negocio Ficticio De Prueba — Preproducción'],
   ['uuid'=>'f00d0000-0000-4000-8000-000000000011','suffix'=>'000011','plan'=>'featured','label'=>'Destacado','name'=>'Negocio Ficticio Destacado — Preproducción'],
   ['uuid'=>'f00d0000-0000-4000-8000-000000000012','suffix'=>'000012','plan'=>'page','label'=>'Página Comercial','name'=>'Negocio Ficticio Página Comercial — Preproducción'],
   ['uuid'=>'f00d0000-0000-4000-8000-000000000013','suffix'=>'000013','plan'=>'page_premium','label'=>'Premium','name'=>'Negocio Ficticio Premium — Preproducción'],
  ];
  $result[$key]['businesses']=[];
  foreach($businessDefinitions as $fixture){
   $q=$pdo->prepare('SELECT id,publication_status FROM businesses WHERE public_id=? FOR UPDATE');$q->execute([$fixture['uuid']]);$business=$q->fetch(PDO::FETCH_ASSOC);$newBusiness=!$business;
   if($newBusiness){
    $destination=$pdo->query('SELECT id FROM destinations WHERE slug="jerico" LIMIT 1')->fetchColumn();
    $category=$pdo->query('SELECT code FROM service_categories WHERE is_active=1 ORDER BY display_order,code LIMIT 1')->fetchColumn();
    if(!$destination||!$category)throw new RuntimeException('Falta Jericó o el catálogo de categorías para crear los negocios ficticios.');
    $q=$pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status,display_order) VALUES(?,?,?,"owner_submitted",?,"ACTIVO","RECLAMADO","DESCONOCIDO","draft",0)');
    $q->execute([$fixture['uuid'],$destination,$category,'preprod-negocio-ficticio-'.$fixture['suffix']]);$businessId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,?,?)')->execute([$businessId,$fixture['name'],'Registro ficticio exclusivo para pruebas locales.','Este negocio no existe. Sirve para probar la suscripción '.$fixture['label'].' en el entorno local.']);
    (new BusinessSubscriptionAdminService($pdo))->save(['business_id'=>$businessId,'plan_code'=>$fixture['plan'],'billing_frequency'=>$fixture['plan']==='free'?'':'monthly']);
    $business=['id'=>$businessId,'publication_status'=>'draft'];
   }
   $businessId=(int)$business['id'];
   if($newBusiness||$created||$reset){
    $q=$pdo->prepare('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"');$q->execute([$businessId]);$owner=$q->fetchColumn();
    if($owner!==false&&(int)$owner!==$id)throw new RuntimeException('Un negocio ficticio está asociado a otra cuenta; revisa esa prueba antes de restablecer.');
    $q=$pdo->prepare('SELECT id FROM business_account_memberships WHERE business_id=? AND account_id=?');$q->execute([$businessId,$id]);$membership=$q->fetchColumn();
    if($membership)$pdo->prepare('UPDATE business_account_memberships SET role="owner",status="active" WHERE id=?')->execute([$membership]);
    else $pdo->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,"owner","active")')->execute([$id,$businessId]);
   }
   $q=$pdo->prepare('SELECT t.display_name,s.plan_code,s.status AS subscription_status,s.billing_frequency FROM business_translations t LEFT JOIN business_subscriptions s ON s.id=(SELECT MAX(id) FROM business_subscriptions WHERE business_id=t.business_id) WHERE t.business_id=? AND t.locale="es"');$q->execute([$businessId]);$detail=$q->fetch(PDO::FETCH_ASSOC);
   $result[$key]['businesses'][]=['id'=>$businessId,'public_id'=>$fixture['uuid'],'expected_plan'=>$fixture['plan'],'initial_label'=>$fixture['label'],'publication_status'=>$business['publication_status'],...$detail];
  }
  // Keep existing consumers of the original Free fixture compatible.
  $result[$key]['business_id']=$result[$key]['businesses'][0]['id'];
  $result[$key]['business_publication_status']=$result[$key]['businesses'][0]['publication_status'];
 }
 $pdo->commit();echo json_encode(['profiles'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

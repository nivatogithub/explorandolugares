<?php
declare(strict_types=1);
/** Called only inside the account verification transaction; never reserves a business for an unverified email. */
final class AutomaticBusinessClaim
{
 public static function afterActivation(PDO $pdo,int $account):void{
  $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
  try{$q=$pdo->prepare('SELECT id,business_id,business_details_json,relationship_code FROM business_account_applications WHERE account_id=? AND application_type="claim_existing" AND status="pending"'.$lock);$q->execute([$account]);}
  catch(PDOException $e){if(($e->errorInfo[1]??0)===1146||str_contains($e->getMessage(),'no such table'))return;throw $e;}
  $requests=$q->fetchAll(PDO::FETCH_ASSOC);$lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
  foreach($requests as $row){$details=json_decode($row['business_details_json'],true)??[];if(empty($details['auto_claim_on_activation']))continue;
   $q=$pdo->prepare('SELECT id,operation_status FROM businesses WHERE id=?'.$lock);$q->execute([$row['business_id']]);$business=$q->fetch(PDO::FETCH_ASSOC);
   $q=$pdo->prepare('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"');$q->execute([$row['business_id']]);$owner=$q->fetchColumn();unset($details['auto_claim_on_activation']);
   if(!$business||$business['operation_status']==='CERRADO_DEFINITIVO'||$owner!==false){$details['claim_review_kind']='disputed_existing';$pdo->prepare('UPDATE business_account_applications SET status="in_review",business_details_json=? WHERE id=?')->execute([json_encode($details,JSON_THROW_ON_ERROR),$row['id']]);continue;}
   $pdo->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,?,"active")')->execute([$account,$row['business_id'],$row['relationship_code']==='owner'?'owner':'manager']);
   $pdo->prepare('UPDATE businesses SET information_status="ACTIVO",claim_status="RECLAMADO",updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$row['business_id']]);
   $pdo->prepare('UPDATE business_account_applications SET status="approved",business_details_json=?,reviewed_at=CURRENT_TIMESTAMP,admin_note=? WHERE id=?')->execute([json_encode($details,JSON_THROW_ON_ERROR),'Asignación automática al verificar el correo: negocio sin responsable. No publica ni activa beneficios.',$row['id']]);
  }
 }
}

<?php
declare(strict_types=1);
/** Verify privately; retire files only after a committed publication. Audit is permanent. */
final class BusinessOwnershipEvidence
{
 public function __construct(private PDO $pdo,private string $storage){}
 public function documents(int $business):array{$q=$this->pdo->prepare('SELECT e.*,r.deleted_at,r.document_sha256 FROM business_account_application_evidence e JOIN business_account_applications a ON a.id=e.application_id LEFT JOIN business_evidence_retirement r ON r.evidence_id=e.id WHERE a.business_id=? AND a.status="approved" AND EXISTS(SELECT 1 FROM business_account_memberships m WHERE m.business_id=a.business_id AND m.account_id=a.account_id AND m.status="active") ORDER BY e.id');$q->execute([$business]);return $q->fetchAll(PDO::FETCH_ASSOC);}
 public const RETENTION_POLICY='delete-after-publication-v2';
 public function verify(int $business,int $evidence,string $reviewer):void{
  if(trim($reviewer)==='')throw new RuntimeException('La validación requiere un administrador identificado.');
  $ownTransaction=!$this->pdo->inTransaction();if($ownTransaction)$this->pdo->beginTransaction();
  try{
   $lock=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$lock->execute([$business]);
   $rows=array_filter($this->documents($business),static fn($r)=>(int)$r['id']===$evidence);$row=reset($rows);
   if(!$row)throw new RuntimeException('El documento no pertenece al responsable de este negocio.');
   if(empty($row['deleted_at'])&&empty($row['document_sha256'])){
    $name=(string)$row['storage_name'];if(!preg_match('/^[a-f0-9]{48}\\.(pdf|jpg|png|webp)$/D',$name))throw new RuntimeException('Referencia de documento no válida.');
    $file=$this->storage.'/'.$name;if(is_link($this->storage)||!is_file($file)||is_link($file))throw new RuntimeException('No se encuentra el documento privado para revisarlo.');
    $hash=hash_file('sha256',$file);if(!$hash)throw new RuntimeException('No se pudo comprobar el documento.');
    $this->pdo->prepare('INSERT INTO business_evidence_retirement(evidence_id,document_sha256,reviewer,verified_at) VALUES(?,?,?,?)')->execute([$evidence,$hash,substr((string)($row['verified_by']?:$reviewer),0,120),$row['verified_at']?:gmdate('Y-m-d H:i:s')]);
   }
   if(empty($row['verified_at']))$this->pdo->prepare('UPDATE business_account_application_evidence SET verified_by=?,verified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([substr($reviewer,0,120),$evidence]);
   if($ownTransaction)$this->pdo->commit();
  }catch(Throwable $error){if($ownTransaction&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
 }
 /** Returns failed evidence IDs. Never repeats publication or activation to retry a file operation. */
 public function retirePublished(int $business):array{
  if($this->pdo->inTransaction())throw new LogicException('El retiro documental requiere una publicación confirmada.');
  $q=$this->pdo->prepare('SELECT publication_status FROM businesses WHERE id=?');$q->execute([$business]);if($q->fetchColumn()!=='published')throw new RuntimeException('El documento se conserva hasta publicar el negocio.');
  $failed=[];foreach($this->documents($business) as $row){if(empty($row['verified_at'])||!empty($row['deleted_at']))continue;
   try{$this->verify($business,(int)$row['id'],(string)$row['verified_by']);$file=$this->storage.'/'.$row['storage_name'];
    if(is_link($this->storage)||!preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png|webp)$/D',(string)$row['storage_name'])||is_link($file)||is_dir($file)||(is_file($file)&&!unlink($file)))throw new RuntimeException('No se pudo retirar el documento privado.');
    $this->pdo->prepare('UPDATE business_evidence_retirement SET deleted_at=CURRENT_TIMESTAMP WHERE evidence_id=? AND deleted_at IS NULL')->execute([$row['id']]);
   }catch(Throwable $error){$failed[]=(int)$row['id'];if(class_exists('PortalIncidents',false))PortalIncidents::capture($error,'ownership_retirement');}
  }return $failed;
 }
 public static function afterPublication(PDO $pdo,int $business,string $storage):void{
  try{(new self($pdo,$storage))->retirePublished($business);}catch(Throwable $error){if(class_exists('PortalIncidents',false))PortalIncidents::capture($error,'ownership_retirement');}
 }
 public static function requireVerified(PDO $pdo,int $business):void{
  $q=$pdo->prepare('SELECT id,business_details_json FROM business_account_applications WHERE business_id=? AND status="approved" AND EXISTS(SELECT 1 FROM business_account_memberships m WHERE m.business_id=business_account_applications.business_id AND m.account_id=business_account_applications.account_id AND m.status="active")');$q->execute([$business]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $a){$details=json_decode($a['business_details_json']??'{}',true);if(!in_array($details['document_retention_policy']??'',['delete-after-verification-v1',self::RETENTION_POLICY],true))continue;$v=$pdo->prepare('SELECT COUNT(*) FROM business_account_application_evidence WHERE application_id=? AND evidence_type=? AND verified_at IS NOT NULL');$v->execute([$a['id'],'business_registration']);if(!(int)$v->fetchColumn())throw new RuntimeException('Antes de prepublicar, valida el documento de titularidad. Se conservará privado hasta la publicación.');}
 }
}

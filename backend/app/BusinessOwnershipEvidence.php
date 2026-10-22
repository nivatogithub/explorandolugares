<?php
declare(strict_types=1);
/** Documents are temporary private files; retain only the verification audit after review. */
final class BusinessOwnershipEvidence
{
 public function __construct(private PDO $pdo,private string $storage){}
 public function documents(int $business):array{$q=$this->pdo->prepare('SELECT e.*,r.deleted_at,r.document_sha256 FROM business_account_application_evidence e JOIN business_account_applications a ON a.id=e.application_id LEFT JOIN business_evidence_retirement r ON r.evidence_id=e.id WHERE a.business_id=? AND a.status="approved" AND EXISTS(SELECT 1 FROM business_account_memberships m WHERE m.business_id=a.business_id AND m.account_id=a.account_id AND m.status="active") ORDER BY e.id');$q->execute([$business]);return $q->fetchAll(PDO::FETCH_ASSOC);}
 public function verifyAndDelete(int $business,int $evidence,string $reviewer):void{
  if(trim($reviewer)==='')throw new RuntimeException('La validación requiere un administrador identificado.');
  $rows=array_filter($this->documents($business),static fn($r)=>(int)$r['id']===$evidence);$row=reset($rows);if(!$row)throw new RuntimeException('El documento no pertenece a este negocio.');if($row['deleted_at'])return;
  $name=(string)$row['storage_name'];if(!preg_match('/^[a-f0-9]{48}\.(pdf|jpg|png|webp)$/D',$name))throw new RuntimeException('Referencia de documento no válida.');$file=$this->storage.'/'.$name;
  if(!$row['document_sha256']){
   if(!is_file($file)||is_link($file))throw new RuntimeException('No se encuentra el documento privado para revisarlo.');$hash=hash_file('sha256',$file);if(!$hash)throw new RuntimeException('No se pudo comprobar el documento.');
   $this->pdo->beginTransaction();try{$this->pdo->prepare('INSERT INTO business_evidence_retirement(evidence_id,document_sha256,reviewer,verified_at) VALUES(?,?,?,CURRENT_TIMESTAMP)')->execute([$evidence,$hash,$reviewer]);$this->pdo->prepare('UPDATE business_account_application_evidence SET verified_by=?,verified_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$reviewer,$evidence]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
  }
  // Persist the verification first so a failed unlink can be retried safely.
  if(is_link($file)|| (is_file($file)&&!unlink($file)))throw new RuntimeException('Titularidad revisada, pero no se pudo eliminar el archivo. Reintenta antes de publicar.');
  $this->pdo->prepare('UPDATE business_evidence_retirement SET deleted_at=CURRENT_TIMESTAMP WHERE evidence_id=? AND deleted_at IS NULL')->execute([$evidence]);
 }
 public static function requireVerified(PDO $pdo,int $business):void{
  $q=$pdo->prepare('SELECT id,business_details_json FROM business_account_applications WHERE business_id=? AND status="approved" AND EXISTS(SELECT 1 FROM business_account_memberships m WHERE m.business_id=business_account_applications.business_id AND m.account_id=business_account_applications.account_id AND m.status="active")');$q->execute([$business]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $a){$details=json_decode($a['business_details_json']??'{}',true);if(($details['document_retention_policy']??'')!=='delete-after-verification-v1')continue;$v=$pdo->prepare('SELECT COUNT(*) FROM business_account_application_evidence e JOIN business_evidence_retirement r ON r.evidence_id=e.id WHERE e.application_id=? AND e.evidence_type=? AND r.deleted_at IS NOT NULL');$v->execute([$a['id'],'business_registration']);if(!(int)$v->fetchColumn())throw new RuntimeException('Antes de publicar, valida el documento de titularidad y elimina su archivo privado.');}
 }
}

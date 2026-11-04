<?php
declare(strict_types=1);
/** Writes claims using the transaction owned by BusinessClaimService. */
final class BusinessClaimWriter
{
    public function __construct(private PDO $pdo) {}
    public function create(int $businessId,int $accountId,string $name,string $email,string $phone,string $relationship,string $message):int
    {
        $insert=$this->pdo->prepare('INSERT INTO business_claim_requests(business_id,account_id,claimant_name,email,phone,relationship_code,message,consent_version,status) VALUES(?,?,?,?,?,?,?,?,"pending")');
        $insert->execute([$businessId,$accountId,$name,$email,$phone,$relationship,$message,'reclamacion-negocio-v1']);
        $id=(int)$this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE businesses SET claim_status="EN_TRAMITE",updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$businessId]);
        return $id;
    }
    public function updateReview(int $id,string $status,string $note):void
    {
        $this->pdo->prepare('UPDATE business_claim_requests SET status=?,admin_note=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,$note,$id]);
    }
    public function releaseBusiness(int $businessId):void
    {
        $this->pdo->prepare('UPDATE businesses SET claim_status="NO_RECLAMADO",updated_at=CURRENT_TIMESTAMP WHERE id=? AND claim_status="EN_TRAMITE"')->execute([$businessId]);
    }
}

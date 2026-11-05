<?php
declare(strict_types=1);

/** Read private-document metadata; access control and file delivery stay with the controller. */
final class AdminEvidenceSelector
{
    public function __construct(private PDO $pdo) {}

    public function payment(int $id):array|false
    {
        $query=$this->pdo->prepare('SELECT storage_name,mime_type,file_size FROM business_subscription_payment_evidence WHERE id=?');
        $query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }
    public function paymentBelongsToBusiness(int $evidenceId,int $businessId):bool
    {
        $query=$this->pdo->prepare('SELECT e.id FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.id=? AND s.business_id=?');
        $query->execute([$evidenceId,$businessId]);return (bool)$query->fetchColumn();
    }

    public function representation(int $id):array|false
    {
        $query=$this->pdo->prepare('SELECT storage_name,mime_type,file_size FROM business_account_application_evidence WHERE id=?');
        $query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function representationRetired(int $id):bool
    {
        $query=$this->pdo->prepare('SELECT deleted_at FROM business_evidence_retirement WHERE evidence_id=?');
        $query->execute([$id]);return (bool)$query->fetchColumn();
    }

    public function businessImage(int $id,bool $application):array|false
    {
        $table=$application?'business_application_images':'business_image_references';
        $query=$this->pdo->prepare('SELECT storage_name,mime_type,file_size FROM '.$table.' WHERE id=?');
        $query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }
}

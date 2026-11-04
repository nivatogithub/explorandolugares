<?php
declare(strict_types=1);
/** Reads needed to validate and complete the existing business registration flow. */
final class BusinessRegistrationSelector
{
    public function __construct(private PDO $pdo) {}
    public function draft(string $id):array|false
    {
        $q=$this->pdo->prepare('SELECT account_id,revision,status,business_id FROM business_registration_drafts WHERE id=?');
        $q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC);
    }
    public function pendingApplication(int $accountId,int $placeId):int
    {
        $q=$this->pdo->prepare('SELECT id FROM business_account_applications WHERE account_id=? AND destination_id=? AND status IN ("pending","in_review") ORDER BY id DESC LIMIT 1');
        $q->execute([$accountId,$placeId]);return (int)$q->fetchColumn();
    }
    public function latestBusiness(int $accountId,int $placeId,string $name):int
    {
        $q=$this->pdo->prepare('SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN business_account_memberships m ON m.business_id=b.id WHERE m.account_id=? AND m.status="active" AND b.destination_id=? AND LOWER(t.display_name)=LOWER(?) ORDER BY b.id DESC LIMIT 1');
        $q->execute([$accountId,$placeId,$name]);return (int)$q->fetchColumn();
    }
    public function hasActiveMembership(int $accountId,int $businessId):bool
    {
        $q=$this->pdo->prepare('SELECT 1 FROM business_account_memberships WHERE account_id=? AND business_id=? AND status="active"');
        $q->execute([$accountId,$businessId]);return (bool)$q->fetchColumn();
    }
}

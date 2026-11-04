<?php
declare(strict_types=1);

/** Reads whether an active account is linked to the requested business. */
final class BusinessAudienceAccessSelector
{
    public function __construct(private PDO $pdo) {}

    public function hasActiveMembership(string $publicId,int $accountId):bool
    {
        $query=$this->pdo->prepare('SELECT b.id FROM businesses b JOIN business_account_memberships m ON m.business_id=b.id JOIN accounts a ON a.id=m.account_id WHERE b.public_id=? AND m.account_id=? AND m.status="active" AND a.status="active" LIMIT 1');
        $query->execute([$publicId,$accountId]);
        return (bool)$query->fetchColumn();
    }
}

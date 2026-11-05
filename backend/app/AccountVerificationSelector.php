<?php
declare(strict_types=1);

/** Account lookup for activation-link resends. */
final class AccountVerificationSelector
{
    public function __construct(private PDO $pdo) {}

    public function eligibleAccountId(string $email):?int
    {
        $query=$this->pdo->prepare('SELECT id FROM accounts WHERE email=? AND (status="pending_activation" OR (status="active" AND email_verified_at IS NULL))');
        $query->execute([$email]);$id=$query->fetchColumn();
        return $id===false?null:(int)$id;
    }
}

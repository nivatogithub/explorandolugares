<?php
declare(strict_types=1);

/** Read access to public contact requests for administration. */
final class PublicRequestSelector
{
    public function __construct(private PDO $pdo) {}

    public function recent():array
    {
        return $this->pdo->query('SELECT * FROM public_requests ORDER BY id DESC LIMIT 100')->fetchAll();
    }
}

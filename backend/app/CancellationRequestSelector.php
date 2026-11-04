<?php
declare(strict_types=1);

/** Reads administrative cancellation requests; review decisions remain in the service. */
final class CancellationRequestSelector
{
    public function __construct(private PDO $pdo) {}
    public function recent():array
    {
        return $this->pdo->query('SELECT r.*,t.display_name AS business_name FROM subscription_cancellation_requests r JOIN business_translations t ON t.business_id=r.business_id AND t.locale="es" ORDER BY r.created_at DESC LIMIT 200')->fetchAll();
    }
}

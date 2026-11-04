<?php
declare(strict_types=1);
/** Read-only aggregate queries for operational reports. */
final class OperationReportSelector
{
    public function __construct(private PDO $pdo) {}
    public function all():array
    {
        return [
            'business_publication'=>$this->pdo->query('SELECT publication_status AS estado,COUNT(*) AS cantidad FROM businesses GROUP BY publication_status')->fetchAll(),
            'subscriptions'=>$this->pdo->query('SELECT plan_code AS suscripcion,status AS estado,COUNT(*) AS cantidad FROM business_subscriptions GROUP BY plan_code,status')->fetchAll(),
            'accounts'=>$this->pdo->query('SELECT status AS estado,COUNT(*) AS cantidad FROM accounts GROUP BY status')->fetchAll(),
            'business_applications'=>$this->pdo->query('SELECT application_type AS tipo,status AS estado,COUNT(*) AS cantidad FROM business_account_applications GROUP BY application_type,status')->fetchAll(),
            'public_requests'=>$this->pdo->query('SELECT request_type AS tipo,status AS estado,COUNT(*) AS cantidad FROM public_requests GROUP BY request_type,status')->fetchAll(),
        ];
    }
}

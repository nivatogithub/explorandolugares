<?php
declare(strict_types=1);

/** Read-only options and readiness for the bulk-edit workflow. */
final class BusinessBulkEditSelector
{
    public function __construct(private PDO $pdo) {}

    public function places():array
    {
        return $this->pdo->query('SELECT d.slug,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY t.display_name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories():array
    {
        return $this->pdo->query('SELECT c.code,t.label FROM service_categories c JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es" WHERE c.is_active=1 ORDER BY c.display_order')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function auditAvailable():bool
    {
        try{$this->pdo->query('SELECT id FROM business_bulk_update_audit LIMIT 0');return true;}catch(PDOException){return false;}
    }
}

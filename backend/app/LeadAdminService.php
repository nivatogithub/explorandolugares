<?php
declare(strict_types=1);
require_once __DIR__.'/LeadStatusStore.php';

/** Administrative state changes never perform communication or account assignment. */
final class LeadAdminService
{
    public const STATUSES = ['nuevo', 'revisado', 'contactado', 'archivado'];

    private LeadStatusStore $store;
    public function __construct(private PDO $pdo) {$this->store=new LeadStatusStore($pdo);}

    public function changeStatus(int $id, string $status): string
    {
        if ($id < 1 || !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Estado o registro inválido.');
        }
        $this->pdo->beginTransaction();
        try {
            $previous = $this->store->statusForUpdate($id);
            $result = $previous === false ? 'missing' : ($previous === $status ? 'unchanged' : 'saved');
            if ($result === 'saved') {
                $this->store->updateStatus($id,$status);
            }
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}

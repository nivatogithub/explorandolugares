<?php
declare(strict_types=1);

/** Administrative state changes never perform communication or account assignment. */
final class LeadAdminService
{
    public const STATUSES = ['nuevo', 'revisado', 'contactado', 'archivado'];

    public function __construct(private PDO $pdo) {}

    public function changeStatus(int $id, string $status): string
    {
        if ($id < 1 || !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Estado o registro inválido.');
        }
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $query = $this->pdo->prepare('SELECT status FROM leads WHERE id=?'.$lock);
            $query->execute([$id]);
            $previous = $query->fetchColumn();
            $result = $previous === false ? 'missing' : ($previous === $status ? 'unchanged' : 'saved');
            if ($result === 'saved') {
                $query = $this->pdo->prepare('UPDATE leads SET status=? WHERE id=?');
                $query->execute([$status, $id]);
            }
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}

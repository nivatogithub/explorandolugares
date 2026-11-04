<?php
declare(strict_types=1);

/** Status persistence on the same connection used by the owning transaction. */
final class LeadStatusStore
{
    public function __construct(private PDO $pdo) {}
    public function statusForUpdate(int $id):mixed
    {
        $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $query=$this->pdo->prepare('SELECT status FROM leads WHERE id=?'.$lock);
        $query->execute([$id]);return $query->fetchColumn();
    }
    public function updateStatus(int $id,string $status):void
    {
        $query=$this->pdo->prepare('UPDATE leads SET status=? WHERE id=?');$query->execute([$status,$id]);
    }
}

<?php
declare(strict_types=1);

/** Persists deletions authorized and validated by the inbox controller. */
final class LeadInboxWriter
{
    public function __construct(private PDO $pdo) {}
    public function delete(array $ids):void
    {
        if(!$ids)throw new InvalidArgumentException('At least one ID is required.');
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $q=$this->pdo->prepare('DELETE FROM leads WHERE id IN ('.$marks.')');$q->execute(array_values($ids));
    }
}

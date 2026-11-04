<?php
declare(strict_types=1);

/** Reads the availability of lead storage and existing request keys. */
final class ParticipationSelector
{
    public function __construct(private PDO $pdo) {}
    public function verifyStorage():void { $this->pdo->query('SELECT 1 FROM leads LIMIT 1'); }
    public function hasRequest(string $requestKey):bool
    {
        $query=$this->pdo->prepare('SELECT id FROM leads WHERE request_key=?');
        $query->execute([$requestKey]);
        return (bool)$query->fetchColumn();
    }
}

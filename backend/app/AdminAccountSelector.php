<?php
declare(strict_types=1);

/** Minimal account projection for an already authorized private layout. */
final class AdminAccountSelector
{
    public function __construct(private PDO $pdo){}

    public function displayName(int $accountId):string
    {
        $query=$this->pdo->prepare('SELECT display_name FROM accounts WHERE id=?');
        $query->execute([$accountId]);
        return (string)($query->fetchColumn()?:'Administrador');
    }
}

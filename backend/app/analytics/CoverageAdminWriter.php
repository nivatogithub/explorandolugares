<?php
declare(strict_types=1);
final class CoverageAdminWriter
{
    public function __construct(private PDO $pdo) {}
    public function setPreparing(string $id,bool $preparing):void
    {
        $query=$this->pdo->prepare('UPDATE el_coverage_places SET preparing=? WHERE id=?');$query->execute([$preparing?1:0,$id]);
    }
}

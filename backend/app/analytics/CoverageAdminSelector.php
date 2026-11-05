<?php
declare(strict_types=1);
final class CoverageAdminSelector
{
    public function __construct(private PDO $pdo) {}
    public function counts():array
    {
        $counts=[];foreach(['sessions'=>'el_measurement_sessions','events'=>'el_measurement_events','places'=>'el_coverage_places'] as $key=>$table)$counts[$key]=(int)$this->pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();return $counts;
    }
    public function places():array
    {
        return $this->pdo->query('SELECT p.id,p.slug,p.name,d.name AS department,p.preparing FROM el_coverage_places p JOIN el_coverage_departments d ON d.code=p.department_code ORDER BY d.name,p.name')->fetchAll();
    }
}

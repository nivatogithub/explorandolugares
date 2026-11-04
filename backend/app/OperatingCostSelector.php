<?php
declare(strict_types=1);

/** Reads administrative cost lists and their private evidence metadata. */
final class OperatingCostSelector
{
    public function __construct(private PDO $pdo) {}

    public function places():array
    {
        return $this->pdo->query("SELECT d.id,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale='es' ORDER BY t.display_name")->fetchAll();
    }

    public function recent():array
    {
        return $this->pdo->query('SELECT c.*,t.display_name AS destination_name FROM operating_costs c LEFT JOIN destination_translations t ON t.destination_id=c.destination_id AND t.locale="es" ORDER BY c.cost_date DESC,c.id DESC LIMIT 200')->fetchAll();
    }

    public function evidenceByCost(array $costs):array
    {
        if(!$costs)return [];
        $ids=array_column($costs,'id');
        $query=$this->pdo->prepare('SELECT id,cost_id,original_name,file_size FROM operating_cost_evidence WHERE cost_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id');
        $query->execute($ids);$result=[];
        foreach($query as $file)$result[$file['cost_id']][]=$file;
        return $result;
    }
}

<?php
declare(strict_types=1);
/** Informational many-to-many links: never confer account permissions. */
final class LeadBusinessLinks
{
    public function __construct(private PDO $pdo) {}
    public function link(int $lead, int $business, string $actor): bool
    {
        if($lead<1||$business<1||trim($actor)===''||mb_strlen($actor)>190)throw new InvalidArgumentException('Confirma la solicitud y la ficha.');
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            foreach(['leads'=>$lead,'businesses'=>$business] as $table=>$id){
                $q=$this->pdo->prepare('SELECT id FROM '.$table.' WHERE id=?'.$lock);$q->execute([$id]);
                if($q->fetchColumn()===false)throw new RuntimeException('La solicitud o el negocio ya no existe.');
            }
            $q=$this->pdo->prepare('SELECT 1 FROM lead_business_links WHERE lead_id=? AND business_id=?');$q->execute([$lead,$business]);
            $created=$q->fetchColumn()===false;
            if($created)$this->pdo->prepare('INSERT INTO lead_business_links(lead_id,business_id,linked_by) VALUES(?,?,?)')->execute([$lead,$business,$actor]);
            $this->pdo->commit();return $created;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function forLead(int $lead):array
    {
        $q=$this->pdo->prepare('SELECT l.*,t.display_name FROM lead_business_links l JOIN business_translations t ON t.business_id=l.business_id AND t.locale="es" WHERE l.lead_id=? ORDER BY l.created_at,l.business_id');$q->execute([$lead]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

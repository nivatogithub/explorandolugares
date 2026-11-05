<?php
declare(strict_types=1);

/** Reads the administrative participation inbox using its existing filters. */
final class LeadInboxSelector
{
    public function __construct(private PDO $pdo) {}
    public function replyBody(string $operationId,int $leadId):string|false
    {
        $query=$this->pdo->prepare('SELECT html_body FROM lead_reply_operations WHERE id=? AND lead_id=?');
        $query->execute([$operationId,$leadId]);return $query->fetchColumn();
    }
    public function page(string $status,string $search,int $page):array
    {
        $conditions=[];$params=[];
        if($status!==''){$conditions[]='status=?';$params[]=$status;}
        if($search!==''){$conditions[]='(business LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ? OR municipality LIKE ?)';$needle='%'.$search.'%';array_push($params,$needle,$needle,$needle,$needle,$needle);}
        $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM leads'.$where);$q->execute($params);$total=(int)$q->fetchColumn();
        $page=min($page,max(1,(int)ceil($total/25)));$offset=($page-1)*25;
        $q=$this->pdo->prepare('SELECT * FROM leads'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset);$q->execute($params);
        return ['total'=>$total,'page'=>$page,'rows'=>$q->fetchAll()];
    }
}

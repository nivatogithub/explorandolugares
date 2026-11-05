<?php
declare(strict_types=1);
final class BusinessContactsSelector
{
    public function __construct(private PDO $pdo) {}
    public function assertResearchAvailable():void{$this->pdo->query('SELECT id FROM business_research_contacts LIMIT 1');}
    public function places():array{return $this->pdo->query('SELECT d.slug,COALESCE(t.display_name,d.slug) AS display_name FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY display_name')->fetchAll(PDO::FETCH_ASSOC);}
    public function profileContacts(int $id):array{$q=$this->pdo->prepare('SELECT channel,contact_value,is_public FROM business_contacts WHERE business_id=? ORDER BY display_order,id');$q->execute([$id]);return $q->fetchAll(PDO::FETCH_ASSOC);}
    public function emailPermissions():array{$out=[];foreach($this->pdo->query('SELECT email,decision FROM business_email_permissions')->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$row['email']]=$row['decision'];return $out;}
    public function researchPage(int $businessId,int $page,int $pageSize):array
    {
        $from=' FROM business_research_contacts r JOIN businesses b ON b.id=r.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id WHERE b.id=?';
        $query=$this->pdo->prepare('SELECT COUNT(*)'.$from);$query->execute([$businessId]);$total=(int)$query->fetchColumn();
        $pageCount=max(1,(int)ceil($total/$pageSize));$page=min(max(1,$page),$pageCount);
        $query=$this->pdo->prepare('SELECT r.*,b.public_id,t.display_name,d.slug AS destination_slug'.$from.' ORDER BY t.display_name,r.channel,r.id LIMIT '.(int)$pageSize.' OFFSET '.(($page-1)*$pageSize));
        $query->execute([$businessId]);return ['rows'=>$query->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'page_count'=>$pageCount];
    }
}

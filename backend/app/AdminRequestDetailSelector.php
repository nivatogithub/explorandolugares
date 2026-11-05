<?php
declare(strict_types=1);

/** Read access for the administrative detail of a participation or account request. */
final class AdminRequestDetailSelector
{
    public function __construct(private PDO $pdo) {}

    public function request(string $type,int $id):array|false
    {
        $table=match($type){'lead'=>'leads','account'=>'business_account_applications',default=>throw new InvalidArgumentException('Invalid request type.')};
        $query=$this->pdo->prepare('SELECT * FROM '.$table.' WHERE id=?');$query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }
    public function businessesMatching(string $search):array
    {
        $query=$this->pdo->prepare('SELECT b.id,t.display_name,d.display_name AS place FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destination_translations d ON d.destination_id=b.destination_id AND d.locale="es" WHERE t.display_name LIKE ? ORDER BY t.display_name,b.id LIMIT 30');
        $query->execute(['%'.$search.'%']);return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function account(int $id):array|false
    {
        $query=$this->pdo->prepare('SELECT id,display_name AS name,email,status FROM accounts WHERE id=?');$query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }
    public function ownershipApplication(int $id):array|false
    {
        $query=$this->pdo->prepare('SELECT a.id,a.business_id,a.business_name,a.status,t.display_name AS destination_name FROM business_account_applications a JOIN destination_translations t ON t.destination_id=a.destination_id AND t.locale="es" WHERE a.id=?');
        $query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }
    public function evidence(int $id):array
    {
        $query=$this->pdo->prepare('SELECT id,original_name,verified_at FROM business_account_application_evidence WHERE application_id=? ORDER BY id');$query->execute([$id]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function photos(int $id):array
    {
        $query=$this->pdo->prepare('SELECT id,original_name FROM business_application_images WHERE application_id=?');$query->execute([$id]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function photosByApplication(array $ids):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        if(!$ids)return [];
        $out=[];
        foreach(array_chunk($ids,500) as $chunk){
            $query=$this->pdo->prepare('SELECT application_id,id,original_name FROM business_application_images WHERE application_id IN ('.implode(',',array_fill(0,count($chunk),'?')).') ORDER BY application_id,id');
            $query->execute($chunk);
            foreach($query->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(int)$row['application_id']][]=$row;
        }
        return $out;
    }
    public function memberships(int $businessId,int $accountId):array
    {
        $query=$this->pdo->prepare('SELECT status,role,created_at FROM business_account_memberships WHERE business_id=? AND account_id=?');$query->execute([$businessId,$accountId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }
    public function categoryNames():array
    {
        return $this->pdo->query('SELECT category_code,label FROM service_category_translations WHERE locale="es"')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    public function business(int $id):array|false
    {
        $query=$this->pdo->prepare('SELECT b.id,b.publication_status,b.information_status,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.id=?');$query->execute([$id]);return $query->fetch(PDO::FETCH_ASSOC);
    }
}

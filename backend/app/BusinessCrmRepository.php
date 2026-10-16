<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessPersonContactService.php';

/** CRM is a private view of businesses, not a second business registry. */
final class BusinessCrmRepository
{
    public function __construct(private PDO $pdo) {}

    public function researchReady():bool
    {
        try{$this->pdo->query('SELECT id FROM business_research_contacts LIMIT 0');return true;}
        catch(PDOException $error){if(($error->errorInfo[0]??'')==='42S02'||str_contains($error->getMessage(),'no such table: business_research_contacts'))return false;throw $error;}
    }

    public function overview(array $filters=[],int $page=1,int $pageSize=25):array
    {
        $research=$this->researchReady();$where=' WHERE 1=1';$params=[];
        $destination=trim((string)($filters['destination']??''));$plan=(string)($filters['subscription']??'free');$contact=(string)($filters['contact']??'');$query=trim((string)($filters['query']??''));
        if($destination!==''){$where.=' AND crm.destination_slug=?';$params[]=$destination;}
        if($plan==='free')$where.=' AND crm.current_plan="free"';elseif($plan==='paid')$where.=' AND crm.current_plan<>"free"';elseif($plan!=='all')throw new RuntimeException('Filtro de suscripción no válido.');
        if($contact==='with')$where.=' AND (crm.research_contact_count+crm.profile_contact_count)>0';elseif($contact==='without')$where.=' AND (crm.research_contact_count+crm.profile_contact_count)=0';elseif($contact!=='')throw new RuntimeException('Filtro de contacto no válido.');
        if($query!==''){
            $where.=' AND (crm.display_name LIKE ? OR EXISTS(SELECT 1 FROM business_contacts pc WHERE pc.business_id=crm.id AND pc.contact_value LIKE ?)';$params[]='%'.$query.'%';$params[]='%'.$query.'%';
            if($research){$where.=' OR EXISTS(SELECT 1 FROM business_research_contacts rc WHERE rc.business_id=crm.id AND rc.contact_value LIKE ?)';$params[]='%'.$query.'%';}
            if((new BusinessPersonContactService($this->pdo))->ready()){
                $where.=' OR EXISTS(SELECT 1 FROM business_person_contacts bc JOIN people p ON p.id=bc.person_id WHERE bc.business_id=crm.id AND bc.status="active" AND p.display_name LIKE ?)
                    OR EXISTS(SELECT 1 FROM business_person_contacts bc JOIN person_channels ch ON ch.person_id=bc.person_id WHERE bc.business_id=crm.id AND bc.status="active" AND (ch.business_id IS NULL OR ch.business_id=bc.business_id) AND ch.channel_value LIKE ?)';
                $params[]='%'.$query.'%';$params[]='%'.$query.'%';
            }
            $where.=')';
        }
        $from=' FROM ('.$this->baseQuery($research).') crm';
        $q=$this->pdo->prepare('SELECT COUNT(*) AS total,COALESCE(SUM(CASE WHEN (crm.research_contact_count+crm.profile_contact_count)>0 THEN 1 ELSE 0 END),0) AS with_contact'.$from.$where);$q->execute($params);$counts=$q->fetch(PDO::FETCH_ASSOC);
        $total=(int)$counts['total'];$pageSize=max(1,min(100,$pageSize));$pageCount=max(1,(int)ceil($total/$pageSize));$page=max(1,min($page,$pageCount));
        $q=$this->pdo->prepare('SELECT crm.*'.$from.$where.' ORDER BY crm.display_name,crm.id LIMIT '.$pageSize.' OFFSET '.(($page-1)*$pageSize));$q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        return ['rows'=>$rows,'total'=>$total,'with_contact'=>(int)$counts['with_contact'],'without_contact'=>$total-(int)$counts['with_contact'],'page'=>$page,'page_count'=>$pageCount,'research_ready'=>$research];
    }

    public function find(int $id):?array
    {
        $q=$this->pdo->prepare('SELECT crm.* FROM ('.$this->baseQuery($this->researchReady()).') crm WHERE crm.id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }

    private function baseQuery(bool $research):string
    {
        $personCount=(new BusinessPersonContactService($this->pdo))->ready()?'(SELECT COUNT(*) FROM business_person_contacts bc WHERE bc.business_id=b.id AND bc.status="active")':'0';
        $researchColumns='0 AS research_contact_count,"" AS crm_stage,"" AS potential_subscription';
        if($research){
            $distinct=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'CONCAT(r.channel,CHAR(31),r.contact_value)':'r.channel||CHAR(31)||r.contact_value';
            $researchColumns='(SELECT COUNT(DISTINCT '.$distinct.') FROM business_research_contacts r WHERE r.business_id=b.id) AS research_contact_count,
                COALESCE((SELECT r.crm_stage FROM business_research_contacts r WHERE r.business_id=b.id AND r.crm_stage<>"" ORDER BY r.id DESC LIMIT 1),"") AS crm_stage,
                COALESCE((SELECT r.potential_subscription FROM business_research_contacts r WHERE r.business_id=b.id AND r.potential_subscription<>"" ORDER BY r.id DESC LIMIT 1),"") AS potential_subscription';
        }
        return 'SELECT b.id,b.public_id,b.category_code,b.publication_status,b.operation_status,b.claim_status,
                COALESCE(NULLIF(t.display_name,""),b.slug) AS display_name,d.slug AS destination_slug,
                COALESCE(NULLIF(dt.display_name,""),d.slug) AS destination_name,COALESCE(ct.label,b.category_code) AS category_label,
                COALESCE((SELECT s.plan_code FROM business_subscriptions s WHERE s.business_id=b.id AND s.status="active" AND (s.starts_at IS NULL OR s.starts_at<=CURRENT_TIMESTAMP) AND (s.ends_at IS NULL OR s.ends_at>CURRENT_TIMESTAMP) ORDER BY s.id DESC LIMIT 1),"free") AS current_plan,
                COALESCE((SELECT s.status FROM business_subscriptions s WHERE s.business_id=b.id ORDER BY s.id DESC LIMIT 1),"sin suscripción") AS subscription_status,
                (SELECT COUNT(*) FROM business_contacts pc WHERE pc.business_id=b.id) AS profile_contact_count,'.$personCount.' AS person_contact_count,'.$researchColumns.'
            FROM businesses b JOIN destinations d ON d.id=b.destination_id
            LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            LEFT JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es"
            LEFT JOIN service_category_translations ct ON ct.category_code=b.category_code AND ct.locale="es"';
    }
}

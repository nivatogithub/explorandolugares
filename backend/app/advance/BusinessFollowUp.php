<?php
declare(strict_types=1);
final class BusinessFollowUp {
    public function __construct(private PDO $db,private string $actor) {}
    private function query(string $sql,array $params=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($params);return $q;}
    public function overview(string $filter='all',string $search='',int $page=1,?string $date=null): array {
        $today=$date??(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d');
        $due=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
        $sql="SELECT b.id,COALESCE(t.display_name,b.slug) AS name,COALESCE(dt.display_name,d.slug) AS destination,
          COALESCE(ct.label,b.category_code) AS category,b.information_status,b.claim_status,b.publication_status,
          COALESCE(f.free_confirmed,0) AS free_confirmed,COALESCE(f.next_action,'') AS next_action,f.follow_up_on,COALESCE(f.validation_notes,'') AS validation_notes,COALESCE(f.publication_blockers,'') AS publication_blockers,COALESCE(f.offer_notes,'') AS offer_notes,
          CASE WHEN s.id IS NOT NULL AND s.status='active' THEN 'subscription' ELSE COALESCE(f.participation_type,'unclassified') END AS participation_type,
          s.id AS subscription_id,s.plan_name,s.ends_on,
          CASE WHEN s.id IS NULL THEN 'none' WHEN s.status='cancelled' THEN 'cancelled' WHEN s.ends_on<? THEN 'expired' WHEN s.ends_on<=? THEN 'due_soon' ELSE 'active' END AS subscription_state,
          CASE WHEN EXISTS(SELECT 1 FROM subscription_payments p WHERE p.subscription_id=s.id AND p.received_on<=? GROUP BY p.currency HAVING SUM(CASE WHEN p.entry_type='payment' THEN p.amount_minor ELSE -p.amount_minor END)>0) THEN 1 ELSE 0 END AS has_payment
          FROM businesses b JOIN destinations d ON d.id=b.destination_id
          LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale='es'
          LEFT JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale='es'
          LEFT JOIN service_category_translations ct ON ct.category_code=b.category_code AND ct.locale='es'
          LEFT JOIN business_follow_up f ON f.business_id=b.id
          LEFT JOIN business_subscriptions s ON s.id=(SELECT s2.id FROM business_subscriptions s2 WHERE s2.business_id=b.id AND s2.starts_on<=? ORDER BY s2.starts_on DESC,s2.id DESC LIMIT 1)";
        $conditions=['all'=>'1=1','free'=>"participation_type='free'",'free_unconfirmed'=>"participation_type='free' AND free_confirmed=0",'free_confirmed'=>"participation_type='free' AND free_confirmed=1",'subscription'=>"participation_type='subscription'",'active'=>"subscription_state IN ('active','due_soon')",'draft'=>"publication_status='draft'",'due_soon'=>"subscription_state='due_soon'",'expired'=>"subscription_state='expired'",'validation'=>"information_status NOT IN ('VERIFICADO','verified') OR claim_status IN ('EN_TRAMITE','under_review')",'paid_unpublished'=>"has_payment=1 AND publication_status='draft'",'published'=>"publication_status='published'",'unclassified'=>"participation_type='unclassified'"];
        if(!isset($conditions[$filter]))throw new InvalidArgumentException('Filtro inexistente.');
        $params=[$today,$due,$today,$today];
        $counts=[];foreach($conditions as $key=>$where)$counts[$key]=(int)$this->query('SELECT COUNT(*) FROM ('.$sql.') business_overview WHERE '.$where,$params)->fetchColumn();
        $where=$conditions[$filter].' AND name LIKE ?';$args=[...$params,'%'.substr($search,0,120).'%'];
        $total=(int)$this->query('SELECT COUNT(*) FROM ('.$sql.') business_overview WHERE '.$where,$args)->fetchColumn();
        $rows=$this->query('SELECT * FROM ('.$sql.') business_overview WHERE '.$where.' ORDER BY name,id LIMIT 25 OFFSET '.((max(1,$page)-1)*25),$args)->fetchAll(PDO::FETCH_ASSOC);
        return ['counts'=>$counts,'rows'=>$rows,'total'=>$total,'today'=>$today];
    }
    public function find(int $id): array {return $this->query('SELECT * FROM business_follow_up WHERE business_id=?',[$id])->fetch(PDO::FETCH_ASSOC)?:['business_id'=>$id,'participation_type'=>'unclassified','free_confirmed'=>0,'version'=>0];}
    public function save(array $data): int {
        $id=filter_var($data['id']??0,FILTER_VALIDATE_INT);$version=filter_var($data['follow_up_version']??0,FILTER_VALIDATE_INT);
        $type=$data['participation_type']??'';if(!$id||$version===false||!in_array($type,['unclassified','free','subscription'],true))throw new InvalidArgumentException('Datos de seguimiento inválidos.');
        $values=[];foreach(['validation_notes','publication_blockers','offer_notes','next_action'] as $key){$value=$data[$key]??'';if(!is_string($value)||strlen($value)>5000)throw new InvalidArgumentException('Revisa '.$key.'.');$values[$key]=trim($value);}
        $date=$data['follow_up_on']??'';if(!is_string($date))throw new InvalidArgumentException('Fecha inválida.');
        if($date!==''&&(($parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date))===false||$parsed->format('Y-m-d')!==$date))throw new InvalidArgumentException('Fecha inválida.');
        $this->db->beginTransaction();try{
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            if(!$this->query('SELECT id FROM businesses WHERE id=?'.$lock,[$id])->fetchColumn())throw new InvalidArgumentException('Negocio inexistente.');
            if($type==='free'){
                $today=(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d');
                $term=$this->query('SELECT status FROM business_subscriptions WHERE business_id=? AND starts_on<=? ORDER BY starts_on DESC,id DESC LIMIT 1',[$id,$today])->fetchColumn();
                if($term==='active')throw new InvalidArgumentException('El negocio tiene un periodo de suscripción sin cancelar. Revisa ese periodo antes de cambiarlo a gratis.');
            }
            $old=$this->find($id);if((int)$old['version']!==$version)throw new RuntimeException('El seguimiento cambió. Recarga antes de guardar.');
            $args=[$type,$type==='free'&&($data['free_confirmed']??'')==='1'?1:0,...array_values($values),$date?:null,gmdate('Y-m-d H:i:s')];
            if($version)$this->query('UPDATE business_follow_up SET participation_type=?,free_confirmed=?,validation_notes=?,publication_blockers=?,offer_notes=?,next_action=?,follow_up_on=?,updated_at=?,version=version+1 WHERE business_id=?',[...$args,$id]);
            else $this->query('INSERT INTO business_follow_up(participation_type,free_confirmed,validation_notes,publication_blockers,offer_notes,next_action,follow_up_on,updated_at,business_id) VALUES(?,?,?,?,?,?,?,?,?)',[...$args,$id]);
            $this->query('INSERT INTO advance_audit(actor,action,entity,entity_id,created_at) VALUES(?,?,?,?,?)',[$this->actor,'save_follow_up','businesses',$id,gmdate('Y-m-d H:i:s')]);$this->db->commit();return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
}

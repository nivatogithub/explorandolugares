<?php
declare(strict_types=1);
/** General reporting reads canonical catalog entities and an explicit manual billing ledger. */
final class AdminReports {
    public function __construct(private PDO $db,private string $actor) {}
    private function query(string $sql,array $params=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($params);return $q;}
    private function count(string $table,string $where='1=1'): int {return (int)$this->query('SELECT COUNT(*) FROM '.$table.' WHERE '.$where)->fetchColumn();}
    public function snapshot(?string $date=null): array {
        $today=$date??(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d');
        $this->date($today);$limit=(new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
        $out=['today'=>$today,'due_until'=>$limit];
        foreach(['destinations','businesses','points_of_interest'] as $table){
            $out[$table]=['total'=>$this->count($table),'published'=>$this->count($table,"publication_status='published'"),'draft'=>$this->count($table,"publication_status='draft'"),'archived'=>$this->count($table,"publication_status='archived'")];
        }
        $out['review']=['leads'=>$this->count('leads',"status='nuevo'"),'contacts'=>$this->count('advance_contacts',"relationship='under_review'"),'campaigns'=>$this->count('advance_campaigns',"status='draft'")];
        $out['destination_readiness']=$this->query("SELECT d.id,COALESCE(t.display_name,d.slug) AS name,d.publication_status,
          CASE WHEN t.destination_id IS NULL OR t.display_name='' OR t.headline='' OR t.intro='' THEN 1 ELSE 0 END AS missing_text,
          CASE WHEN EXISTS(SELECT 1 FROM destination_media m WHERE m.destination_id=d.id AND m.role='hero') THEN 0 ELSE 1 END AS missing_hero,
          (SELECT COUNT(*) FROM businesses b WHERE b.destination_id=d.id AND b.publication_status='published') AS published_businesses
          FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale='es' WHERE d.publication_status<>'archived' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $out['business_mix']=$this->query("SELECT d.id AS destination_id,COALESCE(dt.display_name,d.slug) AS destination, b.category_code,COALESCE(ct.label,b.category_code) AS category,
          COUNT(*) AS total,SUM(CASE WHEN b.publication_status='published' THEN 1 ELSE 0 END) AS published,SUM(CASE WHEN b.publication_status='draft' THEN 1 ELSE 0 END) AS draft
          FROM businesses b JOIN destinations d ON d.id=b.destination_id LEFT JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale='es'
          LEFT JOIN service_category_translations ct ON ct.category_code=b.category_code AND ct.locale='es'
          GROUP BY d.id,COALESCE(dt.display_name,d.slug),b.category_code,COALESCE(ct.label,b.category_code) ORDER BY destination,category")->fetchAll(PDO::FETCH_ASSOC);
        $out['operators']=$this->query("SELECT d.id AS destination_id,COALESCE(t.display_name,d.slug) AS destination,c.capability_code,COUNT(*) AS total
          FROM business_capabilities c JOIN businesses b ON b.id=c.business_id JOIN destinations d ON d.id=b.destination_id
          LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale='es'
          GROUP BY d.id,COALESCE(t.display_name,d.slug),c.capability_code ORDER BY destination,c.capability_code")->fetchAll(PDO::FETCH_ASSOC);
        $out['subscriptions']=$this->query("SELECT COUNT(*) AS total,
          COALESCE(SUM(CASE WHEN status='active' AND starts_on<=? AND ends_on>=? THEN 1 ELSE 0 END),0) AS active,
          COALESCE(SUM(CASE WHEN status='active' AND starts_on<=? AND ends_on>=? AND ends_on<=? THEN 1 ELSE 0 END),0) AS due_soon,
          COALESCE(SUM(CASE WHEN status='active' AND ends_on<? THEN 1 ELSE 0 END),0) AS expired,
          COALESCE(SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END),0) AS cancelled
          FROM business_subscriptions s WHERE s.starts_on<=? AND NOT EXISTS(SELECT 1 FROM business_subscriptions newer WHERE newer.business_id=s.business_id AND newer.starts_on<=? AND (newer.starts_on>s.starts_on OR (newer.starts_on=s.starts_on AND newer.id>s.id)))",[$today,$today,$today,$today,$limit,$today,$today,$today])->fetch(PDO::FETCH_ASSOC);
        $out['subscriptions']['registered']=$this->count('business_subscriptions');
        $out['revenue']=$this->query("SELECT currency,COUNT(*) AS entries,
          SUM(CASE WHEN entry_type='payment' THEN amount_minor ELSE 0 END) AS collected_minor,
          SUM(CASE WHEN entry_type='refund' THEN amount_minor ELSE 0 END) AS refunded_minor,
          SUM(CASE WHEN entry_type='payment' THEN amount_minor ELSE -amount_minor END) AS net_minor
          FROM subscription_payments WHERE received_on<=? GROUP BY currency ORDER BY currency",[$today])->fetchAll(PDO::FETCH_ASSOC);
        $out['renewals']=$this->query("SELECT s.*,COALESCE(t.display_name,b.slug) AS business,COALESCE(dt.display_name,d.slug) AS destination
          FROM business_subscriptions s JOIN businesses b ON b.id=s.business_id JOIN destinations d ON d.id=b.destination_id
          LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale='es' LEFT JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale='es'
          WHERE s.status='active' AND s.starts_on<=? AND s.ends_on<=? AND NOT EXISTS(SELECT 1 FROM business_subscriptions newer WHERE newer.business_id=s.business_id AND newer.starts_on<=? AND (newer.starts_on>s.starts_on OR (newer.starts_on=s.starts_on AND newer.id>s.id))) ORDER BY s.ends_on,s.id LIMIT 100",[$today,$limit,$today])->fetchAll(PDO::FETCH_ASSOC);
        return $out;
    }
    private function date(string $date): string {
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Fecha inválida.');return $date;
    }
    private function text(array $data,string $key,int $max=120): string {
        $v=$data[$key]??'';if(!is_string($v)||trim($v)===''||strlen($v)>$max)throw new InvalidArgumentException('Revisa '.$key.'.');return trim($v);
    }
    public function record(string $action,array $data): void {
        $this->db->beginTransaction();try{
            $now=gmdate('Y-m-d H:i:s');$entity='';$id=0;
            if($action==='subscription'){
                $id=filter_var($data['business_id']??0,FILTER_VALIDATE_INT);
                if(!$id||!$this->query('SELECT id FROM businesses WHERE id=?'.($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id])->fetchColumn())throw new InvalidArgumentException('Negocio inexistente.');
                $start=$this->date($this->text($data,'starts_on',10));$end=$this->date($this->text($data,'ends_on',10));
                if($end<$start)throw new InvalidArgumentException('La fecha final debe ser posterior o igual a la inicial.');
                // Overlapping active periods would inflate counts and hide renewal work.
                if($this->query("SELECT id FROM business_subscriptions WHERE business_id=? AND status='active' AND starts_on<=? AND ends_on>=?",[$id,$end,$start])->fetchColumn())throw new InvalidArgumentException('Ese negocio ya tiene una suscripción que cubre parte de ese periodo.');
                $this->query("INSERT INTO business_subscriptions(business_id,reference,status,starts_on,ends_on,created_at) VALUES(?,?,'active',?,?,?)",[$id,$this->text($data,'reference'),$start,$end,$now]);$entity='subscriptions';$id=(int)$this->db->lastInsertId();
            }elseif($action==='cancel_subscription'){
                $id=filter_var($data['subscription_id']??0,FILTER_VALIDATE_INT);
                if(!$id||!$this->query('SELECT id FROM business_subscriptions WHERE id=?'.($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id])->fetchColumn())throw new InvalidArgumentException('Suscripción inexistente.');
                $this->query("UPDATE business_subscriptions SET status='cancelled' WHERE id=?",[$id]);$entity='subscriptions';
            }elseif($action==='payment'){
                $id=filter_var($data['subscription_id']??0,FILTER_VALIDATE_INT);
                if(!$id||!$this->query('SELECT id FROM business_subscriptions WHERE id=?'.($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id])->fetchColumn())throw new InvalidArgumentException('Suscripción inexistente.');
                $amount=$this->text($data,'amount',16);
                if(!preg_match('/^([0-9]{1,11})(?:\.([0-9]{1,2}))?$/D',$amount,$m))throw new InvalidArgumentException('Importe inválido. Usa punto decimal y hasta dos decimales.');
                $minor=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($minor<=0)throw new InvalidArgumentException('El importe debe ser positivo.');
                $currency=$this->text($data,'currency',3);if(!in_array($currency,['COP','USD','EUR'],true))throw new InvalidArgumentException('Moneda permitida: COP, USD o EUR.');
                $type=$this->text($data,'entry_type',24);if(!in_array($type,['payment','refund'],true))throw new InvalidArgumentException('Tipo de movimiento inválido.');
                $received=$this->date($this->text($data,'received_on',10));if($received>(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d'))throw new InvalidArgumentException('No registres recaudos futuros como recibidos.');
                if($type==='refund'){
                    $balance=(int)$this->query("SELECT COALESCE(SUM(CASE WHEN entry_type='payment' THEN amount_minor ELSE -amount_minor END),0) FROM subscription_payments WHERE subscription_id=? AND currency=?",[$id,$currency])->fetchColumn();
                    if($minor>$balance)throw new InvalidArgumentException('La devolución supera lo registrado en esa moneda.');
                }
                $this->query('INSERT INTO subscription_payments(subscription_id,reference,entry_type,amount_minor,currency,received_on,created_at) VALUES(?,?,?,?,?,?,?)',[$id,$this->text($data,'reference'),$type,$minor,$currency,$received,$now]);$entity='payments';$id=(int)$this->db->lastInsertId();
            }elseif($action==='capability'){
                $id=filter_var($data['business_id']??0,FILTER_VALIDATE_INT);$code=$this->text($data,'capability_code',40);
                if(!$id||!$this->query('SELECT id FROM businesses WHERE id=?'.($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id])->fetchColumn()||!in_array($code,['tour_operator','experience_operator'],true))throw new InvalidArgumentException('Negocio o capacidad inválida.');
                $mode=$data['capability_action']??'add';
                if(!in_array($mode,['add','remove'],true))throw new InvalidArgumentException('Acción de clasificación inválida.');
                if($mode==='remove')$this->query('DELETE FROM business_capabilities WHERE business_id=? AND capability_code=?',[$id,$code]);
                elseif(!$this->query('SELECT business_id FROM business_capabilities WHERE business_id=? AND capability_code=?',[$id,$code])->fetchColumn())$this->query('INSERT INTO business_capabilities(business_id,capability_code) VALUES(?,?)',[$id,$code]);$entity='businesses';
            }else throw new InvalidArgumentException('Acción inválida.');
            $this->query('INSERT INTO advance_audit(actor,action,entity,entity_id,created_at) VALUES(?,?,?,?,?)',[$this->actor,'record_'.$action,$entity,$id,$now]);$this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
}

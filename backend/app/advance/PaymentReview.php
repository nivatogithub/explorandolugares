<?php
declare(strict_types=1);
final class PaymentReview {
    public function __construct(private PDO $db,private string $actor) {}
    private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    private function text(array $data,string $key,int $max): string {$v=$data[$key]??'';if(!is_string($v)||trim($v)===''||strlen($v)>$max)throw new InvalidArgumentException('Revisa '.$key.'.');return trim($v);}
    public function subscriptions(): array {return $this->query("SELECT s.id,s.reference,s.plan_name,s.starts_on,s.ends_on,COALESCE(t.display_name,b.slug) AS business FROM business_subscriptions s JOIN businesses b ON b.id=s.business_id LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale='es' ORDER BY s.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);}
    public function rows(string $status): array {
        if(!in_array($status,['pending','confirmed','rejected'],true))throw new InvalidArgumentException('Estado inválido.');
        return $this->query("SELECT c.*,s.business_id,s.plan_name,COALESCE(t.display_name,b.slug) AS business FROM subscription_payment_claims c JOIN business_subscriptions s ON s.id=c.subscription_id JOIN businesses b ON b.id=s.business_id LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale='es' WHERE c.status=? ORDER BY c.id DESC LIMIT 100",[$status])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function history(): array {
        return $this->query("SELECT p.*,s.business_id,COALESCE(t.display_name,b.slug) AS business,c.payer_name,c.reviewed_by,c.reviewed_at
          FROM subscription_payments p JOIN business_subscriptions s ON s.id=p.subscription_id JOIN businesses b ON b.id=s.business_id
          LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale='es' LEFT JOIN subscription_payment_claims c ON c.payment_id=p.id
          ORDER BY p.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function submit(array $data): void {
        $sub=filter_var($data['subscription_id']??0,FILTER_VALIDATE_INT);$reference=$this->text($data,'reference',120);$payer=$this->text($data,'payer_name',160);$note=$this->text($data,'evidence_note',2000);
        $amount=$this->text($data,'amount',16);if(!preg_match('/^([0-9]{1,11})(?:\.([0-9]{1,2}))?$/D',$amount,$m))throw new InvalidArgumentException('Importe inválido.');
        $minor=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($minor<=0)throw new InvalidArgumentException('El importe debe ser positivo.');
        $currency=$this->text($data,'currency',3);if(!in_array($currency,['COP','USD','EUR'],true))throw new InvalidArgumentException('Moneda inválida.');
        $date=$this->text($data,'reported_on',10);$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date>(new DateTimeImmutable('now',new DateTimeZone('America/Bogota')))->format('Y-m-d'))throw new InvalidArgumentException('Fecha inválida o futura.');
        $this->db->beginTransaction();try{
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            if(!$sub||!$this->query('SELECT id FROM business_subscriptions WHERE id=?'.$lock,[$sub])->fetchColumn())throw new InvalidArgumentException('Suscripción inexistente.');
            if($this->query('SELECT id FROM subscription_payments WHERE reference=?',[$reference])->fetchColumn())throw new InvalidArgumentException('Esa referencia ya está registrada como movimiento confirmado.');
            $this->query("INSERT INTO subscription_payment_claims(subscription_id,reference,payer_name,amount_minor,currency,reported_on,evidence_note,status,created_at) VALUES(?,?,?,?,?,?,?,'pending',?)",[$sub,$reference,$payer,$minor,$currency,$date,$note,gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->lastInsertId();
            $this->audit('report_payment',$id);$this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public function review(array $data,bool $confirm): void {
        $id=filter_var($data['claim_id']??0,FILTER_VALIDATE_INT);$note=$this->text($data,'review_note',2000);
        $this->db->beginTransaction();try{
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $c=$this->query('SELECT * FROM subscription_payment_claims WHERE id=?'.$lock,[$id])->fetch(PDO::FETCH_ASSOC);
            if(!$c||$c['status']!=='pending')throw new InvalidArgumentException('Este reporte no está pendiente de revisión.');
            $payment=null;$now=gmdate('Y-m-d H:i:s');
            if($confirm){
                $this->query('SELECT id FROM business_subscriptions WHERE id=?'.$lock,[$c['subscription_id']]);
                $this->query("INSERT INTO subscription_payments(subscription_id,reference,entry_type,amount_minor,currency,received_on,created_at) VALUES(?,?,'payment',?,?,?,?)",[$c['subscription_id'],$c['reference'],$c['amount_minor'],$c['currency'],$c['reported_on'],$now]);$payment=(int)$this->db->lastInsertId();
            }
            $this->query('UPDATE subscription_payment_claims SET status=?,reviewed_by=?,reviewed_at=?,review_note=?,payment_id=? WHERE id=?',[$confirm?'confirmed':'rejected',$this->actor,$now,$note,$payment,$id]);
            $this->audit($confirm?'confirm_payment':'reject_payment',(int)$id);$this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    private function audit(string $action,int $id): void {$this->query('INSERT INTO advance_audit(actor,action,entity,entity_id,created_at) VALUES(?,?,?,?,?)',[$this->actor,$action,'payment_claims',$id,gmdate('Y-m-d H:i:s')]);}
}

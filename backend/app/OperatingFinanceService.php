<?php
declare(strict_types=1);
final class OperatingFinanceService
{
    public const CATEGORIES=['hosting'=>'Hosting e infraestructura','advertising'=>'Publicidad y adquisición','travel'=>'Viajes','events'=>'Eventos y conferencias','tools'=>'Herramientas','labor'=>'Trabajo y honorarios','other'=>'Otros'];
    public const REASONS=['price'=>'Precio','value'=>'No obtiene el valor esperado','closure'=>'Cierre del negocio','other'=>'Otro motivo'];
    public function __construct(private PDO $pdo){}
    public function ready():bool{try{$this->pdo->query('SELECT cost_date FROM operating_costs LIMIT 0');$this->pdo->query('SELECT subscription_id FROM subscription_cancellation_requests LIMIT 0');return true;}catch(Throwable){return false;}}
    public function addCost(array $data,string $actor):int{
        $date=(string)($data['cost_date']??'');$amount=trim((string)($data['amount_cop']??''));$category=(string)($data['category']??'');$description=trim((string)($data['description']??''));
        $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date>gmdate('Y-m-d'))throw new RuntimeException('Indica una fecha válida, sin registrar gastos futuros como realizados.');
        if(!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D',$amount)||(float)$amount<=0)throw new RuntimeException('Importe COP mayor que cero, sin separadores de miles y con hasta dos decimales.');
        if(!isset(self::CATEGORIES[$category])||$description===''||mb_strlen($description)>500)throw new RuntimeException('Selecciona categoría y una descripción de hasta 500 caracteres.');
        $destination=null;if(($data['destination_id']??'')!==''){$destination=filter_var($data['destination_id'],FILTER_VALIDATE_INT);$q=$this->pdo->prepare('SELECT id FROM destinations WHERE id=?');$q->execute([$destination]);if(!$destination||!$q->fetchColumn())throw new RuntimeException('Lugar no válido.');}
        $q=$this->pdo->prepare('INSERT INTO operating_costs(cost_date,category,amount_cop,destination_id,description,created_by) VALUES(?,?,?,?,?,?)');$q->execute([$date,$category,$amount,$destination,$description,mb_substr($actor,0,120)]);return (int)$this->pdo->lastInsertId();
    }
    public function voidCost(int $id,string $reason):void{$reason=trim($reason);if($reason===''||mb_strlen($reason)>500)throw new RuntimeException('Indica el motivo de anulación (máximo 500 caracteres).');$q=$this->pdo->prepare('UPDATE operating_costs SET voided_at=CURRENT_TIMESTAMP,void_reason=? WHERE id=? AND voided_at IS NULL');$q->execute([$reason,$id]);if($q->rowCount()!==1)throw new RuntimeException('Gasto inexistente o ya anulado.');}
    public function requestCancellation(int $account,int $business,string $reason,string $text):void{
        if(!isset(self::REASONS[$reason])||mb_strlen($text)>1000)throw new RuntimeException('Selecciona un motivo y usa hasta 1000 caracteres.');
        $q=$this->pdo->prepare("SELECT s.id FROM business_subscriptions s JOIN business_account_memberships m ON m.business_id=s.business_id JOIN accounts a ON a.id=m.account_id WHERE m.account_id=? AND m.business_id=? AND m.status='active' AND a.status='active' AND s.plan_code<>'free' AND s.status='active' AND (s.starts_at IS NULL OR s.starts_at<=CURRENT_TIMESTAMP) AND (s.ends_at IS NULL OR s.ends_at>CURRENT_TIMESTAMP) ORDER BY s.id DESC LIMIT 1");$q->execute([$account,$business]);$id=$q->fetchColumn();if(!$id)throw new RuntimeException('No tienes una suscripción paga vigente asociada a este negocio.');
        $q=$this->pdo->prepare('SELECT id FROM subscription_cancellation_requests WHERE subscription_id=?');$q->execute([$id]);if($q->fetchColumn())return;
        try{$q=$this->pdo->prepare('INSERT INTO subscription_cancellation_requests(subscription_id,business_id,account_id,reason_code,reason_text) VALUES(?,?,?,?,?)');$q->execute([$id,$business,$account,$reason,trim($text)]);}catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;}
    }
    public function reviewCancellation(int $id,string $note):void{$note=trim($note);if($note===''||mb_strlen($note)>1000)throw new RuntimeException('Registra el resultado de la atención (hasta 1000 caracteres).');$q=$this->pdo->prepare("UPDATE subscription_cancellation_requests SET status='reviewed',reviewed_at=CURRENT_TIMESTAMP,review_note=? WHERE id=? AND status='pending'");$q->execute([$note,$id]);if($q->rowCount()!==1)throw new RuntimeException('Solicitud inexistente o ya revisada.');}
    public function summary(string $from,string $to):array{
        if(!$this->ready())return ['available'=>false];
        $q=$this->pdo->prepare('SELECT COUNT(*),COALESCE(SUM(amount_cop),0) FROM operating_costs WHERE voided_at IS NULL AND cost_date>=? AND cost_date<?');$q->execute([$from,$to]);$row=$q->fetch(PDO::FETCH_NUM);
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM subscription_cancellation_requests WHERE created_at>=? AND created_at<?');$q->execute([$from,$to]);return ['available'=>true,'cost_count'=>(int)$row[0],'costs_cop'=>(float)$row[1],'cancellation_requests'=>(int)$q->fetchColumn()];
    }
}

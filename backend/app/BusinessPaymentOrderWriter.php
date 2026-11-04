<?php
declare(strict_types=1);
/** Writes only on the transaction owned by BusinessPaymentOrderService. */
final class BusinessPaymentOrderWriter
{
    public function __construct(private PDO $pdo){}
    private function execute(string $sql,array $values):int{if(!$this->pdo->inTransaction())throw new LogicException('Payment order writes require one transaction.');$q=$this->pdo->prepare($sql);$q->execute($values);return $q->rowCount();}
    public function create(int $account,string $key,string $hash,int $total,string $currency):int
    {
        $this->execute('INSERT INTO business_payment_orders(public_id,account_id,request_key,request_hash,checkout_reference,total_cents,currency) VALUES(?,?,?,?,?,?,?)',[bin2hex(random_bytes(24)),$account,$key,$hash,'EL-'.strtoupper(bin2hex(random_bytes(12))),$total,$currency]);return (int)$this->pdo->lastInsertId();
    }
    public function addItem(int $order,array $item):void
    {
        $this->execute('INSERT INTO business_payment_order_items(order_id,business_id,source_request_id,open_business_id,business_name,place_name,quote_json,addon_json,due_cents) VALUES(?,?,?,?,?,?,?,?,?)',[$order,$item['business_id'],$item['source_request_id'],$item['business_id'],$item['business_name'],$item['place_name'],json_encode($item['quote'],JSON_THROW_ON_ERROR),$item['addon']===null?null:json_encode($item['addon'],JSON_THROW_ON_ERROR),$item['quote']['due_cents']]);
    }
    public function createSubscription(array $item):int
    {
        $q=$item['quote'];$this->execute('UPDATE business_subscriptions SET status="cancelled" WHERE business_id=? AND status IN ("pending","pending_publication")',[$item['business_id']]);
        $this->execute('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,quoted_amount,currency,renewal_of_subscription_id) VALUES(?,?,?,"pending",?,?,?)',[$item['business_id'],$q['plan_code'],$q['billing_frequency'],SubscriptionChangeService::money($q['due_cents']),$q['currency'],$q['renewal_of_subscription_id']??($q['kind']==='renewal'?$q['source_subscription_id']:null)]);return (int)$this->pdo->lastInsertId();
    }
    public function target(int $item,int $subscription):void{$this->execute('UPDATE business_payment_order_items SET subscription_id=? WHERE id=?',[$subscription,$item]);}
    public function attachEvidence(array $order,int $subscription,array $file,string $reference,array $details):int
    {
        $this->execute('INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,payment_reference,checkout_reference,transaction_date,paid_amount,payment_method,source_bank) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$subscription,$order['account_id'],$file['storage_name'],$file['original_name'],$file['mime_type'],$file['file_size'],$reference,'EL-'.strtoupper(bin2hex(random_bytes(12))),$details['transaction_date'],$details['paid_amount'],$details['payment_method'],$details['source_bank']]);$id=(int)$this->pdo->lastInsertId();$this->execute('INSERT INTO business_payment_order_evidence(evidence_id,order_id) VALUES(?,?)',[$id,$order['id']]);return $id;
    }
    public function status(int $order,string $status):void{$this->execute('UPDATE business_payment_orders SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$order]);}
    public function release(int $order):void{$this->execute('UPDATE business_payment_order_items SET open_business_id=NULL WHERE order_id=?',[$order]);}
    public function confirm(int $subscription):void{if($this->execute('UPDATE business_subscriptions SET status="paid_pending_publication",reviewed_at=CURRENT_TIMESTAMP WHERE id=? AND status="pending"',[$subscription])!==1)throw new RuntimeException('Una suscripción cambió; no se confirmó ninguna parte del pago.');}
    public function review(int $evidence,string $decision,string $note,string $actor,string $message):void{$this->execute('UPDATE business_subscription_payment_evidence SET status=?,admin_note=?,customer_message=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?',[$decision,$note,$message,$actor,$evidence]);}
}

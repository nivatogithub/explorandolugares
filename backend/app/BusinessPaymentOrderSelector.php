<?php
declare(strict_types=1);
/** SQL reads for grouped payments; callers supply identity and transaction boundaries. */
final class BusinessPaymentOrderSelector
{
    public function __construct(private PDO $pdo){}
    public function available():bool
    {
        try{$this->pdo->query('SELECT id FROM business_payment_orders LIMIT 0');return true;}
        catch(PDOException $e){if(str_contains($e->getMessage(),'no such table')||($e->errorInfo[1]??0)===1146)return false;throw $e;}
    }
    private function lock(bool $lock):string{return $lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
    private function one(string $sql,array $values):?array{$q=$this->pdo->prepare($sql);$q->execute($values);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
    public function account(int $account,bool $lock=false):?array{return $this->one('SELECT id FROM accounts WHERE id=? AND status="active"'.$this->lock($lock),[$account]);}
    public function business(int $account,int $business,bool $lock=false):?array
    {
        return $this->one('SELECT b.*,t.display_name,dt.display_name AS place_name FROM businesses b JOIN business_account_memberships m ON m.business_id=b.id JOIN accounts a ON a.id=m.account_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destination_translations dt ON dt.destination_id=b.destination_id AND dt.locale="es" WHERE b.id=? AND m.account_id=? AND m.status="active" AND m.role IN ("owner","manager") AND a.status="active"'.$this->lock($lock),[$business,$account]);
    }
    public function businesses(int $account):array
    {
        $q=$this->pdo->prepare('SELECT b.id,t.display_name,dt.display_name AS place_name FROM businesses b JOIN business_account_memberships m ON m.business_id=b.id JOIN accounts a ON a.id=m.account_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destination_translations dt ON dt.destination_id=b.destination_id AND dt.locale="es" WHERE m.account_id=? AND m.status="active" AND m.role IN ("owner","manager") AND a.status="active" ORDER BY t.display_name,b.id');$q->execute([$account]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function hasHistory(int $business):bool{return $this->one('SELECT id FROM business_payment_order_items WHERE business_id=? LIMIT 1',[$business])!==null;}
    public function activePlan(string $code):bool{return $this->one('SELECT code FROM subscription_plans WHERE code=? AND is_active=1',[$code])!==null;}
    public function latestSubscription(int $business,bool $lock=false):?array{return $this->one('SELECT * FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1'.$this->lock($lock),[$business]);}
    public function subscription(int $id,bool $lock=false):?array{return $this->one('SELECT * FROM business_subscriptions WHERE id=?'.$this->lock($lock),[$id]);}
    public function openForBusiness(int $business):?array{return $this->one('SELECT o.* FROM business_payment_orders o JOIN business_payment_order_items i ON i.order_id=o.id WHERE i.open_business_id=?',[$business]);}
    public function request(int $account,string $key):?array{return $this->one('SELECT * FROM business_payment_orders WHERE account_id=? AND request_key=?',[$account,$key]);}
    public function order(string $publicId,int $account,bool $lock=false):?array{return $this->one('SELECT * FROM business_payment_orders WHERE public_id=? AND account_id=?'.$this->lock($lock),[$publicId,$account]);}
    public function byId(int $id,bool $lock=false):?array{return $this->one('SELECT * FROM business_payment_orders WHERE id=?'.$this->lock($lock),[$id]);}
    public function byEvidence(int $evidence):?array{return $this->one('SELECT o.* FROM business_payment_orders o JOIN business_payment_order_evidence e ON e.order_id=o.id WHERE e.evidence_id=?',[$evidence]);}
    public function items(int $order):array{$q=$this->pdo->prepare('SELECT * FROM business_payment_order_items WHERE order_id=? ORDER BY business_id');$q->execute([$order]);$items=$q->fetchAll(PDO::FETCH_ASSOC);foreach($items as &$item){$item['quote']=json_decode($item['quote_json'],true,64,JSON_THROW_ON_ERROR);$item['addon']=$item['addon_json']===null?null:json_decode($item['addon_json'],true,32,JSON_THROW_ON_ERROR);}return $items;}
    public function orders(int $account):array{$q=$this->pdo->prepare('SELECT * FROM business_payment_orders WHERE account_id=? ORDER BY id DESC LIMIT 50');$q->execute([$account]);return $q->fetchAll(PDO::FETCH_ASSOC);}
    public function pendingEvidence(int $business):?array
    {
        return $this->one('SELECT e.id FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE s.business_id=? AND e.status="pending" LIMIT 1',[$business]);
    }
    public function latestEvidence(int $account,int $business):?array
    {
        return $this->one('SELECT e.id,e.status,e.customer_message,e.reviewed_at FROM business_subscription_payment_evidence e JOIN business_payment_order_evidence x ON x.evidence_id=e.id JOIN business_payment_order_items i ON i.order_id=x.order_id WHERE e.account_id=? AND i.business_id=? ORDER BY e.id DESC LIMIT 1',[$account,$business]);
    }
    public function evidence(int $order):?array{return $this->one('SELECT e.* FROM business_subscription_payment_evidence e JOIN business_payment_order_evidence x ON x.evidence_id=e.id WHERE x.order_id=? ORDER BY e.id DESC LIMIT 1',[$order]);}
    public function evidenceForSubscription(int $subscription):?array
    {
        return $this->one('SELECT e.* FROM business_subscription_payment_evidence e JOIN business_payment_order_evidence x ON x.evidence_id=e.id JOIN business_payment_order_items i ON i.order_id=x.order_id WHERE i.subscription_id=? ORDER BY e.id DESC LIMIT 1',[$subscription]);
    }
    /** Legacy receipt rows retain their IDs/files; a grouped receipt is displayed once with its allocations. */
    public function decorate(array $rows):array
    {
        if(!$this->available())return $rows;
        foreach($rows as &$row){
            $order=$this->byEvidence((int)($row['evidence_id']??$row['id']??0));if(!$order)continue;
            $row['order_items']=$this->items((int)$order['id']);$row['order_public_id']=$order['public_id'];
            $row['checkout_reference']=$order['checkout_reference'];$row['quoted_amount']=SubscriptionChangeService::money((int)$order['total_cents']);
            $row['currency']=$order['currency'];
        }return $rows;
    }

}

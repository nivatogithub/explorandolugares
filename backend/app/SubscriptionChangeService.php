<?php
declare(strict_types=1);
require_once __DIR__.'/ExperienceOperatorAddon.php';

/** Prices are snapshotted in integer cents. No existing entitlement is changed by quoting. */
final class SubscriptionChangeService
{
    private const RANK=['free'=>0,'basic'=>1,'featured'=>2,'page'=>3,'page_premium'=>4];
    public function __construct(private PDO $pdo){}
    public static function cents(mixed $amount):int
    {
        $text=(string)$amount;
        if(!preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/D',$text,$m))throw new RuntimeException('El importe de la suscripción no es válido.');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }
    public static function money(int $cents):string{return intdiv($cents,100).'.'.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT);}
    private static function stamp(DateTimeImmutable $date):string{return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
    public function current(int $business,DateTimeImmutable $at):?array
    {
        $q=$this->pdo->prepare('SELECT * FROM business_subscriptions WHERE business_id=? AND status="active" AND plan_code<>"free" AND starts_at<=? AND ends_at>? ORDER BY id DESC LIMIT 1');
        $stamp=self::stamp($at);$q->execute([$business,$stamp,$stamp]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function record(int $subscription):?array
    {
        try{$q=$this->pdo->prepare('SELECT * FROM subscription_changes WHERE subscription_id=?');$q->execute([$subscription]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
        catch(PDOException $e){if(str_contains($e->getMessage(),'no such table')||($e->errorInfo[1]??0)===1146)return null;throw $e;}
    }
    public function quote(int $business,string $plan,string $frequency,?array $addon=null,?DateTimeImmutable $at=null):array
    {
        $at??=new DateTimeImmutable('now',new DateTimeZone('UTC'));
        if(!isset(self::RANK[$plan])||$plan==='basic')throw new RuntimeException('Selecciona una suscripción vigente.');
        $full=0;$currency='COP';
        if($plan!=='free'){
            $q=$this->pdo->prepare('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?');$q->execute([$plan,$frequency]);$p=$q->fetch(PDO::FETCH_ASSOC);
            if(!$p||$p['amount']===null||self::cents($p['amount'])<=0)throw new RuntimeException('No hay tarifa válida para esta suscripción y período.');
            $full=self::cents($p['amount'])+self::cents($addon['amount']??0);$currency=(string)$p['currency'];
        }
        $quote=['kind'=>'purchase','source_subscription_id'=>null,'plan_code'=>$plan,'billing_frequency'=>$frequency,'quoted_at'=>self::stamp($at),'term_starts_at'=>null,'effective_at'=>null,'ends_at'=>null,'currency'=>$currency,'source_full_cents'=>0,'target_full_cents'=>$full,'credit_cents'=>0,'target_remaining_cents'=>$full,'due_cents'=>$full,'experience_id'=>(int)($addon['experience_id']??0)];
        $this->preservePaidRequests($business);
        $current=$this->current($business,$at);if(!$current)return $quote;
        try{$this->pdo->query('SELECT subscription_id FROM subscription_changes LIMIT 0');}catch(PDOException $error){throw new RuntimeException('Aplica la actualización 044 de suscripciones antes de cotizar un cambio.',0,$error);}
        if($current['reviewed_at']===null||$current['quoted_amount']===null||$current['currency']!==$currency)throw new RuntimeException('La suscripción vigente requiere revisar su importe y pago antes de cotizar un cambio.');
        $prior=$this->record((int)$current['id']);
        $start=$prior&&$prior['change_kind']==='upgrade'?$prior['term_starts_at']:$current['starts_at'];
        $source=$prior&&$prior['change_kind']==='upgrade'?(int)$prior['target_full_cents']:self::cents($current['quoted_amount']);
        $quote['source_subscription_id']=(int)$current['id'];$quote['source_full_cents']=$source;$quote['term_starts_at']=$start;$quote['ends_at']=$current['ends_at'];
        $oldAddon=(new ExperienceOperatorAddon($this->pdo))->forSubscription((int)$current['id']);
        $lower=self::RANK[$plan]<self::RANK[$current['plan_code']]||($oldAddon&&(int)$oldAddon['experience_id']!==$quote['experience_id']);
        if($lower||($plan===$current['plan_code']&&$quote['experience_id']===(int)($oldAddon['experience_id']??0))){
            $quote['kind']='renewal';$quote['effective_at']=$current['ends_at'];return $quote;
        }
        if($frequency!==$current['billing_frequency'])throw new RuntimeException('Para subir ahora conserva la frecuencia contratada. Puedes cambiarla en la próxima renovación.');
        if($full<=$source)throw new RuntimeException('El cambio no tiene una diferencia positiva. Revisa las tarifas antes de continuar.');
        $from=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->getTimestamp();$end=(new DateTimeImmutable($current['ends_at'],new DateTimeZone('UTC')))->getTimestamp();$remaining=$end-$at->getTimestamp();$duration=$end-$from;
        if($duration<=0||$remaining<=0||$remaining>$duration)throw new RuntimeException('La vigencia actual no permite calcular un saldo válido.');
        // Quotient/remainder avoids multiplying large money values by timestamps.
        $prorate=static fn(int $value):int=>intdiv($value,$duration)*$remaining+(int)round(($value%$duration)*$remaining/$duration,0,PHP_ROUND_HALF_UP);
        $credit=$prorate($source);$target=$prorate($full);
        $quote['kind']='upgrade';$quote['credit_cents']=$credit;$quote['target_remaining_cents']=$target;$quote['due_cents']=$target-$credit;
        if($quote['due_cents']<=0)throw new RuntimeException('La diferencia es inferior a la unidad de cobro. Solicita el cambio en la renovación.');
        return $quote;
    }
    public function preservePaidRequests(int $business):void
    {
        $q=$this->pdo->prepare('SELECT id FROM business_subscriptions WHERE business_id=? AND status="paid_pending_publication" LIMIT 1');$q->execute([$business]);
        if($q->fetchColumn())throw new RuntimeException('Ya tienes un pago confirmado pendiente de publicación. No se puede reemplazar ni perder ese saldo.');
    }
    public function capture(int $subscription,array $quote):void
    {
        if(!$quote['source_subscription_id'])return;
        $q=$this->pdo->prepare('INSERT INTO subscription_changes(subscription_id,source_subscription_id,change_kind,quoted_at,term_starts_at,effective_at,original_ends_at,source_full_cents,target_full_cents,credit_cents,target_remaining_cents,due_cents,currency) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$subscription,$quote['source_subscription_id'],$quote['kind'],$quote['quoted_at'],$quote['term_starts_at'],$quote['effective_at'],$quote['ends_at'],$quote['source_full_cents'],$quote['target_full_cents'],$quote['credit_cents'],$quote['target_remaining_cents'],$quote['due_cents'],$quote['currency']]);
    }
    /** Must run inside the publication transaction, after locking the business. */
    public function activation(array $sub,DateTimeImmutable $at):?array
    {
        $change=$this->record((int)$sub['id']);$current=$this->current((int)$sub['business_id'],$at);$stamp=self::stamp($at);
        if(!$change){if($current&&(int)$current['id']!==(int)$sub['id'])throw new RuntimeException('Este cambio necesita una cotización de saldo antes de activarse.');return null;}
        if($change['change_kind']==='renewal'){
            if($stamp<$change['effective_at'])throw new RuntimeException('La suscripción vigente se conserva hasta '.$change['effective_at'].'. No se puede bajar ni renovar antes de su vencimiento.');
            if($current)throw new RuntimeException('La suscripción vigente todavía no ha terminado. Revisa su vencimiento antes de activar el cambio.');
            return null;
        }
        if(!$current||(int)$current['id']!==(int)$change['source_subscription_id']||$current['ends_at']!==$change['original_ends_at']||$stamp>=$change['original_ends_at'])throw new RuntimeException('La cotización de upgrade ya no corresponde a la vigencia actual. Revisa el pago y recotiza sin perder su historial.');
        if($sub['status']!=='paid_pending_publication'||$sub['reviewed_at']===null||self::cents($sub['quoted_amount'])!==(int)$change['due_cents'])throw new RuntimeException('Valida el pago completo de la diferencia antes de activar la suscripción superior.');
        return ['ends_at'=>$change['original_ends_at']];
    }
}

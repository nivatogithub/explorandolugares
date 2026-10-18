<?php
declare(strict_types=1);
/** Captures intent only: never creates a charge or activates/publicizes a business. */
final class BusinessSubscriptionChoice
{
    public const PERIODS=['monthly'=>'Mensual','quarterly'=>'Trimestral','semiannual'=>'Semestral','annual'=>'Anual'];
    private const PLANS=['free','featured','page','page_premium'];
    public function __construct(private PDO $pdo){}
    public function options():array
    {
        $q=$this->pdo->query('SELECT p.code,t.name,t.summary FROM subscription_plans p JOIN subscription_plan_translations t ON t.plan_code=p.code AND t.locale="es" WHERE p.is_active=1 ORDER BY p.display_order');$result=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){if(!in_array($row['code'],self::PLANS,true))continue;$row['prices']=[];foreach(self::PERIODS as $period=>$label)$row['prices'][$period]=$this->quote($row['code'],$period);$result[]=$row;}return $result;
    }
    public function quote(string $plan,string $period):array
    {
        if(!in_array($plan,self::PLANS,true))throw new RuntimeException('Selecciona una suscripción disponible.');
        $q=$this->pdo->prepare('SELECT code FROM subscription_plans WHERE code=? AND is_active=1');$q->execute([$plan]);if(!$q->fetchColumn())throw new RuntimeException('Esta suscripción no está disponible.');
        if(!isset(self::PERIODS[$period]))throw new RuntimeException('Selecciona una periodicidad válida.');
        $result=['plan_code'=>$plan,'billing_frequency'=>$plan==='free'?null:$period,'quoted_amount'=>$plan==='free'?'0.00':null,'currency'=>'COP','quote_status'=>$plan==='free'?'free':'pending_price','captured_at'=>gmdate('c')];
        if($plan==='free')return $result;
        $q=$this->pdo->prepare('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?');$q->execute([$plan,$period]);$price=$q->fetch(PDO::FETCH_ASSOC);
        if(!$price||$price['amount']===null)return $result;
        if(!is_numeric($price['amount'])||(float)$price['amount']<=0||!preg_match('/^[A-Z]{3}$/D',(string)$price['currency']))throw new RuntimeException('La tarifa requiere revisión. Selecciona otra periodicidad o contacta al equipo.');
        $result['quoted_amount']=number_format((float)$price['amount'],2,'.','');$result['currency']=$price['currency'];$result['quote_status']='quoted';return $result;
    }
}

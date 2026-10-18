<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessSubscriptionChoice.php';
/** A short-lived selection, never a paid entitlement or trusted amount. */
final class SubscriptionPurchaseIntent
{
    public static function capture(PDO $pdo,array $input):array
    {
        $plan=(string)($input['plan']??'');$period=(string)($input['period']??'monthly');
        $quote=(new BusinessSubscriptionChoice($pdo))->quote($plan,$period);
        if($plan!=='free'&&$quote['quoted_amount']===null)throw new RuntimeException('Esta tarifa no está disponible. Vuelve al simulador.');
        $experience=(int)($input['experience']??0);$operator=($input['operator']??'0')==='1'||$experience>0;$destination=null;if($experience>0){$q=$pdo->prepare('SELECT p.destination_id FROM points_of_interest p JOIN destinations d ON d.id=p.destination_id WHERE p.id=? AND p.poi_type="experience" AND p.publication_status="published" AND d.publication_status="published"');$q->execute([$experience]);$destination=$q->fetchColumn();if(!$destination)throw new RuntimeException('Selecciona una experiencia publicada.');}
        if($operator&&$plan==='free')throw new RuntimeException('El complemento requiere una suscripción paga.');
        return ['experience_id'=>$experience,'destination_id'=>$destination?(int)$destination:null,'plan'=>$plan,'period'=>$period,'operator'=>$operator,'created_at'=>time()];
    }
    public static function current():?array
    {
        $value=$_SESSION['subscription_purchase_intent']??null;
        if(!is_array($value)||time()-(int)($value['created_at']??0)>7200){unset($_SESSION['subscription_purchase_intent']);return null;}
        return $value;
    }
}

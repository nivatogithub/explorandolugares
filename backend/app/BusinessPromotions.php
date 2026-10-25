<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessSubscriptionChoice.php';
require_once __DIR__.'/SubscriptionChangeService.php';

/** Registration-only offers. Immutable redemption amounts never become fake payments. */
final class BusinessPromotions
{
    public const PLANS=['featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'];
    public function __construct(private PDO $pdo) {}
    public static function normalize(string $code):string
    {
        $code=strtoupper(trim($code));
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,31}$/D',$code))throw new RuntimeException('El cupón debe tener entre 3 y 32 letras, números, guiones o guiones bajos.');
        return $code;
    }
    public function save(array $input,string $actor):string
    {
        if(trim($actor)===''||strlen($actor)>120)throw new RuntimeException('La gestión de cupones requiere un administrador.');
        $code=self::normalize((string)($input['code']??''));
        $percentage=filter_var($input['percentage']??null,FILTER_VALIDATE_INT);
        if(!$percentage||$percentage<1||$percentage>100)throw new RuntimeException('El descuento debe estar entre 1 y 100 %.');
        $plans=$input['plans']??[];$periods=$input['periods']??[];
        if(!is_array($plans)||!$plans||array_diff($plans,array_keys(self::PLANS)))throw new RuntimeException('Selecciona al menos un plan de pago válido.');
        if(!is_array($periods)||!$periods||array_diff($periods,array_keys(BusinessSubscriptionChoice::PERIODS)))throw new RuntimeException('Selecciona al menos un período válido.');
        $places=$input['destinations']??[];
        if(!is_array($places)||!$places||count($places)>5000)throw new RuntimeException('Selecciona uno o varios lugares para este cupón.');
        foreach($places as $place)if(!is_scalar($place)||!ctype_digit((string)$place)||(int)$place<1)throw new RuntimeException('Selecciona lugares válidos.');
        $places=array_values(array_unique(array_map('intval',$places)));sort($places);
        $placeQuery=$this->pdo->prepare('SELECT id FROM destinations WHERE id IN ('.implode(',',array_fill(0,count($places),'?')).')');$placeQuery->execute($places);
        if(count($placeQuery->fetchAll(PDO::FETCH_COLUMN))!==count($places))throw new RuntimeException('Uno de los lugares seleccionados no existe.');
        $expires=null;$date=trim((string)($input['expires_on']??''));
        if($date!==''){
            $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('America/Bogota'));
            if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('La fecha de vencimiento no es válida.');
            $expires=$parsed->setTime(23,59,59)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }
        $max=trim((string)($input['max_uses']??''));
        if($max!==''&&(!ctype_digit($max)||(int)$max<1||(int)$max>1000000))throw new RuntimeException('El máximo de usos debe ser entre 1 y 1.000.000, o vacío para no limitarlo.');
        $settings=['percentage'=>$percentage,'plans_json'=>json_encode(array_values(array_unique($plans)),JSON_THROW_ON_ERROR),'periods_json'=>json_encode(array_values(array_unique($periods)),JSON_THROW_ON_ERROR),'enabled'=>empty($input['enabled'])?0:1,'expires_at'=>$expires,'max_uses'=>$max===''?null:(int)$max];
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT code FROM business_promotion_codes WHERE code=?'.$this->lock());$q->execute([$code]);
            if($q->fetchColumn())$this->pdo->prepare('UPDATE business_promotion_codes SET percentage=?,plans_json=?,periods_json=?,enabled=?,expires_at=?,max_uses=?,updated_at=CURRENT_TIMESTAMP WHERE code=?')->execute([...array_values($settings),$code]);
            else $this->pdo->prepare('INSERT INTO business_promotion_codes(percentage,plans_json,periods_json,enabled,expires_at,max_uses,code,created_by) VALUES(?,?,?,?,?,?,?,?)')->execute([...array_values($settings),$code,$actor]);
            $scope=$this->pdo->prepare('SELECT code FROM business_promotion_place_scopes WHERE code=?');$scope->execute([$code]);
            if(!$scope->fetchColumn())$this->pdo->prepare('INSERT INTO business_promotion_place_scopes(code) VALUES(?)')->execute([$code]);
            $this->pdo->prepare('DELETE FROM business_promotion_places WHERE code=?')->execute([$code]);
            $assign=$this->pdo->prepare('INSERT INTO business_promotion_places(code,destination_id) VALUES(?,?)');foreach($places as $place)$assign->execute([$code,$place]);
            $this->pdo->prepare('INSERT INTO business_promotion_audit(code,actor,settings_json) VALUES(?,?,?)')->execute([$code,$actor,json_encode([...$settings,'destinations'=>$places],JSON_THROW_ON_ERROR)]);
            $this->pdo->commit();return $code;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function lock():string{return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
    public function quote(string $code,string $plan,string $period,int $destinationId,int $addonCents=0,bool $lock=false):array
    {
        $code=self::normalize($code);
        if($lock&&!$this->pdo->inTransaction())throw new LogicException('El canje requiere una transacción.');
        $q=$this->pdo->prepare('SELECT * FROM business_promotion_codes WHERE code=?'.($lock?$this->lock():''));$q->execute([$code]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row||!(int)$row['enabled']||($row['expires_at']!==null&&$row['expires_at']<gmdate('Y-m-d H:i:s')))throw new RuntimeException('El cupón no está disponible o ya venció.');
        if(!isset(self::PLANS[$plan])||!in_array($plan,json_decode($row['plans_json'],true,8,JSON_THROW_ON_ERROR),true)||!in_array($period,json_decode($row['periods_json'],true,8,JSON_THROW_ON_ERROR),true))throw new RuntimeException('Este cupón no aplica al plan y la periodicidad seleccionados.');
        $scope=$this->pdo->prepare('SELECT p.destination_id FROM business_promotion_places p JOIN business_promotion_place_scopes s ON s.code=p.code WHERE p.code=? AND p.destination_id=?'.($lock?$this->lock():''));$scope->execute([$code,$destinationId]);
        if($destinationId<1||!$scope->fetchColumn())throw new RuntimeException('Este cupón no aplica al lugar donde se registra el negocio.');
        // A locking current read avoids stale REPEATABLE READ counts after another redemption.
        $q=$this->pdo->prepare('SELECT subscription_id FROM business_promotion_redemptions WHERE code=?'.($lock?$this->lock():''));$q->execute([$code]);
        if($row['max_uses']!==null&&count($q->fetchAll(PDO::FETCH_COLUMN))>=(int)$row['max_uses'])throw new RuntimeException('El cupón alcanzó su límite de usos.');
        $price=(new BusinessSubscriptionChoice($this->pdo))->quote($plan,$period);
        if($price['quoted_amount']===null)throw new RuntimeException('Configura la tarifa del plan antes de aplicar un cupón.');
        $list=SubscriptionChangeService::cents($price['quoted_amount']);$percent=(int)$row['percentage'];
        if($percent<1||$percent>100||$addonCents<0)throw new RuntimeException('La configuración del cupón requiere revisión.');
        $discount=intdiv($list*$percent+50,100);
        return ['code'=>$code,'plan_code'=>$plan,'billing_frequency'=>$period,'destination_id'=>$destinationId,'percentage'=>$percent,'list_cents'=>$list,'addon_cents'=>$addonCents,'discount_cents'=>$discount,'due_cents'=>$list-$discount+$addonCents,'currency'=>$price['currency']];
    }
    public function redeem(int $subscription,int $business,int $account,string $code,int $addonCents=0,?array $expected=null):array
    {
        if(!$this->pdo->inTransaction())throw new LogicException('El canje requiere una transacción.');
        $q=$this->pdo->prepare('SELECT * FROM business_subscriptions WHERE id=? AND business_id=?'.$this->lock());$q->execute([$subscription,$business]);$sub=$q->fetch(PDO::FETCH_ASSOC);
        if(!$sub)throw new RuntimeException('La suscripción del cupón no existe.');
        $existing=$this->forSubscription($subscription);
        if($existing){if($existing['code']!==self::normalize($code)||(int)$existing['account_id']!==$account)throw new RuntimeException('La suscripción ya tiene otro cupón.');return $existing;}
        $membership=$this->pdo->prepare('SELECT 1 FROM business_account_memberships WHERE business_id=? AND account_id=? AND status="active"');$membership->execute([$business,$account]);
        if(!$membership->fetchColumn()||$sub['status']!=='pending'||$sub['starts_at']!==null)throw new RuntimeException('El cupón solo aplica al registro inicial pendiente de este negocio.');
        $prior=$this->pdo->prepare('SELECT id FROM business_subscriptions WHERE business_id=? AND id<>? AND plan_code<>"free" LIMIT 1');$prior->execute([$business,$subscription]);
        if($prior->fetchColumn())throw new RuntimeException('El cupón de registro no se aplica a renovaciones ni cambios de plan.');
        $place=$this->pdo->prepare('SELECT destination_id FROM businesses WHERE id=?'.$this->lock());$place->execute([$business]);
        $offer=$this->quote($code,$sub['plan_code'],$sub['billing_frequency'],(int)$place->fetchColumn(),$addonCents,true);
        if($expected!==null&&$offer!==$expected)throw new RuntimeException('El cupón o la tarifa cambió. Vuelve al paso Suscripción para revisar el descuento.');
        $used=$this->pdo->prepare('SELECT 1 FROM business_promotion_redemptions WHERE code=? AND business_id=?');$used->execute([$offer['code'],$business]);
        if($used->fetchColumn())throw new RuntimeException('Este negocio ya utilizó el cupón.');
        $this->pdo->prepare('INSERT INTO business_promotion_redemptions(subscription_id,code,business_id,account_id,plan_code,billing_frequency,percentage,list_cents,addon_cents,discount_cents,due_cents,currency) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$subscription,$offer['code'],$business,$account,$offer['plan_code'],$offer['billing_frequency'],$offer['percentage'],$offer['list_cents'],$offer['addon_cents'],$offer['discount_cents'],$offer['due_cents'],$offer['currency']]);
        $this->pdo->prepare('INSERT INTO business_promotion_redemption_places(subscription_id,destination_id) VALUES(?,?)')->execute([$subscription,$offer['destination_id']]);
        $covered=$offer['due_cents']===0;
        $this->pdo->prepare('UPDATE business_subscriptions SET quoted_amount=?,status=?,reviewed_at=? WHERE id=?')->execute([SubscriptionChangeService::money($offer['due_cents']),$covered?'paid_pending_publication':'pending',$covered?gmdate('Y-m-d H:i:s'):null,$subscription]);
        return $offer;
    }
    public function forSubscription(int $subscription):?array
    {
        try{
            // Older tables can have a different MySQL collation. Compare the
            // immutable codes exactly in PHP, not through an implicit SQL coercion.
            $q=$this->pdo->prepare('SELECT r.*,s.plan_code AS selected_plan,s.billing_frequency AS selected_period,s.currency AS selected_currency FROM business_promotion_redemptions r JOIN business_subscriptions s ON s.id=r.subscription_id AND s.business_id=r.business_id WHERE r.subscription_id=?');$q->execute([$subscription]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||$row['plan_code']!==$row['selected_plan']||$row['billing_frequency']!==$row['selected_period']||$row['currency']!==$row['selected_currency'])return null;
            unset($row['selected_plan'],$row['selected_period'],$row['selected_currency']);return $row;
        }
        catch(PDOException $e){if(str_contains($e->getMessage(),'no such table')||($e->errorInfo[1]??0)===1146)return null;throw $e;}
    }
    public function covered(int $subscription):bool
    {
        $offer=$this->forSubscription($subscription);if(!$offer||(int)$offer['percentage']!==100||(int)$offer['list_cents']<=0||(int)$offer['due_cents']!==0||(int)$offer['discount_cents']!==(int)$offer['list_cents']||(int)$offer['addon_cents']!==0)return false;
        $q=$this->pdo->prepare('SELECT quoted_amount,status,reviewed_at FROM business_subscriptions WHERE id=?');$q->execute([$subscription]);$sub=$q->fetch(PDO::FETCH_ASSOC);
        return $sub&&$sub['quoted_amount']!==null&&SubscriptionChangeService::cents($sub['quoted_amount'])===0&&in_array($sub['status'],['paid_pending_publication','active'],true)&&$sub['reviewed_at']!==null;
    }
    /** Retain the redeemed price, not today's price or an editable client total. */
    public function checkout(array $subscription,array $quote):array
    {
        $offer=$this->forSubscription((int)$subscription['id']);if(!$offer)return $quote;
        if($quote['kind']!=='purchase'||$quote['source_subscription_id']||$subscription['plan_code']!==$quote['plan_code']||$subscription['billing_frequency']!==$quote['billing_frequency']||(int)$offer['due_cents']<=0||SubscriptionChangeService::cents($subscription['quoted_amount'])!==(int)$offer['due_cents'])throw new RuntimeException('El descuento registrado requiere revisión antes de pagar.');
        $quote['target_full_cents']=(int)$offer['list_cents']+(int)$offer['addon_cents'];
        $quote['target_remaining_cents']=$quote['target_full_cents'];$quote['due_cents']=(int)$offer['due_cents'];
        $quote['promotion']=['code'=>$offer['code'],'percentage'=>(int)$offer['percentage'],'discount_cents'=>(int)$offer['discount_cents']];
        return $quote;
    }
    public function listing():array
    {
        $rows=$this->pdo->query('SELECT c.*,(SELECT COUNT(*) FROM business_promotion_redemptions r WHERE r.code=c.code) AS uses FROM business_promotion_codes c ORDER BY c.created_at DESC,c.code')->fetchAll(PDO::FETCH_ASSOC);
        $places=$this->pdo->query('SELECT p.code,p.destination_id,COALESCE(t.display_name,d.slug) AS name FROM business_promotion_places p JOIN destinations d ON d.id=p.destination_id LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $byCode=[];foreach($places as $place)$byCode[$place['code']][]=['id'=>(int)$place['destination_id'],'name'=>$place['name']];
        foreach($rows as &$row)$row['places']=$byCode[$row['code']]??[];unset($row);return $rows;
    }
    public function redemptions():array
    {
        return $this->pdo->query('SELECT r.*,t.display_name AS business_name,a.display_name AS account_name,dt.display_name AS destination_name FROM business_promotion_redemptions r JOIN business_translations t ON t.business_id=r.business_id AND t.locale="es" JOIN accounts a ON a.id=r.account_id LEFT JOIN business_promotion_redemption_places rp ON rp.subscription_id=r.subscription_id LEFT JOIN destination_translations dt ON dt.destination_id=rp.destination_id AND dt.locale="es" ORDER BY r.redeemed_at DESC,r.subscription_id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    }
}

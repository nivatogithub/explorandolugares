<?php
declare(strict_types=1);
final class ExperienceOperatorAddon
{
    public function __construct(private PDO $pdo){}
    public static function eligibleCategories(array $codes):bool
    {
        return in_array('experiencias',$codes,true);
    }
    public function eligibleBusiness(int $id):bool
    {
        $q=$this->pdo->prepare('SELECT 1 FROM businesses b JOIN service_categories c ON c.code="experiencias" AND c.is_active=1 WHERE b.id=? AND (b.category_code=c.code OR EXISTS(SELECT 1 FROM business_categories bc WHERE bc.business_id=b.id AND bc.category_code=c.code))');
        $q->execute([$id]);return (bool)$q->fetchColumn();
    }
    public function requireEligible(int $id):void
    {
        if(!$this->eligibleBusiness($id))throw new RuntimeException('Este complemento solo está disponible para negocios con la categoría Experiencias y actividades.');
    }
    public function options(int $destination):array
    {
        $q=$this->pdo->prepare('SELECT p.id,p.slug,t.name FROM points_of_interest p JOIN point_of_interest_translations t ON t.point_of_interest_id=p.id AND t.locale="es" JOIN destinations d ON d.id=p.destination_id WHERE p.destination_id=? AND p.poi_type="experience" AND p.publication_status="published" AND d.publication_status="published" ORDER BY p.display_order,t.name');$q->execute([$destination]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function prices():array{return $this->pdo->query('SELECT billing_frequency,amount,currency FROM experience_operator_prices')->fetchAll(PDO::FETCH_UNIQUE|PDO::FETCH_ASSOC);}
    public function quote(string $plan,string $period,int $destination,int $experience):?array
    {
        if(!$experience)return null;
        if(!in_array($plan,['featured','page','page_premium'],true))throw new RuntimeException('La presencia en experiencias requiere Destacado, Página Comercial o Premium. No se vende con Gratis.');
        $found=array_values(array_filter($this->options($destination),static fn($row)=>(int)$row['id']===$experience));if(!$found)throw new RuntimeException('Selecciona una experiencia publicada del mismo lugar del negocio.');
        $prices=$this->prices();$price=$prices[$period]??null;if(!$price||!is_numeric($price['amount'])||(float)$price['amount']<=0||$price['currency']!=='COP')throw new RuntimeException('El complemento todavía no tiene una tarifa válida para ese período.');
        return ['experience_id'=>$experience,'experience_name'=>$found[0]['name'],'amount'=>(string)$price['amount'],'currency'=>'COP'];
    }
    public function attach(int $subscription,?array $quote):void
    {
        if(!$quote)return;$q=$this->pdo->prepare('INSERT INTO business_experience_subscriptions(subscription_id,experience_id,quoted_amount,currency) VALUES(?,?,?,?)');$q->execute([$subscription,$quote['experience_id'],$quote['amount'],$quote['currency']]);
    }
    private function installed():bool
    {
        try{$this->pdo->query('SELECT subscription_id FROM business_experience_subscriptions LIMIT 0');return true;}catch(PDOException $error){if(str_contains($error->getMessage(),'no such table')||($error->errorInfo[1]??0)===1146)return false;throw $error;}
    }
    public function forSubscription(int $id):?array
    {
        try{$q=$this->pdo->prepare('SELECT x.*,t.name AS experience_name FROM business_experience_subscriptions x JOIN point_of_interest_translations t ON t.point_of_interest_id=x.experience_id AND t.locale="es" WHERE x.subscription_id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}catch(PDOException $error){if(str_contains($error->getMessage(),'no such table')||($error->errorInfo[1]??0)===1146)return null;throw $error;}
    }
    public function active(int $experience,string $locale='es'):array
    {
        if(!$this->installed())return [];
        $q=$this->pdo->prepare('SELECT b.public_id,b.slug,t.display_name,t.summary,s.plan_code,d.slug AS destination_slug FROM business_experience_subscriptions x JOIN business_subscriptions s ON s.id=x.subscription_id JOIN businesses b ON b.id=s.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale=? JOIN points_of_interest p ON p.id=x.experience_id JOIN destinations d ON d.id=p.destination_id WHERE x.experience_id=? AND EXISTS(SELECT 1 FROM service_categories c WHERE c.code="experiencias" AND c.is_active=1 AND (b.category_code=c.code OR EXISTS(SELECT 1 FROM business_categories bc WHERE bc.business_id=b.id AND bc.category_code=c.code))) AND b.destination_id=p.destination_id AND p.poi_type="experience" AND p.publication_status="published" AND d.publication_status="published" AND b.publication_status="published" AND b.operation_status<>"CERRADO_DEFINITIVO" AND s.plan_code IN ("featured","page","page_premium") AND s.status="active" AND s.reviewed_at IS NOT NULL AND s.starts_at<=CURRENT_TIMESTAMP AND s.ends_at>CURRENT_TIMESTAMP AND s.id=(SELECT MAX(s2.id) FROM business_subscriptions s2 WHERE s2.business_id=b.id AND s2.status="active" AND s2.starts_at<=CURRENT_TIMESTAMP AND s2.ends_at>CURRENT_TIMESTAMP) ORDER BY t.display_name');$q->execute([$locale,$experience]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessAdminService.php';
/** Administrative read model: editorial, payment and ownership are independent facts. */
final class BusinessAdminWorkspace
{
    public const STATES=[
        'ownership'=>'Pendiente de titularidad','payment'=>'Pendiente de pago',
        'payment_review'=>'Pendiente de validar pago','payment_clarification'=>'Aclaración de pago solicitada',
        'payment_rejected'=>'Pago rechazado','editorial'=>'En preparación editorial',
        'changes'=>'Correcciones solicitadas','rejected'=>'Propuesta no aprobada',
        'awaiting_owner'=>'Pendiente de publicación por el propietario','published'=>'Publicado',
        'expired'=>'Suscripción vencida','cancelled'=>'Suscripción cancelada',
    ];
    public function __construct(private PDO $pdo,private string $storage) {}
    public static function state(array $row):string
    {
        if(($row['publication_status']??'')!=='published'&&!empty($row['has_owner'])&&empty($row['title_verified']))return 'ownership';
        if(($row['subscription_status']??'')==='pending')return match($row['payment_status']??''){'pending'=>'payment_review','clarification_requested'=>'payment_clarification','rejected'=>'payment_rejected',default=>'payment'};
        if(in_array($row['subscription_status']??'',['expired','cancelled'],true))return $row['subscription_status'];
        return match($row['editorial_status']??''){
            'awaiting_owner','owner_approved'=>'awaiting_owner','changes_requested'=>'changes','rejected'=>'rejected',
            'draft','in_editorial'=>'editorial',default=>($row['publication_status']??'')==='published'?'published':'editorial',
        };
    }
    public function rows():array
    {
        $rows=(new BusinessAdminService($this->pdo,$this->storage))->all();
        $versions=$this->pdo->query('SELECT v.business_id,v.status FROM business_editorial_versions v WHERE v.id=(SELECT MAX(v2.id) FROM business_editorial_versions v2 WHERE v2.business_id=v.business_id)')->fetchAll(PDO::FETCH_KEY_PAIR);
        $payments=$this->pdo->query('SELECT s.business_id,e.status FROM business_subscriptions s JOIN business_subscription_payment_evidence e ON e.subscription_id=s.id WHERE s.id=(SELECT MAX(s2.id) FROM business_subscriptions s2 WHERE s2.business_id=s.business_id) AND e.id=(SELECT MAX(e2.id) FROM business_subscription_payment_evidence e2 WHERE e2.subscription_id=s.id)')->fetchAll(PDO::FETCH_KEY_PAIR);
        $titles=$this->pdo->query('SELECT a.business_id,MAX(CASE WHEN e.verified_at IS NOT NULL THEN 1 ELSE 0 END) FROM business_account_applications a LEFT JOIN business_account_application_evidence e ON e.application_id=a.id AND e.evidence_type="business_registration" WHERE a.status="approved" AND EXISTS(SELECT 1 FROM business_account_memberships m WHERE m.business_id=a.business_id AND m.account_id=a.account_id AND m.status="active") GROUP BY a.business_id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $accounts=$this->pdo->query('SELECT m.business_id,a.id,a.display_name,a.email,a.status,a.email_verified_at,(SELECT COUNT(DISTINCT m2.business_id) FROM business_account_memberships m2 WHERE m2.account_id=a.id AND m2.status="active") AS business_count FROM business_account_memberships m JOIN accounts a ON a.id=m.account_id WHERE m.status="active" ORDER BY a.display_name,a.id')->fetchAll(PDO::FETCH_ASSOC);
        $byBusiness=[];foreach($accounts as $account)$byBusiness[(int)$account['business_id']][]=$account;
        foreach($rows as &$row){$id=(int)$row['id'];$row['accounts']=$byBusiness[$id]??[];$row['has_owner']=(bool)$row['accounts'];$row['editorial_status']=$versions[$id]??'';$row['payment_status']=$payments[$id]??'';$row['title_verified']=(bool)($titles[$id]??false);$row['workflow_state']=self::state($row);}
        unset($row);return $rows;
    }
}

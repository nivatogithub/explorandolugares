<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessAccountPortalService.php';
require_once __DIR__.'/BusinessRegistrationDrafts.php';
require_once __DIR__.'/BusinessEditorialService.php';

/** One customer row per business; registration drafts and disputed claims remain private. */
final class BusinessWorkspace
{
    public const PLANS=['free'=>'Gratis','featured'=>'Destacado','page'=>'Página Comercial','page_premium'=>'Premium'];
    public const PERIODS=['monthly'=>'1 mes','quarterly'=>'3 meses','semiannual'=>'6 meses','annual'=>'12 meses'];
    public function __construct(private PDO $pdo) {}
    public static function state(array $business,?array $version,?array $payment):array
    {
        $status=$version['status']??'';
        if(in_array($status,['awaiting_owner','owner_approved'],true))return ['label'=>'Pendiente de publicación','code'=>'awaiting_owner','pay'=>false,'publish'=>true];
        if(in_array($payment['status']??'',['clarification_requested','rejected'],true)&&($business['subscription_status']??'')!=='active')return ['label'=>'Requiere aclaración del pago','code'=>'clarification','pay'=>true,'publish'=>false];
        if($status==='changes_requested')return ['label'=>'Correcciones solicitadas','code'=>'changes','pay'=>false,'publish'=>false];
        if($status==='rejected')return ['label'=>'Publicación en revisión','code'=>'rejected','pay'=>false,'publish'=>false];
        if(($business['publication_status']??'')==='published')return ['label'=>'Publicado','code'=>'published','pay'=>false,'publish'=>false];
        if(($payment['status']??'')==='pending')return ['label'=>'Pendiente de validación','code'=>'validation','pay'=>false,'publish'=>false];
        if(($payment['status']??'')==='approved'||($business['subscription_status']??'')==='paid_pending_publication')return ['label'=>'En preparación para publicación','code'=>'editorial','pay'=>false,'publish'=>false];
        $paid=in_array($business['plan_code']??'',['featured','page','page_premium'],true);
        return ['label'=>$paid?'Pendiente de pago':'Pendiente de validación','code'=>$paid?'payment':'validation','pay'=>$paid,'publish'=>false];
    }
    public function rows(int $account):array
    {
        $portal=new BusinessAccountPortalService($this->pdo);$businesses=$portal->businesses($account);
        $editorial=new BusinessEditorialService($this->pdo,'');$editorialReady=$editorial->ready();
        $payment=$this->pdo->prepare('SELECT e.status,e.customer_message,e.reviewed_at FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.account_id=? AND s.business_id=? ORDER BY e.id DESC LIMIT 1');
        $rows=[];$seen=[];
        foreach($businesses as $business){
            $id=(int)$business['id'];$seen[$id]=true;$payment->execute([$account,$id]);$proof=$payment->fetch(PDO::FETCH_ASSOC)?:null;
            $sub=$this->pdo->prepare('SELECT id FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1');$sub->execute([$id]);$subscriptionId=(int)$sub->fetchColumn();
            $promotions=new BusinessPromotions($this->pdo);$promotion=$subscriptionId?$promotions->forSubscription($subscriptionId):null;
            $version=$editorialReady?$editorial->latest($id):null;
            $rows[]=[...$business,'kind'=>'business','state'=>self::state($business,$version,$proof),'version'=>$version,
                'message'=>($promotion?'Cupón '.$promotion['code'].' · '.$promotion['percentage'].' % de descuento. '.($promotions->covered($subscriptionId)?'Sin pago pendiente. ':'Saldo: '.SubscriptionChangeService::money((int)$promotion['due_cents']).' '.$promotion['currency'].'. '):'').(trim((string)($proof['customer_message']??''))?:trim((string)($version['note']??'')))];
        }
        foreach($portal->applications($account) as $application){
            $id=(int)($application['business_id']??0);if($id&&isset($seen[$id]))continue;if($id)$seen[$id]=true;
            $rows[]=['kind'=>'application','id'=>$id,'display_name'=>$application['business_name'],'destination_name'=>$application['destination_name'],'plan_code'=>'',
                'state'=>['label'=>['not_approved'=>'Solicitud no aprobada','needs_selection'=>'Requiere elegir negocio'][$application['status']]??'Pendiente de validación','code'=>'application','pay'=>false,'publish'=>false],
                'message'=>$application['review_message']?:'La solicitud está en revisión; no concede acceso a otro responsable.'];
        }
        foreach($portal->claims($account) as $claim){
            // Old claims have no row ID in the portal API; coalesce by name and place only for this historic representation.
            $exists=false;foreach($rows as $row)if($row['display_name']===$claim['display_name']&&$row['destination_name']===$claim['destination_name']){$exists=true;break;}
            if(!$exists)$rows[]=['kind'=>'application','id'=>0,'display_name'=>$claim['display_name'],'destination_name'=>$claim['destination_name'],'plan_code'=>'',
                'state'=>['label'=>'Reclamación en revisión','code'=>'application','pay'=>false,'publish'=>false],'message'=>'Revisión de representación pendiente.'];
        }
        foreach((new BusinessRegistrationDrafts($this->pdo))->listing($account) as $draft){
            $payload=$draft['payload'];$rows[]=['kind'=>'draft','draft_id'=>$draft['id'],'id'=>0,'display_name'=>$payload['business_name']??'Negocio sin nombre','destination_name'=>$draft['destination_name'],'plan_code'=>$payload['plan_code']??'',
                'state'=>['label'=>'Borrador','code'=>'draft','pay'=>false,'publish'=>false],'message'=>'Registro incompleto. Continúa desde el último paso guardado.','step'=>(int)$draft['wizard_step']];
        }
        return $rows;
    }
}

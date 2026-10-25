<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessBulkPublicationService.php';
require_once __DIR__.'/BusinessOnboardingDetails.php';
require_once __DIR__.'/BusinessSubscriptionChoice.php';
require_once __DIR__.'/ExperienceOperatorAddon.php';
require_once __DIR__.'/BusinessPromotions.php';
/** Runs inside the caller transaction. Initial responsibility is declared, never certified. */
final class BusinessSelfOnboarding
{
    public const POLICY='document-review-v2';
    public static function complete(PDO $pdo,int $applicationId):void
    {
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $q=$pdo->prepare('SELECT r.*,a.status AS account_status,a.email_verified_at FROM business_account_applications r JOIN accounts a ON a.id=r.account_id WHERE r.id=?'.$lock);$q->execute([$applicationId]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row||$row['status']==='approved')return;
        $details=json_decode($row['business_details_json']??'{}',true,32,JSON_THROW_ON_ERROR);
        if(($details['onboarding_policy']??'')!==self::POLICY||empty($details['responsibility_declared'])||$row['account_status']!=='active'||empty($row['email_verified_at']))return;
        $q=$pdo->prepare('SELECT id FROM business_account_application_evidence WHERE application_id=? AND evidence_type="business_registration" LIMIT 1');$q->execute([$applicationId]);if(!$q->fetchColumn())throw new RuntimeException('Falta el documento privado del negocio.');
        // All creations in the same place serialize, including different accounts/tabs.
        $q=$pdo->prepare('SELECT id FROM destinations WHERE id=?'.$lock);$q->execute([$row['destination_id']]);if(!$q->fetchColumn())throw new RuntimeException('El lugar ya no está disponible.');
        $businessId=(int)$row['business_id'];
        if($row['application_type']==='request_new'&&!$businessId){
            $duplicates=self::duplicates($pdo,(int)$row['destination_id'],$row['business_name']);
            if($duplicates&&!self::distinctLocationAllowed($pdo,$duplicates,$details)){$details['duplicate_candidates']=array_column($duplicates,'id');$details['next_action']='select_existing_business';$pdo->prepare('UPDATE business_account_applications SET status="needs_selection",business_details_json=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([json_encode($details,JSON_THROW_ON_ERROR),$applicationId]);return;}
            $hex=bin2hex(random_bytes(16));$publicId=substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20,12);
            $slug='negocio-'.substr($hex,0,20);
            $pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status) VALUES(?,?,?,"owner_submitted",?,"ACTIVO","RECLAMADO","DESCONOCIDO","draft")')->execute([$publicId,$row['destination_id'],$row['category_code'],$slug]);
            $businessId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")')->execute([$businessId,$row['business_name']]);
            BusinessOnboardingDetails::apply($pdo,$businessId,$row['category_code'],$details);
            $pdo->prepare('INSERT INTO business_image_references(business_id,storage_name,original_name,mime_type,file_size,width,height) SELECT ?,storage_name,original_name,mime_type,file_size,width,height FROM business_application_images WHERE application_id=?')->execute([$businessId,$applicationId]);
            $pdo->prepare('UPDATE business_account_applications SET business_id=? WHERE id=?')->execute([$businessId,$applicationId]);
        }
        $q=$pdo->prepare('SELECT id,operation_status FROM businesses WHERE id=?'.$lock);$q->execute([$businessId]);$business=$q->fetch(PDO::FETCH_ASSOC);
        if(!$business||$business['operation_status']==='CERRADO_DEFINITIVO')throw new RuntimeException('El negocio ya no está disponible.');
        $q=$pdo->prepare('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"'.$lock);$q->execute([$businessId]);$owners=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(array_diff($owners,[(int)$row['account_id']])){
            $details['claim_review_kind']='disputed_existing';$details['next_action']='manual_dispute';
            $pdo->prepare('UPDATE business_account_applications SET status="in_review",business_details_json=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([json_encode($details,JSON_THROW_ON_ERROR),$applicationId]);return;
        }
        if(!in_array((int)$row['account_id'],$owners,true)){
            $q=$pdo->prepare('SELECT id FROM business_account_memberships WHERE account_id=? AND business_id=?'.$lock);$q->execute([$row['account_id'],$businessId]);
            if($q->fetchColumn())$pdo->prepare('UPDATE business_account_memberships SET status="active" WHERE account_id=? AND business_id=?')->execute([$row['account_id'],$businessId]);
            else $pdo->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,?,"active")')->execute([$row['account_id'],$businessId,$row['relationship_code']==='owner'?'owner':'manager']);
        }
        $choice=$details['requested_subscription']??[];$plan=(string)($choice['plan_code']??'free');$period=(string)($choice['billing_frequency']??'monthly');
        $q=$pdo->prepare('SELECT id,plan_code FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1'.$lock);$q->execute([$businessId]);$previousSubscription=$q->fetch(PDO::FETCH_ASSOC);
        if(!$previousSubscription||($previousSubscription['plan_code']==='free'&&$plan!=='free')){
            $quote=(new BusinessSubscriptionChoice($pdo))->quote($plan,$period);$addonService=new ExperienceOperatorAddon($pdo);$addon=$addonService->quote($plan,$period,(int)$row['destination_id'],(int)($choice['operator_addon']['experience_id']??0));
            $pdo->prepare('UPDATE business_subscriptions SET status="cancelled" WHERE business_id=? AND plan_code="free" AND status="pending_publication"')->execute([$businessId]);
            $amount=$quote['quoted_amount']===null?null:(float)$quote['quoted_amount']+(float)($addon['amount']??0);
            $pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,quoted_amount,currency) VALUES(?,?,?,?,?,?)')->execute([$businessId,$plan,$plan==='free'?'monthly':$period,$plan==='free'?'pending_publication':'pending',$amount,$quote['currency']]);$subscriptionId=(int)$pdo->lastInsertId();$addonService->attach($subscriptionId,$addon);
            if(!empty($choice['promotion']['code']))(new BusinessPromotions($pdo))->redeem($subscriptionId,$businessId,(int)$row['account_id'],(string)$choice['promotion']['code'],SubscriptionChangeService::cents($addon['amount']??0),$choice['promotion']);
        }elseif(!empty($details['requested_subscription']['promotion']['code'])){
            // A claim of an already configured business must not silently lose an offer.
            throw new RuntimeException('El cupón de registro solo aplica a un negocio sin suscripción anterior.');
        }
        $details['responsibility_association']=['basis'=>'declared_with_business_document','ownership_verified'=>false];unset($details['auto_claim_on_activation']);
        $pdo->prepare('UPDATE businesses SET claim_status="RECLAMADO",information_status="ACTIVO",updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$businessId]);
        $pdo->prepare('UPDATE business_account_applications SET status="approved",business_details_json=?,admin_note=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([json_encode($details,JSON_THROW_ON_ERROR),'Asociación declarada con respaldo documental. No certifica titularidad. Publicación separada.',$applicationId]);
        // Submitted content, positions and products must exist privately before payment.
        $pdo->prepare('INSERT INTO business_image_references(business_id,storage_name,original_name,mime_type,file_size,width,height) SELECT ?,i.storage_name,i.original_name,i.mime_type,i.file_size,i.width,i.height FROM business_application_images i WHERE i.application_id=? AND NOT EXISTS(SELECT 1 FROM business_image_references r WHERE r.business_id=? AND r.storage_name=i.storage_name)')->execute([$businessId,$applicationId,$businessId]);
        require_once __DIR__.'/BusinessEditorialService.php';
        $editorial=new BusinessEditorialService($pdo,function_exists('private_storage_path')?private_storage_path('business-image-references'):sys_get_temp_dir());
        if(!$editorial->latest($businessId))$editorial->save((int)$row['account_id'],$businessId,0,[],!empty($details['image_consent']));
    }
    /** Explicit homonym declaration needs a distinct address; it is not verified automatically. */
    public static function distinctLocationAllowed(PDO $pdo,array $duplicates,array $details):bool
    {
        if(empty($details['distinct_business_declared']))return false;
        $address=trim((string)($details['location']['address_text']??''));
        if(mb_strlen($address)<8||mb_strlen($address)>300)return false;
        $normalize=static fn(string $v):string=>preg_replace('/[^\p{L}\p{N}]+/u','',mb_strtolower($v));
        $q=$pdo->prepare('SELECT address_text FROM businesses WHERE id=?');
        foreach($duplicates as $duplicate){$q->execute([$duplicate['id']]);$existing=(string)$q->fetchColumn();if($existing!==''&&$normalize($existing)===$normalize($address))return false;}
        return true;
    }

    public static function duplicates(PDO $pdo,int $destination,string $name):array
    {
        $q=$pdo->prepare('SELECT b.id,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=? AND b.operation_status<>"CERRADO_DEFINITIVO"'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$destination]);
        return array_values(array_filter($q->fetchAll(PDO::FETCH_ASSOC),static fn(array $row):bool=>BusinessBulkPublicationService::looksLikeDuplicate($name,$row['display_name'])));
    }
}

<?php
declare(strict_types=1);

require_once __DIR__.'/ExperienceOperatorAddon.php';
require_once __DIR__.'/SubscriptionChangeService.php';
require_once __DIR__.'/BusinessPromotions.php';
final class BusinessPaymentEvidenceService
{
    public const MAX_BYTES=8*1024*1024;
    private const MIME_EXTENSIONS=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    public function __construct(private PDO $pdo,private string $storage)
    {
        if(!is_dir($this->storage)&&!mkdir($this->storage,0700,true)&&!is_dir($this->storage))throw new RuntimeException('No se pudo preparar el almacenamiento privado de comprobantes.');
    }

    public function submit(int $accountId,int $businessId,string $planCode,string $frequency,string $reference,array $upload,array $transaction=[]):int
    {
        $saved=$transaction['subscription_quote']??null;
        $reference=trim($reference);if(strlen($reference)>160)throw new RuntimeException('La referencia de pago supera los 160 caracteres.');
        if(!throttle('business_payment_evidence',5,3600,(string)$accountId))throw new RuntimeException('Se alcanzó el límite de envíos de comprobantes. Inténtalo más tarde.');
        $membership=$this->pdo->prepare('SELECT b.id FROM business_account_memberships m JOIN businesses b ON b.id=m.business_id JOIN accounts a ON a.id=m.account_id WHERE m.account_id=? AND m.business_id=? AND m.status="active" AND m.role IN ("owner","manager") AND a.status="active"');$membership->execute([$accountId,$businessId]);if(!$membership->fetchColumn())throw new RuntimeException('No tienes permiso para gestionar este negocio.');
        if(!in_array($planCode,['free','featured','page','page_premium'],true))throw new RuntimeException('Selecciona una suscripción vigente.');
        $planQuery=$this->pdo->prepare('SELECT code FROM subscription_plans WHERE code=? AND is_active=1');$planQuery->execute([$planCode]);if(!$planQuery->fetchColumn())throw new RuntimeException('Selecciona una suscripción vigente.');
        $pendingEvidence=$this->pdo->prepare('SELECT 1 FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.account_id=? AND s.business_id=? AND e.status="pending" LIMIT 1');$pendingEvidence->execute([$accountId,$businessId]);
        if($pendingEvidence->fetchColumn())throw new RuntimeException('Ya enviaste un comprobante para este negocio. Espera su revisión antes de enviar otro.');
        $addon=null;$addonService=new ExperienceOperatorAddon($this->pdo);$experienceId=(int)($transaction['operator_experience_id']??0);if($planCode==='free'&&$experienceId)throw new RuntimeException('El complemento requiere una suscripción paga.');
        $amount=null;$currency='COP';$status='pending_publication';$frequency=$planCode==='free'?'':$frequency;
        if($planCode!=='free'){
            if(!in_array($frequency,['monthly','quarterly','semiannual','annual'],true))throw new RuntimeException('Selecciona la frecuencia de pago.');
            $priceQuery=$this->pdo->prepare('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?');$priceQuery->execute([$planCode,$frequency]);$price=$priceQuery->fetch(PDO::FETCH_ASSOC);
            if(!$price||$price['amount']===null||(float)$price['amount']<=0)throw new RuntimeException('Esta suscripción no tiene una tarifa válida configurada.');
            $addon=null;if($experienceId){$addonService->requireEligible($businessId);$destinationQuery=$this->pdo->prepare('SELECT destination_id FROM businesses WHERE id=?');$destinationQuery->execute([$businessId]);$addon=$addonService->quote($planCode,$frequency,(int)$destinationQuery->fetchColumn(),$experienceId);}
            $amount=(float)$price['amount']+(float)($addon['amount']??0);$currency=(string)$price['currency'];if($addon&&$currency!=='COP')throw new RuntimeException('El complemento requiere una cotización base en COP.');$status='pending';
            if(preg_match('/^[A-Z]{3}$/',$currency)!==1)throw new RuntimeException('La moneda de esta tarifa no es válida. Contacta al administrador antes de enviar el comprobante.');
            $transaction=self::validateTransaction($reference,$transaction);
            [$storedName,$originalName,$mime,$bytes]=$this->validateUpload($upload);
        }
        $changes=new SubscriptionChangeService($this->pdo);
        $quoteAt=null;
        if($saved!==null){
            $quoteAt=new DateTimeImmutable($saved['quoted_at'],new DateTimeZone('UTC'));
            $age=time()-$quoteAt->getTimestamp();
            if($age<0||$age>1800)throw new RuntimeException('La cotización venció. Actualízala antes de pagar.');
        }
        $path=null;
        if($planCode!=='free'){$path=$this->storage.'/'.$storedName;if(!move_uploaded_file((string)$upload['tmp_name'],$path))throw new RuntimeException('No se pudo guardar el comprobante privado.');@chmod($path,0600);}
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $locked=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.$lock);$locked->execute([$businessId]);
            // Serialize duplicate uploads and retain the original coupon redemption.
            $pendingEvidence->execute([$accountId,$businessId]);if($pendingEvidence->fetchColumn())throw new RuntimeException('Ya enviaste un comprobante para este negocio. Espera su revisión.');
            $latest=$this->pdo->prepare('SELECT * FROM business_subscriptions WHERE business_id=? ORDER BY id DESC LIMIT 1'.$lock);$latest->execute([$businessId]);$latest=$latest->fetch(PDO::FETCH_ASSOC);
            $promotions=new BusinessPromotions($this->pdo);$promotion=$latest?$promotions->forSubscription((int)$latest['id']):null;
            if($promotion&&($latest['plan_code']!==$planCode||$latest['billing_frequency']!==$frequency||$latest['status']!=='pending'))throw new RuntimeException('El pago debe conservar el plan y período del cupón registrado.');
            $changes->preservePaidRequests($businessId);
            $changeQuote=$changes->quote($businessId,$planCode,$frequency,$addon,$quoteAt);
            if($promotion)$changeQuote=$promotions->checkout($latest,$changeQuote);
            if($saved!==null&&$saved!==$changeQuote)throw new RuntimeException('La suscripción o tarifa cambió. Solicita una cotización nueva antes de enviar el pago.');
            if($changeQuote['source_subscription_id']&&$saved===null)throw new RuntimeException('Consulta primero el saldo y la diferencia del cambio antes de pagar.');
            if($changeQuote['source_subscription_id']&&(($changes->current($businessId,new DateTimeImmutable('now',new DateTimeZone('UTC')))['id']??null)!=$changeQuote['source_subscription_id']))throw new RuntimeException('La vigencia cambió. Solicita otra cotización antes de continuar.');
            $amount=$planCode==='free'?null:SubscriptionChangeService::money($changeQuote['due_cents']);
            if($planCode!=='free'&&SubscriptionChangeService::cents($transaction['paid_amount'])<$changeQuote['due_cents'])throw new RuntimeException('El valor pagado es inferior al importe que corresponde a esta solicitud.');
            if($promotion){$subscriptionId=(int)$latest['id'];}
            else{
                $this->pdo->prepare('UPDATE business_subscriptions SET status="cancelled" WHERE business_id=? AND status IN ("pending","pending_publication","paid_pending_publication")')->execute([$businessId]);
                $insert=$this->pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,reviewed_at,publish_not_before,quoted_amount,currency) VALUES(?,?,?, ?,NULL,NULL,NULL,NULL,?,?)');$insert->execute([$businessId,$planCode,$frequency?:null,$status,$amount,$currency]);$subscriptionId=(int)$this->pdo->lastInsertId();$changes->capture($subscriptionId,$changeQuote);$addonService->attach($subscriptionId,$addon);
            }
            if($planCode!=='free'){$evidence=$this->pdo->prepare('INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,payment_reference,checkout_reference,transaction_date,paid_amount,payment_method,source_bank) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');$evidence->execute([$subscriptionId,$accountId,$storedName,$originalName,$mime,$bytes,$reference,$transaction['checkout_reference'],$transaction['transaction_date'],$transaction['paid_amount'],$transaction['payment_method'],$transaction['source_bank']]);}
            $this->pdo->commit();return $subscriptionId;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();if($path&&is_file($path))@unlink($path);throw $error;}
    }

    public function pending():array
    {
        return $this->pdo->query('SELECT e.id,e.checkout_reference,e.transaction_date,e.paid_amount,e.payment_method,e.source_bank,e.original_name,e.mime_type,e.file_size,e.payment_reference,e.submitted_at,a.display_name AS account_name,a.email,b.id AS business_id,t.display_name AS business_name,d.slug AS destination_slug,s.plan_code,s.billing_frequency,s.quoted_amount,s.currency,x.quoted_amount AS operator_amount,xt.name AS operator_experience FROM business_subscription_payment_evidence e JOIN accounts a ON a.id=e.account_id JOIN business_subscriptions s ON s.id=e.subscription_id JOIN businesses b ON b.id=s.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id LEFT JOIN business_experience_subscriptions x ON x.subscription_id=s.id LEFT JOIN point_of_interest_translations xt ON xt.point_of_interest_id=x.experience_id AND xt.locale="es" WHERE e.status="pending" ORDER BY e.submitted_at,e.id')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Return payment status and the response for the authenticated submitting account; never expose file paths. */
    public function history(int $accountId):array
    {
        $query=$this->pdo->prepare('SELECT e.checkout_reference,e.transaction_date,e.paid_amount,e.payment_method,e.source_bank,e.status,e.payment_reference,e.customer_message,e.submitted_at,e.reviewed_at,
                s.id AS subscription_id,s.business_id,s.plan_code,s.billing_frequency,s.quoted_amount,s.currency,
                COALESCE(pt.name,s.plan_code) AS plan_name,t.display_name AS business_name,dt.display_name AS destination_name
            FROM business_subscription_payment_evidence e
            JOIN business_subscriptions s ON s.id=e.subscription_id
            JOIN businesses b ON b.id=s.business_id
            LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            LEFT JOIN destinations d ON d.id=b.destination_id
            LEFT JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es"
            LEFT JOIN subscription_plan_translations pt ON pt.plan_code=s.plan_code AND pt.locale="es"
            WHERE e.account_id=? ORDER BY e.submitted_at DESC,e.id DESC');
        $query->execute([$accountId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function review(int $evidenceId,string $decision,string $note,string $admin,string $customerMessage=''):void
    {
        if(!in_array($decision,['approved','clarification_requested','rejected'],true))throw new RuntimeException('Selecciona aprobar, pedir aclaración o rechazar el comprobante.');$note=trim($note);$customerMessage=trim($customerMessage);if(in_array($decision,['clarification_requested','rejected'],true)&&strlen($note)<8)throw new RuntimeException('Deja una nota interna para registrar el motivo.');if(in_array($decision,['clarification_requested','rejected'],true)&&strlen($customerMessage)<8)throw new RuntimeException('Escribe un mensaje claro para el negocio con el motivo y el siguiente paso.');
        $this->pdo->beginTransaction();try{$lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$query=$this->pdo->prepare('SELECT e.subscription_id,e.status,e.paid_amount,s.business_id,s.status AS subscription_status,s.plan_code,s.billing_frequency,s.quoted_amount,s.currency FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.id=?'.$lock);$query->execute([$evidenceId]);$row=$query->fetch(PDO::FETCH_ASSOC);if(!$row||$row['status']!=='pending')throw new RuntimeException('El comprobante ya fue revisado o no existe.');if($row['subscription_status']!=='pending')throw new RuntimeException('La suscripción ya cambió de estado; revisa el negocio antes de continuar.');
            if($decision==='approved'){ $operatorService=new ExperienceOperatorAddon($this->pdo);if($operatorService->forSubscription((int)$row['subscription_id']))$operatorService->requireEligible((int)$row['business_id']);}
            $validFrequency=isset(['monthly'=>true,'quarterly'=>true,'semiannual'=>true,'annual'=>true][$row['billing_frequency']??'']);
            $validQuote=$row['plan_code']!=='free'&&$validFrequency&&$row['quoted_amount']!==null&&(float)$row['quoted_amount']>0&&preg_match('/^[A-Z]{3}$/',(string)$row['currency'])===1;
            if($decision==='approved'&&$validQuote&&SubscriptionChangeService::cents($row['paid_amount'])<SubscriptionChangeService::cents($row['quoted_amount']))throw new RuntimeException('El pago no cubre el importe completo de la solicitud.');
            if($decision==='approved'&&!$validQuote)throw new RuntimeException('No se puede confirmar el pago: la suscripción no tiene una frecuencia, un importe cotizado y una moneda válidos.');
            $this->pdo->prepare('UPDATE business_subscription_payment_evidence SET status=?,admin_note=?,customer_message=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$decision,substr($note,0,1000),substr($customerMessage,0,1000),substr($admin,0,120),$evidenceId]);
            if($decision==='approved'){$update=$this->pdo->prepare('UPDATE business_subscriptions SET status="paid_pending_publication",reviewed_at=CURRENT_TIMESTAMP WHERE id=? AND status="pending"');$update->execute([$row['subscription_id']]);if($update->rowCount()!==1)throw new RuntimeException('La suscripción cambió de estado y no se confirmó el pago.');}
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public static function validateTransaction(string $reference,array $data):array
    {
        if(trim($reference)===''||trim($reference)==='[SIMULACIÓN]')throw new RuntimeException('Indica el número de transacción o autorización del comprobante.');
        $date=(string)($data['transaction_date']??'');$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date||$date>date('Y-m-d'))throw new RuntimeException('Indica una fecha de pago válida, no futura.');
        $amount=(string)($data['paid_amount']??'');if(!preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/D',$amount)||(float)$amount<=0)throw new RuntimeException('Indica el valor efectivamente pagado, mayor que cero.');
        $method=(string)($data['payment_method']??'');if(!in_array($method,['breb','transfer','qr','other'],true))throw new RuntimeException('Selecciona el medio usado para pagar.');
        $bank=trim((string)($data['source_bank']??''));if($bank===''||strlen($bank)>120||preg_match('/[\x00-\x1f\x7f]/',$bank))throw new RuntimeException('Indica el banco o aplicación desde donde pagaste.');
        $checkout=(string)($data['checkout_reference']??'');if(!preg_match('/^EL-[A-F0-9]{24}$/D',$checkout))throw new RuntimeException('La referencia de solicitud no es válida. Recarga la página.');
        return ['transaction_date'=>$date,'paid_amount'=>$amount,'payment_method'=>$method,'source_bank'=>$bank,'checkout_reference'=>$checkout];
    }

    private function validateUpload(array $upload):array
    {
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Adjunta el soporte de transferencia en PDF, JPG, PNG o WebP.');$tmp=(string)($upload['tmp_name']??'');$size=(int)($upload['size']??0);if(!is_uploaded_file($tmp)||$size<1||$size>self::MAX_BYTES)throw new RuntimeException('El comprobante debe pesar máximo 8 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!is_string($mime)||!isset(self::MIME_EXTENSIONS[$mime]))throw new RuntimeException('El comprobante debe ser PDF, JPG, PNG o WebP.');$extension=self::MIME_EXTENSIONS[$mime];return [bin2hex(random_bytes(24)).'.'.$extension,substr(basename((string)($upload['name']??'comprobante.'.$extension)),0,255),$mime,$size];
    }
}

<?php
declare(strict_types=1);

final class BusinessPaymentEvidenceService
{
    public const MAX_BYTES=8*1024*1024;
    private const MIME_EXTENSIONS=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    public function __construct(private PDO $pdo,private string $storage)
    {
        if(!is_dir($this->storage)&&!mkdir($this->storage,0700,true)&&!is_dir($this->storage))throw new RuntimeException('No se pudo preparar el almacenamiento privado de comprobantes.');
    }

    public function submit(int $accountId,int $businessId,string $planCode,string $frequency,string $reference,array $upload):int
    {
        $reference=trim($reference);if(strlen($reference)>160)throw new RuntimeException('La referencia de pago supera los 160 caracteres.');
        if(!throttle('business_payment_evidence',5,3600,(string)$accountId))throw new RuntimeException('Se alcanzó el límite de envíos de comprobantes. Inténtalo más tarde.');
        $membership=$this->pdo->prepare('SELECT b.id FROM business_account_memberships m JOIN businesses b ON b.id=m.business_id JOIN accounts a ON a.id=m.account_id WHERE m.account_id=? AND m.business_id=? AND m.status="active" AND m.role IN ("owner","manager") AND a.status="active"');$membership->execute([$accountId,$businessId]);if(!$membership->fetchColumn())throw new RuntimeException('No tienes permiso para gestionar este negocio.');
        $planQuery=$this->pdo->prepare('SELECT code FROM subscription_plans WHERE code=? AND is_active=1');$planQuery->execute([$planCode]);if(!$planQuery->fetchColumn())throw new RuntimeException('Selecciona una suscripción vigente.');
        $amount=null;$currency='COP';$status='pending_publication';$frequency=$planCode==='free'?'':$frequency;
        if($planCode!=='free'){
            if(!in_array($frequency,['monthly','quarterly','semiannual','annual'],true))throw new RuntimeException('Selecciona la frecuencia de pago.');
            $priceQuery=$this->pdo->prepare('SELECT amount,currency FROM subscription_plan_prices WHERE plan_code=? AND billing_frequency=?');$priceQuery->execute([$planCode,$frequency]);$price=$priceQuery->fetch(PDO::FETCH_ASSOC);
            if(!$price||$price['amount']===null||(float)$price['amount']<=0)throw new RuntimeException('Esta suscripción no tiene una tarifa válida configurada.');
            $amount=(float)$price['amount'];$currency=(string)$price['currency'];$status='pending';
            if(preg_match('/^[A-Z]{3}$/',$currency)!==1)throw new RuntimeException('La moneda de esta tarifa no es válida. Contacta al administrador antes de enviar el comprobante.');
            [$storedName,$originalName,$mime,$bytes]=$this->validateUpload($upload);
        }
        $path=null;
        if($planCode!=='free'){$path=$this->storage.'/'.$storedName;if(!move_uploaded_file((string)$upload['tmp_name'],$path))throw new RuntimeException('No se pudo guardar el comprobante privado.');@chmod($path,0600);}
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('UPDATE business_subscriptions SET status="cancelled" WHERE business_id=? AND status IN ("pending","pending_publication","paid_pending_publication")')->execute([$businessId]);
            $insert=$this->pdo->prepare('INSERT INTO business_subscriptions(business_id,plan_code,billing_frequency,status,starts_at,ends_at,reviewed_at,publish_not_before,quoted_amount,currency) VALUES(?,?,?, ?,NULL,NULL,NULL,NULL,?,?)');$insert->execute([$businessId,$planCode,$frequency?:null,$status,$amount,$currency]);$subscriptionId=(int)$this->pdo->lastInsertId();
            if($planCode!=='free'){$evidence=$this->pdo->prepare('INSERT INTO business_subscription_payment_evidence(subscription_id,account_id,storage_name,original_name,mime_type,file_size,payment_reference) VALUES(?,?,?,?,?,?,?)');$evidence->execute([$subscriptionId,$accountId,$storedName,$originalName,$mime,$bytes,$reference]);}
            $this->pdo->commit();return $subscriptionId;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();if($path&&is_file($path))@unlink($path);throw $error;}
    }

    public function pending():array
    {
        return $this->pdo->query('SELECT e.id,e.original_name,e.mime_type,e.file_size,e.payment_reference,e.submitted_at,a.display_name AS account_name,a.email,b.id AS business_id,t.display_name AS business_name,d.slug AS destination_slug,s.plan_code,s.billing_frequency,s.quoted_amount,s.currency FROM business_subscription_payment_evidence e JOIN accounts a ON a.id=e.account_id JOIN business_subscriptions s ON s.id=e.subscription_id JOIN businesses b ON b.id=s.business_id JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id WHERE e.status="pending" ORDER BY e.submitted_at,e.id')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Return payment status and the response for the authenticated submitting account; never expose file paths. */
    public function history(int $accountId):array
    {
        $query=$this->pdo->prepare('SELECT e.status,e.payment_reference,e.customer_message,e.submitted_at,e.reviewed_at,
                s.plan_code,s.billing_frequency,s.quoted_amount,s.currency,
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
        if(!in_array($decision,['approved','rejected'],true))throw new RuntimeException('Selecciona aprobar o rechazar el comprobante.');$note=trim($note);$customerMessage=trim($customerMessage);if($decision==='rejected'&&strlen($note)<8)throw new RuntimeException('Deja una nota interna para registrar el motivo del rechazo.');if($decision==='rejected'&&strlen($customerMessage)<8)throw new RuntimeException('Escribe un mensaje claro para el negocio con el motivo y el siguiente paso.');
        $this->pdo->beginTransaction();try{$lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$query=$this->pdo->prepare('SELECT e.subscription_id,e.status,s.status AS subscription_status,s.plan_code,s.billing_frequency,s.quoted_amount,s.currency FROM business_subscription_payment_evidence e JOIN business_subscriptions s ON s.id=e.subscription_id WHERE e.id=?'.$lock);$query->execute([$evidenceId]);$row=$query->fetch(PDO::FETCH_ASSOC);if(!$row||$row['status']!=='pending')throw new RuntimeException('El comprobante ya fue revisado o no existe.');if($row['subscription_status']!=='pending')throw new RuntimeException('La suscripción ya cambió de estado; revisa el negocio antes de continuar.');
            $validFrequency=isset(['monthly'=>true,'quarterly'=>true,'semiannual'=>true,'annual'=>true][$row['billing_frequency']??'']);
            $validQuote=$row['plan_code']!=='free'&&$validFrequency&&$row['quoted_amount']!==null&&(float)$row['quoted_amount']>0&&preg_match('/^[A-Z]{3}$/',(string)$row['currency'])===1;
            if($decision==='approved'&&!$validQuote)throw new RuntimeException('No se puede confirmar el pago: la suscripción no tiene una frecuencia, un importe cotizado y una moneda válidos.');
            $this->pdo->prepare('UPDATE business_subscription_payment_evidence SET status=?,admin_note=?,customer_message=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$decision,substr($note,0,1000),substr($customerMessage,0,1000),substr($admin,0,120),$evidenceId]);
            if($decision==='approved'){$update=$this->pdo->prepare('UPDATE business_subscriptions SET status="paid_pending_publication",reviewed_at=CURRENT_TIMESTAMP WHERE id=? AND status="pending"');$update->execute([$row['subscription_id']]);if($update->rowCount()!==1)throw new RuntimeException('La suscripción cambió de estado y no se confirmó el pago.');}
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    private function validateUpload(array $upload):array
    {
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Adjunta el soporte de transferencia en PDF, JPG, PNG o WebP.');$tmp=(string)($upload['tmp_name']??'');$size=(int)($upload['size']??0);if(!is_uploaded_file($tmp)||$size<1||$size>self::MAX_BYTES)throw new RuntimeException('El comprobante debe pesar máximo 8 MB.');$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!is_string($mime)||!isset(self::MIME_EXTENSIONS[$mime]))throw new RuntimeException('El comprobante debe ser PDF, JPG, PNG o WebP.');$extension=self::MIME_EXTENSIONS[$mime];return [bin2hex(random_bytes(24)).'.'.$extension,substr(basename((string)($upload['name']??'comprobante.'.$extension)),0,255),$mime,$size];
    }
}

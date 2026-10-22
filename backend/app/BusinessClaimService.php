<?php
declare(strict_types=1);

require_once __DIR__.'/ContactPhone.php';
final class BusinessClaimService
{
    public function __construct(private PDO $pdo) {}

    public function destinations(): array
    {
        return $this->pdo->query('SELECT DISTINCT d.id,d.slug,t.display_name FROM destinations d
            JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es"
            JOIN businesses b ON b.destination_id=d.id
            WHERE d.publication_status="published" AND b.publication_status="published"
              AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
            ORDER BY d.display_order,t.display_name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories(int $destinationId): array
    {
        $query=$this->pdo->prepare('SELECT DISTINCT c.code,t.label FROM service_categories c
            JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es"
            JOIN businesses b ON b.category_code=c.code
            WHERE c.is_active=1 AND b.destination_id=? AND b.publication_status="published"
              AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
            ORDER BY c.display_order,t.label');
        $query->execute([$destinationId]);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(int $destinationId,string $category,string $name=''): array
    {
        if($destinationId<1||$category==='')return [];
        $query=$this->pdo->prepare('SELECT b.id,b.information_status,t.display_name,b.address_text
            FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            WHERE b.destination_id=? AND b.category_code=? AND b.publication_status="published"
              AND b.claim_status="NO_RECLAMADO" AND b.operation_status<>"CERRADO_DEFINITIVO"
              AND t.display_name LIKE ? ORDER BY t.display_name LIMIT 500');
        $query->execute([$destinationId,$category,'%'.str_replace(['%','_'],'',trim($name)).'%']);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function submit(array $input): int
    {
        $accountId=filter_var($_SESSION['business_account_id']??null,FILTER_VALIDATE_INT);
        if(!$accountId)throw new RuntimeException('Crea una cuenta empresarial o inicia sesión para solicitar la reclamación.');
        $accountQuery=$this->pdo->prepare('SELECT id,display_name,email FROM accounts WHERE id=? AND account_type="business" AND status="active"');
        $accountQuery->execute([$accountId]);$account=$accountQuery->fetch(PDO::FETCH_ASSOC);
        if(!$account)throw new RuntimeException('Inicia sesión con una cuenta empresarial activa.');
        $businessId=filter_var($input['business_id']??null,FILTER_VALIDATE_INT);
        $name=(string)$account['display_name'];
        $email=(string)$account['email'];
        $phone=ContactPhone::capture($input);
        $relationship=(string)($input['relationship_code']??'');
        $message=trim((string)($input['message']??''));
        if(!$businessId||$name===''||strlen($name)>180)throw new RuntimeException('Completa tu nombre para continuar.');
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Revisa el correo electrónico.');
        if($phone!==''&&!preg_match('/^\\+?[0-9() .-]{7,24}$/D',$phone))throw new RuntimeException('Revisa el número de teléfono.');
        if($email===''&&$phone==='')throw new RuntimeException('Indica un correo o un teléfono para poder contactarte.');
        if(!in_array($relationship,['owner','authorized_representative','other'],true))throw new RuntimeException('Selecciona tu relación con el negocio.');
        if(strlen($message)>1000)throw new RuntimeException('El mensaje supera los 1.000 caracteres.');
        if(($input['consent']??'')!=='1')throw new RuntimeException('Autoriza el uso de estos datos para revisar la solicitud.');
        if(!throttle('business_claim',4,3600,(string)$businessId))throw new RuntimeException('Se alcanzó el límite de solicitudes para este negocio. Inténtalo más tarde.');

        $this->pdo->beginTransaction();
        try{
            $driver=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $lock=$driver==='mysql'?' FOR UPDATE':'';
            $business=$this->pdo->prepare('SELECT id,claim_status,publication_status FROM businesses WHERE id=?'.$lock);
            $business->execute([$businessId]);$row=$business->fetch(PDO::FETCH_ASSOC);
            if(!$row||$row['publication_status']!=='published'||$row['claim_status']!=='NO_RECLAMADO')throw new RuntimeException('Este negocio ya no está disponible para reclamar. Actualiza la búsqueda.');
            $insert=$this->pdo->prepare('INSERT INTO business_claim_requests(business_id,account_id,claimant_name,email,phone,relationship_code,message,consent_version,status) VALUES(?,?,?,?,?,?,?,?,"pending")');
            $insert->execute([$businessId,$accountId,$name,$email,$phone,$relationship,$message,'reclamacion-negocio-v1']);
            $this->pdo->prepare('UPDATE businesses SET claim_status="EN_TRAMITE",updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$businessId]);
            $id=(int)$this->pdo->lastInsertId();$this->pdo->commit();return $id;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function requests(): array
    {
        return $this->pdo->query('SELECT r.*,b.public_id,b.information_status,b.claim_status,t.display_name,d.slug AS destination_slug,dt.display_name AS destination_name,c.code AS category_code,ct.label AS category_label
            FROM business_claim_requests r JOIN businesses b ON b.id=r.business_id
            JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            JOIN destinations d ON d.id=b.destination_id JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es"
            JOIN service_categories c ON c.code=b.category_code JOIN service_category_translations ct ON ct.category_code=c.code AND ct.locale="es"
            ORDER BY CASE r.status WHEN "pending" THEN 0 WHEN "in_review" THEN 1 ELSE 2 END,r.created_at')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRequest(int $id,string $status,string $note=''): void
    {
        if(!in_array($status,['in_review','not_approved'],true))throw new RuntimeException('Estado de reclamación no válido.');
        $this->pdo->beginTransaction();
        try{
            $query=$this->pdo->prepare('SELECT business_id,status FROM business_claim_requests WHERE id=?');$query->execute([$id]);$request=$query->fetch(PDO::FETCH_ASSOC);
            if(!$request||!in_array($request['status'],['pending','in_review'],true))throw new RuntimeException('La solicitud ya fue cerrada o no existe.');
            $this->pdo->prepare('UPDATE business_claim_requests SET status=?,admin_note=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,substr(trim($note),0,1000),$id]);
            if($status==='not_approved')$this->pdo->prepare('UPDATE businesses SET claim_status="NO_RECLAMADO",updated_at=CURRENT_TIMESTAMP WHERE id=? AND claim_status="EN_TRAMITE"')->execute([$request['business_id']]);
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}

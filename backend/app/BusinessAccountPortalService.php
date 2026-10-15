<?php
declare(strict_types=1);

final class BusinessAccountPortalService
{
    public function __construct(private PDO $pdo) {}

    public function registrationDestinations(): array
    {
        return $this->pdo->query('SELECT d.id,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" WHERE d.publication_status="published" ORDER BY d.display_order,t.display_name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrationCategories(int $destinationId): array
    {
        $query=$this->pdo->prepare('SELECT c.code,t.label FROM service_categories c JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es" WHERE c.is_active=1 AND EXISTS(SELECT 1 FROM destinations d WHERE d.id=? AND d.publication_status="published") ORDER BY c.display_order,t.label');
        $query->execute([$destinationId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchRegistrationBusinesses(int $destinationId,string $category,string $term): array
    {
        $term=trim($term);if($destinationId<1||$category===''||strlen($term)<2)return [];
        $query=$this->pdo->prepare('SELECT b.id,t.display_name,b.claim_status,b.information_status FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id WHERE b.destination_id=? AND b.category_code=? AND d.publication_status="published" AND b.operation_status<>"CERRADO_DEFINITIVO" AND t.display_name LIKE ? ORDER BY t.display_name LIMIT 20');
        $query->execute([$destinationId,$category,'%'.str_replace(['%','_'],'',$term).'%']);return array_map(static function(array $row):array{$row['can_request']=$row['claim_status']==='NO_RECLAMADO';unset($row['claim_status'],$row['information_status']);return $row;},$query->fetchAll(PDO::FETCH_ASSOC));
    }

    public function register(string $name, string $email, string $password, array $application): int
    {
        $name=trim($name);$email=strtolower(trim($email));
        if(strlen($name)<2||strlen($name)>180)throw new RuntimeException('Escribe tu nombre o el de la persona responsable.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new RuntimeException('Escribe un correo válido.');
        if(strlen($password)<12||strlen($password)>200)throw new RuntimeException('La contraseña debe tener entre 12 y 200 caracteres.');
        if(!preg_match('/[A-Za-z]/',$password)||!preg_match('/[0-9]/',$password))throw new RuntimeException('Combina letras y números en la contraseña.');
        if(!throttle('business_account_register',5,3600,$email))throw new RuntimeException('Se alcanzó el límite de registros. Inténtalo más tarde.');
        $type=(string)($application['application_type']??'');$destinationId=(int)($application['destination_id']??0);$category=trim((string)($application['category_code']??''));
        $businessId=(int)($application['business_id']??0);$businessName=trim((string)($application['business_name']??''));
        $relationship=(string)($application['relationship_code']??'');$phone=trim((string)($application['phone']??''));$message=trim((string)($application['message']??''));
        if(!in_array($type,['existing_business','new_business'],true))throw new RuntimeException('Indica si encontraste el negocio o si necesitas solicitar su inclusión.');
        if($destinationId<1||$category==='')throw new RuntimeException('Selecciona el destino y la categoría del negocio.');
        if(!in_array($relationship,['owner','authorized_representative'],true))throw new RuntimeException('Indica si eres propietario o representante autorizado.');
        if($phone!==''&&!preg_match('/^\\+?[0-9() .-]{7,24}$/D',$phone))throw new RuntimeException('Revisa el número de teléfono.');
        if(strlen($message)>1000)throw new RuntimeException('El mensaje supera los 1.000 caracteres.');
        if(($application['consent']??'')!=='1')throw new RuntimeException('Autoriza el uso de tus datos para revisar esta solicitud.');
        if($type==='new_business'&&(strlen($businessName)<2||strlen($businessName)>180))throw new RuntimeException('Escribe el nombre del negocio que no encontraste.');
        $categoryQuery=$this->pdo->prepare('SELECT COUNT(*) FROM service_categories c WHERE c.code=? AND c.is_active=1 AND EXISTS(SELECT 1 FROM destinations d WHERE d.id=? AND d.publication_status="published")');
        $categoryQuery->execute([$category,$destinationId]);if(!(int)$categoryQuery->fetchColumn())throw new RuntimeException('La categoría no está disponible para este destino.');

        $this->pdo->beginTransaction();
        try{
            if($type==='existing_business'){
                $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                $target=$this->pdo->prepare('SELECT id,claim_status,operation_status FROM businesses WHERE id=? AND destination_id=? AND category_code=?'.$lock);$target->execute([$businessId,$destinationId,$category]);$business=$target->fetch(PDO::FETCH_ASSOC);
                if(!$business)throw new RuntimeException('El negocio elegido ya no está disponible. Vuelve a buscarlo.');
                if($business['operation_status']==='CERRADO_DEFINITIVO')throw new RuntimeException('Este negocio aparece como cerrado y no admite solicitudes.');
                if($business['claim_status']!=='NO_RECLAMADO')throw new RuntimeException('Este negocio ya tiene una solicitud o una cuenta asociada. Si lo representas, contáctanos para revisarlo.');
                $businessNameQuery=$this->pdo->prepare('SELECT display_name FROM business_translations WHERE business_id=? AND locale="es"');$businessNameQuery->execute([$businessId]);$businessName=(string)$businessNameQuery->fetchColumn();
            }
            $stmt=$this->pdo->prepare('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,?,?,?,?,"active")');
            $stmt->execute([$this->uuid(), 'business', $name, $email, password_hash($password,PASSWORD_DEFAULT)]);$accountId=(int)$this->pdo->lastInsertId();
            $insert=$this->pdo->prepare('INSERT INTO business_account_applications(account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,contact_phone,message,consent_version,status) VALUES(?,?,?,?,?,?,?,?,?,? ,"pending")');
            $insert->execute([$accountId,$type==='existing_business'?'claim_existing':'request_new',$type==='existing_business'?$businessId:null,$destinationId,$category,$businessName,$relationship,$phone,$message,'business-account-onboarding-v1']);
            if($type==='existing_business'){$update=$this->pdo->prepare('UPDATE businesses SET claim_status="EN_TRAMITE",updated_at=CURRENT_TIMESTAMP WHERE id=? AND claim_status="NO_RECLAMADO"');$update->execute([$businessId]);if($update->rowCount()!==1)throw new RuntimeException('El negocio acaba de recibir otra solicitud. Vuelve a buscarlo.');}
            $this->pdo->commit();return $accountId;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function authenticate(string $email,string $password): array
    {
        $email=strtolower(trim($email));
        if(!throttle('business_account_login',10,900,$email))throw new RuntimeException('Demasiados intentos. Inténtalo más tarde.');
        $stmt=$this->pdo->prepare('SELECT id,public_id,account_type,display_name,email,password_hash,status FROM accounts WHERE email=?');
        $stmt->execute([$email]);$account=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$account||$account['account_type']!=='business'||$account['status']!=='active'||!$account['password_hash']||!password_verify($password,$account['password_hash']))throw new RuntimeException('Correo o contraseña incorrectos.');
        if(password_needs_rehash($account['password_hash'],PASSWORD_DEFAULT)){$this->pdo->prepare('UPDATE accounts SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$account['id']]);}
        unset($account['password_hash']);return $account;
    }

    public function current(int $id): ?array
    {
        $stmt=$this->pdo->prepare('SELECT id,public_id,display_name,email,status,email_verified_at FROM accounts WHERE id=? AND account_type="business"');
        $stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }

    public function businesses(int $accountId): array
    {
        $stmt=$this->pdo->prepare('SELECT b.public_id,b.information_status,b.claim_status,b.operation_status,b.publication_status,
                t.display_name,d.slug AS destination_slug,dt.display_name AS destination_name,m.role,
                (SELECT sp.code FROM business_subscriptions bs JOIN subscription_plans sp ON sp.code=bs.plan_code WHERE bs.business_id=b.id ORDER BY bs.id DESC LIMIT 1) AS plan_code,
                (SELECT bs.status FROM business_subscriptions bs WHERE bs.business_id=b.id ORDER BY bs.id DESC LIMIT 1) AS subscription_status
            FROM business_account_memberships m JOIN businesses b ON b.id=m.business_id
            JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            JOIN destinations d ON d.id=b.destination_id JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es"
            WHERE m.account_id=? AND m.status="active" ORDER BY t.display_name');
        $stmt->execute([$accountId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function claims(int $accountId): array
    {
        $stmt=$this->pdo->prepare('SELECT r.status,r.created_at,t.display_name,d.slug AS destination_slug,dt.display_name AS destination_name
            FROM business_claim_requests r JOIN businesses b ON b.id=r.business_id
            JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            JOIN destinations d ON d.id=b.destination_id JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale="es"
            WHERE r.account_id=? ORDER BY r.created_at DESC');
        $stmt->execute([$accountId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function applications(int $accountId): array
    {
        $stmt=$this->pdo->prepare('SELECT r.application_type,r.business_name,r.status,r.created_at,t.label AS category_label,dt.display_name AS destination_name FROM business_account_applications r JOIN service_category_translations t ON t.category_code=r.category_code AND t.locale="es" JOIN destination_translations dt ON dt.destination_id=r.destination_id AND dt.locale="es" WHERE r.account_id=? ORDER BY r.created_at DESC');
        $stmt->execute([$accountId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminApplications(): array
    {
        return $this->pdo->query('SELECT r.*,a.display_name AS account_name,a.email,t.label AS category_label,dt.display_name AS destination_name FROM business_account_applications r JOIN accounts a ON a.id=r.account_id JOIN service_category_translations t ON t.category_code=r.category_code AND t.locale="es" JOIN destination_translations dt ON dt.destination_id=r.destination_id AND dt.locale="es" ORDER BY CASE r.status WHEN "pending" THEN 0 WHEN "in_review" THEN 1 ELSE 2 END,r.created_at')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reviewApplication(int $id,string $status,string $note=''): void
    {
        if(!in_array($status,['in_review','not_approved'],true))throw new RuntimeException('Estado de solicitud no válido.');
        $this->pdo->beginTransaction();
        try{
            $query=$this->pdo->prepare('SELECT business_id,status FROM business_account_applications WHERE id=?');$query->execute([$id]);$row=$query->fetch(PDO::FETCH_ASSOC);
            if(!$row||!in_array($row['status'],['pending','in_review'],true))throw new RuntimeException('La solicitud ya fue cerrada o no existe.');
            $this->pdo->prepare('UPDATE business_account_applications SET status=?,admin_note=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,substr(trim($note),0,1000),$id]);
            if($status==='not_approved'&&$row['business_id'])$this->pdo->prepare('UPDATE businesses SET claim_status="NO_RECLAMADO",updated_at=CURRENT_TIMESTAMP WHERE id=? AND claim_status="EN_TRAMITE"')->execute([$row['business_id']]);
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    private function uuid():string
    {
        $hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-'.dechex((hexdec($hex[16])&3)|8).substr($hex,17,3).'-'.substr($hex,20,12);
    }
}

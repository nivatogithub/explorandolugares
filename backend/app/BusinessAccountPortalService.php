<?php
declare(strict_types=1);
require_once __DIR__.'/AccountProfileService.php';
require_once __DIR__.'/AccountResidence.php';
require_once __DIR__.'/AccountAuthorization.php';
require_once __DIR__.'/BusinessOnboardingDetails.php';
require_once __DIR__.'/BusinessExistenceVerification.php';
require_once __DIR__.'/BusinessSelfOnboarding.php';
require_once __DIR__.'/BusinessOnboardingUploadService.php';

final class BusinessAccountPortalService
{
    public function __construct(private PDO $pdo) {}

    public function registerTraveler(string $name,string $email,string $password,string $confirmation,bool $consent,?array $residence=null): int
    {
        $name=trim($name);$email=strtolower(trim($email));
        if(strlen($name)<2||strlen($name)>180)throw new RuntimeException('Escribe tu nombre, entre 2 y 180 caracteres.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new RuntimeException('Escribe un correo válido.');
        if($password!==$confirmation)throw new RuntimeException('Las contraseñas no coinciden.');
        if(strlen($password)<12||strlen($password)>200||!preg_match('/[A-Za-z]/',$password)||!preg_match('/[0-9]/',$password))throw new RuntimeException('Usa entre 12 y 200 caracteres, con letras y números.');
        if(!$consent)throw new RuntimeException('Acepta el tratamiento de datos para crear tu cuenta.');
        if(!throttle('traveler_register',5,3600,$email))throw new RuntimeException('Demasiados intentos de registro.');
        $q=$this->pdo->prepare('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,"traveler",?,?,?,"pending_activation")');
        $this->pdo->beginTransaction();
        try{$q->execute([$this->uuid(),$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$this->pdo->lastInsertId();if($residence!==null)AccountResidence::save($this->pdo,$id,$residence);$this->pdo->prepare('INSERT INTO account_consents(account_id,consent_version) VALUES(?,"traveler-registration-v1")')->execute([$id]);$this->pdo->commit();return $id;}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function registrationDestinations(): array
    {
        return $this->pdo->query('SELECT d.id,d.latitude,d.longitude,d.department,d.country_code,d.destination_type,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale="es" WHERE d.publication_status="published" ORDER BY d.display_order,t.display_name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrationCategories(int $destinationId): array
    {
        $query=$this->pdo->prepare('SELECT c.code,t.label FROM service_categories c JOIN service_category_translations t ON t.category_code=c.code AND t.locale="es" WHERE c.is_active=1 AND EXISTS(SELECT 1 FROM destinations d WHERE d.id=? AND d.publication_status="published") ORDER BY c.display_order,t.label');
        $query->execute([$destinationId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchRegistrationBusinesses(int $destinationId,string $category,string $term): array
    {
        $term=trim($term);if($destinationId<1||$category===''||strlen($term)<2)return [];
        $sql='SELECT b.id,t.display_name,b.claim_status,b.information_status,EXISTS(SELECT 1 FROM business_account_memberships bm WHERE bm.business_id=b.id AND bm.status="active") AS has_responsible FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" JOIN destinations d ON d.id=b.destination_id WHERE b.destination_id=? AND (b.category_code=? OR EXISTS(SELECT 1 FROM business_categories bc WHERE bc.business_id=b.id AND bc.category_code=?)) AND d.publication_status="published" AND b.operation_status<>"CERRADO_DEFINITIVO"';
        $query=$this->pdo->prepare($sql.' AND t.display_name LIKE ? ORDER BY t.display_name LIMIT 20');
        $query->execute([$destinationId,$category,$category,'%'.str_replace(['%','_'],'',$term).'%']);$found=$query->fetchAll(PDO::FETCH_ASSOC);
        if(!$found&&mb_strlen($term)>=9){$query=$this->pdo->prepare($sql.' ORDER BY t.display_name');$query->execute([$destinationId,$category,$category]);foreach($query->fetchAll(PDO::FETCH_ASSOC) as $candidate)if(BusinessBulkPublicationService::looksLikeDuplicate($term,$candidate['display_name'])){$found[]=$candidate;if(count($found)>=20)break;}}
        return array_map(static function(array $row):array{$row['can_request']=true;$row['requires_review']=(bool)$row['has_responsible'];$row['review_message']=$row['requires_review']?'Puedes solicitar la revisión de tu relación con este negocio. No cambiaremos su responsable actual.':'';unset($row['claim_status'],$row['information_status'],$row['has_responsible']);return $row;},$found);
    }

    public function register(string $name, string $email, string $password, array $application, array $evidenceUploads=[], ?string $evidenceStorage=null,mixed $referenceUploads=null,?string $referenceStorage=null,?int $existingAccountId=null): int
    {
        $existingAccount=$existingAccountId?$this->current($existingAccountId):null;
        if($existingAccountId&&!$existingAccount)throw new RuntimeException('Inicia sesión para continuar con tu cuenta.');
        if($existingAccount){$name=$existingAccount['display_name'];$email=$existingAccount['email'];}
        $name=trim($name);$email=strtolower(trim($email));
        if(strlen($name)<2||strlen($name)>180)throw new RuntimeException('Escribe tu nombre o el de la persona responsable.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new RuntimeException('Escribe un correo válido.');
        if(!$existingAccount&&(strlen($password)<12||strlen($password)>200))throw new RuntimeException('La contraseña debe tener entre 12 y 200 caracteres.');
        if(!$existingAccount&&(!preg_match('/[A-Za-z]/',$password)||!preg_match('/[0-9]/',$password)))throw new RuntimeException('Combina letras y números en la contraseña.');
        if(!throttle('business_account_register',5,3600,$email))throw new RuntimeException('Se alcanzó el límite de registros. Inténtalo más tarde.');
        $type=(string)($application['application_type']??'');$destinationId=(int)($application['destination_id']??0);$category=trim((string)($application['category_code']??''));
        $businessId=(int)($application['business_id']??0);$businessName=trim((string)($application['business_name']??''));
        $relationship=(string)($application['relationship_code']??'');$phone=trim((string)($application['phone']??''));$message=trim((string)($application['message']??''));
        if(!in_array($type,['existing_business','new_business'],true))throw new RuntimeException('Indica si encontraste el negocio o si necesitas solicitar su inclusión.');
        if($destinationId<1||$category==='')throw new RuntimeException('Selecciona el destino y la categoría del negocio.');
        if(!in_array($relationship,['owner','legal_representative','authorized_administrator','authorized_representative'],true))throw new RuntimeException('Indica si eres propietario, representante legal o administrador autorizado.');
        if($phone!==''&&!preg_match('/^\\+?[0-9() .-]{7,24}$/D',$phone))throw new RuntimeException('Revisa el número de teléfono.');
        if(strlen($message)>1000)throw new RuntimeException('El mensaje supera los 1.000 caracteres.');
        if(($application['consent']??'')!=='1')throw new RuntimeException('Autoriza el uso de tus datos para revisar esta solicitud.');
        if($type==='new_business'&&(strlen($businessName)<2||strlen($businessName)>180))throw new RuntimeException('Escribe el nombre del negocio que no encontraste.');
        $categoryQuery=$this->pdo->prepare('SELECT COUNT(*) FROM service_categories c WHERE c.code=? AND c.is_active=1 AND EXISTS(SELECT 1 FROM destinations d WHERE d.id=? AND d.publication_status="published")');
        $categoryQuery->execute([$category,$destinationId]);if(!(int)$categoryQuery->fetchColumn())throw new RuntimeException('La categoría no está disponible para este destino.');

        $selfOnboarding=($application['self_onboarding_policy']??'')===BusinessSelfOnboarding::POLICY;
        $temporary=(array)($application['temporary_uploads']??[]);$draft=(string)($application['onboarding_draft']??'');$temporaryEvidence=(string)($temporary['evidence']['business_registration']??'');$temporaryImages=array_filter((array)($temporary['editorial']??[]),static fn($id)=>is_string($id)&&preg_match('/^[a-f0-9]{48}$/D',$id));
        if($selfOnboarding&&($application['responsibility_declared']??'')!=='1')throw new RuntimeException('Confirma que tienes autorización para administrar el negocio.');
        $requiredEvidence=$selfOnboarding?['business_registration']:$this->requiredEvidence($relationship);
        if($selfOnboarding&&$temporaryEvidence===''&&($evidenceUploads['business_registration']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Adjunta un documento del negocio: RUT, matrícula o certificado equivalente.');
        if($selfOnboarding&&in_array((string)($application['business_details']['requested_subscription']['plan_code']??''),['page','page_premium'],true)&&!$temporaryImages&&!in_array(UPLOAD_ERR_OK,(array)($referenceUploads['error']??[]),true))throw new RuntimeException('Adjunta una fotografía de portada para preparar la página del negocio.');
        $imageLimit=match((string)($application['business_details']['requested_subscription']['plan_code']??'free')){'page'=>1,'page_premium'=>6,default=>0};if(count($temporaryImages)>$imageLimit)throw new RuntimeException('La suscripción elegida no permite esa cantidad de imágenes.');
        $uploadsPresent=$temporaryEvidence!=='';foreach($requiredEvidence as $evidenceType){if(($evidenceUploads[$evidenceType]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$uploadsPresent=true;}
        if($uploadsPresent){
            if(!$evidenceStorage)throw new RuntimeException('No está configurado el almacenamiento privado de documentos.');
            foreach($requiredEvidence as $evidenceType)if(!($evidenceType==='business_registration'&&$temporaryEvidence!==''))$this->validateEvidenceUpload($evidenceUploads[$evidenceType]??[]);
            if(!is_dir($evidenceStorage)&&!mkdir($evidenceStorage,0700,true)&&!is_dir($evidenceStorage))throw new RuntimeException('No se pudo preparar el almacenamiento privado de documentos.');
        }
        if($type==='new_business'&&!$selfOnboarding){
            $duplicate=$this->pdo->prepare('SELECT b.id FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=? AND LOWER(TRIM(t.display_name))=LOWER(?) LIMIT 1');$duplicate->execute([$destinationId,$businessName]);
            if($duplicate->fetchColumn())throw new RuntimeException('Ese negocio ya está registrado en este lugar. Búscalo y solicita asociarlo a tu cuenta.');
        }
        $existenceAssessment=null;
        if($type==='new_business'){
            $destinationQuery=$this->pdo->prepare('SELECT display_name FROM destination_translations WHERE destination_id=? AND locale="es"');$destinationQuery->execute([$destinationId]);
            $ids=BusinessExistenceVerification::capture(['business_rnt'=>$application['business_details']['existence_inputs']['rnt']??'','business_nit'=>$application['business_details']['existence_inputs']['nit']??'']);
            $existenceAssessment=BusinessExistenceVerification::assessNew($businessName,(string)$destinationQuery->fetchColumn(),$ids);
        }
        $storedFiles=[];$disputedClaim=false;
        $this->pdo->beginTransaction();
        try{
            if($existingAccountId){$locked=$this->pdo->prepare('SELECT id FROM accounts WHERE id=? AND status="active"'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$locked->execute([$existingAccountId]);if(!$locked->fetchColumn())throw new RuntimeException('La cuenta ya no está disponible.');}
            if($selfOnboarding&&$type==='new_business'){$place=$this->pdo->prepare('SELECT id FROM destinations WHERE id=?'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$place->execute([$destinationId]);$matches=BusinessSelfOnboarding::duplicates($this->pdo,$destinationId,$businessName);if($matches){if($existingAccountId&&count($matches)===1){$owned=$this->pdo->prepare('SELECT 1 FROM business_account_memberships WHERE business_id=? AND account_id=? AND status="active"');$owned->execute([$matches[0]['id'],$existingAccountId]);if($owned->fetchColumn()&&mb_strtolower(trim($matches[0]['display_name']))===mb_strtolower($businessName)){$this->pdo->commit();return $existingAccountId;}}if(!BusinessSelfOnboarding::distinctLocationAllowed($this->pdo,$matches,$application['business_details']??[]))throw new RuntimeException('Encontramos un negocio igual o similar en este lugar. Selecciona la ficha correcta o declara que es otro establecimiento e indica una dirección diferente.');}}
            $duplicateRequest=$this->pdo->prepare('SELECT id FROM business_account_applications WHERE account_id=? AND destination_id=? AND LOWER(business_name)=LOWER(?) AND status IN ("pending","in_review") LIMIT 1');$duplicateRequest->execute([$existingAccountId??0,$destinationId,$businessName]);if($duplicateRequest->fetchColumn())throw new RuntimeException('Ya tienes una solicitud pendiente para este negocio. Consúltala en Mis negocios.');
            if($type==='existing_business'){
                $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                $target=$this->pdo->prepare('SELECT id,claim_status,operation_status FROM businesses WHERE id=? AND destination_id=? AND (category_code=? OR EXISTS(SELECT 1 FROM business_categories bc WHERE bc.business_id=businesses.id AND bc.category_code=?))'.$lock);$target->execute([$businessId,$destinationId,$category,$category]);$business=$target->fetch(PDO::FETCH_ASSOC);
                if(!$business)throw new RuntimeException('El negocio elegido ya no está disponible. Vuelve a buscarlo.');
                if($business['operation_status']==='CERRADO_DEFINITIVO')throw new RuntimeException('Este negocio aparece como cerrado y no admite solicitudes.');
                $ownerQuery=$this->pdo->prepare('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"');$ownerQuery->execute([$businessId]);$owners=array_map('intval',$ownerQuery->fetchAll(PDO::FETCH_COLUMN));
                if($existingAccountId&&in_array($existingAccountId,$owners,true)){if($selfOnboarding){$this->pdo->commit();return $existingAccountId;}throw new RuntimeException('Este negocio ya está asociado a tu cuenta. Consúltalo en Mis negocios.');}
                $disputedClaim=count($owners)>0;
                $duplicateClaim=$this->pdo->prepare('SELECT id FROM business_account_applications WHERE account_id=? AND business_id=? AND status IN ("pending","in_review") LIMIT 1');$duplicateClaim->execute([$existingAccountId??0,$businessId]);
                if($duplicateClaim->fetchColumn())throw new RuntimeException('Ya tienes una solicitud pendiente para este negocio. Consúltala en Mis negocios.');
                $businessNameQuery=$this->pdo->prepare('SELECT display_name FROM business_translations WHERE business_id=? AND locale="es"');$businessNameQuery->execute([$businessId]);$businessName=(string)$businessNameQuery->fetchColumn();
            }
            $accountId=$existingAccountId;
            if(!$accountId){
                $stmt=$this->pdo->prepare('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,"traveler",?,?,?,"pending_activation")');
                $stmt->execute([$this->uuid(),$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$accountId=(int)$this->pdo->lastInsertId();
                if(isset($application['account_residence']))AccountResidence::save($this->pdo,$accountId,$application['account_residence']);
            }
            $insert=$this->pdo->prepare('INSERT INTO business_account_applications(account_id,application_type,business_id,destination_id,category_code,business_name,relationship_code,contact_phone,message,consent_version,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            $insert->execute([$accountId,$type==='existing_business'?'claim_existing':'request_new',$type==='existing_business'?$businessId:null,$destinationId,$category,$businessName,$relationship,$phone,$disputedClaim?'RECLAMACIÓN EN REVISIÓN: el negocio ya tiene responsable o una solicitud previa. No cambia su asociación actual. '.substr($message,0,850):$message,'business-account-onboarding-v1',$disputedClaim?'in_review':'pending']);
            $applicationId=(int)$this->pdo->lastInsertId();
            if($temporaryImages&&$referenceStorage){$tempService=new BusinessOnboardingUploadService($this->pdo,private_storage_path('business-onboarding-uploads'));$imageInsert=$this->pdo->prepare('INSERT INTO business_application_images(application_id,storage_name,original_name,mime_type,file_size,width,height) VALUES(?,?,?,?,?,?,?)');foreach($temporaryImages as $slot=>$uploadId){$image=$tempService->consume($draft,$uploadId,$referenceStorage,20);$storedFiles[]=$image['path'];$normalizedSlot=str_replace('-','_',(string)$slot);if(preg_match('/^(cover|product_[0-4])$/D',$normalizedSlot))$application['business_details']['source_images'][$normalizedSlot]=$image['storage_name'];$imageInsert->execute([$applicationId,$image['storage_name'],$image['original_name'],$image['mime_type'],$image['file_size'],$image['width'],$image['height']]);}}
            elseif($referenceUploads&&$referenceStorage)(new BusinessAdminService($this->pdo,$referenceStorage))->storeApplicationReferences($applicationId,$referenceUploads,$storedFiles);
            $details=$application['business_details']??[];unset($details['claim_review_kind'],$details['auto_claim_on_activation']);
            if($selfOnboarding){$details['onboarding_policy']=BusinessSelfOnboarding::POLICY;$details['responsibility_declared']=true;$details['document_policy']='business-document-8mb-v1';$details['document_retention_policy']='delete-after-verification-v1';}
            $details['existence_assessment']=$type==='existing_business'?BusinessExistenceVerification::assessExisting($this->pdo,$businessId):$existenceAssessment;
            if($disputedClaim)$details['claim_review_kind']='disputed_existing';
            elseif($type==='existing_business'&&!$existingAccountId)$details['auto_claim_on_activation']=true;
            if($details)$this->pdo->prepare('UPDATE business_account_applications SET business_details_json=? WHERE id=?')->execute([json_encode($details,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$applicationId]);
            $profile=new AccountProfileService($this->pdo);
            if($profile->ready())$profile->initializePhone($accountId,$phone);
            if($uploadsPresent){
                foreach($requiredEvidence as $evidenceType){
                    if($evidenceType==='business_registration'&&$temporaryEvidence!==''){$tempService??=new BusinessOnboardingUploadService($this->pdo,private_storage_path('business-onboarding-uploads'));$document=$tempService->consume($draft,$temporaryEvidence,(string)$evidenceStorage,24);$storedFiles[]=$document['path'];$this->pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$applicationId,$evidenceType,$document['storage_name'],$document['original_name'],$document['mime_type'],$document['file_size']]);continue;}
                    [$storedName,$originalName,$mime,$bytes]=$this->validateEvidenceUpload($evidenceUploads[$evidenceType]);
                    $path=$evidenceStorage.'/'.$storedName;
                    if(!move_uploaded_file((string)$evidenceUploads[$evidenceType]['tmp_name'],$path))throw new RuntimeException('No se pudo guardar de forma privada el documento de verificación.');
                    @chmod($path,0600);$storedFiles[]=$path;
                    $this->pdo->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?,?)')->execute([$applicationId,$evidenceType,$storedName,$originalName,$mime,$bytes]);
                }
            }
            // A submitted request remains pending. The account receives no
            // business access and no record is published until manual review.
            if(!$selfOnboarding&&$type==='existing_business'&&!$disputedClaim&&$existingAccountId){
                $this->assignMembership($accountId,$businessId,$relationship==='owner'?'owner':'manager');
                $this->pdo->prepare('UPDATE businesses SET information_status="ACTIVO",claim_status="RECLAMADO",updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$businessId]);
                $this->pdo->prepare('UPDATE business_account_applications SET status="approved",reviewed_at=CURRENT_TIMESTAMP,admin_note=? WHERE id=?')->execute(['Asignación automática: no había responsable asociado. No verifica existencia, no publica ni activa suscripciones.',$applicationId]);
            }
            $this->pdo->commit();return $accountId;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();foreach($storedFiles as $path)if(is_file($path))@unlink($path);throw $error;}
    }

    public function authenticate(string $email,string $password): array
    {
        $email=strtolower(trim($email));
        if(!throttle('business_account_login',10,900,$email))throw new RuntimeException('Demasiados intentos. Inténtalo más tarde.');
        $stmt=$this->pdo->prepare('SELECT id,public_id,account_type,display_name,email,password_hash,status FROM accounts WHERE email=?');
        $stmt->execute([$email]);$account=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$account||!in_array($account['account_type'],['business','traveler'],true)||$account['status']!=='active'||!$account['password_hash']||!password_verify($password,$account['password_hash']))throw new RuntimeException('Correo o contraseña incorrectos.');
        if(password_needs_rehash($account['password_hash'],PASSWORD_DEFAULT)){$this->pdo->prepare('UPDATE accounts SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$account['id']]);}
        unset($account['password_hash']);return $account;
    }

    public function current(int $id): ?array
    {
        $stmt=$this->pdo->prepare('SELECT id,public_id,account_type,display_name,email,status,email_verified_at FROM accounts WHERE id=? AND status="active" AND account_type IN ("business","traveler")');
        $stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return $row?:null;
    }

    public function businesses(int $accountId): array
    {
        $stmt=$this->pdo->prepare('SELECT b.id,b.destination_id,b.public_id,b.information_status,b.claim_status,b.operation_status,b.publication_status,
                t.display_name,d.slug AS destination_slug,dt.display_name AS destination_name,m.role,
                (SELECT sp.code FROM business_subscriptions bs JOIN subscription_plans sp ON sp.code=bs.plan_code WHERE bs.business_id=b.id ORDER BY bs.id DESC LIMIT 1) AS plan_code,
                (SELECT bs.status FROM business_subscriptions bs WHERE bs.business_id=b.id ORDER BY bs.id DESC LIMIT 1) AS subscription_status,
                (SELECT bs.plan_code FROM business_subscriptions bs WHERE bs.business_id=b.id AND bs.status="active" AND (bs.starts_at IS NULL OR bs.starts_at<=CURRENT_TIMESTAMP) AND (bs.ends_at IS NULL OR bs.ends_at>CURRENT_TIMESTAMP) ORDER BY bs.id DESC LIMIT 1) AS active_plan_code
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
        $stmt=$this->pdo->prepare('SELECT r.id,r.business_id,r.application_type,r.business_name,r.status,r.created_at,r.business_details_json,t.label AS category_label,dt.display_name AS destination_name FROM business_account_applications r JOIN service_category_translations t ON t.category_code=r.category_code AND t.locale="es" JOIN destination_translations dt ON dt.destination_id=r.destination_id AND dt.locale="es" WHERE r.account_id=? ORDER BY r.created_at DESC');
        $stmt->execute([$accountId]);return array_map(static function(array $row):array{
            $details=json_decode($row['business_details_json']??'{}',true)??[];
            $row['review_kind']=$details['claim_review_kind']??'standard';
            $row['review_message']=$row['review_kind']==='disputed_existing'?'Recibimos tu reclamación para revisión. El negocio conserva su responsable actual; esta solicitud no te concede acceso para administrarlo.':'';
            if($row['status']==='needs_selection')$row['review_message']='Encontramos una ficha igual o similar. Vuelve a Registrar o reclamar un negocio y selecciona la ficha correcta; tu cuenta ya puede usarse.';
            if(($details['onboarding_policy']??'')===BusinessSelfOnboarding::POLICY&&$row['status']==='pending')$row['review_message']='Recibimos tu solicitud y el soporte privado del negocio. Está pendiente de revisión; aún no puedes administrar ni publicar esta ficha.';
            $choice=$details['requested_subscription']??[];$row['subscription_next_url']=null;
            if($row['status']==='approved'&&!empty($row['business_id'])&&in_array($choice['plan_code']??'',['featured','page','page_premium'],true))$row['subscription_next_url']='/suscripciones/continuar/?'.http_build_query(['plan'=>$choice['plan_code'],'period'=>$choice['billing_frequency']??'monthly','business'=>$row['business_id'],'experience'=>(int)($choice['operator_addon']['experience_id']??0)]);
            unset($row['business_details_json']);return $row;
        },$stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function adminApplications(): array
    {
        return $this->pdo->query('SELECT r.*,a.display_name AS account_name,a.email,t.label AS category_label,dt.display_name AS destination_name FROM business_account_applications r JOIN accounts a ON a.id=r.account_id JOIN service_category_translations t ON t.category_code=r.category_code AND t.locale="es" JOIN destination_translations dt ON dt.destination_id=r.destination_id AND dt.locale="es" ORDER BY CASE r.status WHEN "pending" THEN 0 WHEN "in_review" THEN 1 ELSE 2 END,r.created_at')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function applicationEvidence(int $applicationId):array
    {
        $query=$this->pdo->prepare('SELECT id,evidence_type,original_name,mime_type,file_size,verified_by,verified_at,submitted_at FROM business_account_application_evidence WHERE application_id=? ORDER BY id');
        $query->execute([$applicationId]);return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The automatic account/business association is not a document review. */
    public function verifyApprovedBusinessRegistration(int $applicationId,array $verifiedEvidenceIds,string $note,string $reviewer):void
    {
        $note=trim($note);if(strlen($note)<12)throw new RuntimeException('Deja una nota interna indicando cómo validaste el soporte de titularidad.');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $application=$this->pdo->prepare('SELECT id,business_id,status FROM business_account_applications WHERE id=?'.$lock);$application->execute([$applicationId]);$row=$application->fetch(PDO::FETCH_ASSOC);
            if(!$row||$row['status']!=='approved'||(int)$row['business_id']<1)throw new RuntimeException('La solicitud no está disponible para validar su soporte.');
            $documents=$this->pdo->prepare('SELECT id,verified_at FROM business_account_application_evidence WHERE application_id=? AND evidence_type="business_registration"'.$lock);$documents->execute([$applicationId]);$documents=$documents->fetchAll(PDO::FETCH_ASSOC);
            if(!$documents)throw new RuntimeException('La solicitud no tiene documento de registro del negocio.');
            $selected=array_map('intval',$verifiedEvidenceIds);foreach($documents as $document)if(empty($document['verified_at'])&&!in_array((int)$document['id'],$selected,true))throw new RuntimeException('Marca el documento de registro que revisaste antes de validarlo.');
            $mark=$this->pdo->prepare('UPDATE business_account_application_evidence SET verified_by=?,verified_at=CURRENT_TIMESTAMP WHERE id=? AND application_id=? AND evidence_type="business_registration"');
            foreach($documents as $document)if(empty($document['verified_at']))$mark->execute([substr($reviewer?:'admin',0,120),(int)$document['id'],$applicationId]);
            $this->pdo->prepare('UPDATE business_account_applications SET admin_note=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([substr($note,0,1000),$applicationId]);
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    private function assignMembership(int $account,int $business,string $role):void
    {
        $q=$this->pdo->prepare('SELECT status FROM business_account_memberships WHERE account_id=? AND business_id=?');$q->execute([$account,$business]);
        if($q->fetchColumn()===false)$this->pdo->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,?,"active")')->execute([$account,$business,$role]);
        else $this->pdo->prepare('UPDATE business_account_memberships SET role=?,status="active" WHERE account_id=? AND business_id=?')->execute([$role,$account,$business]);
    }

    public function reviewApplication(int $id,string $status,string $note='',array $verifiedEvidenceIds=[],string $reviewer='',bool $manualResolution=false): void
    {
        if(!in_array($status,['in_review','not_approved','approved'],true))throw new RuntimeException('Estado de solicitud no válido.');
        $note=trim($note);
        if($status==='approved'&&strlen($note)<12)throw new RuntimeException('Para aprobar, deja una nota interna con la evidencia de representación que revisaste (por ejemplo, matrícula mercantil o RUT).');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $query=$this->pdo->prepare('SELECT * FROM business_account_applications WHERE id=?'.$lock);$query->execute([$id]);$row=$query->fetch(PDO::FETCH_ASSOC);
            if(!$row||!in_array($row['status'],['pending','in_review'],true))throw new RuntimeException('La solicitud ya fue cerrada o no existe.');
            $details=json_decode($row['business_details_json']??'{}',true)??[];
            $isDisputed=($details['claim_review_kind']??'')==='disputed_existing';
            if($status==='approved'&&$isDisputed&&(!$manualResolution||$reviewer===''||strlen($note)<30))throw new RuntimeException('Esta reclamación requiere conciliación manual: confirma que resolviste la representación y documenta la decisión con al menos 30 caracteres.');
            if($status==='approved'){
                if(!$isDisputed){
                $evidenceQuery=$this->pdo->prepare('SELECT id,evidence_type FROM business_account_application_evidence WHERE application_id=?');$evidenceQuery->execute([$id]);$evidence=$evidenceQuery->fetchAll(PDO::FETCH_ASSOC);
                $required=(($details['onboarding_policy']??'')===BusinessSelfOnboarding::POLICY)?['business_registration']:$this->requiredEvidence((string)$row['relationship_code']);$ids=array_map('intval',$verifiedEvidenceIds);$byType=[];foreach($evidence as $item)$byType[$item['evidence_type']]=$item;
                foreach($required as $evidenceType){if(!isset($byType[$evidenceType]))throw new RuntimeException('No se puede aprobar: falta el documento obligatorio de '.$this->evidenceLabel($evidenceType).'.');if(!in_array((int)$byType[$evidenceType]['id'],$ids,true))throw new RuntimeException('Marca como revisados todos los documentos requeridos antes de aprobar.');}
                if(strlen($note)<12)throw new RuntimeException('Para aprobar, deja una nota interna con qué documentos revisaste y cómo comprobaste la representación.');
                $mark=$this->pdo->prepare('UPDATE business_account_application_evidence SET verified_by=?,verified_at=CURRENT_TIMESTAMP WHERE id=? AND application_id=?');
                foreach($required as $evidenceType)$mark->execute([substr($reviewer?:'admin',0,120),(int)$byType[$evidenceType]['id'],$id]);
                }
                $businessId=(int)($row['business_id']??0);
                if($row['application_type']==='claim_existing'){
                    if($isDisputed){
                        $target=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.$lock);$target->execute([$businessId]);if(!$target->fetchColumn())throw new RuntimeException('El negocio ya no existe.');
                        $this->pdo->prepare('UPDATE business_account_memberships SET status="revoked" WHERE business_id=? AND status="active"')->execute([$businessId]);
                        $this->pdo->prepare('UPDATE businesses SET claim_status="EN_TRAMITE" WHERE id=?')->execute([$businessId]);
                        $note='Conciliación manual por '.substr($reviewer,0,120).': '.$note;
                    }
                    if($businessId<1)throw new RuntimeException('La solicitud no tiene un negocio existente asociado.');
                    $update=$this->pdo->prepare('UPDATE businesses SET information_status="ACTIVO",claim_status="RECLAMADO",updated_at=CURRENT_TIMESTAMP WHERE id=? AND claim_status="EN_TRAMITE" AND operation_status<>"CERRADO_DEFINITIVO"');
                    $update->execute([$businessId]);
                    if($update->rowCount()!==1)throw new RuntimeException('El negocio ya no está disponible para asociarlo. Revisa su estado antes de aprobar.');
                }elseif($row['application_type']==='request_new'){
                    $slug=$this->availableBusinessSlug((int)$row['destination_id'],$row['business_name']);
                    $publicId=$this->uuid();
                    $insert=$this->pdo->prepare('INSERT INTO businesses(public_id,destination_id,category_code,record_origin,slug,information_status,claim_status,operation_status,publication_status,display_order) VALUES(?,?,?,"owner_submitted",?,"ACTIVO","RECLAMADO","DESCONOCIDO","draft",0)');
                    $insert->execute([$publicId,(int)$row['destination_id'],$row['category_code'],$slug]);
                    $businessId=(int)$this->pdo->lastInsertId();
                    $translation=$this->pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,?,?,?,?)');
                    $translation->execute([$businessId,'es',$row['business_name'],'','']);
                    $this->pdo->prepare('UPDATE business_account_applications SET business_id=? WHERE id=?')->execute([$businessId,$id]);
                    BusinessOnboardingDetails::apply($this->pdo,$businessId,$row['category_code'],json_decode($row['business_details_json']??'{}',true,32,JSON_THROW_ON_ERROR));
                    if(!empty($row['business_details_json']))$this->pdo->prepare('INSERT INTO business_image_references(business_id,storage_name,original_name,mime_type,file_size,width,height) SELECT ?,storage_name,original_name,mime_type,file_size,width,height FROM business_application_images WHERE application_id=?')->execute([$businessId,$id]);
                }else throw new RuntimeException('El tipo de solicitud no se puede aprobar.');

                $role=$row['relationship_code']==='owner'?'owner':'manager';
                $assigned=$this->pdo->prepare('SELECT account_id FROM business_account_memberships WHERE business_id=? AND status="active"');$assigned->execute([$businessId]);
                if($assigned->fetchColumn()!==false)throw new RuntimeException('Este negocio ya tiene una cuenta responsable. Revisa su asociación antes de aprobar otra.');
                $this->assignMembership((int)$row['account_id'],$businessId,$role);
            }
            $this->pdo->prepare('UPDATE business_account_applications SET status=?,admin_note=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$status,substr($note,0,1000),$id]);
            if($status==='approved'){
                $profile=new AccountProfileService($this->pdo);
                if($profile->ready())$profile->syncApprovedApplication($id);
            }
            if($status==='not_approved'&&$row['business_id']&&!$isDisputed)$this->pdo->prepare('UPDATE businesses SET claim_status="NO_RECLAMADO",updated_at=CURRENT_TIMESTAMP WHERE id=? AND claim_status="EN_TRAMITE"')->execute([$row['business_id']]);
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public static function requiredEvidence(string $relationship):array
    {
        $types=['identity_document','business_registration'];
        if(in_array($relationship,['authorized_administrator','authorized_representative'],true))$types[]='authority_document';
        return $types;
    }

    public static function evidenceLabel(string $type):string
    {
        return ['identity_document'=>'identidad','business_registration'=>'existencia o registro del negocio','authority_document'=>'autorización del representante legal'][$type]??'evidencia';
    }

    private function validateEvidenceUpload(array $upload):array
    {
        $extensions=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Adjunta el documento del negocio en un formato admitido.');
        $tmp=(string)($upload['tmp_name']??'');$size=(int)($upload['size']??0);
        if(!is_uploaded_file($tmp)||$size<1||$size>8*1024*1024)throw new RuntimeException('Cada documento debe ser un PDF o imagen JPG, PNG o WebP de máximo 8 MB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);if(!is_string($mime)||!isset($extensions[$mime]))throw new RuntimeException('El documento debe ser PDF, JPG, PNG o WebP.');
        if($mime==='application/pdf'&&file_get_contents($tmp,false,null,0,5)!=='%PDF-')throw new RuntimeException('El archivo no es un PDF válido.');
        if($mime!=='application/pdf'&&@getimagesize($tmp)===false)throw new RuntimeException('La imagen del documento no es válida.');
        return [bin2hex(random_bytes(24)).'.'.$extensions[$mime],substr(basename((string)($upload['name']??'documento.'.$extensions[$mime])),0,255),$mime,$size];
    }

    private function availableBusinessSlug(int $destinationId,string $name):string
    {
        $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$name);$base=strtolower((string)$ascii);$base=preg_replace('/[^a-z0-9]+/','-',trim($base))?:'negocio';$base=substr(trim($base,'-'),0,130)?:'negocio';
        $candidate=$base;$suffix=2;$check=$this->pdo->prepare('SELECT 1 FROM businesses WHERE destination_id=? AND slug=?');
        do{$check->execute([$destinationId,$candidate]);$exists=(bool)$check->fetchColumn();if($exists)$candidate=substr($base,0,130-strlen((string)$suffix)).'-'.$suffix++;}while($exists);
        return $candidate;
    }

    private function uuid():string
    {
        $hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-'.dechex((hexdec($hex[16])&3)|8).substr($hex,17,3).'-'.substr($hex,20,12);
    }
}

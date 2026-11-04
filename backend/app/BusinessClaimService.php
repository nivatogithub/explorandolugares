<?php
declare(strict_types=1);

require_once __DIR__.'/ContactPhone.php';
require_once __DIR__.'/BusinessClaimSelector.php';
require_once __DIR__.'/BusinessClaimWriter.php';
require_once __DIR__.'/BusinessClaimException.php';
final class BusinessClaimService
{
    public function __construct(private PDO $pdo) {}

    public function places(string $locale='es'): array
    {
        return (new BusinessClaimSelector($this->pdo))->places($locale);
    }

    public function categories(int $destinationId,string $locale='es'): array
    {
        return (new BusinessClaimSelector($this->pdo))->categories($destinationId,$locale);
    }

    public function search(int $destinationId,string $category,string $name='',string $locale='es'): array
    {
        return (new BusinessClaimSelector($this->pdo))->search($destinationId,$category,$name,$locale);
    }

    public function submit(array $input): int
    {
        $accountId=filter_var($_SESSION['business_account_id']??null,FILTER_VALIDATE_INT);
        if(!$accountId)throw new BusinessClaimException('account_required','Crea una cuenta empresarial o inicia sesión para solicitar la reclamación.');
        $account=(new BusinessClaimSelector($this->pdo))->activeAccount((int)$accountId);
        if(!$account)throw new BusinessClaimException('account_inactive','Inicia sesión con una cuenta empresarial activa.');
        $businessId=filter_var($input['business_id']??null,FILTER_VALIDATE_INT);
        $name=(string)$account['display_name'];
        $email=(string)$account['email'];
        try{$phone=ContactPhone::capture($input);}catch(RuntimeException $error){throw new BusinessClaimException('phone_invalid',$error->getMessage(),$error);}
        $relationship=(string)($input['relationship_code']??'');
        $message=trim((string)($input['message']??''));
        if(!$businessId||$name===''||strlen($name)>180)throw new BusinessClaimException('name_required','Completa tu nombre para continuar.');
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new BusinessClaimException('email_invalid','Revisa el correo electrónico.');
        if($phone!==''&&!preg_match('/^\\+?[0-9() .-]{7,24}$/D',$phone))throw new BusinessClaimException('phone_invalid','Revisa el número de teléfono.');
        if($email===''&&$phone==='')throw new BusinessClaimException('contact_required','Indica un correo o un teléfono para poder contactarte.');
        if(!in_array($relationship,['owner','authorized_representative','other'],true))throw new BusinessClaimException('relationship_required','Selecciona tu relación con el negocio.');
        if(strlen($message)>1000)throw new BusinessClaimException('message_too_long','El mensaje supera los 1.000 caracteres.');
        if(($input['consent']??'')!=='1')throw new BusinessClaimException('consent_required','Autoriza el uso de estos datos para revisar la solicitud.');
        if(!throttle('business_claim',4,3600,(string)$businessId))throw new BusinessClaimException('rate_limited','Se alcanzó el límite de solicitudes para este negocio. Inténtalo más tarde.');

        $this->pdo->beginTransaction();
        try{
            $row=(new BusinessClaimSelector($this->pdo))->lockBusiness((int)$businessId);
            if(!$row||$row['publication_status']!=='published'||$row['claim_status']!=='NO_RECLAMADO')throw new BusinessClaimException('business_unavailable','Este negocio ya no está disponible para reclamar. Actualiza la búsqueda.');
            $id=(new BusinessClaimWriter($this->pdo))->create((int)$businessId,(int)$accountId,$name,$email,$phone,$relationship,$message);
            $this->pdo->commit();return $id;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function requests(): array
    {
        return (new BusinessClaimSelector($this->pdo))->requests();
    }

    public function updateRequest(int $id,string $status,string $note=''): void
    {
        if(!in_array($status,['in_review','not_approved'],true))throw new RuntimeException('Estado de reclamación no válido.');
        $this->pdo->beginTransaction();
        try{
            $request=(new BusinessClaimSelector($this->pdo))->findRequest($id);
            if(!$request||!in_array($request['status'],['pending','in_review'],true))throw new RuntimeException('La solicitud ya fue cerrada o no existe.');
            $writer=new BusinessClaimWriter($this->pdo);
            $writer->updateReview($id,$status,substr(trim($note),0,1000));
            if($status==='not_approved')$writer->releaseBusiness((int)$request['business_id']);
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}

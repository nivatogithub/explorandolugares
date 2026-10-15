<?php
declare(strict_types=1);

final class AccountAdminService
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        $rows=$this->pdo->query('SELECT id,public_id,account_type,display_name,email,status,created_at FROM accounts ORDER BY created_at DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $q=$this->pdo->prepare('SELECT t.display_name FROM business_account_memberships m JOIN business_translations t ON t.business_id=m.business_id AND t.locale="es" WHERE m.account_id=? AND m.status="active" ORDER BY t.display_name');
        foreach($rows as &$row){$q->execute([$row['id']]);$row['businesses']=implode(', ',$q->fetchAll(PDO::FETCH_COLUMN));}unset($row);
        return $rows;
    }

    public function businesses(): array
    {
        return $this->pdo->query('SELECT b.id,t.display_name,d.slug AS destination_slug FROM businesses b
            JOIN business_translations t ON t.business_id=b.id AND t.locale="es"
            JOIN destinations d ON d.id=b.destination_id WHERE b.information_status="ACTIVO" AND b.claim_status="RECLAMADO" ORDER BY t.display_name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $input): int
    {
        $name=trim((string)($input['display_name']??''));$email=strtolower(trim((string)($input['email']??'')));
        $type=(string)($input['account_type']??'');$businessIds=array_values(array_unique(array_filter(array_map('intval',(array)($input['business_ids']??[])))));
        if(strlen($name)<2||strlen($name)>180)throw new RuntimeException('Ingresa el nombre de la persona o responsable.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)throw new RuntimeException('Ingresa un correo válido.');
        if(!in_array($type,['traveler','business'],true))throw new RuntimeException('Selecciona cuenta de viajero o de negocio.');
        if($type!=='business'&&$businessIds)throw new RuntimeException('Una cuenta viajera no puede administrar negocios.');
        if($type==='business'&&!$businessIds)throw new RuntimeException('Una cuenta empresarial debe quedar vinculada desde su creación a un negocio ya autorizado. Si aún se revisa una solicitud, usa el registro empresarial externo.');
        $role=(string)($input['membership_role']??'owner');if(!in_array($role,['owner','manager','editor'],true))throw new RuntimeException('Selecciona un permiso válido para la cuenta.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('INSERT INTO accounts(public_id,account_type,display_name,email,password_hash,status) VALUES(?,?,?,?,NULL,"pending_activation")');
            $q->execute([$this->uuid(),$type,$name,$email]);$id=(int)$this->pdo->lastInsertId();
            if($businessIds){$exists=$this->pdo->prepare('SELECT id FROM businesses WHERE id=? AND information_status="ACTIVO" AND claim_status="RECLAMADO"');$link=$this->pdo->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,?,"active")');
                foreach($businessIds as $businessId){$exists->execute([$businessId]);if(!$exists->fetchColumn())throw new RuntimeException('Uno de los negocios seleccionados ya no existe o no está reclamado y autorizado.');$link->execute([$id,$businessId,$role]);}}
            $this->pdo->commit();return $id;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function linkBusiness(array $input):void
    {
        $accountId=filter_var($input['account_id']??null,FILTER_VALIDATE_INT);$businessId=filter_var($input['business_id']??null,FILTER_VALIDATE_INT);$role=(string)($input['membership_role']??'owner');
        if(!$accountId||!$businessId||!in_array($role,['owner','manager','editor'],true))throw new RuntimeException('Selecciona una cuenta empresarial, un negocio aprobado y un permiso válido.');
        $q=$this->pdo->prepare('SELECT id FROM accounts WHERE id=? AND account_type="business" AND status IN ("pending_activation","active")');$q->execute([$accountId]);if(!$q->fetchColumn())throw new RuntimeException('La cuenta no está disponible o no es de tipo empresarial.');
        $q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=? AND information_status="ACTIVO" AND claim_status="RECLAMADO"');$q->execute([$businessId]);if(!$q->fetchColumn())throw new RuntimeException('El negocio debe estar reclamado y autorizado antes de vincularlo.');
        $q=$this->pdo->prepare('INSERT INTO business_account_memberships(account_id,business_id,role,status) VALUES(?,?,?,"active")');$q->execute([$accountId,$businessId,$role]);
    }

    private function uuid(): string
    {
        $hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-'.dechex((hexdec($hex[16])&3)|8).substr($hex,17,3).'-'.substr($hex,20,12);
    }
}

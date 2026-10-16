<?php
declare(strict_types=1);

/** People, their business relationships and their private channels; never grants account access. */
final class BusinessPersonContactService
{
    public const ROLES = [
        'owner'=>'Propietario/a',
        'legal_representative'=>'Representante legal',
        'administrator'=>'Administrador/a',
        'employee'=>'Empleado/a',
        'authorized_representative'=>'Representante autorizado/a',
        'other'=>'Otro vínculo',
    ];

    public function __construct(private PDO $pdo) {}

    public function ready(): bool
    {
        foreach (['people','business_person_contacts','business_person_contact_roles','person_channels','account_person_links'] as $table) {
            try { $this->pdo->query('SELECT * FROM '.$table.' LIMIT 0'); }
            catch (PDOException $error) {
                if (($error->errorInfo[0]??'')==='42S02'||str_contains($error->getMessage(),'no such table:')) return false;
                throw $error;
            }
        }
        return true;
    }

    public function forBusiness(int $businessId): array
    {
        $this->requireReady();
        $q=$this->pdo->prepare('SELECT c.*,p.public_id,p.display_name,
            (SELECT l.account_id FROM account_person_links l WHERE l.person_id=p.id) AS account_id
            FROM business_person_contacts c JOIN people p ON p.id=c.person_id
            WHERE c.business_id=? ORDER BY CASE c.status WHEN "active" THEN 0 ELSE 1 END,c.is_primary DESC,p.display_name,c.id');
        $q->execute([$businessId]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $roles=$this->pdo->prepare('SELECT role_code FROM business_person_contact_roles WHERE contact_id=? ORDER BY role_code');
        $channels=$this->pdo->prepare('SELECT id,channel,channel_value,business_id,source_type,source_url,source_note,suppressed
            FROM person_channels WHERE person_id=? AND (business_id IS NULL OR business_id=?) ORDER BY channel,id');
        foreach ($rows as &$row) {
            $roles->execute([$row['id']]);$row['roles']=$roles->fetchAll(PDO::FETCH_COLUMN);
            $channels->execute([$row['person_id'],$businessId]);$row['channels']=$channels->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($row);return $rows;
    }

    public function peopleOptions(string $name=''): array
    {
        $this->requireReady();
        $q=$this->pdo->prepare('SELECT id,display_name FROM people WHERE display_name LIKE ? ORDER BY display_name,id LIMIT 500');
        $q->execute(['%'.trim($name).'%']);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveContact(int $businessId,array $input): int
    {
        $this->requireReady();
        $roles=$input['roles']??[];
        if (!is_array($roles)||!array_is_list($roles)||!$roles) throw new RuntimeException('Selecciona al menos una relación de la persona con el negocio.');
        foreach ($roles as $role) if (!is_string($role)||!isset(self::ROLES[$role])) throw new RuntimeException('La relación con el negocio no es válida.');
        $roles=array_values(array_unique($roles));
        $personId=self::optionalId($input['person_id']??'');
        $name=self::text($input['display_name']??'',180);
        if (!$personId&&mb_strlen($name,'UTF-8')<2) throw new RuntimeException('Escribe el nombre de la persona identificada o selecciona una ya registrada.');
        $note=self::text($input['source_note']??'',1000);
        if ($note==='') throw new RuntimeException('Indica cómo conociste a esta persona o quién proporcionó sus datos.');
        $email=self::text($input['email']??'',254);$phone=self::text($input['phone']??'',40);
        $scope=(string)($input['channel_scope']??'business');
        if (!in_array($scope,['business','personal'],true)) throw new RuntimeException('Selecciona a qué ámbito pertenecen los canales de la persona.');
        // Validate all proposed channels before creating a person or relationship.
        if ($email!=='') self::validateChannel('email',$email);
        if ($phone!=='') self::validateChannel('phone',$phone);
        return $this->atomic(function() use ($businessId,$input,$personId,$name,$note,$roles,$email,$phone,$scope): int {
            $this->lockBusiness($businessId);
            if ($personId) $this->requirePerson($personId);
            else $personId=$this->createPerson($name,'manual_admin');
            $contactId=$this->upsertContact($businessId,$personId,$roles,($input['is_primary']??'')==='1','manual_admin',$note,false);
            $context=$scope==='business'?$businessId:null;
            if ($email!=='') $this->addChannel($personId,$context,'email',$email,'manual_admin',$note);
            if ($phone!=='') $this->addChannel($personId,$context,'phone',$phone,'manual_admin',$note);
            return $contactId;
        });
    }

    public function deactivateContact(int $businessId,int $contactId): void
    {
        $this->requireReady();
        $this->atomic(function() use ($businessId,$contactId): void {
            $this->lockBusiness($businessId);
            $q=$this->pdo->prepare('SELECT id,is_primary FROM business_person_contacts WHERE id=? AND business_id=? AND status="active"');
            $q->execute([$contactId,$businessId]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('El contacto activo no pertenece a este negocio.');
            $this->pdo->prepare('UPDATE business_person_contacts SET status="inactive",is_primary=0,ended_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$contactId]);
            if ($row['is_primary']) {
                $q=$this->pdo->prepare('SELECT id FROM business_person_contacts WHERE business_id=? AND status="active" ORDER BY id LIMIT 1');
                $q->execute([$businessId]);$next=$q->fetchColumn();
                if ($next!==false) $this->pdo->prepare('UPDATE business_person_contacts SET is_primary=1 WHERE id=?')->execute([$next]);
            }
        });
    }

    /** Called only with the name supplied in the account form, not a scraped business name. */
    public function ensureAccountPerson(int $accountId): int
    {
        $this->requireReady();
        return $this->atomic(function() use ($accountId): int {
            $lock=$this->lockSuffix();
            $q=$this->pdo->prepare('SELECT id,display_name FROM accounts WHERE id=?'.$lock);
            $q->execute([$accountId]);$account=$q->fetch(PDO::FETCH_ASSOC);
            if (!$account) throw new RuntimeException('La cuenta no existe.');
            $q=$this->pdo->prepare('SELECT person_id FROM account_person_links WHERE account_id=?');
            $q->execute([$accountId]);$id=$q->fetchColumn();
            if ($id!==false) return (int)$id;
            $name=self::text($account['display_name'],180);
            if (mb_strlen($name,'UTF-8')<2) throw new RuntimeException('Completa el nombre del responsable antes de asociar su persona.');
            $personId=$this->createPerson($name,'account_registration');
            $this->pdo->prepare('INSERT INTO account_person_links(account_id,person_id) VALUES(?,?)')->execute([$accountId,$personId]);
            return $personId;
        });
    }

    /** An approved application supplies the person and role; this method does not create memberships. */
    public function syncApprovedApplication(int $applicationId): int
    {
        $this->requireReady();
        return $this->atomic(function() use ($applicationId): int {
            $q=$this->pdo->prepare('SELECT r.account_id,r.business_id,r.relationship_code,r.contact_phone,a.email
                FROM business_account_applications r JOIN accounts a ON a.id=r.account_id WHERE r.id=? AND r.status="approved"');
            $q->execute([$applicationId]);$application=$q->fetch(PDO::FETCH_ASSOC);
            if (!$application||!(int)$application['business_id']) throw new RuntimeException('La persona debe proceder de una solicitud de negocio aprobada.');
            $businessId=(int)$application['business_id'];$this->lockBusiness($businessId);
            $personId=$this->ensureAccountPerson((int)$application['account_id']);
            $role=match ($application['relationship_code']) {
                'owner'=>'owner','legal_representative'=>'legal_representative',
                'authorized_administrator'=>'administrator','authorized_representative'=>'authorized_representative',
                default=>'other',
            };
            $note='Datos facilitados por el responsable en la solicitud aprobada '.$applicationId.'.';
            $contactId=$this->upsertContact($businessId,$personId,[$role],false,'approved_application',$note,true);
            // A login email is recorded as a private means of reaching this person for this business.
            // It never becomes a general business channel or a marketing authorization.
            $this->addChannel($personId,$businessId,'email',(string)$application['email'],'approved_application',$note);
            if ($application['contact_phone']!=='') $this->addChannel($personId,$businessId,'phone',(string)$application['contact_phone'],'approved_application',$note);
            return $contactId;
        });
    }

    /** Explicit admin reconciliation for accounts approved before this model existed. */
    public function syncMissingApprovedPeople(int $businessId): int
    {
        $this->requireReady();
        return $this->atomic(function() use ($businessId): int {
            $this->lockBusiness($businessId);
            $q=$this->pdo->prepare('SELECT r.id FROM business_account_applications r
                WHERE r.business_id=? AND r.status="approved"
                AND EXISTS(SELECT 1 FROM business_account_memberships m WHERE m.business_id=r.business_id AND m.account_id=r.account_id AND m.status="active")
                AND NOT EXISTS(SELECT 1 FROM account_person_links l JOIN business_person_contacts c ON c.person_id=l.person_id AND c.business_id=r.business_id WHERE l.account_id=r.account_id)
                ORDER BY r.id');
            $q->execute([$businessId]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);$contacts=[];
            foreach ($ids as $id) $contacts[$this->syncApprovedApplication((int)$id)]=true;
            return count($contacts);
        });
    }

    private function upsertContact(int $businessId,int $personId,array $roles,bool $primary,string $source,string $note,bool $mergeRoles): int
    {
        $q=$this->pdo->prepare('SELECT id,is_primary FROM business_person_contacts WHERE business_id=? AND person_id=?');
        $q->execute([$businessId,$personId]);$found=$q->fetch(PDO::FETCH_ASSOC);
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM business_person_contacts WHERE business_id=? AND status="active" AND is_primary=1');
        $q->execute([$businessId]);$primary=$primary||($found&&(bool)$found['is_primary'])||!(int)$q->fetchColumn();
        if ($primary) $this->pdo->prepare('UPDATE business_person_contacts SET is_primary=0 WHERE business_id=?')->execute([$businessId]);
        if ($found===false) {
            $this->pdo->prepare('INSERT INTO business_person_contacts(business_id,person_id,is_primary,source_type,source_note) VALUES(?,?,?,?,?)')->execute([$businessId,$personId,$primary?1:0,$source,$note]);
            $contactId=(int)$this->pdo->lastInsertId();
        } else {
            $contactId=(int)$found['id'];
            $this->pdo->prepare('UPDATE business_person_contacts SET status="active",is_primary=?,source_type=?,source_note=?,ended_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$primary?1:0,$source,$note,$contactId]);
        }
        if (!$mergeRoles) $this->pdo->prepare('DELETE FROM business_person_contact_roles WHERE contact_id=?')->execute([$contactId]);
        $existing=$this->pdo->prepare('SELECT role_code FROM business_person_contact_roles WHERE contact_id=?');$existing->execute([$contactId]);
        $current=$existing->fetchAll(PDO::FETCH_COLUMN);
        $insert=$this->pdo->prepare('INSERT INTO business_person_contact_roles(contact_id,role_code) VALUES(?,?)');
        foreach ($roles as $role) if (!in_array($role,$current,true)) $insert->execute([$contactId,$role]);
        return $contactId;
    }

    private function addChannel(int $personId,?int $businessId,string $channel,string $value,string $source,string $note): void
    {
        $value=trim($value);if ($channel==='email') $value=strtolower($value);
        self::validateChannel($channel,$value);
        $identity=in_array($channel,['phone','phone_fixed','whatsapp'],true)?preg_replace('/\D/','',$value):$value;
        $hash=hash('sha256',json_encode([$businessId,$channel,$identity],JSON_THROW_ON_ERROR));
        $q=$this->pdo->prepare('SELECT id FROM person_channels WHERE person_id=? AND channel_hash=?');$q->execute([$personId,$hash]);
        if ($q->fetchColumn()!==false) return; // Preserve any later suppression or consent decision.
        $this->pdo->prepare('INSERT INTO person_channels(person_id,business_id,channel,channel_value,channel_hash,source_type,source_note) VALUES(?,?,?,?,?,?,?)')->execute([$personId,$businessId,$channel,$value,$hash,$source,$note]);
    }

    private static function validateChannel(string $channel,string $value): void
    {
        $valid=match ($channel) {
            'email'=>strlen($value)<=254&&(bool)filter_var($value,FILTER_VALIDATE_EMAIL),
            'phone','phone_fixed','whatsapp'=>strlen($value)<=40&&(bool)preg_match('/^\+?[0-9() .-]{7,39}$/D',$value)&&strlen(preg_replace('/\D/','',$value)??'')>=7,
            default=>false,
        };
        if (!$valid) throw new RuntimeException('Revisa el correo o teléfono de la persona.');
    }

    private function createPerson(string $name,string $origin): int
    {
        $hex=bin2hex(random_bytes(16));$uuid=substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-'.dechex((hexdec($hex[16])&3)|8).substr($hex,17,3).'-'.substr($hex,20,12);
        $this->pdo->prepare('INSERT INTO people(public_id,display_name,record_origin) VALUES(?,?,?)')->execute([$uuid,$name,$origin]);
        return (int)$this->pdo->lastInsertId();
    }

    private function lockBusiness(int $id): void
    {
        $q=$this->pdo->prepare('SELECT id FROM businesses WHERE id=?'.$this->lockSuffix());$q->execute([$id]);
        if ($id<1||$q->fetchColumn()===false) throw new RuntimeException('Selecciona un negocio existente.');
    }

    private function requirePerson(int $id): void
    {
        $q=$this->pdo->prepare('SELECT id FROM people WHERE id=?'.$this->lockSuffix());$q->execute([$id]);
        if ($q->fetchColumn()===false) throw new RuntimeException('La persona seleccionada no existe.');
    }

    private function lockSuffix(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''; }
    private function requireReady(): void { if (!$this->ready()) throw new RuntimeException('Falta habilitar la estructura de personas y contactos en Base de datos (028).'); }

    private static function text(mixed $value,int $max): string
    {
        if (!is_string($value)||strlen($value)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value)) throw new RuntimeException('Un dato de la persona tiene un formato o longitud no válido.');
        return trim($value);
    }

    private static function optionalId(mixed $value): int
    {
        if ($value===''||$value===null||$value===0||$value==='0') return 0;
        $id=filter_var($value,FILTER_VALIDATE_INT);
        if (!$id||$id<1) throw new RuntimeException('Selecciona una persona válida.');
        return (int)$id;
    }

    private function atomic(callable $operation): mixed
    {
        $own=!$this->pdo->inTransaction();if ($own) $this->pdo->beginTransaction();
        try { $result=$operation();if ($own) $this->pdo->commit();return $result; }
        catch (Throwable $error) { if ($own&&$this->pdo->inTransaction()) $this->pdo->rollBack();throw $error; }
    }
}

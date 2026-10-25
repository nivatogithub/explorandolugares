<?php
declare(strict_types=1);

/** Private, account-owned capture state. Not a business, application or publication. */
final class BusinessRegistrationDrafts
{
    private const FIELDS=['application_type','business_id','business_name','destination_id','category_code','category_codes',
        'plan_code','billing_frequency','promotion_code','summary','business_nit','business_rnt','address_text','location_reference',
        'latitude','longitude','location_map_url','location_confirmed','location_map_changed','business_phone','channels',
        'products','temporary_uploads','distinct_business','distinct_address','relationship_code','responsibility_declared',
        'consent','operator_experience_id'];
    public function __construct(private PDO $pdo) {}
    public function ready():bool
    {
        try{$this->pdo->query('SELECT id FROM business_registration_drafts LIMIT 0');return true;}
        catch(PDOException $e){if(str_contains($e->getMessage(),'no such table')||($e->errorInfo[1]??0)===1146)return false;throw $e;}
    }
    private function account(int $account):void
    {
        $q=$this->pdo->prepare('SELECT id FROM accounts WHERE id=? AND status="active" AND email_verified_at IS NOT NULL');
        $q->execute([$account]);if(!$q->fetchColumn())throw new RuntimeException('Verifica tu cuenta antes de guardar el negocio.');
    }
    public function load(int $account,string $id):array
    {
        if(!preg_match('/^[a-f0-9]{48}$/D',$id))throw new RuntimeException('Borrador no disponible.');
        $q=$this->pdo->prepare('SELECT * FROM business_registration_drafts WHERE id=? AND account_id=? AND status="editing"');
        $q->execute([$id,$account]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Este borrador no está disponible para tu cuenta.');
        $row['payload']=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);
        return $row;
    }
    private function cleanValue(mixed $value,int $depth=0):mixed
    {
        if($depth>4)throw new RuntimeException('El borrador tiene una estructura no válida.');
        if(is_array($value)){
            if(count($value)>40)throw new RuntimeException('El borrador tiene demasiados campos.');
            $result=[];foreach($value as $key=>$entry){if(!preg_match('/^[a-zA-Z0-9_-]{1,50}$/D',(string)$key))throw new RuntimeException('Campo no válido.');$result[$key]=$this->cleanValue($entry,$depth+1);}return $result;
        }
        if(!is_scalar($value)&&$value!==null)throw new RuntimeException('Campo no válido.');
        $value=(string)$value;if(strlen($value)>12000||!mb_check_encoding($value,'UTF-8')||str_contains($value,"\0"))throw new RuntimeException('El campo es demasiado largo o no válido.');
        return $value;
    }
    public function save(int $account,string $id,int $revision,int $step,array $input):array
    {
        $this->account($account);
        if(!preg_match('/^[a-f0-9]{48}$/D',$id)||$step<1||$step>6)throw new RuntimeException('Borrador no válido.');
        $payload=$this->cleanValue(array_intersect_key($input,array_flip(self::FIELDS)));
        foreach($payload as $key=>$value){$isArray=in_array($key,['category_codes','business_phone','channels','products','temporary_uploads'],true);if($isArray!==is_array($value))throw new RuntimeException('El campo '.$key.' no tiene un formato válido.');}
        if(strlen(json_encode($payload,JSON_THROW_ON_ERROR))>90000)throw new RuntimeException('El borrador es demasiado grande.');
        if(!in_array($payload['application_type']??'',['new_business','existing_business'],true))throw new RuntimeException('Selecciona el negocio antes de continuar.');
        $place=$this->pdo->prepare('SELECT 1 FROM destinations WHERE id=? AND publication_status="published"');$place->execute([(int)($payload['destination_id']??0)]);
        if(!$place->fetchColumn())throw new RuntimeException('Selecciona un lugar publicado.');
        if(($payload['application_type']??'')==='existing_business'){
            $q=$this->pdo->prepare('SELECT t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.id=? AND b.destination_id=?');
            $q->execute([(int)($payload['business_id']??0),(int)$payload['destination_id']]);$name=$q->fetchColumn();
            if(!$name)throw new RuntimeException('El negocio no corresponde al lugar elegido.');$payload['business_name']=$name;
        }elseif(trim((string)($payload['business_name']??''))==='')throw new RuntimeException('Escribe el nombre del negocio.');
        $uploads=[];foreach((array)($payload['temporary_uploads']??[]) as $kind=>$slots){
            if(!in_array($kind,['editorial','evidence'],true)||!is_array($slots))throw new RuntimeException('Archivos no válidos.');
            foreach($slots as $slot=>$upload){
                $q=$this->pdo->prepare('SELECT id FROM business_onboarding_uploads WHERE id=? AND draft_token=? AND upload_kind=? AND slot_code=? AND consumed_at IS NULL AND expires_at>CURRENT_TIMESTAMP');
                $q->execute([$upload,$id,$kind,$slot]);if(!$q->fetchColumn())throw new RuntimeException('Un archivo ya no está disponible. Vuelve a cargarlo.');$uploads[]=$upload;
            }
        }
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT account_id,revision,status FROM business_registration_drafts WHERE id=?'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));
            $q->execute([$id]);$existing=$q->fetch(PDO::FETCH_ASSOC);
            if($existing&&((int)$existing['account_id']!==$account||$existing['status']!=='editing'))throw new RuntimeException('Este borrador ya no está disponible.');
            if((int)($existing['revision']??0)!==$revision)throw new RuntimeException('El borrador cambió en otra pestaña. Vuelve a Mis negocios para abrir la última versión.');
            $json=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            if($existing)$this->pdo->prepare('UPDATE business_registration_drafts SET payload_json=?,wizard_step=?,revision=revision+1,updated_at=CURRENT_TIMESTAMP WHERE id=? AND account_id=?')->execute([$json,$step,$id,$account]);
            else $this->pdo->prepare('INSERT INTO business_registration_drafts(id,account_id,payload_json,wizard_step) VALUES(?,?,?,?)')->execute([$id,$account,$json,$step]);
            // Saved capture files remain private and recoverable for 30 days.
            $extend=$this->pdo->prepare('UPDATE business_onboarding_uploads SET expires_at=? WHERE id=? AND draft_token=? AND consumed_at IS NULL');
            foreach($uploads as $upload)$extend->execute([gmdate('Y-m-d H:i:s',time()+30*86400),$upload,$id]);
            $this->pdo->commit();return ['id'=>$id,'revision'=>$revision+1,'step'=>$step];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function finish(int $account,string $id,?int $business):void
    {
        // Retain the capture history; never recreate a submitted business on resume.
        $this->pdo->prepare('UPDATE business_registration_drafts SET status="submitted",business_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND account_id=? AND status="editing"')->execute([$business?:null,$id,$account]);
    }
    public function uploads(string $id):array
    {
        $q=$this->pdo->prepare('SELECT id,upload_kind,slot_code,original_name FROM business_onboarding_uploads WHERE draft_token=? AND consumed_at IS NULL AND expires_at>CURRENT_TIMESTAMP');$q->execute([$id]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listing(int $account):array
    {
        if(!$this->ready())return [];
        // Resolve the small private listing without relying on engine-specific JSON SQL.
        $q=$this->pdo->prepare('SELECT * FROM business_registration_drafts WHERE account_id=? AND status="editing" ORDER BY updated_at DESC');$q->execute([$account]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);$places=$this->pdo->query('SELECT destination_id,display_name FROM destination_translations WHERE locale="es"')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($rows as &$row){$row['payload']=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);$row['destination_name']=$places[(int)($row['payload']['destination_id']??0)]??'Por elegir';}unset($row);
        return $rows;
    }
}

<?php
declare(strict_types=1);
require_once __DIR__.'/LeadReplyTemplate.php';
require_once __DIR__.'/BrevoTransactionalClient.php';
final class LeadReplyService
{
    public function __construct(private PDO $pdo){}
    public function find(string $id,int $lead):?array
    {
        $q=$this->pdo->prepare('SELECT * FROM lead_reply_operations WHERE id=? AND lead_id=?');$q->execute([$id,$lead]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function history(int $lead):array
    {
        $q=$this->pdo->prepare('SELECT * FROM lead_reply_operations WHERE lead_id=? ORDER BY created_at DESC,id');$q->execute([$lead]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function prepare(int $lead,array $input,array $settings,string $actor,?string $id=null):array
    {
        $actor=self::actor($actor);
        $q=$this->pdo->prepare('SELECT email,wants_contact FROM leads WHERE id=?');$q->execute([$lead]);$request=$q->fetch(PDO::FETCH_ASSOC);
        if(!$request)throw new RuntimeException('La solicitud no existe.');
        if(!(bool)$request['wants_contact'])throw new RuntimeException('La solicitud no autoriza contacto. No se preparó un envío.');
        if($id===null){$pending=$this->pdo->prepare('SELECT COUNT(*) FROM lead_reply_operations WHERE lead_id=? AND status IN ("sending","uncertain")');$pending->execute([$lead]);if((int)$pending->fetchColumn()>0)throw new RuntimeException('Hay un envío pendiente de reconciliar. No prepares otro para reintentarlo.');}
        $recipient=trim((string)($input['recipient']??$request['email']));$subject=trim((string)($input['subject']??''));$message=trim((string)($input['message']??''));
        $sender=trim((string)($settings['sender_email']??''));$name=trim((string)($settings['sender_name']??''));$reply=trim((string)($settings['reply_to']??''))?:$sender;
        foreach([$recipient,$sender,$reply] as $email)if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Revisa destinatario, remitente y correo de respuesta.');
        if($subject===''||mb_strlen($subject)>200||preg_match('/[\r\n\x00]/',$subject)||$message===''||mb_strlen($message)>5000||$name===''||mb_strlen($name)>180||preg_match('/[\r\n\x00]/',$name))throw new RuntimeException('Completa asunto, mensaje y nombre del remitente dentro de sus límites.');
        $offers=$this->pdo->query('SELECT t.name,p.amount FROM subscription_plan_prices p JOIN subscription_plan_translations t ON t.plan_code=p.plan_code AND t.locale="es" WHERE p.billing_frequency="monthly" AND p.plan_code IN ("featured","page","page_premium") ORDER BY p.amount')->fetchAll(PDO::FETCH_ASSOC);
        $body=LeadReplyTemplate::render($message,$offers);
        $fields=['recipient'=>$recipient,'sender_email'=>$sender,'sender_name'=>$name,'reply_to'=>$reply,'subject'=>$subject,'message_text'=>$message,'html_body'=>$body['html'],'text_body'=>$body['text'],'template_version'=>LeadReplyTemplate::VERSION];
        $fields['content_hash']=self::hash($fields);
        if($id===null){$id=bin2hex(random_bytes(16));$q=$this->pdo->prepare('INSERT INTO lead_reply_operations(id,lead_id,'.implode(',',array_keys($fields)).',created_by) VALUES('.implode(',',array_fill(0,count($fields)+3,'?')).')');$q->execute([$id,$lead,...array_values($fields),$actor]);}
        else{$q=$this->pdo->prepare('UPDATE lead_reply_operations SET '.implode(',',array_map(static fn($key)=>$key.'=?',array_keys($fields))).',updated_at=CURRENT_TIMESTAMP WHERE id=? AND lead_id=? AND status="draft"');$q->execute([...array_values($fields),$id,$lead]);$current=$this->find($id,$lead);if(!$current||$current['status']!=='draft'||$current['content_hash']!==$fields['content_hash'])throw new RuntimeException('Esta respuesta ya no es un borrador editable.');}
        return $this->find($id,$lead);
    }
    public function reconcile(int $lead,string $id,string $outcome,string $reference,string $note,string $actor):void
    {
        $actor=self::actor($actor);
        if(!in_array($outcome,['accepted','failed'],true)||mb_strlen(trim($note))<30||mb_strlen($note)>2000||strlen(trim($reference))<5||strlen($reference)>500)throw new RuntimeException('Registra el resultado comprobado, la referencia del proveedor y una explicación de al menos 30 caracteres.');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT * FROM lead_reply_operations WHERE id=? AND lead_id=?'.$lock);$q->execute([$id,$lead]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||!in_array($row['status'],['sending','uncertain'],true))throw new RuntimeException('La operación no requiere reconciliación.');
            if($row['status']==='sending'&&strtotime($row['claimed_at'].' UTC')>time()-300)throw new RuntimeException('Espera cinco minutos antes de reconciliar una operación que puede seguir en curso.');
            $this->pdo->prepare('UPDATE lead_reply_operations SET status=?,reconciled_at=CURRENT_TIMESTAMP,reconciled_by=?,reconciliation_note=?,result_code=?,provider_message_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$outcome,$actor,trim($note).' Referencia: '.trim($reference),'manually_reconciled_'.$outcome,$outcome==='accepted'?trim($reference):null,$id]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private static function actor(string $actor):string{if(trim($actor)===''||mb_strlen($actor)>190)throw new InvalidArgumentException('No se pudo identificar al administrador.');return $actor;}

    private static function hash(array $fields):string
    {
        $snapshot=[];foreach(['recipient','sender_email','sender_name','reply_to','subject','message_text','html_body','text_body','template_version'] as $key)$snapshot[$key]=(string)$fields[$key];
        return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR));
    }
    public function send(int $lead,string $id,string $confirmedHash,array $settings,string $actor,BrevoTransactionalClient $client):string
    {
        $actor=self::actor($actor);
        if(!$client->ready())throw new RuntimeException('Configura y habilita Brevo antes de enviar.');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT wants_contact FROM leads WHERE id=?'.$lock);$q->execute([$lead]);if(!(bool)$q->fetchColumn())throw new RuntimeException('La solicitud ya no permite contacto.');
            $q=$this->pdo->prepare('SELECT * FROM lead_reply_operations WHERE id=? AND lead_id=?'.$lock);$q->execute([$id,$lead]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('La respuesta no existe.');
            if($row['status']!=='draft'){$this->pdo->commit();return $row['status'];}
            $pending=$this->pdo->prepare('SELECT COUNT(*) FROM lead_reply_operations WHERE lead_id=? AND id<>? AND status IN ("sending","uncertain")');$pending->execute([$lead,$id]);if((int)$pending->fetchColumn()>0)throw new RuntimeException('Hay otra respuesta pendiente de reconciliar.');
            if(!hash_equals($row['content_hash'],$confirmedHash)||!hash_equals(self::hash($row),$confirmedHash))throw new RuntimeException('La respuesta cambió. Revisa la nueva vista previa antes de confirmar.');
            foreach(['sender_email','sender_name','reply_to'] as $field){$actual=(string)($settings[$field]??'');if($field==='reply_to'&&$actual==='')$actual=(string)($settings['sender_email']??'');if($actual!==$row[$field])throw new RuntimeException('Cambió la configuración del remitente. Guarda y revisa un nuevo borrador.');}
            $q=$this->pdo->prepare('UPDATE lead_reply_operations SET status="sending",claimed_at=CURRENT_TIMESTAMP,confirmed_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status="draft"');$q->execute([$actor,$id]);
            if($q->rowCount()!==1)throw new RuntimeException('Otra sesión ya está procesando la respuesta.');
            $this->pdo->prepare('INSERT INTO lead_reply_attempts(operation_id) VALUES(?)')->execute([$id]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $outcome='uncertain';$http=0;$providerId=null;$code='transport_uncertain';
        try{
            $response=$client->send(['sender'=>['email'=>$row['sender_email'],'name'=>$row['sender_name']],'replyTo'=>['email'=>$row['reply_to']],'to'=>[['email'=>$row['recipient']]],'subject'=>$row['subject'],'htmlContent'=>$row['html_body'],'textContent'=>$row['text_body'],'headers'=>['X-EL-Operation'=>$id]]);
            $http=(int)($response['status']??0);
            if($http===201&&!empty($response['body']['messageId'])){$outcome='accepted';$providerId=substr((string)$response['body']['messageId'],0,500);$code='provider_accepted';}
            elseif($http>=400&&$http<500){$outcome='failed';$code='provider_rejected';}
        }catch(Throwable){/* An exception after transport may mean the provider accepted it. Never retry automatically. */}
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('UPDATE lead_reply_operations SET status=?,completed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP,provider_message_id=?,result_code=? WHERE id=? AND status="sending"')->execute([$outcome,$providerId,$code,$id]);
            $this->pdo->prepare('UPDATE lead_reply_attempts SET outcome=?,http_status=?,completed_at=CURRENT_TIMESTAMP WHERE operation_id=?')->execute([$outcome,$http,$id]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw new RuntimeException('No se pudo registrar el resultado. No repitas el envío: reconcilia la operación con el proveedor.',0,$e);}
        return $outcome;
    }
}

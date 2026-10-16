<?php
declare(strict_types=1);
require_once __DIR__.'/BrevoCampaignClient.php';

final class BusinessEmailCampaigns
{
    public function __construct(private PDO $db,private string $actor) {}
    private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    public function ready(): bool {try{$this->db->query('SELECT content_hash FROM business_email_campaigns LIMIT 0');return true;}catch(PDOException){return false;}}
    private function audit(?int $id,string $action,string $detail=''):void {$this->query('INSERT INTO business_email_audit(campaign_id,actor,action,detail) VALUES(?,?,?,?)',[$id,$this->actor,$action,$detail]);}

    /** One candidate per business and normalized email; sources stay on the original channel. */
    public function audience(string $destination=''):array
    {
        $args=[];$where='';if($destination!==''){$where=' AND d.slug=?';$args[]=$destination;}
        $rows=$this->query('SELECT MIN(r.id) AS channel_id,b.id AS business_id,t.display_name AS business_name,d.slug AS destination_slug,LOWER(TRIM(r.contact_value)) AS email,MAX(r.suppressed) AS suppressed,MIN(r.source_url) AS source_url
            FROM business_research_contacts r JOIN businesses b ON b.id=r.business_id JOIN destinations d ON d.id=b.destination_id JOIN business_translations t ON t.business_id=b.id AND t.locale=\'es\'
            WHERE r.channel=\'email\''.$where.' GROUP BY b.id,t.display_name,d.slug,LOWER(TRIM(r.contact_value)) ORDER BY t.display_name,email',$args)->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){$permission=$this->query('SELECT decision,evidence FROM business_email_permissions WHERE email=?',[$row['email']])->fetch(PDO::FETCH_ASSOC);$row['decision']=$permission['decision']??'needs_review';$row['evidence']=$permission['evidence']??'';$row['eligible']=filter_var($row['email'],FILTER_VALIDATE_EMAIL)!==false && !$row['suppressed'] && $row['decision']==='allowed';}
        unset($row);return $rows;
    }

    public function permission(int $channelId,string $decision,string $evidence,bool $confirmed): void
    {
        if(!in_array($decision,['allowed','needs_review','suppressed'],true))throw new RuntimeException('Elige el estado del correo.');
        $evidence=trim($evidence);if(strlen($evidence)<10||strlen($evidence)>2000)throw new RuntimeException('Registra una referencia o explicación de entre 10 y 2.000 caracteres.');
        if($decision==='allowed'&&!$confirmed)throw new RuntimeException('Confirma que cuentas con autorización para campañas; una fuente pública no la acredita.');
        $r=$this->query('SELECT contact_value FROM business_research_contacts WHERE id=? AND channel=\'email\'',[$channelId])->fetch(PDO::FETCH_ASSOC);
        if(!$r)throw new RuntimeException('No se encontró ese canal de correo.');$email=strtolower(trim($r['contact_value']));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Corrige primero el correo del negocio.');
        $this->db->beginTransaction();try{
            // Suppression is persistent; an ordinary permission edit cannot erase a previous opt-out.
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $old=$this->query('SELECT decision FROM business_email_permissions WHERE email=?'.$lock,[$email])->fetchColumn();
            if($old==='suppressed'&&$decision!=='suppressed')throw new RuntimeException('Este correo está excluido. Una nueva autorización requiere revisión separada; no se reactivó.');
            $sql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                ?'INSERT INTO business_email_permissions(email,decision,evidence,actor) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE decision=CASE WHEN decision=\'suppressed\' THEN decision ELSE VALUES(decision) END,evidence=VALUES(evidence),actor=VALUES(actor),updated_at=CURRENT_TIMESTAMP'
                :'INSERT INTO business_email_permissions(email,decision,evidence,actor) VALUES(?,?,?,?) ON CONFLICT(email) DO UPDATE SET decision=CASE WHEN business_email_permissions.decision=\'suppressed\' THEN business_email_permissions.decision ELSE excluded.decision END,evidence=excluded.evidence,actor=excluded.actor,updated_at=CURRENT_TIMESTAMP';
            $this->query($sql,[$email,$decision,$evidence,$this->actor]);if($this->query('SELECT decision FROM business_email_permissions WHERE email=?',[$email])->fetchColumn()!==$decision)throw new RuntimeException('Este correo fue excluido durante la revisión; no se reactivó.');$this->audit(null,'permission',json_encode(['channel_id'=>$channelId,'decision'=>$decision,'evidence'=>$evidence],JSON_THROW_ON_ERROR));$this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public static function sender(array $settings):array {return array_intersect_key($settings,array_flip(['sender_email','sender_name','reply_to','sender_address']));}
    private static function digest(array $campaign): string {return hash('sha256',json_encode(array_intersect_key($campaign,array_flip(['name','subject','body','action_url','recipients_json','sender_json'])),JSON_THROW_ON_ERROR));}
    public function create(array $input,array $channelIds,array $settings):int
    {
        $c=[];foreach(['name'=>180,'subject'=>180,'body'=>15000,'action_url'=>1000] as $key=>$max){$c[$key]=is_string($input[$key]??null)?trim($input[$key]):'';if(strlen($c[$key])>$max||($key!=='action_url'&&$c[$key]===''))throw new RuntimeException('Completa el nombre, asunto y mensaje dentro de los límites indicados.');}
        if(preg_match('/[\r\n\x00]/',$c['subject']))throw new RuntimeException('El asunto debe ocupar una sola línea.');
        if($c['action_url']!==''&&(!filter_var($c['action_url'],FILTER_VALIDATE_URL)||parse_url($c['action_url'],PHP_URL_SCHEME)!=='https'||parse_url($c['action_url'],PHP_URL_USER)!==null))throw new RuntimeException('El enlace de la campaña debe usar HTTPS y no contener credenciales.');
        $ids=array_values(array_unique(array_map('intval',$channelIds)));if(!$ids||count($ids)>300)throw new RuntimeException('Selecciona entre 1 y 300 correos habilitados.');
        $available=[];foreach($this->audience() as $r)$available[(int)$r['channel_id']]=$r;
        $selected=[];$businesses=[];
        foreach($ids as $id){$r=$available[$id]??null;if(!$r||!$r['eligible'])throw new RuntimeException('Hay correos pendientes de revisión o excluidos. Revisa la audiencia.');if(isset($businesses[$r['business_id']]))throw new RuntimeException('Selecciona un solo correo por negocio en la misma campaña.');$businesses[$r['business_id']]=true;
            if(isset($selected[$r['email']])){$selected[$r['email']]['business_ids'][]=(int)$r['business_id'];$selected[$r['email']]['business_name'].=' / '.$r['business_name'];}
            else $selected[$r['email']]=['channel_id'=>(int)$r['channel_id'],'business_ids'=>[(int)$r['business_id']],'business_name'=>$r['business_name'],'email'=>$r['email']];
        }
        $c['recipients_json']=json_encode(array_values($selected),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);$c['sender_json']=json_encode(self::sender($settings),JSON_THROW_ON_ERROR);$hash=self::digest($c);
        $this->db->beginTransaction();try{$this->query('INSERT INTO business_email_campaigns(name,subject,body,action_url,recipients_json,sender_json,content_hash) VALUES(?,?,?,?,?,?,?)',[...array_values($c),$hash]);$id=(int)$this->db->lastInsertId();$this->audit($id,'create');$this->db->commit();return $id;}catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function all():array {return $this->query('SELECT id,name,subject,status,created_at FROM business_email_campaigns ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);}
    public function get(int $id):array {$c=$this->query('SELECT * FROM business_email_campaigns WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);if(!$c)throw new RuntimeException('No se encontró la campaña.');return $c;}
    private function validate(array $c,array $settings):void
    {
        if(!hash_equals($c['content_hash'],self::digest($c)))throw new RuntimeException('El contenido cambió; crea un borrador nuevo y revísalo.');
        if(self::sender($settings)!==json_decode($c['sender_json'],true))throw new RuntimeException('El remitente cambió. Crea un borrador con los datos actuales.');
        $available=[];foreach($this->audience() as $r)$available[$r['business_id'].'|'.$r['email']]=$r;
        foreach(json_decode($c['recipients_json'],true,32,JSON_THROW_ON_ERROR) as $recipient)foreach($recipient['business_ids'] as $businessId){$current=$available[$businessId.'|'.$recipient['email']]??null;if(!$current||!$current['eligible'])throw new RuntimeException('Un destinatario cambió o dejó de estar habilitado. Crea una campaña con la audiencia actual.');}
    }
    public function approve(int $id,string $hash,array $settings):void
    {
        (new BrevoCampaignClient($settings,static fn()=>[]))->assertReady();$c=$this->get($id);$this->validate($c,$settings);
        if(!hash_equals($c['content_hash'],$hash))throw new RuntimeException('Recarga la revisión de la campaña.');
        $this->db->beginTransaction();try{$q=$this->query('UPDATE business_email_campaigns SET status=\'approved\',approved_by=?,approved_at=CURRENT_TIMESTAMP WHERE id=? AND status=\'draft\' AND content_hash=?',[$this->actor,$id,$hash]);if($q->rowCount()!==1)throw new RuntimeException('La campaña ya cambió de estado.');$this->audit($id,'approve');$this->db->commit();}catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public static function html(array $c):string
    {
        $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$s=json_decode($c['sender_json'],true);
        $body='<html><body><h1>'.$escape($c['subject']).'</h1><p>'.nl2br($escape($c['body'])).'</p>';
        if($c['action_url']!=='')$body.='<p><a href="'.$escape($c['action_url']).'">Conocer más</a></p>';
        return $body.'<hr><p>'.$escape($s['sender_name']??'Explorando Lugares').' · '.$escape($s['sender_address']??'').'</p><p><a href="{{ unsubscribe }}">Dejar de recibir estos correos</a></p></body></html>';
    }

    /** One resumable provider operation per POST. No preparation step sends email. */
    public function prepareStep(int $id,array $settings,BrevoCampaignClient $client):array
    {
        $client->assertReady();$c=$this->get($id);$this->validate($c,$settings);
        if(!in_array($c['status'],['approved','preparing'],true))throw new RuntimeException('Aprueba primero la campaña o revisa su estado.');
        $claim=$this->query('UPDATE business_email_campaigns SET busy=1,status=\'preparing\' WHERE id=? AND busy=0 AND status IN (\'approved\',\'preparing\')',[$id]);if($claim->rowCount()!==1)throw new RuntimeException('Hay una operación en curso. Si quedó interrumpida, revisa Brevo antes de repetirla.');
        try{
            $recipients=json_decode($c['recipients_json'],true,32,JSON_THROW_ON_ERROR);
            if(!$c['provider_folder_id']){$r=$client->request('POST','/contacts/folders',['name'=>'Explorando Lugares · '.$id]);$value=(int)($r['id']??0);if($value<1)throw new RuntimeException('Brevo no confirmó la carpeta.');$this->query('UPDATE business_email_campaigns SET provider_folder_id=? WHERE id=?',[$value,$id]);}
            elseif(!$c['provider_list_id']){$r=$client->request('POST','/contacts/lists',['name'=>'Campaña '.$id,'folderId'=>(int)$c['provider_folder_id']]);$value=(int)($r['id']??0);if($value<1)throw new RuntimeException('Brevo no confirmó la lista.');$this->query('UPDATE business_email_campaigns SET provider_list_id=? WHERE id=?',[$value,$id]);}
            elseif((int)$c['sync_cursor']<count($recipients)){$client->syncContact($recipients[(int)$c['sync_cursor']]['email'],(int)$c['provider_list_id']);$this->query('UPDATE business_email_campaigns SET sync_cursor=sync_cursor+1 WHERE id=?',[$id]);}
            else{$sender=json_decode($c['sender_json'],true);$payload=['name'=>'EL '.$id.' · '.$c['name'],'subject'=>$c['subject'],'sender'=>['email'=>$sender['sender_email'],'name'=>$sender['sender_name']],'type'=>'classic','htmlContent'=>self::html($c),'recipients'=>['listIds'=>[(int)$c['provider_list_id']]]];if(!empty($sender['reply_to']))$payload['replyTo']=$sender['reply_to'];$r=$client->request('POST','/emailCampaigns',$payload);$value=(int)($r['id']??0);if($value<1)throw new RuntimeException('Brevo no confirmó el borrador de campaña.');$this->query('UPDATE business_email_campaigns SET provider_campaign_id=?,status=\'prepared\' WHERE id=?',[$value,$id]);}
            $this->query('UPDATE business_email_campaigns SET busy=0,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$id]);$this->audit($id,'prepare_step');return $this->get($id);
        }catch(Throwable $e){$this->query('UPDATE business_email_campaigns SET busy=0,status=\'uncertain\',last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[mb_substr($e->getMessage(),0,500),$id]);$this->audit($id,'prepare_uncertain');throw $e;}
    }

    public function send(int $id,string $hash,array $settings,BrevoCampaignClient $client):void
    {
        $client->assertReady();$c=$this->get($id);$this->validate($c,$settings);if(!hash_equals($c['content_hash'],$hash)||!(int)$c['provider_campaign_id'])throw new RuntimeException('Revisa la campaña preparada antes de enviarla.');
        if($c['status']!=='prepared')throw new RuntimeException('Este envío ya comenzó o requiere revisión; no se repetirá.');
        $native=$client->request('GET','/emailCampaigns/'.(int)$c['provider_campaign_id']);$sender=json_decode($c['sender_json'],true);
        if(($native['status']??'')!=='draft'||($native['subject']??'')!==$c['subject']||($native['sender']['email']??'')!==($sender['sender_email']??'')||($native['htmlContent']??'')!==self::html($c)||($native['recipients']['listIds']??[])!==[(int)$c['provider_list_id']]||($native['replyTo']??'')!==($sender['reply_to']??''))throw new RuntimeException('El borrador de Brevo cambió o ya se envió. No se enviará una versión distinta de la aprobada.');
        $provider=$client->request('GET','/contacts/lists/'.(int)$c['provider_list_id'].'/contacts?limit=500');
        $actual=[];foreach(($provider['contacts']??[]) as $contact){if(!empty($contact['emailBlacklisted']))throw new RuntimeException('Brevo excluyó un correo. Revisa la audiencia antes de enviar.');$actual[]=strtolower(trim((string)($contact['email']??'')));}
        $expected=array_column(json_decode($c['recipients_json'],true),'email');sort($actual);sort($expected);
        if($actual!==$expected || (int)($provider['count']??count($actual))!==count($expected))throw new RuntimeException('La lista de Brevo no coincide con los destinatarios aprobados. No se envió.');
        $q=$this->query('UPDATE business_email_campaigns SET status=\'sending\' WHERE id=? AND status=\'prepared\' AND busy=0',[$id]);if($q->rowCount()!==1)throw new RuntimeException('Este envío ya comenzó o requiere revisión; no se repetirá.');
        $this->audit($id,'send_requested');
        try{$client->request('POST','/emailCampaigns/'.(int)$c['provider_campaign_id'].'/sendNow');$this->query('UPDATE business_email_campaigns SET status=\'accepted\',updated_at=CURRENT_TIMESTAMP WHERE id=?',[$id]);$this->audit($id,'provider_accepted');}
        catch(Throwable $e){$this->query('UPDATE business_email_campaigns SET status=\'uncertain\',last_error=? WHERE id=?',[mb_substr($e->getMessage(),0,500),$id]);$this->audit($id,'send_uncertain');throw $e;}
    }
    public function refresh(int $id,BrevoCampaignClient $client):array
    {
        $c=$this->get($id);if(!(int)$c['provider_campaign_id'])throw new RuntimeException('Esta campaña todavía no existe en Brevo.');$r=$client->request('GET','/emailCampaigns/'.(int)$c['provider_campaign_id']);$stats=[];
        foreach(['sent','delivered','uniqueViews','uniqueClicks','unsubscriptions','hardBounces','softBounces','complaints'] as $key)$stats[$key]=max(0,(int)($r['statistics']['globalStats'][$key]??0));
        $stats['provider_status']=(string)($r['status']??'unknown');$stats['checked_at']=gmdate('c');$this->query('UPDATE business_email_campaigns SET stats_json=? WHERE id=?',[json_encode($stats,JSON_THROW_ON_ERROR),$id]);return $stats;
    }
    public function cancel(int $id):void {$q=$this->query('UPDATE business_email_campaigns SET status=\'cancelled\' WHERE id=? AND busy=0 AND status IN (\'draft\',\'approved\',\'preparing\',\'prepared\')',[$id]);if($q->rowCount()!==1)throw new RuntimeException('No se puede cancelar un envío iniciado o incierto desde esta acción.');$this->audit($id,'cancel');}
}

<?php
declare(strict_types=1);
require_once __DIR__.'/ResendClient.php';
final class AdvanceAdmin {
    private const TABLES=['contacts','establishments','templates','campaigns','deliveries'];
    public function __construct(private PDO $db, private string $actor) {}
    private function query(string $sql,array $params=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($params);return $q;}
    private function text(array $data,string $key,int $max,bool $required=false): string {
        $v=$data[$key]??'';
        if (!is_string($v)||strlen($v)>$max||($required&&trim($v)==='')) throw new InvalidArgumentException('Revisa el campo '.$key.'.');
        return trim($v);
    }
    private function choice(array $data,string $key,array $allowed): string {
        $v=$this->text($data,$key,40,true);if(!in_array($v,$allowed,true))throw new InvalidArgumentException('Revisa el campo '.$key.'.');return $v;
    }
    private function audit(string $action,string $entity,int $id): void {
        $this->query('INSERT INTO advance_audit(actor,action,entity,entity_id,created_at) VALUES(?,?,?,?,?)',[$this->actor,$action,$entity,$id,gmdate('Y-m-d H:i:s')]);
    }
    public function rows(string $table,string $search='',int $page=1): array {
        if(!in_array($table,self::TABLES,true))throw new InvalidArgumentException('Sección inválida.');
        $where='';$params=[];
        if($search!==''&&in_array($table,['contacts','establishments','templates','campaigns'],true)){$where=' WHERE name LIKE ?';$params[]='%'.substr($search,0,120).'%';}
        return $this->query('SELECT * FROM advance_'.$table.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.((max(1,$page)-1)*25),$params)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(string $table,int $id): array {
        if(!in_array($table,self::TABLES,true))throw new InvalidArgumentException('Sección inválida.');
        $r=$this->query('SELECT * FROM advance_'.$table.' WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$r)throw new InvalidArgumentException('Registro inexistente.');return $r;
    }
    public function dashboard(): array {
        $out=[];foreach(['contacts','establishments','campaigns','deliveries'] as $t)$out[$t]=(int)$this->query('SELECT COUNT(*) FROM advance_'.$t)->fetchColumn();
        $out['delivery_status']=$this->query('SELECT status,COUNT(*) AS total FROM advance_deliveries GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
        $out['municipalities']=$this->query('SELECT municipality,COUNT(*) AS total FROM advance_contacts GROUP BY municipality ORDER BY total DESC LIMIT 25')->fetchAll(PDO::FETCH_ASSOC);
        return $out;
    }
    public function save(string $table,array $data): int {
        if(!in_array($table,['contacts','establishments','templates'],true))throw new InvalidArgumentException('Sección inválida.');
        $values=['name'=>$this->text($data,'name',$table==='contacts'?120:160,true)];
        if($table==='contacts'){
            $values+=['email'=>strtolower($this->text($data,'email',254)),'phone'=>$this->text($data,'phone',40),
                'municipality'=>$this->text($data,'municipality',120,true),
                'relationship'=>$this->choice($data,'relationship',['prospecto','en_revision','aliado','archivado']),
                'notes'=>$this->text($data,'notes',5000),'source'=>$this->text($data,'source',2000,true),
                'marketing_consent'=>($data['marketing_consent']??'')==='1'?1:0,
                'consent_evidence'=>$this->text($data,'consent_evidence',2000),'suppressed'=>($data['suppressed']??'')==='1'?1:0];
            if($values['email']!==''&&!filter_var($values['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Correo inválido.');
            if($values['email']===''&&$values['phone']==='')throw new InvalidArgumentException('Indica un canal de contacto.');
            if($values['marketing_consent']&&!$values['consent_evidence'])throw new InvalidArgumentException('Documenta la autorización de campañas, fuente y fecha.');
        }elseif($table==='establishments'){
            $contact=filter_var($data['contact_id']??'',FILTER_VALIDATE_INT);
            if($contact)$this->find('contacts',$contact);
            $category=$this->text($data,'category',32,true);
            if(!isset(categories()[$category]))throw new InvalidArgumentException('Categoría inválida.');
            $values+=['contact_id'=>$contact?:null,'municipality'=>$this->text($data,'municipality',120,true),'category'=>$category,
                'description'=>$this->text($data,'description',10000),'source'=>$this->text($data,'source',2000,true),
                'verification'=>$this->choice($data,'verification',['pendiente','verificado']),
                'operation'=>$this->choice($data,'operation',['desconocida','abierto','cerrado']),
                'status'=>$this->choice($data,'status',['borrador','archivado'])];
        }else{
            $values+=['subject'=>$this->text($data,'subject',200,true),'html'=>$this->text($data,'html',60000,true)];
            self::validateTemplate($values['subject'],$values['html']);
        }
        $id=filter_var($data['id']??0,FILTER_VALIDATE_INT);if($id===false||$id<0)throw new InvalidArgumentException('ID inválido.');
        $now=gmdate('Y-m-d H:i:s');$values['updated_at']=$now;
        $this->db->beginTransaction();
        try{
            if($id){
                $version=filter_var($data['version']??0,FILTER_VALIDATE_INT);
                $set=implode(',',array_map(fn($k)=>$k.'=?',array_keys($values)));
                $q=$this->query('UPDATE advance_'.$table.' SET '.$set.',version=version+1 WHERE id=? AND version=?',[...array_values($values),$id,$version]);
                if($q->rowCount()!==1)throw new RuntimeException('El registro cambió. Recarga antes de guardar.');
            }else{
                $values['created_at']=$now;$keys=implode(',',array_keys($values));$marks=implode(',',array_fill(0,count($values),'?'));
                $this->query('INSERT INTO advance_'.$table.'('.$keys.') VALUES('.$marks.')',array_values($values));$id=(int)$this->db->lastInsertId();
            }
            $this->audit('save',$table,$id);$this->db->commit();return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public static function validateTemplate(string $subject,string $html): void {
        if(preg_match('/[\r\n]/',$subject))throw new InvalidArgumentException('El asunto debe ocupar una línea.');
        preg_match_all('/{{(.*?)}}/s',$subject.$html,$matches);
        foreach($matches[1] as $key)if(!in_array($key,['nombre','municipio'],true))throw new InvalidArgumentException('Variables permitidas: nombre y municipio.');
        // HTML se conserva como contenido privado. Vista previa aislada en iframe sandbox sin scripts/red.
    }
    private static function render(string $text,array $contact,bool $html): string {
        $values=['{{nombre}}'=>$contact['name'],'{{municipio}}'=>$contact['municipality']];
        if($html)$values=array_map(fn($v)=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$values);
        $result=strtr($text,$values);
        if(!$html)$result=str_replace(["\r","\n"],' ',$result);
        return $result;
    }
    public function importLead(int $id): int {
        $lead=$this->query('SELECT * FROM leads WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$lead)throw new InvalidArgumentException('Solicitud inexistente.');
        $existing=$this->query('SELECT id FROM advance_contacts WHERE lead_id=?',[$id])->fetchColumn();if($existing)return (int)$existing;
        $now=gmdate('Y-m-d H:i:s');
        $this->db->beginTransaction();try{
            $this->query('INSERT INTO advance_contacts(lead_id,name,email,phone,municipality,notes,source,consent_evidence,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
                [$id,$lead['name'],strtolower($lead['email']),$lead['phone'],$lead['municipality'],'','Solicitud #'.$id.' · '.$lead['created_at'],'',$now,$now]);
            $new=(int)$this->db->lastInsertId();$this->audit('import_lead','contacts',$new);$this->db->commit();return $new;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public function createCampaign(array $data): int {
        $name=$this->text($data,'name',160,true);$template=$this->find('templates',(int)($data['template_id']??0));
        $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',$this->text($data,'contact_ids',4000,true))),fn($id)=>$id>0)));
        if(!$ids||count($ids)>100)throw new InvalidArgumentException('Selecciona de 1 a 100 contactos por ID.');
        $this->db->beginTransaction();try{
            $now=gmdate('Y-m-d H:i:s');
            $this->query('INSERT INTO advance_campaigns(name,subject,html,created_at) VALUES(?,?,?,?)',[$name,$template['subject'],$template['html'],$now]);$id=(int)$this->db->lastInsertId();$emails=[];
            foreach($ids as $cid){
                $c=$this->find('contacts',$cid);
                if(!$c['marketing_consent']||$c['suppressed']||!filter_var($c['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Todos los destinatarios requieren correo y autorización de campañas vigente.');
                if($this->query('SELECT id FROM advance_contacts WHERE email=? AND suppressed=1',[$c['email']])->fetchColumn())throw new InvalidArgumentException('Un correo está suprimido.');
                if(isset($emails[$c['email']]))continue;$emails[$c['email']]=true;
                $subject=self::render($template['subject'],$c,false);
                $html=self::render($template['html'],$c,true);
                if(strlen($html)>60000)throw new InvalidArgumentException('El HTML personalizado supera el límite permitido.');
                if(strlen($subject)>200)throw new InvalidArgumentException('El asunto personalizado supera 200 bytes.');
                $this->query('INSERT INTO advance_deliveries(campaign_id,contact_id,email,subject,html,updated_at) VALUES(?,?,?,?,?,?)',
                    [$id,$cid,$c['email'],$subject,$html,$now]);
            }
            $this->audit('create','campaigns',$id);$this->db->commit();return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public function approve(int $id,int $version): void {
        $this->db->beginTransaction();try{
            $q=$this->query("UPDATE advance_campaigns SET status='aprobada',approved_by=?,approved_at=? WHERE id=? AND version=? AND status='borrador'",[$this->actor,gmdate('Y-m-d H:i:s'),$id,$version]);
            if($q->rowCount()!==1)throw new RuntimeException('La campaña no está disponible para aprobación.');
            $this->audit('approve','campaigns',$id);$this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public function deliveries(int $id): array {return $this->query('SELECT * FROM advance_deliveries WHERE campaign_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC);}
    public function sendDelivery(int $id,ResendClient $client): void {
        $client->assertReady();
        $this->db->beginTransaction();try{
            // Una reclamación condicional evita envíos concurrentes. No se reintentan estados inciertos.
            $q=$this->query("UPDATE advance_deliveries SET status='enviando',updated_at=? WHERE id=? AND status='pendiente' AND campaign_id IN (SELECT id FROM advance_campaigns WHERE status='aprobada')",[gmdate('Y-m-d H:i:s'),$id]);
            if($q->rowCount()!==1)throw new RuntimeException('Entrega no pendiente o campaña no aprobada.');
            $d=$this->find('deliveries',$id);$c=$this->find('contacts',(int)$d['contact_id']);
            if(!$c['marketing_consent']||$c['suppressed']||$c['email']!==$d['email']||$this->query('SELECT id FROM advance_contacts WHERE email=? AND suppressed=1',[$d['email']])->fetchColumn()){
                $this->query("UPDATE advance_deliveries SET status='omitida' WHERE id=?",[$id]);$this->audit('suppress','deliveries',$id);$this->db->commit();return;
            }
            $this->audit('dispatch','deliveries',$id);$this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
        try{$provider=$client->send($d,'advance-delivery-'.$id.'-'.hash('sha256',$d['email'].$d['subject'].$d['html']));}
        catch(Throwable $e){$this->query("UPDATE advance_deliveries SET status='incierta',updated_at=? WHERE id=?",[gmdate('Y-m-d H:i:s'),$id]);throw $e;}
        $this->query("UPDATE advance_deliveries SET status='aceptada',provider_id=?,updated_at=? WHERE id=?",[$provider,gmdate('Y-m-d H:i:s'),$id]);
        $this->audit('accepted','deliveries',$id);
    }
}

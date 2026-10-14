<?php
declare(strict_types=1);
/** Adaptador del catálogo compartido. No crea destinos, categorías, medios ni experiencias. */
final class BusinessAdmin {
    public function __construct(private PDO $db,private string $actor) {}
    private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    public function destinations(): array {return $this->query("SELECT d.id,COALESCE(t.display_name,d.slug) AS name FROM destinations d LEFT JOIN destination_translations t ON t.destination_id=d.id AND t.locale='es' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);}
    public function categories(): array {return $this->query("SELECT c.code,COALESCE(t.label,c.code) AS name FROM service_categories c LEFT JOIN service_category_translations t ON t.category_code=c.code AND t.locale='es' WHERE c.is_active=1 ORDER BY c.display_order,c.code")->fetchAll(PDO::FETCH_ASSOC);}
    private function select(): string {
        return "SELECT b.*,COALESCE(t.display_name,b.slug) AS name,COALESCE(t.description,'') AS description,COALESCE(t.summary,'') AS summary,
         COALESCE(dt.display_name,d.slug) AS municipality,crm.contact_id,COALESCE(crm.version,0) AS version
         FROM businesses b JOIN destinations d ON d.id=b.destination_id
         LEFT JOIN business_translations t ON t.business_id=b.id AND t.locale='es'
         LEFT JOIN destination_translations dt ON dt.destination_id=d.id AND dt.locale='es'
         LEFT JOIN advance_business_crm crm ON crm.business_id=b.id";
    }
    public function rows(string $search='',int $page=1): array {
        return $this->query($this->select()." WHERE COALESCE(t.display_name,b.slug) LIKE ? ORDER BY b.id DESC LIMIT 25 OFFSET ".((max(1,$page)-1)*25),['%'.substr($search,0,120).'%'])->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(int $id): array {
        $r=$this->query($this->select().' WHERE b.id=?',[$id])->fetch(PDO::FETCH_ASSOC);
        if(!$r)throw new InvalidArgumentException('Negocio inexistente.');
        $r['sources']=$this->query('SELECT * FROM business_sources WHERE business_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC);
        $r['channels']=$this->query('SELECT * FROM business_contacts WHERE business_id=? ORDER BY id',[$id])->fetchAll(PDO::FETCH_ASSOC);
        // Incluye todas las traducciones y las fuentes para detectar cambios de otro módulo.
        $r['translations']=$this->query('SELECT * FROM business_translations WHERE business_id=? ORDER BY locale',[$id])->fetchAll(PDO::FETCH_ASSOC);
        $r['revision']=hash('sha256',json_encode($r,JSON_THROW_ON_ERROR));return $r;
    }
    public function linkContact(array $data): int {
        $id=filter_var($data['id']??0,FILTER_VALIDATE_INT);
        $contact=($data['contact_id']??'')===''?null:filter_var($data['contact_id'],FILTER_VALIDATE_INT);
        if(!$id||$id<0||($contact!==null&&(!$contact||$contact<0)))throw new InvalidArgumentException('IDs inválidos.');
        $this->db->beginTransaction();try{
            $suffix=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $this->query('SELECT id FROM businesses WHERE id=?'.$suffix,[$id]);$old=$this->find($id);
            if(!hash_equals($old['revision'],$this->text($data,'revision',64,true)))throw new RuntimeException('El negocio cambió. Recarga antes de vincular.');
            if($contact&&!$this->query('SELECT id FROM advance_contacts WHERE id=?',[$contact])->fetchColumn())throw new InvalidArgumentException('Contacto CRM inexistente.');
            if((int)$old['version']>0)$this->query('UPDATE advance_business_crm SET contact_id=?,version=version+1 WHERE business_id=?',[$contact,$id]);
            else $this->query('INSERT INTO advance_business_crm(business_id,contact_id,version) VALUES(?,?,1)',[$id,$contact]);
            $this->query('INSERT INTO advance_audit(actor,action,entity,entity_id,created_at) VALUES(?,?,?,?,?)',[$this->actor,'link_contact','businesses',$id,gmdate('Y-m-d H:i:s')]);
            $this->db->commit();return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    private function text(array $data,string $key,int $max,bool $required=false): string {
        $v=$data[$key]??'';if(!is_string($v)||strlen($v)>$max||($required&&trim($v)===''))throw new InvalidArgumentException('Revisa el campo '.$key.'.');return trim($v);
    }
    public function save(array $data): int {
        $id=filter_var($data['id']??0,FILTER_VALIDATE_INT);$destination=filter_var($data['destination_id']??0,FILTER_VALIDATE_INT);
        $contact=($data['contact_id']??'')===''?null:filter_var($data['contact_id'],FILTER_VALIDATE_INT);
        if($id===false||$id<0||!$destination||($contact!==null&&(!$contact||$contact<0)))throw new InvalidArgumentException('IDs inválidos.');
        $category=$this->text($data,'category_code',40,true);$slug=$this->text($data,'slug',140,true);
        if(!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new InvalidArgumentException('Usa un identificador URL en minúsculas, números y guiones.');
        $name=$this->text($data,'name',180,true);$summary=$this->text($data,'summary',500);$description=$this->text($data,'description',10000);
        $status=$this->text($data,'publication_status',20,true);if(!in_array($status,['draft','archived'],true))throw new InvalidArgumentException('Solo se permiten borradores o archivados.');
        $source=$this->text($data,'source_url',1000);$note=$this->text($data,'source_note',500);
        if($source!==''&&(!filter_var($source,FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($source,PHP_URL_SCHEME)??''),['http','https'],true)))throw new InvalidArgumentException('Fuente editorial inválida.');
        if(!$id&&$source===''&&$note==='')throw new InvalidArgumentException('Documenta la procedencia del negocio.');
        $this->db->beginTransaction();try{
            if(!$this->query('SELECT id FROM destinations WHERE id=?',[$destination])->fetchColumn())throw new InvalidArgumentException('Destino inexistente.');
            $validCategory=$this->query('SELECT is_active FROM service_categories WHERE code=?',[$category])->fetchColumn();
            if($validCategory===false)throw new InvalidArgumentException('Categoría inexistente.');
            if($contact&&!$this->query('SELECT id FROM advance_contacts WHERE id=?',[$contact])->fetchColumn())throw new InvalidArgumentException('Contacto CRM inexistente.');
            if($id){
                // Bloquear el padre compartido y comparar su contenido antes de escribir.
                $suffix=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                $this->query('SELECT id FROM businesses WHERE id=?'.$suffix,[$id]);$old=$this->find($id);
                if($old['publication_status']==='published')throw new RuntimeException('El negocio está publicado. Su edición requiere el flujo editorial de publicación.');
                if(!hash_equals($old['revision'],$this->text($data,'revision',64,true)))throw new RuntimeException('El negocio cambió en otro panel. Recarga antes de guardar.');
                if(!(int)$validCategory&&$old['category_code']!==$category)throw new InvalidArgumentException('Categoría inactiva.');
                $this->query('UPDATE businesses SET destination_id=?,category_code=?,slug=?,publication_status=?,updated_at=? WHERE id=?',[$destination,$category,$slug,$status,gmdate('Y-m-d H:i:s'),$id]);
            }else{
                if(!(int)$validCategory)throw new InvalidArgumentException('Categoría inactiva.');
                $public=bin2hex(random_bytes(16));$public=substr($public,0,8).'-'.substr($public,8,4).'-'.substr($public,12,4).'-'.substr($public,16,4).'-'.substr($public,20);
                $this->query('INSERT INTO businesses(public_id,destination_id,category_code,slug,publication_status) VALUES(?,?,?,?,?)',[$public,$destination,$category,$slug,$status]);$id=(int)$this->db->lastInsertId();
            }
            if($this->query("SELECT business_id FROM business_translations WHERE business_id=? AND locale='es'",[$id])->fetchColumn())
                $this->query("UPDATE business_translations SET display_name=?,summary=?,description=? WHERE business_id=? AND locale='es'",[$name,$summary,$description,$id]);
            else $this->query("INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,'es',?,?,?)",[$id,$name,$summary,$description]);
            $this->query('DELETE FROM advance_business_crm WHERE business_id=?',[$id]);
            $this->query('INSERT INTO advance_business_crm(business_id,contact_id,version) VALUES(?,?,?)',[$id,$contact,(int)($old['version']??0)+1]);
            if($source!==''||$note!==''){
                $exists=$this->query('SELECT id FROM business_sources WHERE business_id=? AND source_url=? AND note=?',[$id,$source,$note])->fetchColumn();
                if(!$exists)$this->query('INSERT INTO business_sources(business_id,source_url,checked_at,note) VALUES(?,?,?,?)',[$id,$source,gmdate('Y-m-d H:i:s'),$note]);
            }
            $this->query('INSERT INTO advance_audit(actor,action,entity,entity_id,created_at) VALUES(?,?,?,?,?)',[$this->actor,'save','businesses',$id,gmdate('Y-m-d H:i:s')]);
            $this->db->commit();return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
}

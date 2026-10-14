<?php
declare(strict_types=1);
/** Tokens only authorize suppression; they never expose or grant access to CRM records. */
final class Unsubscribe {
    public function __construct(private PDO $db,private array $config) {}
    private function query(string $sql,array $args=[]): PDOStatement {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    private function key(): string {
        $key=$this->config['app_key']??'';
        if(!is_string($key)||strlen($key)<32)throw new RuntimeException('Falta configurar la firma de enlaces de baja.');return $key;
    }
    public function assertReady(): void {
        $this->key();$base=$this->config['public_base_url']??'';
        if(!is_string($base)||strlen($base)>1800||!filter_var($base,FILTER_VALIDATE_URL))throw new RuntimeException('Configura public_base_url para los enlaces de baja.');
        $parts=parse_url($base);
        if(($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment']))throw new RuntimeException('public_base_url debe ser una URL HTTPS pública sin credenciales, consulta ni fragmento.');
    }
    public function token(int $deliveryId,string $email): string {
        if($deliveryId<1)throw new InvalidArgumentException('Entrega inválida.');
        return $deliveryId.'.'.hash_hmac('sha256','advance-unsubscribe-v1|'.$deliveryId.'|'.strtolower($email),$this->key());
    }
    public function url(int $deliveryId,string $email): string {
        $this->assertReady();return rtrim($this->config['public_base_url'],'/').'/api/unsubscribe.php?token='.rawurlencode($this->token($deliveryId,$email));
    }
    public function resolve(string $token): array {
        if(!preg_match('/^([1-9][0-9]{0,18})\.([a-f0-9]{64})$/D',$token,$parts))throw new InvalidArgumentException('El enlace de baja no es válido.');
        $record=$this->query('SELECT id,email FROM advance_deliveries WHERE id=?',[$parts[1]])->fetch(PDO::FETCH_ASSOC);
        if(!$record||!hash_equals($this->token((int)$record['id'],$record['email']),$token))throw new InvalidArgumentException('El enlace de baja no es válido.');
        return $record;
    }
    public function suppress(string $token): void {
        $record=$this->resolve($token);$email=strtolower($record['email']);$now=gmdate('Y-m-d H:i:s');
        $this->db->beginTransaction();try{
            $sql=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
                ? "INSERT INTO advance_email_suppressions(email,reason,created_at) VALUES(?,'recipient_unsubscribe',?) ON DUPLICATE KEY UPDATE email=VALUES(email)"
                : "INSERT INTO advance_email_suppressions(email,reason,created_at) VALUES(?,'recipient_unsubscribe',?) ON CONFLICT(email) DO NOTHING";
            $this->query($sql,[$email,$now]);
            $updated=$this->query('UPDATE advance_contacts SET suppressed=1,marketing_consent=0,version=version+1,updated_at=? WHERE email=? AND (suppressed<>1 OR marketing_consent<>0)',[$now,$email])->rowCount();
            $skipped=$this->query("UPDATE advance_deliveries SET status='skipped',updated_at=? WHERE email=? AND status='pending'",[$now,$email])->rowCount();
            if($updated||$skipped)$this->query('INSERT INTO advance_audit(actor,action,entity,entity_id,created_at) VALUES(?,?,?,?,?)',['recipient','unsubscribe','deliveries',(int)$record['id'],$now]);
            $this->db->commit();
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }
    public static function appendFooter(string $html,string $url): string {
        $footer='<p>Explorando Lugares · <a href="'.htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">Dejar de recibir correos de campañas</a></p>';
        if(preg_match('/<\/body\s*>/i',$html))return preg_replace_callback('/<\/body\s*>/i',static fn($match)=>$footer.$match[0],$html,1);
        return $html.$footer;
    }
}

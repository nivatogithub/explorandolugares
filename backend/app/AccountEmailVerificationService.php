<?php
declare(strict_types=1);
require_once __DIR__.'/AutomaticBusinessClaim.php';
/** New accounts remain pending until a one-use email capability is confirmed. */
final class AccountEmailVerificationService
{
    public const RESEND_WAIT_SECONDS = 60;

    public function __construct(private PDO $pdo) {}
    public function issue(int $accountId,bool $allowActiveUnverified=false,?string $expectedEmail=null):?string
    {
        $token=bin2hex(random_bytes(32));$this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $eligible=$allowActiveUnverified?'(status="pending_activation" OR (status="active" AND email_verified_at IS NULL))':'status="pending_activation"';
            $q=$this->pdo->prepare('SELECT email FROM accounts WHERE id=? AND '.$eligible.$lock);$q->execute([$accountId]);
            $email=$q->fetchColumn();
            if($email===false||($expectedEmail!==null&&$email!==$expectedEmail))throw new RuntimeException('La cuenta no admite activación.');
            $now=time();
            $q=$this->pdo->prepare('SELECT 1 FROM account_email_verifications WHERE account_id=? AND used_at IS NULL AND expires_at>? AND created_at>? LIMIT 1');
            $q->execute([$accountId,gmdate('Y-m-d H:i:s',$now),gmdate('Y-m-d H:i:s',$now-self::RESEND_WAIT_SECONDS)]);
            if($q->fetchColumn()){
                $this->pdo->commit();
                return null;
            }
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM account_email_verifications WHERE account_id=? AND created_at>?');$q->execute([$accountId,gmdate('Y-m-d H:i:s',time()-900)]);
            if((int)$q->fetchColumn()>=3)throw new RuntimeException('Espera quince minutos antes de pedir otro enlace.');
            $this->pdo->prepare('UPDATE account_email_verifications SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$accountId]);
            $this->pdo->prepare('INSERT INTO account_email_verifications(account_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$accountId,hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+86400)]);
            $this->pdo->commit();return $token;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function correctPendingEmail(int $accountId,string $email,string $password):string
    {
        $email=strtolower(trim($email));
        if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Escribe un correo válido.');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $account=$this->pdo->prepare('SELECT email,password_hash FROM accounts WHERE id=? AND status="pending_activation" AND email_verified_at IS NULL'.$lock);
            $account->execute([$accountId]);
            $row=$account->fetch(PDO::FETCH_ASSOC);
            if(!$row||!password_verify($password,$row['password_hash']))throw new RuntimeException('No se pudo confirmar el registro pendiente. Revisa tu contraseña.');
            if($row['email']===$email)throw new RuntimeException('Escribe un correo diferente al del registro.');
            $available=$this->pdo->prepare('SELECT 1 FROM accounts WHERE email=?');
            $available->execute([$email]);
            if($available->fetchColumn())throw new RuntimeException('No se puede usar ese correo. Si ya tienes una cuenta, utiliza el acceso o la recuperación.');
            $update=$this->pdo->prepare('UPDATE accounts SET email=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status="pending_activation"');
            $update->execute([$email,$accountId]);
            $this->pdo->prepare('UPDATE account_email_verifications SET used_at=COALESCE(used_at,CURRENT_TIMESTAMP),expires_at=CURRENT_TIMESTAMP WHERE account_id=? AND expires_at>CURRENT_TIMESTAMP')->execute([$accountId]);
            $this->pdo->prepare('UPDATE account_password_resets SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$accountId]);
            $token=bin2hex(random_bytes(32));
            $this->pdo->prepare('INSERT INTO account_email_verifications(account_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$accountId,hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+86400)]);
            $this->pdo->commit();
            return $token;
        }catch(Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            if($e instanceof PDOException&&$e->getCode()==='23000')throw new RuntimeException('No se puede usar ese correo. Si ya tienes una cuenta, utiliza el acceso o la recuperación.',0,$e);
            throw $e;
        }
    }
    public function revoke(string $token):void{$this->pdo->prepare('UPDATE account_email_verifications SET used_at=CURRENT_TIMESTAMP WHERE token_hash=?')->execute([hash('sha256',$token)]);}
    public function verify(string $token):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo.');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT v.account_id,v.expires_at,v.used_at,a.status,a.email_verified_at FROM account_email_verifications v JOIN accounts a ON a.id=v.account_id WHERE token_hash=?'.$lock);
            $q->execute([hash('sha256',$token)]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||strtotime($row['expires_at'].' UTC')<=time())throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo.');
            if($row['used_at']!==null&&$row['status']==='active'&&$row['email_verified_at']!==null){
                $this->pdo->commit();
                return;
            }
            if($row['used_at']!==null||!in_array($row['status'],['pending_activation','active'],true)||$row['email_verified_at']!==null){
                throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo.');
            }
            $q=$this->pdo->prepare('UPDATE accounts SET status="active",email_verified_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND (status="pending_activation" OR (status="active" AND email_verified_at IS NULL))');$q->execute([$row['account_id']]);if($q->rowCount()!==1)throw new RuntimeException('La cuenta no está disponible.');
            AutomaticBusinessClaim::afterActivation($this->pdo,(int)$row['account_id']);
            $this->pdo->prepare('UPDATE account_email_verifications SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$row['account_id']]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}

<?php
declare(strict_types=1);
require_once __DIR__.'/AutomaticBusinessClaim.php';
/** New accounts remain pending until a one-use email capability is confirmed. */
final class AccountEmailVerificationService
{
    public function __construct(private PDO $pdo) {}
    public function issue(int $accountId,bool $allowActiveUnverified=false):string
    {
        $token=bin2hex(random_bytes(32));$this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $eligible=$allowActiveUnverified?'(status="pending_activation" OR (status="active" AND email_verified_at IS NULL))':'status="pending_activation"';
            $q=$this->pdo->prepare('SELECT id FROM accounts WHERE id=? AND '.$eligible.$lock);$q->execute([$accountId]);
            if(!$q->fetchColumn())throw new RuntimeException('La cuenta no admite activación.');
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM account_email_verifications WHERE account_id=? AND created_at>?');$q->execute([$accountId,gmdate('Y-m-d H:i:s',time()-900)]);
            if((int)$q->fetchColumn()>=3)throw new RuntimeException('Espera quince minutos antes de pedir otro enlace.');
            $this->pdo->prepare('UPDATE account_email_verifications SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$accountId]);
            $this->pdo->prepare('INSERT INTO account_email_verifications(account_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$accountId,hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+86400)]);
            $this->pdo->commit();return $token;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function revoke(string $token):void{$this->pdo->prepare('UPDATE account_email_verifications SET used_at=CURRENT_TIMESTAMP WHERE token_hash=?')->execute([hash('sha256',$token)]);}
    public function verify(string $token):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo.');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT v.id,v.account_id,v.expires_at FROM account_email_verifications v JOIN accounts a ON a.id=v.account_id WHERE token_hash=? AND used_at IS NULL AND (a.status="pending_activation" OR (a.status="active" AND a.email_verified_at IS NULL))'.$lock);$q->execute([hash('sha256',$token)]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||strtotime($row['expires_at'].' UTC')<=time())throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo.');
            $q=$this->pdo->prepare('UPDATE accounts SET status="active",email_verified_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND (status="pending_activation" OR (status="active" AND email_verified_at IS NULL))');$q->execute([$row['account_id']]);if($q->rowCount()!==1)throw new RuntimeException('La cuenta no está disponible.');
            AutomaticBusinessClaim::afterActivation($this->pdo,(int)$row['account_id']);
            $this->pdo->prepare('UPDATE account_email_verifications SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$row['account_id']]);$this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}

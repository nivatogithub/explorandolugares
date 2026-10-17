<?php
declare(strict_types=1);

final class AccountPasswordResetService
{
    public function __construct(private PDO $pdo) {}

    /** Issues a one-time 24-hour capability; only its digest is stored. */
    public function issue(int $accountId, string $createdBy = 'admin'): string
    {
        $account=$this->pdo->prepare('SELECT id FROM accounts WHERE id=? AND account_type IN ("business","traveler") AND status IN ("active","pending_activation")');
        $account->execute([$accountId]);
        if(!$account->fetchColumn())throw new RuntimeException('La cuenta no existe, no es empresarial o está deshabilitada.');
        $token=bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try{
            $this->pdo->prepare('UPDATE account_password_resets SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$accountId]);
            $insert=$this->pdo->prepare('INSERT INTO account_password_resets(account_id,token_hash,created_by,expires_at) VALUES(?,?,?,?)');
            $insert->execute([$accountId,hash('sha256',$token),substr($createdBy,0,120),gmdate('Y-m-d H:i:s',time()+86400)]);
            $this->pdo->commit();
            return $token;
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }

    public function isValid(string $token): bool
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
        $q=$this->pdo->prepare('SELECT r.expires_at FROM account_password_resets r JOIN accounts a ON a.id=r.account_id WHERE r.token_hash=? AND r.used_at IS NULL AND a.account_type IN ("business","traveler") AND a.status<>"disabled"');
        $q->execute([hash('sha256',$token)]);$expiresAt=$q->fetchColumn();
        return is_string($expiresAt)&&strtotime($expiresAt.' UTC')>time();
    }

    public function reset(string $token,string $password,string $confirmation): void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo al equipo.');
        if($password!==$confirmation)throw new RuntimeException('Las contraseñas no coinciden.');
        if(strlen($password)<12||strlen($password)>200||!preg_match('/[A-Za-z]/',$password)||!preg_match('/[0-9]/',$password))throw new RuntimeException('La contraseña debe tener entre 12 y 200 caracteres e incluir letras y números.');
        $this->pdo->beginTransaction();
        try{
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT r.id,r.account_id,r.expires_at FROM account_password_resets r JOIN accounts a ON a.id=r.account_id WHERE r.token_hash=? AND r.used_at IS NULL AND a.account_type IN ("business","traveler") AND a.status<>"disabled"'.$lock);
            $q->execute([hash('sha256',$token)]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||strtotime((string)$row['expires_at'].' UTC')<=time())throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo al equipo.');
            $update=$this->pdo->prepare('UPDATE accounts SET password_hash=?,status="active",email_verified_at=COALESCE(email_verified_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE id=? AND status<>"disabled"');
            $update->execute([password_hash($password,PASSWORD_DEFAULT),(int)$row['account_id']]);
            if($update->rowCount()!==1)throw new RuntimeException('La cuenta está deshabilitada y no puede cambiar su contraseña.');
            $this->pdo->prepare('UPDATE account_password_resets SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([(int)$row['account_id']]);
            $this->pdo->commit();
        }catch(Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}

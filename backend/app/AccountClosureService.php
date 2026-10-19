<?php
declare(strict_types=1);

require_once __DIR__.'/AccountLifecycle.php';
final class AccountClosureService
{
    public function __construct(private PDO $pdo) {}

    public function close(int $id,string $password,bool $confirmed): void
    {
        if(!$confirmed)throw new RuntimeException('Confirma que quieres cerrar tu cuenta.');
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->pdo->prepare('SELECT password_hash,status FROM accounts WHERE id=?'.$lock);$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row||$row['status']!=='active'||!password_verify($password,(string)$row['password_hash']))throw new RuntimeException('Comprueba tu contraseña para cerrar la cuenta.');
            // Preserve the final active administrator, so the portal cannot lose its operator.
            $roles=$this->pdo->query('SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.status="active" AND r.role="administrator" ORDER BY a.id'.$lock)->fetchAll(PDO::FETCH_COLUMN);
            $roles=array_map('intval',$roles);
            if(in_array($id,$roles,true)&&count($roles)<2)throw new RuntimeException('Antes de cerrar esta cuenta, asigna otra cuenta administradora activa.');
            $this->pdo->prepare('UPDATE accounts SET status="disabled",password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
            AccountLifecycle::revoke($this->pdo,$id);
            AccountLifecycle::record($this->pdo,$id,'closed',$id,'Cierre voluntario confirmado');
            $this->pdo->commit();
        } catch(Throwable $error) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}

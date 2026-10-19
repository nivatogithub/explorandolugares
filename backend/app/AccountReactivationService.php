<?php
declare(strict_types=1);
require_once __DIR__.'/AccountLifecycle.php';
final class AccountReactivationService
{
 public function __construct(private PDO $pdo){}
 private function row(string $token,bool $lock=false):array|false {
  if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
  $q=$this->pdo->prepare('SELECT r.account_id,r.expires_at FROM account_reactivations r JOIN accounts a ON a.id=r.account_id WHERE r.token_hash=? AND r.used_at IS NULL AND a.status="disabled"'.($lock&&$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([hash('sha256',$token)]);$row=$q->fetch(PDO::FETCH_ASSOC);
  return $row&&strtotime($row['expires_at'].' UTC')>time()&&(AccountLifecycle::state($this->pdo,(int)$row['account_id'])['event_type']??'')==='closed'?$row:false;
 }
 public function issue(int $id):string {
  $this->pdo->beginTransaction();try{
   $q=$this->pdo->prepare('SELECT status FROM accounts WHERE id=?'.($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''));$q->execute([$id]);
   if($q->fetchColumn()!=='disabled'||(AccountLifecycle::state($this->pdo,$id)['event_type']??'')!=='closed')throw new RuntimeException('La cuenta no admite reactivación automática.');
   $q=$this->pdo->prepare('SELECT COUNT(*) FROM account_reactivations WHERE account_id=? AND created_at>?');$q->execute([$id,gmdate('Y-m-d H:i:s',time()-900)]);if((int)$q->fetchColumn()>=3)throw new RuntimeException('Espera quince minutos antes de solicitar otro enlace.');
   $this->revokeAll($id);$token=bin2hex(random_bytes(32));$this->pdo->prepare('INSERT INTO account_reactivations(account_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$id,hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+86400)]);$this->pdo->commit();return $token;
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function revokeAll(int $id):void {$this->pdo->prepare('UPDATE account_reactivations SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$id]);}
 public function revoke(string $token):void {$this->pdo->prepare('UPDATE account_reactivations SET used_at=CURRENT_TIMESTAMP WHERE token_hash=?')->execute([hash('sha256',$token)]);}
 public function isValid(string $token):bool {return (bool)$this->row($token);}
 public function reset(string $token,string $password,string $confirmation):void {
  if($password!==$confirmation)throw new RuntimeException('Las contraseñas no coinciden.');
  if(strlen($password)<12||strlen($password)>200||!preg_match('/[A-Za-z]/',$password)||!preg_match('/[0-9]/',$password))throw new RuntimeException('Usa entre 12 y 200 caracteres, con letras y números.');
  $this->pdo->beginTransaction();try{
   $row=$this->row($token,true);if(!$row)throw new RuntimeException('El enlace no es válido o ya venció. Solicita uno nuevo.');$id=(int)$row['account_id'];
   $this->pdo->prepare('UPDATE accounts SET status="active",password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status="disabled"')->execute([password_hash($password,PASSWORD_DEFAULT),$id]);
   AccountLifecycle::revoke($this->pdo,$id);AccountLifecycle::record($this->pdo,$id,'reactivated',$id,'Confirmación por correo y nueva contraseña');$this->pdo->commit();
  }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
}

<?php
declare(strict_types=1);
/** Accounts stay disabled at the authentication layer; history distinguishes why. */
final class AccountLifecycle
{
 public static function state(PDO $pdo,int $id):?array {
  $q=$pdo->prepare('SELECT id,event_type,reason,created_at,actor_account_id FROM account_access_events WHERE account_id=? ORDER BY id DESC LIMIT 1');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
 }
 public static function record(PDO $pdo,int $id,string $event,?int $actor=null,string $reason=''):void {
  $pdo->prepare('INSERT INTO account_access_events(account_id,event_type,actor_account_id,reason) VALUES(?,?,?,?)')->execute([$id,$event,$actor,mb_substr(trim($reason),0,500)]);
 }
 public static function revoke(PDO $pdo,int $id):void {
  foreach(['account_password_resets','account_email_verifications','account_reactivations'] as $table)$pdo->prepare('UPDATE '.$table.' SET used_at=CURRENT_TIMESTAMP WHERE account_id=? AND used_at IS NULL')->execute([$id]);
 }
}

<?php
declare(strict_types=1);

/** Server-side credential proof: never exposes the password hash to the browser. */
require_once __DIR__.'/AccountLifecycle.php';
final class AccountSessionSecurity
{
    public static function bind(PDO $pdo, array &$session, int $id): void
    {
        $q=$pdo->prepare('SELECT password_hash FROM accounts WHERE id=?');$q->execute([$id]);
        $hash=$q->fetchColumn();
        if(!is_string($hash)||$hash==='')throw new RuntimeException('La cuenta no está disponible.');
        $session['account_credential_proof']=self::proof($pdo,$id,$hash);
    }

    public static function synchronize(PDO $pdo, array &$session): void
    {
        if(empty($session['business_account_id']))return;
        $q=$pdo->prepare('SELECT password_hash,status FROM accounts WHERE id=?');$q->execute([(int)$session['business_account_id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row||$row['status']!=='active'||!is_string($session['account_credential_proof']??null)||!hash_equals(self::proof($pdo,(int)$session['business_account_id'],(string)$row['password_hash']),$session['account_credential_proof']))self::clear($session);
    }

    private static function proof(PDO $pdo,int $id,string $hash):string
    {
        return hash('sha256',$hash.':'.(string)(AccountLifecycle::state($pdo,$id)['id']??0));
    }

    public static function clear(array &$session): void
    {
        // Clear purchase intents, payment references and all authorization context too.
        $session=['csrf'=>bin2hex(random_bytes(32))];
    }
}

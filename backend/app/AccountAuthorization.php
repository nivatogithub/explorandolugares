<?php
declare(strict_types=1);
final class AccountAuthorization
{
    public static function isAdmin(PDO $pdo,int $id): bool
    {
        $q=$pdo->prepare('SELECT a.id FROM accounts a JOIN account_roles r ON r.account_id=a.id WHERE a.id=? AND a.status="active" AND r.role="administrator"');
        $q->execute([$id]);return (bool)$q->fetchColumn();
    }
    public static function synchronize(PDO $pdo,array &$session): void
    {
        // Never infer privileges from email, registration fields or account type.
        if(empty($session['admin_account_id']))return;
        $id=(int)$session['admin_account_id'];
        if($id!==(int)($session['business_account_id']??0)||time()-(int)($session['business_last_seen']??0)>1800||!self::isAdmin($pdo,$id)){
            unset($session['admin'],$session['admin_account_id'],$session['admin_user'],$session['last_seen']);return;
        }
        $session['admin']=true;$session['last_seen']=time();$session['business_last_seen']=time();
    }
}

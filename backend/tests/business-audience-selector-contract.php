<?php
declare(strict_types=1);
if(!class_exists('BusinessAudienceAccessSelector',false))require __DIR__.'/../app/BusinessAudienceAccessSelector.php';

/** Temporary tables shadow live names only for this connection. */
function checkBusinessAudienceAccessSelector(PDO $pdo):void
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $tables=['businesses','business_account_memberships','accounts'];
    try{
        $pdo->exec('CREATE TEMPORARY TABLE businesses (id INTEGER NOT NULL,public_id VARCHAR(160) NOT NULL)');
        $pdo->exec('CREATE TEMPORARY TABLE accounts (id INTEGER NOT NULL,status VARCHAR(32) NOT NULL)');
        $pdo->exec('CREATE TEMPORARY TABLE business_account_memberships (business_id INTEGER NOT NULL,account_id INTEGER NOT NULL,status VARCHAR(32) NOT NULL)');
        $pdo->exec("INSERT INTO businesses VALUES (1,'business-a'),(2,'business-b'),(3,'business-c')");
        $pdo->exec("INSERT INTO accounts VALUES (1,'active'),(2,'active'),(3,'disabled')");
        $pdo->exec("INSERT INTO business_account_memberships VALUES (1,1,'active'),(2,2,'active'),(2,1,'inactive'),(3,3,'active')");
        $selector=new BusinessAudienceAccessSelector($pdo);
        $sql='SELECT b.id FROM businesses b JOIN business_account_memberships m ON m.business_id=b.id JOIN accounts a ON a.id=m.account_id WHERE b.public_id=? AND m.account_id=? AND m.status="active" AND a.status="active" LIMIT 1';
        $cases=[['business-a',1,true],['business-a',2,false],['business-b',1,false],['business-b',2,true],['business-c',3,false],['missing',1,false],['business-a',999,false],["business-a' OR 1=1 --",1,false],['business-a',0,false]];
        foreach($cases as [$publicId,$accountId,$expected]){
            $original=$pdo->prepare($sql);$original->execute([$publicId,$accountId]);$before=(bool)$original->fetchColumn();
            $after=$selector->hasActiveMembership($publicId,$accountId);
            if($before!==$after||$after!==$expected)throw new RuntimeException('Authorization query changed for synthetic case.');
        }
        if((int)$pdo->query('SELECT COUNT(*) FROM business_account_memberships')->fetchColumn()!==4)throw new RuntimeException('Selector modified membership data.');
    }finally{
        $sqlite=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach($tables as $table)$pdo->exec($sqlite?'DROP TABLE IF EXISTS temp.'.$table:'DROP TEMPORARY TABLE IF EXISTS '.$table);
    }
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    checkBusinessAudienceAccessSelector(new PDO('sqlite::memory:'));
    echo "OK: 9 audience access cases on disposable SQLite tables.\n";
}

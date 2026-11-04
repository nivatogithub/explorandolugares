<?php
declare(strict_types=1);
/** Only use a disposable connection: creates a temporary shadow table. */
function checkPublicRequestSelector(PDO $pdo):void
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TEMPORARY TABLE public_requests (id INTEGER NOT NULL PRIMARY KEY,reference VARCHAR(24) NOT NULL,name VARCHAR(180) NOT NULL,status VARCHAR(20) NOT NULL)');
    $selector=new PublicRequestSelector($pdo);
    if($selector->recent()!==[])throw new RuntimeException('Empty list changed.');
    $insert=$pdo->prepare('INSERT INTO public_requests (id,reference,name,status) VALUES (?,?,?,?)');
    for($id=1;$id<=105;$id++)$insert->execute([$id,'synthetic-'.$id,'Test '.$id,$id%2?'pending':'resolved']);
    $expected=$pdo->query('SELECT * FROM public_requests ORDER BY id DESC LIMIT 100')->fetchAll();$actual=$selector->recent();
    if($actual!==$expected||count($actual)!==100||(int)$actual[0]['id']!==105||(int)$actual[99]['id']!==6)throw new RuntimeException('List, order or limit changed.');
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){require dirname(__DIR__).'/app/PublicRequestSelector.php';checkPublicRequestSelector(new PDO('sqlite::memory:'));echo "OK: empty and 105 synthetic contact requests; order and limit preserved.\n";}

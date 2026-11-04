<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Reserved dev only.');
$config=require '/srv/state/private/config.php';if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic local database required.');
require '/srv/state/lead-inbox-test/LeadInboxSelector.php';require '/srv/state/lead-inbox-test/LeadInboxWriter.php';
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('CREATE TEMPORARY TABLE leads(id INT PRIMARY KEY,business VARCHAR(190),name VARCHAR(190),email VARCHAR(254),phone VARCHAR(40),municipality VARCHAR(190),status VARCHAR(30)) ENGINE=InnoDB');
$q=$pdo->prepare('INSERT INTO leads VALUES(?,?,?,?,?,?,?)');for($i=1;$i<=60;$i++)$q->execute([$i,'Synthetic '.$i,'Person '.$i,'test@example.invalid','','Jericó',$i%2?'nuevo':'revisado']);
$s=new LeadInboxSelector($pdo);$checks=0;
foreach([['','',1],['','',2],['','',999],['nuevo','',1],['revisado','Synthetic',1],['','Synthetic 2',1],['','nomatch',1],['','%',1]] as [$status,$search,$page]){
 $conditions=[];$params=[];if($status!==''){$conditions[]='status=?';$params[]=$status;}if($search!==''){$conditions[]='(business LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ? OR municipality LIKE ?)';$needle='%'.$search.'%';array_push($params,$needle,$needle,$needle,$needle,$needle);}$where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
 $q=$pdo->prepare('SELECT COUNT(*) FROM leads'.$where);$q->execute($params);$total=(int)$q->fetchColumn();$expectedPage=min($page,max(1,(int)ceil($total/25)));$offset=($expectedPage-1)*25;$q=$pdo->prepare('SELECT * FROM leads'.$where.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset);$q->execute($params);$expected=['total'=>$total,'page'=>$expectedPage,'rows'=>$q->fetchAll()];
 if($s->page($status,$search,$page)!==$expected)throw new RuntimeException('Filter or pagination mismatch.');$checks++;
}
(new LeadInboxWriter($pdo))->delete([1,2]);if((int)$pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn()!==58)throw new RuntimeException('Synthetic delete failed.');
echo "OK MariaDB: $checks filter/page scenarios and deletion on a temporary table only.\n";

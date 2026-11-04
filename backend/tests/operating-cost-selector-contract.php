<?php
declare(strict_types=1);
if(!class_exists('OperatingCostSelector',false))require __DIR__.'/../app/OperatingCostSelector.php';

function checkOperatingCostSelector(PDO $pdo):void
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $tables=['operating_cost_evidence','operating_costs','destination_translations','destinations'];
    try{
        $pdo->exec('CREATE TEMPORARY TABLE destinations (id INTEGER NOT NULL)');
        $pdo->exec('CREATE TEMPORARY TABLE destination_translations (destination_id INTEGER NOT NULL,locale VARCHAR(10),display_name VARCHAR(100))');
        $pdo->exec('CREATE TEMPORARY TABLE operating_costs (id INTEGER NOT NULL,destination_id INTEGER,cost_date VARCHAR(10),description VARCHAR(100))');
        $pdo->exec('CREATE TEMPORARY TABLE operating_cost_evidence (id INTEGER NOT NULL,cost_id INTEGER NOT NULL,original_name VARCHAR(100),file_size INTEGER)');
        $pdo->exec('INSERT INTO destinations VALUES(1),(2)');
        $pdo->exec("INSERT INTO destination_translations VALUES(1,'es','Zeta'),(1,'en','Zed'),(2,'es','Alfa')");
        $insert=$pdo->prepare('INSERT INTO operating_costs VALUES(?,?,?,?)');
        for($i=1;$i<=205;$i++)$insert->execute([$i,$i%2?1:null,'2026-01-'.sprintf('%02d',1+$i%28),'Synthetic '.$i]);
        $pdo->exec("INSERT INTO operating_cost_evidence VALUES(3,205,'c.pdf',3),(1,205,'a.png',1),(2,1,'b.pdf',2)");
        $selector=new OperatingCostSelector($pdo);
        $places=$pdo->query("SELECT d.id,t.display_name FROM destinations d JOIN destination_translations t ON t.destination_id=d.id AND t.locale='es' ORDER BY t.display_name")->fetchAll();
        $costs=$pdo->query('SELECT c.*,t.display_name AS destination_name FROM operating_costs c LEFT JOIN destination_translations t ON t.destination_id=c.destination_id AND t.locale="es" ORDER BY c.cost_date DESC,c.id DESC LIMIT 200')->fetchAll();
        if($selector->places()!==$places||$selector->recent()!==$costs||count($costs)!==200)throw new RuntimeException('Place or cost reads changed.');
        $ids=array_column($costs,'id');$q=$pdo->prepare('SELECT id,cost_id,original_name,file_size FROM operating_cost_evidence WHERE cost_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id');$q->execute($ids);$expected=[];foreach($q as $file)$expected[$file['cost_id']][]=$file;
        if($selector->evidenceByCost($costs)!==$expected||$selector->evidenceByCost([])!==[])throw new RuntimeException('Evidence grouping changed.');
        if((int)$pdo->query('SELECT COUNT(*) FROM operating_costs')->fetchColumn()!==205)throw new RuntimeException('Selector modified costs.');
    }finally{
        $sqlite=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        foreach($tables as $table)$pdo->exec($sqlite?'DROP TABLE IF EXISTS temp.'.$table:'DROP TEMPORARY TABLE IF EXISTS '.$table);
    }
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){checkOperatingCostSelector(new PDO('sqlite::memory:'));echo "OK: cost reads, ordering, 200-item limit and evidence groups.\n";}

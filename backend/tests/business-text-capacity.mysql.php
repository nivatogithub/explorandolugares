<?php
declare(strict_types=1);
// Local disposable MariaDB database only; never run on the hosting.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local test only.');
$sql=getenv('EL_TEXT_RULES_SQL');
$sql=$sql?base64_decode($sql,true):file_get_contents('/srv/portal/app/database/migrations/053-current-business-text-rules.mysql.sql');
if(!$sql)throw new RuntimeException('Migration unavailable.');
$budgetSql=getenv('EL_DESCRIPTION_BUDGETS_SQL');
$budgetSql=$budgetSql?base64_decode($budgetSql,true):file_get_contents('/srv/portal/app/database/migrations/054-business-description-budgets.mysql.sql');
if(!$budgetSql)throw new RuntimeException('Description budget migration unavailable.');
$admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_text_'.bin2hex(random_bytes(6));
if(!preg_match('/^el_test_text_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Invalid test database.');
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
try{
    $p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $p->exec('CREATE TABLE subscription_plans(code VARCHAR(24) PRIMARY KEY,max_summary_chars INT,max_description_chars INT,max_brand_story_chars INT,max_featured_services_chars INT) ENGINE=InnoDB');
    $p->exec("INSERT INTO subscription_plans VALUES('free',0,0,0,0),('featured',500,1000,0,0),('page',750,2000,0,0),('page_premium',1000,3500,1500,1500)");
    $p->exec('CREATE TABLE business_translations(id INT PRIMARY KEY,summary VARCHAR(1000) NOT NULL DEFAULT \'\') ENGINE=InnoDB');
    $p->exec("INSERT INTO business_translations VALUES(1,'Conservar contenido')");
    $apply=static function()use($p,$sql):void{$clean=preg_replace('/^[ \t]*--[^\r\n]*(?:\r?\n|$)/m','',$sql);foreach(explode(';',$clean) as $statement)if(trim($statement)!=='')$p->exec($statement);};
    $apply();$before=$p->query('SELECT * FROM subscription_plans ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);$apply();
    if($before!==$p->query('SELECT * FROM subscription_plans ORDER BY code')->fetchAll(PDO::FETCH_ASSOC))throw new RuntimeException('Migration not idempotent.');
    if($p->query('SELECT summary FROM business_translations WHERE id=1')->fetchColumn()!=='Conservar contenido')throw new RuntimeException('Lost existing content.');
    $p->prepare('INSERT INTO business_translations VALUES(2,?)')->execute([str_repeat('á',1200)]);
    if((int)$p->query('SELECT CHAR_LENGTH(summary) FROM business_translations WHERE id=2')->fetchColumn()!==1200)throw new RuntimeException('Cannot store 1200 Unicode characters.');
    $p->exec('ALTER TABLE business_translations MODIFY summary TEXT NOT NULL');$apply();
    $type=$p->query("SHOW COLUMNS FROM business_translations LIKE 'summary'")->fetch(PDO::FETCH_ASSOC)['Type'];
    if(strtolower($type)!=='text')throw new RuntimeException('Must not shrink wider storage.');
    $p->exec($budgetSql);$budgets=$p->query('SELECT code,max_summary_chars FROM subscription_plans ORDER BY code')->fetchAll(PDO::FETCH_KEY_PAIR);$p->exec($budgetSql);
    if(array_map('intval',$budgets)!==['featured'=>100,'free'=>0,'page'=>300,'page_premium'=>600]||$budgets!==$p->query('SELECT code,max_summary_chars FROM subscription_plans ORDER BY code')->fetchAll(PDO::FETCH_KEY_PAIR))throw new RuntimeException('Description budgets disagree or are not idempotent.');
    if((int)$p->query('SELECT CHAR_LENGTH(summary) FROM business_translations WHERE id=2')->fetchColumn()!==1200)throw new RuntimeException('New quotas truncated existing content.');
    echo "OK MariaDB: 1000→1200 Unicode capacity retained, 054 twice sets 0/100/300/600, existing long content retained, wider TEXT not shrunk.\n";
}finally{$admin->exec('DROP DATABASE `'.$name.'`');}

<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Disposable local test only.');
$sql=base64_decode((string)getenv('EL_DRAFT_SQL'),true);
$source=base64_decode((string)getenv('EL_DRAFT_CLASS'),true);
if(!$sql||!$source)throw new RuntimeException('Test inputs unavailable.');
eval(substr($source,5));
$admin=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='el_test_draft_'.bin2hex(random_bytes(6));if(!preg_match('/^el_test_draft_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Test name not allowed.');
$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
try{
 $p=new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname='.$name.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $p->exec('CREATE TABLE accounts(id BIGINT UNSIGNED PRIMARY KEY,status VARCHAR(20),email_verified_at DATETIME) ENGINE=InnoDB');
 $p->exec('CREATE TABLE businesses(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
 $p->exec('CREATE TABLE destinations(id BIGINT UNSIGNED PRIMARY KEY,publication_status VARCHAR(20)) ENGINE=InnoDB');
 $p->exec('CREATE TABLE business_onboarding_uploads(id CHAR(48) PRIMARY KEY,draft_token CHAR(48),consumed_at DATETIME,expires_at DATETIME) ENGINE=InnoDB');
 $apply=static function()use($p,$sql):void{foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$p->exec($statement);};
 $apply();$apply();
 $p->exec('INSERT INTO accounts VALUES(1,"active",CURRENT_TIMESTAMP),(2,"active",CURRENT_TIMESTAMP);INSERT INTO destinations VALUES(1,"published")');
 $service=new BusinessRegistrationDrafts($p);$id=str_repeat('a',48);
 $input=['application_type'=>'new_business','business_name'=>'Borrador Unicode áéí','destination_id'=>'1','summary'=>'Texto sin terminar','plan_code'=>'page_premium'];
 $service->save(1,$id,0,2,$input);$service->save(1,$id,1,3,$input);
 if((int)$service->load(1,$id)['revision']!==2)throw new RuntimeException('Lost draft revision.');
 $denied=false;try{$service->load(2,$id);}catch(RuntimeException){$denied=true;}if(!$denied)throw new RuntimeException('Cross-account access.');
 if((int)$p->query('SELECT COUNT(*) FROM businesses')->fetchColumn()!==0)throw new RuntimeException('Partial save created a business.');
 $service->finish(1,$id,null);if($p->query('SELECT status FROM business_registration_drafts')->fetchColumn()!=='submitted')throw new RuntimeException('Submission not locked.');
 echo "OK MariaDB: migration 055 twice, Unicode draft, revision updates, account isolation, no early business and completed capture lock.\n";
}finally{$admin->exec('DROP DATABASE `'.$name.'`');}

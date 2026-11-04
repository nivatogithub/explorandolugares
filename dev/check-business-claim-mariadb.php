<?php
declare(strict_types=1);
// One connection and temporary tables only: live dev rows are shadowed, never modified.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local dev only.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic development database required.');
$base=$argv[1]??'';
if(!in_array($base,['/srv/portal/app','/srv/state/architecture-claim-writer-candidate'],true))throw new RuntimeException('Unexpected application path.');
function throttle(...$args):bool{return true;}
$_SESSION=['business_account_id'=>1];
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require $base.'/BusinessClaimService.php';
foreach(['business_claim_requests','businesses','accounts'] as $table)$pdo->exec('DROP TEMPORARY TABLE IF EXISTS '.$table);
$pdo->exec('CREATE TEMPORARY TABLE accounts(id INT PRIMARY KEY,account_type VARCHAR(20),status VARCHAR(20),display_name VARCHAR(180),email VARCHAR(254)) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE businesses(id INT PRIMARY KEY,claim_status VARCHAR(30),publication_status VARCHAR(30),updated_at TIMESTAMP NULL) ENGINE=InnoDB');
$pdo->exec('CREATE TEMPORARY TABLE business_claim_requests(id INT AUTO_INCREMENT PRIMARY KEY,business_id INT,account_id INT,claimant_name VARCHAR(180),email VARCHAR(254),phone VARCHAR(24),relationship_code VARCHAR(40),message TEXT,consent_version VARCHAR(60),status VARCHAR(30),admin_note VARCHAR(1000),reviewed_at TIMESTAMP NULL,updated_at TIMESTAMP NULL) ENGINE=InnoDB');
$pdo->exec("INSERT INTO accounts VALUES(1,'business','active','Synthetic Owner','test@example.invalid')");
$pdo->exec("INSERT INTO businesses(id,claim_status,publication_status) VALUES(1,'NO_RECLAMADO','published')");
$service=new BusinessClaimService($pdo);
$input=['business_id'=>1,'relationship_code'=>'owner','consent'=>'1'];
$id=$service->submit($input);
$returnedId=$id;$expectedId=(int)($argv[2]??1);
if(!in_array($expectedId,[0,1],true)||$id!==$expectedId||$pdo->query('SELECT claim_status FROM businesses')->fetchColumn()!=='EN_TRAMITE')throw new RuntimeException('Unexpected returned ID or claim status.');
$id=(int)$pdo->query('SELECT id FROM business_claim_requests')->fetchColumn();
try{$service->submit($input);throw new RuntimeException('Duplicate claim accepted.');}catch(RuntimeException $e){if($e->getMessage()==='Duplicate claim accepted.')throw $e;}
if((int)$pdo->query('SELECT COUNT(*) FROM business_claim_requests')->fetchColumn()!==1)throw new RuntimeException('Duplicate wrote a row.');
$service->updateRequest($id,'in_review','Review');
$pdo->exec('ALTER TABLE businesses DROP COLUMN updated_at');
try{$service->updateRequest($id,'not_approved','Closed');throw new RuntimeException('Expected review database error.');}catch(PDOException $expected){}
if($pdo->inTransaction()||$pdo->query('SELECT status FROM business_claim_requests')->fetchColumn()!=='in_review'||$pdo->query('SELECT claim_status FROM businesses')->fetchColumn()!=='EN_TRAMITE')throw new RuntimeException('Review rollback failed.');
$pdo->exec('ALTER TABLE businesses ADD COLUMN updated_at TIMESTAMP NULL');
$service->updateRequest($id,'not_approved','Closed');
if($pdo->query('SELECT claim_status FROM businesses')->fetchColumn()!=='NO_RECLAMADO')throw new RuntimeException('Rejected claim was not released.');
// Remove a column in a temporary table so the update fails after insert; rollback must remove the inserted request.
$pdo->exec('ALTER TABLE businesses DROP COLUMN updated_at');
try{$service->submit($input);throw new RuntimeException('Expected database error.');}catch(PDOException $expected){}
if($pdo->inTransaction()||(int)$pdo->query('SELECT COUNT(*) FROM business_claim_requests')->fetchColumn()!==1||$pdo->query('SELECT claim_status FROM businesses')->fetchColumn()!=='NO_RECLAMADO')throw new RuntimeException('Rollback failed.');
echo json_encode(['driver'=>'MariaDB','temporary_tables_only'=>true,'submitted'=>true,'returned_id_correct'=>$returnedId===1,'duplicate_rejected'=>true,'review_and_release'=>true,'rollback_after_insert'=>true,'rollback_during_review'=>true],JSON_THROW_ON_ERROR)."\n";

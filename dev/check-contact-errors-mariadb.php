<?php
declare(strict_types=1);
// This test shadows the application table in one connection; no dev rows are changed.
if(PHP_SAPI!=='cli'||getenv('EL_PREPROD_LOCAL')!=='1')throw new RuntimeException('Local dev only.');
$config=require '/srv/state/private/config.php';
if(empty($config['local_test'])||!str_contains($config['dsn'],'host=127.0.0.1;dbname=el_test_destination_'))throw new RuntimeException('Synthetic dev database required.');
$pdo=new PDO($config['dsn'],$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TEMPORARY TABLE public_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,reference CHAR(24) NOT NULL UNIQUE,request_type VARCHAR(20) NOT NULL,name VARCHAR(180) NOT NULL,email VARCHAR(254) NOT NULL,message TEXT NOT NULL,status VARCHAR(20) NOT NULL DEFAULT "pending",response_note TEXT NULL,reviewed_at DATETIME NULL,consent_version VARCHAR(60) NOT NULL) ENGINE=InnoDB');
require '/srv/portal/app/PublicRequestService.php';
$service=new PublicRequestService($pdo);$valid=['request_type'=>'suggestion','name'=>'Synthetic Name','email'=>'synthetic@example.invalid','message'=>'Synthetic request only','consent'=>'1'];
foreach(['invalid_type'=>['request_type'=>'invalid'],'invalid_name'=>['name'=>'x'],'invalid_email'=>['email'=>'bad'],'invalid_message'=>['message'=>'bad'],'consent_required'=>['consent'=>'0']] as $reason=>$input){
 try{$service->submit(array_replace($valid,$input));throw new LogicException('Invalid request was accepted.');}
 catch(RuntimeException $e){if(class_exists('PublicRequestException',false)&&(!($e instanceof PublicRequestException)||$e->reason!==$reason))throw new LogicException('Unexpected rejection reason.');}
 if((int)$pdo->query('SELECT COUNT(*) FROM public_requests')->fetchColumn()!==0)throw new LogicException('Rejection wrote a request.');
}
$reference=$service->submit($valid);
if(strlen($reference)!==24||(int)$pdo->query('SELECT COUNT(*) FROM public_requests')->fetchColumn()!==1)throw new LogicException('Valid submission failed.');
$service->review(1,'resolved','Synthetic review');
$row=$pdo->query('SELECT status,response_note,reviewed_at FROM public_requests WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if($row['status']!=='resolved'||$row['response_note']!=='Synthetic review'||empty($row['reviewed_at']))throw new LogicException('Review failed.');
echo "OK MariaDB: cinco rechazos y un envío en tabla temporal; sin cambios en filas reales.\n";

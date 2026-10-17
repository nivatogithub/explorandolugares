<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/DatabaseChangePackage.php';
function check_package(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "OK: $message\n";}
$sql='CREATE TABLE package_example (id INT PRIMARY KEY);';$file='999-example.mysql.sql';$catalog=['999-example'=>['file'=>$file,'sha256'=>hash('sha256',$sql)]];
$manifest=['format'=>'explorandolugares-db-v1','engine'=>'mysql-mariadb','migrations'=>[['id'=>'999-example','file'=>$file,'sha256'=>hash('sha256',$sql)]]];
function make_package(array $entries):string{$path=tempnam(sys_get_temp_dir(),'el-db-');$zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);foreach($entries as $name=>$data)$zip->addFromString($name,$data);$zip->close();return $path;}
$valid=['manifest.json'=>json_encode($manifest),$file=>$sql];$path=make_package($valid);try{check_package(DatabaseChangePackage::validate($path,$catalog)===['999-example'],'Accept exact registered script');}finally{unlink($path);}
foreach(['tampered'=>array_replace($valid,[$file=>$sql.' DROP TABLE accounts;']),'traversal'=>$valid+['../extra.php'=>'bad'],'extra'=>$valid+['notes.txt'=>'bad'],'missing'=>['manifest.json'=>json_encode($manifest)],'unknown'=>array_replace($valid,['manifest.json'=>str_replace('999-example','998-unknown',json_encode($manifest))])] as $name=>$entries){$path=make_package($entries);$rejected=false;try{DatabaseChangePackage::validate($path,$catalog);}catch(Throwable){$rejected=true;}finally{unlink($path);}check_package($rejected,'Reject '.$name);}
require dirname(__DIR__).'/app/SystemHealth.php';
$pdo=new PDO('sqlite::memory:');
$health=SystemHealth::inspect($pdo,['hosting_storage'=>['quota_bytes'=>100,'used_bytes'=>80,'measured_at'=>gmdate('c')]],sys_get_temp_dir());check_package($health['storage']['percent']===80.0,'Storage reaches red threshold at 80 percent');
check_package(SystemHealth::inspect($pdo,[],sys_get_temp_dir())['storage']===null,'Unknown account quota remains unknown');

<?php
declare(strict_types=1);
/** Disposable databases only. No production configuration or datasets are loaded. */
require dirname(__DIR__).'/app/BusinessSelfOnboarding.php';
require dirname(__DIR__).'/app/LeadReplyService.php';
require dirname(__DIR__).'/app/BusinessEditorialService.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function connection(?string $database=null):PDO{return new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock'.($database?';dbname='.$database:'').';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
$admin=connection();$database='el_admin_repair_test_'.bin2hex(random_bytes(6));$restore=$database.'_restore';$children=[];
try{
 $admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET latin1 COLLATE latin1_swedish_ci');$p=connection($database);
 $root=dirname(__DIR__).'/database';$p->exec(file_get_contents($root.'/mysql.sql'));$p->exec(file_get_contents($root.'/migrations/003-dynamic-destinations.mysql.sql'));$p->exec(file_get_contents($root.'/migrations/004-media-origin.mysql.sql'));$p->exec(file_get_contents($root.'/migrations/006-poi-experience-links.mysql.sql'));$p->exec(file_get_contents($root.'/migrations/007-media-provenance.mysql.sql'));
 $files=glob($root.'/migrations/*.mysql.sql');sort($files);foreach($files as $file)if((int)basename($file)>=8)$p->exec(file_get_contents($file));
 foreach(['047-lead-business-links','048-individual-lead-replies','049-business-editorial'] as $name)$p->exec(file_get_contents($root.'/migrations/'.$name.'.mysql.sql'));
 $p->exec('INSERT INTO leads(id,request_key,name,business,municipality,category,has_brand,description,interest,wants_contact,email,consent_version,created_at) VALUES(1,"synthetic","Persona","Comercio ficticio","Lugar ficticio","hospedaje","no","Prueba","negocio",1,"test@example.invalid","test",CURRENT_TIMESTAMP)');
 $p->exec('INSERT INTO destinations(id,slug,publication_status) VALUES(1,"lugar-ficticio","published");INSERT INTO destination_translations(destination_id,locale,display_name,headline,intro) VALUES(1,"es","Lugar ficticio","","");INSERT INTO service_categories(code,is_active) VALUES("hospedaje",1)');
 foreach([1,2] as $account)$p->prepare('INSERT INTO accounts(id,public_id,account_type,display_name,email,status,email_verified_at) VALUES(?,?,"traveler",?,?,"active",CURRENT_TIMESTAMP)')->execute([$account,'persona-'.$account,'Persona ficticia '.$account,'persona'.$account.'@example.invalid']);
 $details=['onboarding_policy'=>BusinessSelfOnboarding::POLICY,'responsibility_declared'=>true,'categories'=>['hospedaje'],'location'=>['address_text'=>'','location_reference'=>'','latitude'=>null,'longitude'=>null,'location_source'=>null,'location_map_url'=>'','location_confirmed'=>0,'opening_hours'=>'','price_range'=>'','capacity_count'=>null,'menu_url'=>'','reservations_url'=>'']];
 foreach([1,2] as $account){$p->prepare('INSERT INTO business_account_applications(id,account_id,application_type,destination_id,category_code,business_name,relationship_code,consent_version,business_details_json) VALUES(?,?,"request_new",1,"hospedaje","Comercio Ficticio Concurrente","owner","test",?)')->execute([$account,$account,json_encode($details)]);$p->prepare('INSERT INTO business_account_application_evidence(application_id,evidence_type,storage_name,original_name,mime_type,file_size) VALUES(?,"business_registration","synthetic.pdf","ficticio.pdf","application/pdf",100)')->execute([$account]);}
 // Fork with independent connections; never reuse a parent PDO connection in children.
 $p=null;$admin=null;
 foreach([1,2] as $id){$pid=pcntl_fork();if($pid===0){try{$db=connection($database);$db->beginTransaction();BusinessSelfOnboarding::complete($db,$id);$db->commit();exit(0);}catch(Throwable $e){fwrite(STDERR,'Synthetic onboarding worker failed: '.$e->getMessage()."\n");exit(1);}}$children[]=$pid;}
 foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wexitstatus($status)===0,'Concurrent onboarding failed');}$children=[];$p=connection($database);$admin=connection();
 check((int)$p->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===1,'Concurrent duplicate created');
 check((int)$p->query('SELECT COUNT(*) FROM business_account_memberships')->fetchColumn()===1,'Concurrent duplicate membership');
 check((int)$p->query('SELECT COUNT(*) FROM business_subscriptions')->fetchColumn()===1,'Concurrent duplicate subscription');
 check((int)$p->query('SELECT COUNT(*) FROM business_account_applications WHERE status="needs_selection"')->fetchColumn()===1,'Duplicate must request explicit selection');
 // Exercise the editorial transaction with MariaDB, not only its migration DDL.
 $winner=$p->query('SELECT account_id,business_id FROM business_account_memberships LIMIT 1')->fetch(PDO::FETCH_ASSOC);$business=(int)$winner['business_id'];$account=(int)$winner['account_id'];
 $editorial=new BusinessEditorialService($p,dirname(__DIR__).'/editorial-references');$original=$editorial->live($business);
 $revision=$editorial->save($account,$business,0,['translations'=>['es'=>['display_name'=>'Comercio Ficticio Editorial']]]);$version=$editorial->version($revision);check($editorial->live($business)===$original,'MariaDB private save changed live data');
 $editorial->transition($revision,'submit',$account,'','',$version['payload_hash']);
 $revision=$editorial->save(0,$business,$revision,['translations'=>['en'=>['display_name'=>'Fictitious Editorial Business'],'pt-BR'=>['display_name'=>'Negócio Editorial Fictício']]],false,true,'administrador-ficticio');$version=$editorial->version($revision);
 $editorial->transition($revision,'prepare',0,'administrador-ficticio','',$version['payload_hash']);check($editorial->live($business)===$original,'MariaDB prepare savepoint committed changes');
 $editorial->transition($revision,'approve',$account,'','',$version['payload_hash']);
 $p->prepare('UPDATE businesses SET information_status="VERIFICADO" WHERE id=?')->execute([$business]);$editorial->publish($revision,'administrador-ficticio',$version['payload_hash']);
 check($editorial->latest($business)['status']==='published','MariaDB exact version did not publish');check($editorial->live($business)['translations']['es']['display_name']==='Comercio Ficticio Editorial','MariaDB revision not applied');
 if(is_dir(dirname(__DIR__).'/editorial-references'))rmdir(dirname(__DIR__).'/editorial-references');
 $settings=['enabled'=>true,'api_key'=>'mock','sender_email'=>'sender@example.invalid','sender_name'=>'Pruebas','reply_to'=>'reply@example.invalid'];
 $s=new LeadReplyService($p);$reply=$s->prepare(1,['subject'=>'Prueba sintética','message'=>'Respuesta de prueba'],$settings,'test');$p->exec('CREATE TABLE test_mail_deliveries(id INTEGER AUTO_INCREMENT PRIMARY KEY,operation_id CHAR(32) NOT NULL)');$s=null;$p=null;$admin=null;
 foreach([1,2] as $worker){$pid=pcntl_fork();if($pid===0){try{$db=connection($database);$transport=new BrevoTransactionalClient($settings,function($payload)use($db){$db->prepare('INSERT INTO test_mail_deliveries(operation_id) VALUES(?)')->execute([$payload['headers']['X-EL-Operation']]);usleep(200000);return ['status'=>201,'body'=>['messageId'=>'mock-concurrent']];});(new LeadReplyService($db))->send(1,$reply['id'],$reply['content_hash'],$settings,'test',$transport);exit(0);}catch(Throwable $e){fwrite(STDERR,'Synthetic reply worker failed: '.$e->getMessage()."\n");exit(1);}}$children[]=$pid;}
 foreach($children as $pid){pcntl_waitpid($pid,$status);check(pcntl_wexitstatus($status)===0,'Concurrent reply failed');}$children=[];$p=connection($database);$admin=connection();
 check((int)$p->query('SELECT COUNT(*) FROM test_mail_deliveries')->fetchColumn()===1,'Double send occurred');check((int)$p->query('SELECT COUNT(*) FROM lead_reply_attempts')->fetchColumn()===1,'Double attempt occurred');
 // Restore a dump of synthetic data into another disposable database, never over the source.
 $admin->exec('CREATE DATABASE `'.$restore.'` CHARACTER SET latin1 COLLATE latin1_swedish_ci');
 $process=proc_open(['mariadb-dump','--single-transaction','--skip-comments',$database],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$dump=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($process)===0,'Synthetic backup failed');
 $restored=connection($restore);$restored->exec($dump);foreach(['businesses','business_account_memberships','business_subscriptions','lead_reply_operations','lead_reply_attempts','business_editorial_versions','business_editorial_events','business_editorial_images'] as $table)check($p->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$restored->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(),'Restored count differs: '.$table);
 echo 'OK MariaDB '.$p->query('SELECT VERSION()')->fetchColumn().": migrations 047/048, automatic Free concurrency, duplicate protection, single reply across two processes and synthetic backup/restore\n";
}finally{
 foreach($children as $pid)pcntl_waitpid($pid,$status);
 $admin=connection();foreach([$restore,$database] as $name)if(preg_match('/^el_admin_repair_test_[a-f0-9]{12}(?:_restore)?$/D',$name))$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}

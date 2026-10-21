<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/LeadReplyService.php';
function check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$p->exec('CREATE TABLE leads(id INTEGER PRIMARY KEY,email TEXT,wants_contact INTEGER,status TEXT);INSERT INTO leads VALUES(1,"persona@example.invalid",1,"nuevo"),(2,"no@example.invalid",0,"nuevo");CREATE TABLE subscription_plan_prices(plan_code TEXT,billing_frequency TEXT,amount NUMERIC);CREATE TABLE subscription_plan_translations(plan_code TEXT,locale TEXT,name TEXT);INSERT INTO subscription_plan_prices VALUES("featured","monthly",49900);INSERT INTO subscription_plan_translations VALUES("featured","es","Destacado")');$p->exec(file_get_contents(dirname(__DIR__).'/database/migrations/048-individual-lead-replies.sqlite.sql'));
$s=new LeadReplyService($p);$settings=['enabled'=>true,'api_key'=>'fake','sender_email'=>'sender@example.invalid','sender_name'=>'Pruebas','reply_to'=>'reply@example.invalid'];$calls=0;
$client=new BrevoTransactionalClient($settings,function($payload)use(&$calls){$calls++;check(!isset($payload['listIds']),'no campaigns');return ['status'=>201,'body'=>['messageId'=>'mock-1']];});
$row=$s->prepare(1,['subject'=>'Solicitud','message'=>'Hola <script>bad()</script>'],$settings,'test');check($calls===0,'preview never sends');check(str_contains($row['html_body'],'&lt;script&gt;'),'escape message');check(str_contains($row['text_body'],'49.900'),'current prices');
$oldHash=$row['content_hash'];$row=$s->prepare(1,['subject'=>'Cambio','message'=>'Otro mensaje'],$settings,'test',$row['id']);
try{$s->send(1,$row['id'],$oldHash,$settings,'test',$client);throw new RuntimeException('accepted stale hash');}catch(RuntimeException $e){check($e->getMessage()!=='accepted stale hash','stale version rejected');}check($calls===0,'stale version no transport');
check($s->send(1,$row['id'],$row['content_hash'],$settings,'test',$client)==='accepted','provider accepted');check($s->send(1,$row['id'],$row['content_hash'],$settings,'test',$client)==='accepted','repeat stable status');check($calls===1,'only one transport call');
check($p->query('SELECT status FROM leads WHERE id=1')->fetchColumn()==='nuevo','send does not mark contacted');
$row=$s->prepare(1,['subject'=>'Timeout','message'=>'Mensaje incierto'],$settings,'test');$timeout=new BrevoTransactionalClient($settings,function($payload)use(&$calls){$calls++;throw new RuntimeException('timeout');});check($s->send(1,$row['id'],$row['content_hash'],$settings,'test',$timeout)==='uncertain','timeout uncertain');$before=$calls;$s->send(1,$row['id'],$row['content_hash'],$settings,'test',$client);check($calls===$before,'uncertain never retried');
try{$s->prepare(1,['subject'=>'No repetir','message'=>'No repetir'],$settings,'test');throw new RuntimeException('uncertain bypassed');}catch(RuntimeException $e){check($e->getMessage()!=='uncertain bypassed','new operation blocked while uncertain');}
$s->reconcile(1,$row['id'],'failed','support-test-123','El proveedor confirmó en soporte que no aceptó esta operación sintética.','test');
check($s->find($row['id'],1)['reconciled_by']==='test','reconciliation audited');
check($p->query('SELECT outcome FROM lead_reply_attempts WHERE operation_id='.$p->quote($row['id']))->fetchColumn()==='uncertain','original uncertain attempt preserved');
$row=$s->prepare(1,['subject'=>'Cambio remitente','message'=>'Mensaje'],$settings,'test');$changed=$settings;$changed['reply_to']='changed@example.invalid';try{$s->send(1,$row['id'],$row['content_hash'],$changed,'test',$client);throw new RuntimeException('accepted changed settings');}catch(RuntimeException $e){check($e->getMessage()!=='accepted changed settings','settings change invalidates confirmation');}
try{$s->prepare(2,['subject'=>'No','message'=>'No'],$settings,'test');throw new RuntimeException('contacted opt-out');}catch(RuntimeException $e){check($e->getMessage()!=='contacted opt-out','no contact without permission');}
check((int)$p->query('SELECT COUNT(*) FROM lead_reply_attempts')->fetchColumn()===2,'one attempt each sent operation');
echo "OK: draft snapshot, preview, content/settings confirmation, HTML escape, single send, uncertain timeout, no marketing or status side effects\n";

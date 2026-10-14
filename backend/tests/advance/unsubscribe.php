<?php
declare(strict_types=1);
require __DIR__.'/service.php';
$u=new Unsubscribe($db,$testConfig);
check(str_contains($s->deliveries($cid)[0]['html'],'Dejar de recibir correos'),'Footer incluido en snapshot');
check(str_starts_with($s->deliveries($cid)[0]['unsubscribe_url'],'https://example.invalid/api/unsubscribe.php?token='),'Origen configurado');
rejects(fn()=>(new Unsubscribe($db,['app_key'=>'short','public_base_url'=>'https://example.invalid']))->assertReady());
foreach(['http://example.invalid','https://user:secret@example.invalid','https://example.invalid?redirect=evil','https://example.invalid#fragment'] as $bad)rejects(fn()=>(new Unsubscribe($db,[...$testConfig,'public_base_url'=>$bad]))->assertReady());
$token=$u->token((int)$d['id'],'persona@example.invalid');$before=$s->find('contacts',$id);
$u->resolve($token);check($s->find('contacts',$id)===$before,'Resolver enlace no modifica contacto');
rejects(fn()=>$u->resolve($token.'x'));
rejects(fn()=>$u->resolve($u->token((int)$d['id'],'otra@example.invalid')));
rejects(fn()=>(new Unsubscribe($db,[...$testConfig,'app_key'=>str_repeat('y',64)]))->resolve($token));
$copy=$s->save('contacts',[...$base,'name'=>'Segundo registro del correo','marketing_consent'=>'1','consent_evidence'=>'Prueba']);
$c=$s->createCampaign(['name'=>'Pendiente al recibir baja','template_id'=>$template,'contact_ids'=>(string)$copy]);
$versions=$db->query('SELECT id,version FROM advance_contacts ORDER BY id')->fetchAll();
$accepted=$s->deliveries($cid)[0];
$u->suppress($token);
foreach([$id,$copy] as $contactId){$r=$s->find('contacts',$contactId);check((int)$r['suppressed']===1&&(int)$r['marketing_consent']===0,'Baja en todos los registros del correo');}
check($s->deliveries($c)[0]['status']==='skipped','Pendientes excluidos');
check($s->deliveries($cid)[0]===$accepted,'Entrega ya aceptada no reescrita');
$audits=$db->query("SELECT COUNT(*) FROM advance_audit WHERE action='unsubscribe'")->fetchColumn();
$after=$db->query('SELECT id,version FROM advance_contacts ORDER BY id')->fetchAll();$u->suppress($token);
check($db->query('SELECT id,version FROM advance_contacts ORDER BY id')->fetchAll()===$after,'Baja repetida idempotente');
check($db->query("SELECT COUNT(*) FROM advance_audit WHERE action='unsubscribe'")->fetchColumn()===$audits,'Sin auditoría duplicada');
rejects(fn()=>$s->save('contacts',[...$base,'marketing_consent'=>'1','consent_evidence'=>'Prueba']));
rejects(fn()=>$s->save('contacts',[...$base,'id'=>$copy,'version'=>$s->find('contacts',$copy)['version'],'suppressed'=>'0']));
rejects(fn()=>$s->createCampaign(['name'=>'No reactivar','template_id'=>$template,'contact_ids'=>(string)$copy]));
// Una campaña histórica sin enlace no puede enviarse ni alterarse para añadirlo tras aprobar.
$db->exec("UPDATE advance_deliveries SET unsubscribe_url='' WHERE id=".(int)$did);
rejects(fn()=>$s->sendDelivery($did,$client));
$db->exec("INSERT INTO leads(request_key,name,business,municipality,category,email,phone,has_brand,description,interest,consent_version,created_at) VALUES('suppressed-import','Persona ficticia','Negocio ficticio','Prueba','hospedaje','persona@example.invalid','','no','','contacto','test','2026-10-14')");
$fromLead=$s->importLead(2);check((int)$s->find('contacts',$fromLead)['suppressed']===1,'Solicitud importada respeta baja persistente');
check(count($db->query('SELECT * FROM advance_email_suppressions')->fetchAll())===1,'Una exclusión persistente por correo');
echo "OK: enlaces firmados, GET sin efectos, baja idempotente, múltiples contactos, exclusión persistente, protección de snapshots y configuración segura.\n";

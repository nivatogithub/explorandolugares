<?php
declare(strict_types=1);
require __DIR__.'/service.php';
$db->exec("UPDATE advance_contacts SET relationship='en_revision'");
$db->exec("UPDATE advance_campaigns SET status='aprobada'");
$db->exec("UPDATE advance_deliveries SET status='incierta'");
$before=$db->query('SELECT id,campaign_id,contact_id,email,subject,html,provider_id,updated_at FROM advance_deliveries ORDER BY id')->fetchAll();
$approval=$db->query('SELECT id,approved_by,approved_at,version FROM advance_campaigns ORDER BY id')->fetchAll();
$sql=file_get_contents(dirname(__DIR__,2).'/database/migrations/009-advance-english-values.sqlite.sql');$db->exec($sql);$db->exec($sql);
check($db->query('SELECT DISTINCT relationship FROM advance_contacts')->fetchAll(PDO::FETCH_COLUMN)===['under_review'],'Relaciones inglesas');
check($db->query('SELECT DISTINCT status FROM advance_campaigns')->fetchAll(PDO::FETCH_COLUMN)===['approved'],'Campañas inglesas');
check($db->query('SELECT DISTINCT status FROM advance_deliveries')->fetchAll(PDO::FETCH_COLUMN)===['uncertain'],'No reactivar envíos inciertos');
check($before===$db->query('SELECT id,campaign_id,contact_id,email,subject,html,provider_id,updated_at FROM advance_deliveries ORDER BY id')->fetchAll(),'Payload inmutable');
check($approval===$db->query('SELECT id,approved_by,approved_at,version FROM advance_campaigns ORDER BY id')->fetchAll(),'Aprobación intacta');
AdvanceAdmin::validateTemplate('Hola {{name}}','<p>{{municipality}}</p>');
AdvanceAdmin::validateTemplate('Hola {{nombre}}','<p>{{municipio}}</p>');
// Compatibilidad: una plantilla previa sigue funcionando sin reescribir campañas aprobadas.
$s->save('contacts',[...$base,'id'=>$id,'version'=>5,'marketing_consent'=>'1','consent_evidence'=>'Prueba']);
$t=$s->save('templates',['name'=>'Legacy','subject'=>'Hola {{nombre}}','html'=>'<p>{{municipio}}</p>']);
$c=$s->createCampaign(['name'=>'Legacy local','template_id'=>$t,'contact_ids'=>(string)$id]);
check($s->deliveries($c)[0]['html']==='<p>Destino de prueba</p>','Alias anteriores preservados');
echo "OK: migración inglesa repetible, payload y aprobación preservados, envíos inciertos bloqueados y compatibilidad de plantillas.\n";

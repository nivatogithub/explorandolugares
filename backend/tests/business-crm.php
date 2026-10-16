<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessCrmRepository.php';
function crm_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/destinations.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/008-business-subscriptions.sqlite.sql'));
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/026-business-research-contacts.sqlite.sql'));
$pdo->exec('INSERT INTO destinations(id,slug) VALUES(1,"jerico"),(2,"jardin"); INSERT INTO service_categories(code) VALUES("hospedaje");');
$q=$pdo->prepare('INSERT INTO businesses(id,public_id,destination_id,category_code,slug) VALUES(?,?,?,"hospedaje",?)');
foreach([[1,'ID-uno',1,'uno'],[2,'ID-dos',1,'dos'],[3,'ID-tres',2,'tres'],[4,'ID-cuatro',1,'cuatro']] as $row)$q->execute($row);
$q=$pdo->prepare('INSERT INTO business_translations(business_id,locale,display_name,summary,description) VALUES(?,"es",?,"","")');foreach([1=>'Negocio con contactos',2=>'Negocio sin contacto',3=>'Negocio de otro destino',4=>'Cliente con suscripción paga'] as $id=>$name)$q->execute([$id,$name]);
$pdo->exec('INSERT INTO business_subscriptions(business_id,plan_code,status) VALUES(1,"free","pending_publication"),(2,"free","pending_publication"),(3,"page","expired"),(4,"page","active"); INSERT INTO business_research_contacts(business_id,channel,contact_value,source_url,consulted_at,evidence_status,source_note,legal_basis_status,record_hash) VALUES(1,"email","contacto@example.invalid","https://example.invalid/uno","2026-10-15","PUBLICADO_PARA_CLIENTES","Fuente 1","PENDIENTE","hash1"),(1,"email","contacto@example.invalid","https://example.invalid/dos","2026-10-15","PUBLICADO_PARA_CLIENTES","Fuente 2","PENDIENTE","hash2");');
$repo=new BusinessCrmRepository($pdo);$view=$repo->overview(['destination'=>'jerico']);
crm_check($view['total']===2&&$view['with_contact']===1&&$view['without_contact']===1,'CRM includes free businesses with and without contact.');
crm_check(count($view['rows'])===2&&count(array_unique(array_column($view['rows'],'id')))===2,'Several contact observations do not multiply the business row.');
crm_check((int)$repo->find(1)['research_contact_count']===1,'Multiple sources for the same channel/value count as one contact.');
crm_check($repo->overview(['destination'=>'jerico','contact'=>'without'])['rows'][0]['public_id']==='ID-dos','No-contact prospects remain searchable by their existing business IDs.');
crm_check($repo->overview(['destination'=>'jerico','subscription'=>'paid'])['total']===1,'Paying customers can be viewed separately without deleting their history.');
crm_check($repo->overview(['destination'=>'jardin'])['total']===1,'An expired paid subscription is a current free opportunity.');
crm_check($repo->overview(['query'=>'contacto@example.invalid'])['total']===1,'A contact search finds its business once.');
crm_check($repo->overview(['subscription'=>'all'],1,2)['page_count']===2,'Pagination counts businesses, not contact observations.');
crm_check($repo->find(2)['public_id']==='ID-dos'&&(int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===4,'CRM uses the original business record and performs no writes.');
$pdo->exec('DROP TABLE business_research_contacts');
$view=$repo->overview(['destination'=>'jerico']);
crm_check($view['total']===2&&$view['without_contact']===2&&!$view['research_ready'],'Businesses remain in CRM even before the contacts table is installed.');
echo "OK: one CRM row per existing business ID, all free prospects including no-contact records, source deduplication, contact search, subscription filters, pagination and read-only behavior.\n";

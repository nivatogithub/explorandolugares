<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessResearchContactImporter.php';
function research_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);}
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON; CREATE TABLE destinations(id INTEGER PRIMARY KEY,slug TEXT); CREATE TABLE businesses(id INTEGER PRIMARY KEY,destination_id INTEGER,publication_status TEXT); CREATE TABLE business_translations(business_id INTEGER,locale TEXT,display_name TEXT); CREATE TABLE business_contacts(business_id INTEGER,channel TEXT,contact_value TEXT,is_public INTEGER); INSERT INTO destinations VALUES(1,"jerico"),(2,"jardin"); INSERT INTO businesses VALUES(1,1,"published"),(2,2,"draft"); INSERT INTO business_translations VALUES(1,"es","Café de prueba"),(2,"es","Café de prueba");');
$pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/026-business-research-contacts.sqlite.sql'));
$contact=['channel'=>'email','value'=>'comercio@example.invalid','source_url'=>'https://example.invalid/negocio','consulted_at'=>'2026-10-15','evidence_status'=>'PUBLICADO_PARA_CLIENTES','source_note'=>'Observación ficticia de prueba','authorization_status'=>'NO_OBTENIDA','legal_basis_status'=>'PENDIENTE_REVISION'];
$package=['schema_version'=>'business-research-contacts-v1','destination_slug'=>'jerico','businesses'=>[['name'=>'Cafe De Prueba','contacts'=>[$contact]]]];
$first=BusinessResearchContactImporter::import($pdo,$package);
research_check($first===['businesses'=>1,'inserted'=>1,'existing'=>0],'Match ignores accents/case while respecting the destination.');
research_check((int)$pdo->query('SELECT business_id FROM business_research_contacts')->fetchColumn()===1,'Contact linked to the right existing business.');
research_check((int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn()===2&&(int)$pdo->query('SELECT COUNT(*) FROM business_contacts')->fetchColumn()===0,'No businesses or public contact channels created.');
research_check($pdo->query('SELECT publication_status FROM businesses WHERE id=1')->fetchColumn()==='published','An already published business remains published.');
$pdo->exec('UPDATE business_research_contacts SET suppressed=1');
$repeat=BusinessResearchContactImporter::import($pdo,$package);
research_check($repeat['inserted']===0&&$repeat['existing']===1&&(int)$pdo->query('SELECT suppressed FROM business_research_contacts')->fetchColumn()===1,'Idempotent import preserves a later suppression decision.');
$modern=$package;$modern['schema_version']='business-channels-import-v1';
$modern['businesses'][0]['channels']=$modern['businesses'][0]['contacts'];unset($modern['businesses'][0]['contacts']);
$repeat=BusinessResearchContactImporter::import($pdo,$modern);
research_check($repeat['inserted']===0&&$repeat['existing']===1,'The channel schema reuses the legacy evidence without duplication and needs no exported business IDs.');
$badModern=$modern;$badModern['businesses'][0]['contacts']=[$contact];$failed=false;
try{BusinessResearchContactImporter::import($pdo,$badModern);}catch(RuntimeException){$failed=true;}
research_check($failed,'Mixing people/contact and channel collections is rejected.');
research_check((int)$pdo->query('SELECT COUNT(*) FROM business_research_contacts WHERE campaign_allowed<>0 OR marketing_consent<>0')->fetchColumn()===0,'No campaign permission or consent fabricated.');
$bad=$package;$bad['businesses'][]=['name'=>'No existe','contacts'=>[$contact]];
$failed=false;try{BusinessResearchContactImporter::import($pdo,$bad);}catch(RuntimeException $e){$failed=true;}
research_check($failed&&(int)$pdo->query('SELECT COUNT(*) FROM business_research_contacts')->fetchColumn()===1,'Unknown business rejects the whole package before any insertion.');
$pdo->exec('INSERT INTO businesses VALUES(3,1,"draft"); INSERT INTO business_translations VALUES(3,"es","Cafe de prueba");');
$failed=false;try{BusinessResearchContactImporter::import($pdo,$package);}catch(RuntimeException $e){$failed=true;}
research_check($failed,'Ambiguous match is rejected, never guessed.');
$pdo->exec('DELETE FROM business_translations WHERE business_id=3; DELETE FROM businesses WHERE id=3;');
foreach([['campaign_allowed'=>1],['is_public'=>1],['authorization_status'=>'AUTORIZADO'],['consulted_at'=>'2026-02-30'],['source_url'=>'javascript:alert(1)']] as $override){
    $bad=$package;$bad['businesses'][0]['contacts'][0]=[...$contact,...$override];$failed=false;
    try{BusinessResearchContactImporter::import($pdo,$bad);}catch(RuntimeException $e){$failed=true;}
    research_check($failed,'Invalid date, source, public flag or consent injection rejected.');
}
echo "OK: private research import, strict matching, idempotence, suppression preservation, no publication/campaign side effects, and invalid-input rejection.\n";

<?php
declare(strict_types=1);
if(!class_exists('ParticipationService',false))require __DIR__.'/../app/ParticipationService.php';
function checkParticipationPersistence(PDO $pdo):void
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
    $id=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?'INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY';
    try{
        $pdo->exec('CREATE TEMPORARY TABLE leads (id '.$id.',request_key VARCHAR(48) NOT NULL UNIQUE,name VARCHAR(100) NOT NULL UNIQUE,business VARCHAR(100),municipality VARCHAR(100),category VARCHAR(100),email VARCHAR(100),phone VARCHAR(100),phone_type VARCHAR(100),participation_version VARCHAR(100),has_brand VARCHAR(100),brand_name VARCHAR(100),description VARCHAR(100),interest VARCHAR(100),wants_contact INTEGER,consent_version VARCHAR(100),created_at VARCHAR(30))');
        $data=['name'=>'Persona','business'=>'Negocio','municipality'=>'Jericó','category'=>'hospedaje','email'=>'test@example.invalid','phone'=>'','phone_type'=>'','participation_version'=>'test-v1','has_brand'=>'no','brand_name'=>'','description'=>'Prueba','interest'=>'contacto','wants_contact'=>1];
        $selector=new ParticipationSelector($pdo);$writer=new ParticipationWriter($pdo);$service=new ParticipationService($selector,$writer);$selector->verifyStorage();
        if($selector->hasRequest('one'))throw new RuntimeException('Unexpected synthetic request.');
        $writer->insert('one',$data,'2026-01-01 12:00:00');
        $data['name']='Segunda';$service->record('two',$data,'2026-01-01 12:00:00');
        $service->record('two',$data,'2026-01-01 12:00:00');
        if(!$selector->hasRequest('one')||!$selector->hasRequest('two')||$selector->hasRequest('missing'))throw new RuntimeException('Request lookup changed.');
        $rows=$pdo->query('SELECT request_key,name,business,municipality,category,email,phone,phone_type,participation_version,has_brand,brand_name,description,interest,wants_contact,consent_version,created_at FROM leads ORDER BY request_key')->fetchAll();
        if(count($rows)!==2||$rows[0]['name']!=='Persona'||$rows[1]['name']!=='Segunda'||$rows[1]['consent_version']!=='contacto-negocio-v1'||$rows[1]['created_at']!=='2026-01-01 12:00:00')throw new RuntimeException('Stored values or retry semantics changed.');
        $thrown=false;try{$service->record('three',$data,'2026-01-01 12:00:00');}catch(PDOException){$thrown=true;}
        if(!$thrown||$selector->hasRequest('three'))throw new RuntimeException('Unrelated unique constraint was suppressed.');
    }finally{
        $sqlite=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $pdo->exec($sqlite?'DROP TABLE IF EXISTS temp.leads':'DROP TEMPORARY TABLE IF EXISTS leads');
    }
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){checkParticipationPersistence(new PDO('sqlite::memory:'));echo "OK: storage, persisted data, duplicate retry and unrelated constraint.\n";}

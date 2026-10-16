<?php
declare(strict_types=1);

/** Complement existing business records with private research; never publishes or creates businesses. */
final class BusinessResearchContactImporter
{
    public static function import(PDO $pdo,array $package):array
    {
        $schema=$package['schema_version']??'';
        if(!in_array($schema,['business-research-contacts-v1','business-channels-import-v1'],true)||array_diff(array_keys($package),['schema_version','destination_slug','businesses']))throw new RuntimeException('Usa un archivo de canales de negocios válido.');
        $collection=$schema==='business-channels-import-v1'?'channels':'contacts';
        $businesses=$package['businesses']??null;
        if(!is_array($businesses)||!array_is_list($businesses)||count($businesses)<1||count($businesses)>500)throw new RuntimeException('El paquete debe incluir entre 1 y 500 negocios existentes.');
        $q=$pdo->prepare('SELECT id FROM destinations WHERE slug=?');$q->execute([self::text($package['destination_slug']??'',100)]);$destinationId=(int)$q->fetchColumn();
        if(!$destinationId)throw new RuntimeException('No existe el destino del paquete.');
        $q=$pdo->prepare('SELECT b.id,t.display_name FROM businesses b JOIN business_translations t ON t.business_id=b.id AND t.locale="es" WHERE b.destination_id=?');$q->execute([$destinationId]);$names=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$names[self::normalize((string)$row['display_name'])][]=(int)$row['id'];
        $prepared=[];$matched=[];$recipientReady=self::recipientReady($pdo);
        foreach($businesses as $business){
            if(!is_array($business)||array_diff(array_keys($business),['name',$collection]))throw new RuntimeException('Registro de negocio no válido.');
            $name=self::text($business['name']??'',180);$ids=$names[self::normalize($name)]??[];
            if(count($ids)!==1)throw new RuntimeException('El negocio «'.$name.'» no tiene una coincidencia única en este destino. No se creó ni modificó ningún negocio.');
            $id=$ids[0];$contacts=$business[$collection]??null;
            if(!is_array($contacts)||!array_is_list($contacts)||count($contacts)<1||count($contacts)>100)throw new RuntimeException('Revisa los canales de «'.$name.'».');
            $matched[$id]=true;
            foreach($contacts as $contact){
                $allowed=['channel','value','source_url','source_type','source_field','source_period','consulted_at','evidence_status','source_note','authorization_status','legal_basis_status','crm_stage','potential_subscription','recipient_label'];
                if(!is_array($contact)||array_diff(array_keys($contact),$allowed))throw new RuntimeException('Un canal incluye campos desconocidos; la importación no admite publicación ni autorización de campañas.');
                $channel=self::text($contact['channel']??'',24);
                if(!in_array($channel,['email','phone','phone_fixed','whatsapp','instagram','facebook','website','linkedin'],true))throw new RuntimeException('Canal no admitido.');
                // Preserve an observed number even if incomplete; evidence_status records the issue.
                $value=self::text($contact['value']??'',500);$url=self::text($contact['source_url']??'',1000);
                if($value===''||!filter_var($url,FILTER_VALIDATE_URL)||!in_array(parse_url($url,PHP_URL_SCHEME),['https','http'],true))throw new RuntimeException('Cada canal necesita un valor y una URL de procedencia válida.');
                $date=self::text($contact['consulted_at']??'',10);$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new RuntimeException('La fecha de consulta debe ser una fecha válida AAAA-MM-DD.');
                $data=[$id,$channel,$value,$url,self::text($contact['source_type']??'',80),self::text($contact['source_field']??'',80),self::text($contact['source_period']??'',120),$date,self::text($contact['evidence_status']??'',100),self::text($contact['source_note']??'',4000),self::text($contact['authorization_status']??'NO_OBTENIDA',80),self::text($contact['legal_basis_status']??'',500),self::text($contact['crm_stage']??'',80),self::text($contact['potential_subscription']??'',40)];
                if($data[8]===''||$data[11]==='')throw new RuntimeException('Cada canal necesita su estado de evidencia y de base de uso.');
                if(!in_array($data[10],['NO_OBTENIDA','PENDIENTE'],true))throw new RuntimeException('Este importador no acredita autorizaciones comerciales.');
                $hash=hash('sha256',json_encode(array_slice($data,1,9),JSON_THROW_ON_ERROR));
                $recipient=self::text($contact['recipient_label']??'',180);
                if($recipient!==''&&!$recipientReady)throw new RuntimeException('Habilita la actualización 028 antes de guardar a quién va dirigido un canal.');
                $prepared[]=[$data,$hash,$recipient];
            }
        }
        if(count($prepared)>5000)throw new RuntimeException('El paquete supera 5.000 registros de canal y fuente.');
        $result=['businesses'=>count($matched),'inserted'=>0,'existing'=>0];$pdo->beginTransaction();
        try{
            $exists=$pdo->prepare('SELECT id'.($recipientReady?',recipient_label':'').' FROM business_research_contacts WHERE business_id=? AND record_hash=?');
            $insert=$pdo->prepare('INSERT INTO business_research_contacts(business_id,channel,contact_value,source_url,source_type,source_field,source_period,consulted_at,evidence_status,source_note,authorization_status,legal_basis_status,crm_stage,potential_subscription,record_hash,marketing_consent,campaign_allowed,suppressed'.($recipientReady?',recipient_label':'').') VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,0'.($recipientReady?',?':'').')');
            foreach($prepared as [$data,$hash,$recipient]){
                $exists->execute([$data[0],$hash]);
                $existing=$exists->fetch(PDO::FETCH_ASSOC);
                if($existing){
                    if($recipient!==''){
                        if($existing['recipient_label']!==''&&$existing['recipient_label']!==$recipient)throw new RuntimeException('El canal ya tiene un destinatario diferente. Revisa la actualización antes de sobrescribirlo.');
                        if($existing['recipient_label']==='')$pdo->prepare('UPDATE business_research_contacts SET recipient_label=? WHERE id=?')->execute([$recipient,$existing['id']]);
                    }
                    $result['existing']++;continue;
                }
                $values=[...$data,$hash];if($recipientReady)$values[]=$recipient;
                $insert->execute($values);$result['inserted']++;
            }
            $pdo->commit();return $result;
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    }

    public static function recipientReady(PDO $pdo):bool
    {
        try{$pdo->query('SELECT recipient_label FROM business_research_contacts LIMIT 0');return true;}
        catch(PDOException $error){
            if(in_array($error->errorInfo[0]??'',['42S02','42S22'],true)||str_contains($error->getMessage(),'no such table:')||str_contains($error->getMessage(),'no such column:'))return false;
            throw $error;
        }
    }

    private static function text(mixed $value,int $max):string
    {
        if(!is_string($value)||strlen($value)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$value))throw new RuntimeException('Un campo del paquete tiene formato o longitud no válidos.');
        return trim($value);
    }

    private static function normalize(string $value):string
    {
        $value=mb_strtolower(trim($value),'UTF-8');$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
        return preg_replace('/[^a-z0-9]+/','',strtolower(is_string($ascii)?$ascii:$value))??'';
    }
}

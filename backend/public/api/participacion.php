<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
try {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        if (!ready()) json_response(['enabled'=>false,'message'=>'La recepción de solicitudes está en preparación. No se enviará ni almacenará información.']);
        db()->query('SELECT 1 FROM leads LIMIT 1');
        $_SESSION['request_key'] ??= bin2hex(random_bytes(24));
        $privacy=$config['privacy'];
        $privacy['retention']=PortalSettings::retention($privacy,(string)($_GET['lang']??$_POST['lang']??'es'));
        unset($privacy['retention_en'],$privacy['retention_pt']);
        json_response(['enabled'=>true,'csrf'=>$_SESSION['csrf'],'request_key'=>$_SESSION['request_key'],'privacy'=>$privacy]);
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') {header('Allow: GET, POST');json_response(['message'=>'Método no permitido.'],405);}
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>16000) json_response(['message'=>'La solicitud supera el tamaño permitido.'],413);
    if (!ready()) json_response(['message'=>'La recepción aún no está habilitada.'],503);
    csrf();
    if (!is_string($_POST['request_key']??null) || !hash_equals($_SESSION['request_key']??'',$_POST['request_key']) || empty($_POST['request_key'])) json_response(['message'=>'Recarga la página antes de enviar.'],403);
    if (!empty($_POST['website'])) json_response(['message'=>'No pudimos validar la solicitud.'],422);
    [$data,$errors]=lead_validation($_POST);
    if ($errors) json_response(['message'=>'Revisa los campos indicados.','errors'=>$errors],422);
    $q=db()->prepare('SELECT id FROM leads WHERE request_key=?');$q->execute([$_POST['request_key']]);
    if ($q->fetchColumn()) json_response(['message'=>'Tus datos de contacto ya fueron recibidos. No se duplicó la solicitud.']);
    if (!throttle('registro',5,3600)) json_response(['message'=>'Has enviado varias solicitudes. Intenta de nuevo más tarde.'],429);
    $columns=['request_key','name','business','municipality','category','email','phone','phone_type','participation_version','has_brand','brand_name','description','interest','wants_contact','consent_version','created_at'];
    $values=[$_POST['request_key']];foreach(array_slice($columns,1,-2) as $key) $values[]=$data[$key];
    $values[]='contacto-negocio-v1';$values[]=gmdate('Y-m-d H:i:s');
    $sql='INSERT INTO leads ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
    try {db()->prepare($sql)->execute($values);} catch (PDOException $e) {
        if (!in_array($e->getCode(),['23000','23505'],true)) throw $e;
        $q=db()->prepare('SELECT id FROM leads WHERE request_key=?');$q->execute([$_POST['request_key']]);if (!$q->fetchColumn()) throw $e;
    }
    json_response(['message'=>$data['interest']==='impulsor_local'?'Recibimos tu postulación como impulsor local. La revisaremos antes de asignar un rol, código o beneficio.':'Recibimos tus datos para contactarte sobre tu negocio. Esto no crea una cuenta ni publica tu negocio automáticamente.']);
} catch (Throwable $e) {if(class_exists('PortalIncidents',false))PortalIncidents::technical($e,'participacion');error_log('Explorando Lugares: fallo de recepción.');json_response(['message'=>'No pudimos confirmar el registro. Conserva tus datos e intenta nuevamente más tarde.'],503);}

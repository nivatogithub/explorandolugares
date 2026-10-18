<?php
declare(strict_types=1);
// Compatibilidad con enlaces anteriores: la cuenta ya no exige elegir un espacio.
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();
$tab='personal';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf();
    $space=(string)($_POST['space']??'');
    if(!in_array($space,['explore','business'],true)){http_response_code(400);exit('Sección no válida.');}
    $tab=$space==='business'?'business':'personal';
}
header('Location: /negocios/portal/?tab='.$tab,true,303);
exit;

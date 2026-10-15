<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
require_business_portal_access();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método no permitido.');}
csrf();unset($_SESSION['business_account_id'],$_SESSION['business_last_seen']);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));header('Location: /v1/negocios/acceso/',true,303);exit;

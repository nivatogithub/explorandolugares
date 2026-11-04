<?php
declare(strict_types=1);
// Reuse the guarded local fixture/session helpers and retain its private-page regression.
ob_start();require '/srv/state/check-account-auth-http.php';$privateCheckOutput=ob_get_clean();
$checks=[];
layout_http_session($pdo,$account,'guest',function(string $session,string $csrf)use(&$checks):void{
    foreach(['es','en','pt-br'] as $locale){
        $cases=[['registration','/negocios/registro/',''],['traveler','/negocios/registro/','&tipo=traveler'],
            ['verification','/cuenta/verificar-correo/',''],['verification-sent','/cuenta/verificar-correo/','&enviado=1'],['business-gate','/negocios/registro/','&tipo=business']];
        foreach($cases as [$name,$route,$query]){
            [$status,$html,$headers]=auth_http_request($session,$route.'?lang='.$locale.$query);
            if($status!==200||!str_contains($headers['cache-control']??'','no-store')||!str_contains($headers['x-robots-tag']??'','noindex'))throw new RuntimeException('Respuesta privada de acceso incorrecta: '.$name);
            $document=new DOMDocument();$previous=libxml_use_internal_errors(true);$document->loadHTML('<?xml encoding="UTF-8">'.str_replace($csrf,'synthetic-csrf',$html),LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($document);
            foreach(['//html[@lang="'.$locale.'"]','//body[@class="portal-account-page"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Composición del acceso incorrecta: '.$name.' / '.$locale);
            $floating=$name!=='business-gate';
            if($xpath->query('//dialog[@open and @data-account-auth-dialog]/div[@data-account-auth-content]')->length!==($floating?1:0))throw new RuntimeException('Diálogo inesperado o ausente.');
            if($xpath->query('//dialog//form[@method="post"]/input[@name="csrf" and @value="synthetic-csrf"]')->length!==($floating?1:0))throw new RuntimeException('Formulario firmado inesperado o ausente.');
            foreach(iterator_to_array($xpath->query('//@src | //@href | //@action')) as $attribute)$attribute->value=auth_http_path($attribute->value,$route);
            foreach(iterator_to_array($xpath->query('//text()')) as $node){$value=trim($node->nodeValue);if($value==='')$node->parentNode->removeChild($node);else $node->nodeValue=$value;}
            $checks[]=['case'=>$name,'locale'=>$locale,'http'=>200,'headers_shell_dialog_and_fields_preserved'=>true,'canonical_dom_sha256'=>hash('sha256',$document->saveHTML())];
        }
        foreach(['/negocios/registro/','/cuenta/verificar-correo/'] as $route){
            [$status,$html,$headers]=auth_http_request($session,$route.'?lang='.$locale,['csrf'=>'invalid','account_type'=>'traveler']);
            if($status!==303||($headers['location']??'')!=='/negocios/acceso/?sesion=expirada'||str_contains($html,'data-account-auth-content'))throw new RuntimeException('CSRF inválido no rechazado: '.$route.' / '.$locale.' / HTTP '.$status.' / '.($headers['location']??'sin redirección'));
            $checks[]=['case'=>'csrf-invalid','route'=>$route,'locale'=>$locale,'http'=>303,'rejected_before_form_and_database_write'=>true];
        }
    }
});
file_put_contents('/srv/state/account-registration-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'database_rows_written'=>false,'real_mail_sent'=>false,'test_sessions_removed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo $privateCheckOutput.'OK HTTP/MariaDB: '.count($checks)." casos de registro/verificación, idiomas, diálogo, encabezados privados y CSRF; sesiones sintéticas retiradas.\n";

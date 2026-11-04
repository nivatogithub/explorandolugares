<?php
declare(strict_types=1);
// Reuse the guarded dev1 helpers; keep all previous private/account regressions.
ob_start();require '/srv/state/check-account-registration-http.php';$previousCheckOutput=ob_get_clean();
$checks=[];
function profile_http_markup(string $html,string $csrf,string $route):array
{
    $document=new DOMDocument();$previous=libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.str_replace($csrf,'synthetic-csrf',$html),LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($previous);$xpath=new DOMXPath($document);
    foreach(iterator_to_array($xpath->query('//@src | //@href | //@action')) as $attribute)$attribute->ownerElement->setAttribute($attribute->name,auth_http_path($attribute->value,$route));
    foreach(iterator_to_array($xpath->query('//text()')) as $node){$value=trim($node->nodeValue);if($value==='')$node->parentNode->removeChild($node);else $node->nodeValue=$value;}
    return [$xpath,hash('sha256',$document->saveHTML())];
}
layout_http_session($pdo,$account,'valid',function(string $session,string $csrf)use(&$checks):void{
    foreach(['es','en','pt-br'] as $locale){
        $route='/negocios/portal/?tab=personal&lang='.$locale;
        $cases=[['profile','',null,200],['saved','&ubicacion=guardada',null,200],
            ['unknown-action','',['csrf'=>$csrf,'action'=>'unknown'],400],
            ['invalid-residence','',['csrf'=>$csrf,'action'=>'save_residence','residence_country_code'=>'XX'],400],
            ['csrf-save','',['csrf'=>'invalid','action'=>'save_residence'],303],
            ['csrf-close','',['csrf'=>'invalid','action'=>'close_account'],303]];
        foreach($cases as [$name,$suffix,$post,$expected]){
            [$status,$html,$headers]=auth_http_request($session,$route.$suffix,$post);
            if($status!==$expected)throw new RuntimeException('Estado de perfil incorrecto: '.$name.' / '.$locale.' / '.$status);
            if($status===303){
                if(($headers['location']??'')!=='/negocios/acceso/?sesion=expirada'||str_contains($html,'account-personal-card'))throw new RuntimeException('CSRF de perfil no rechazado.');
                $checks[]=['case'=>$name,'locale'=>$locale,'http'=>303,'location'=>$headers['location']];continue;
            }
            if(!str_contains($headers['cache-control']??'','no-store')||!str_contains($headers['x-robots-tag']??'','noindex'))throw new RuntimeException('Encabezados privados de perfil ausentes.');
            [$xpath,$digest]=profile_http_markup($html,$csrf,$route);
            foreach(['//html[@lang="'.$locale.'"]','//body[@class="portal-account-page"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main','//nav[@class="account-tabs"]/a[@aria-current="page"]'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Composición de perfil incorrecta: '.$name.' / '.$locale);
            if($xpath->query('//main//dialog')->length!==0||$xpath->query('//main//form[@method="post"]/input[@name="csrf" and @value="synthetic-csrf"]')->length!==3)throw new RuntimeException('Formularios firmados de perfil incorrectos.');
            if($xpath->query('//*[@role="alert"]')->length!==($expected===400?1:0)||$xpath->query('//*[@role="status"]')->length!==($name==='saved'?1:0))throw new RuntimeException('Avisos de perfil incorrectos.');
            $checks[]=['case'=>$name,'locale'=>$locale,'http'=>$status,'canonical_dom_sha256'=>$digest,'private_headers_and_signed_forms'=>true];
        }
        $route='/negocios/portal/?tab=business&lang='.$locale;
        [$status,$html,$headers]=auth_http_request($session,$route);
        if($status!==200)throw new RuntimeException('Panel de negocios inaccesible.');
        [$xpath,$digest]=profile_http_markup($html,$csrf,$route);
        if($xpath->query('//form[@class="account-profile-logout" and @action="/negocios/salir/"]/input[@name="csrf" and @value="synthetic-csrf"]')->length!==1)throw new RuntimeException('Salida compartida del panel incorrecta.');
        $checks[]=['case'=>'business-shared-logout','locale'=>$locale,'http'=>200,'canonical_dom_sha256'=>$digest];
    }
});
foreach(['guest','expired','invalid_proof'] as $mode){
    layout_http_session($pdo,$account,$mode,function(string $session,string $csrf)use(&$checks,$mode):void{
        foreach(['es','en','pt-br'] as $locale){
            [$status,$html,$headers]=auth_http_request($session,'/negocios/portal/?tab=personal&lang='.$locale);
            if($status!==303||($headers['location']??'')!=='/negocios/acceso/?sesion=expirada'||str_contains($html,'account-personal-card'))throw new RuntimeException('Perfil privado expuesto: '.$mode);
            $checks[]=['case'=>$mode,'locale'=>$locale,'http'=>303,'location'=>$headers['location']];
        }
    });
}
file_put_contents('/srv/state/account-profile-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'account_and_residence_rows_written'=>false,'real_mail_sent'=>false,'test_sessions_removed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo $previousCheckOutput.'OK HTTP/MariaDB: '.count($checks)." casos de perfil, residencia rechazada, formularios firmados, salida compartida y autorización; sesiones sintéticas retiradas.\n";

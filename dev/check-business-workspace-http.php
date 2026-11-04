<?php
declare(strict_types=1);
ob_start();require '/srv/state/check-account-profile-http.php';$previousCheckOutput=ob_get_clean();
$checks=[];
layout_http_session($pdo,$account,'valid',function(string $session,string $csrf)use(&$checks):void{
    foreach(['es','en','pt-br'] as $locale){
        $route='/negocios/portal/?tab=business&lang='.$locale;
        $cases=[['workspace','',null,200],['published','&resultado=publicado',null,200],
            ['changes','&resultado=correcciones',null,200],['payment-sent','&pago=enviado',null,200],
            ['registration-saved','&solicitud=guardada',null,200],['coupon','&cupon=aplicado',null,200],
            ['notice-priority','&resultado=publicado&pago=enviado&solicitud=guardada&cupon=aplicado',null,200],
            ['unknown-action','',['csrf'=>$csrf,'action'=>'unknown'],400],
            ['missing-business','',['csrf'=>$csrf,'action'=>'owner_publish','business_id'=>'999999999','version_id'=>'999999999','payload_hash'=>'invalid'],400],
            ['csrf-publish','',['csrf'=>'invalid','action'=>'owner_publish'],303],
            ['csrf-changes','',['csrf'=>'invalid','action'=>'owner_changes'],303]];
        foreach($cases as [$name,$suffix,$post,$expected]){
            [$status,$html,$headers]=auth_http_request($session,$route.$suffix,$post);
            if($status!==$expected)throw new RuntimeException('Estado del panel incorrecto: '.$name.' / '.$locale.' / '.$status);
            if($status===303){
                if(($headers['location']??'')!=='/negocios/acceso/?sesion=expirada'||str_contains($html,'business-grid-heading'))throw new RuntimeException('CSRF del panel no rechazado.');
                $checks[]=['case'=>$name,'locale'=>$locale,'http'=>303,'location'=>$headers['location']];continue;
            }
            if(!str_contains($headers['cache-control']??'','no-store')||!str_contains($headers['x-robots-tag']??'','noindex'))throw new RuntimeException('Faltan encabezados privados del panel.');
            [$xpath,$digest]=profile_http_markup($html,$csrf,$route);
            foreach(['//html[@lang="'.$locale.'"]','//body[@class="portal-account-page"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main','//nav[@class="account-tabs"]/a[@aria-current="page" and @href="/negocios/portal/?tab=business"]','//dialog[@id="business-changes-dialog"]','//dialog[@id="business-notice-dialog"]','//dialog[@id="business-qr-dialog"]','//dialog[contains(@class,"business-preview-dialog")]','//form[@class="account-profile-logout"]/input[@name="csrf" and @value="synthetic-csrf"]','//dialog[@id="business-changes-dialog"]//input[@name="csrf" and @value="synthetic-csrf"]'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Composición del panel incorrecta: '.$name.' / '.$locale);
            if($xpath->query('//*[@role="alert"]')->length!==($expected===400?1:0))throw new RuntimeException('Aviso de error del panel incorrecto.');
            $checks[]=['case'=>$name,'locale'=>$locale,'http'=>$status,'canonical_dom_sha256'=>$digest,'signed_forms_dialogs_resources_and_private_headers'=>true];
        }
    }
});
foreach(['guest','expired','invalid_proof'] as $mode){
    layout_http_session($pdo,$account,$mode,function(string $session,string $csrf)use(&$checks,$mode):void{
        foreach(['es','en','pt-br'] as $locale){
            [$status,$html,$headers]=auth_http_request($session,'/negocios/portal/?tab=business&lang='.$locale);
            if($status!==303||($headers['location']??'')!=='/negocios/acceso/?sesion=expirada'||str_contains($html,'business-grid-heading'))throw new RuntimeException('Panel privado expuesto: '.$mode);
            $checks[]=['case'=>$mode,'locale'=>$locale,'http'=>303,'location'=>$headers['location']];
        }
    });
}
file_put_contents('/srv/state/business-workspace-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'businesses_payments_and_proposals_written'=>false,'real_mail_sent'=>false,'test_sessions_removed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo $previousCheckOutput.'OK HTTP/MariaDB: '.count($checks)." casos del panel de negocios, avisos, formularios, diálogos, CSRF y autorización; sin publicar ni modificar negocios o pagos.\n";

<?php
declare(strict_types=1);
ob_start();require '/srv/state/check-business-workspace-http.php';$previousCheckOutput=ob_get_clean();
$checks=[];
layout_http_session($pdo,$account,'valid',function(string $session,string $csrf)use(&$checks):void{
    foreach(['es','en','pt-br'] as $locale){
        $route='/negocios/registro/?tipo=business&lang='.$locale;
        $cases=[['wizard','',null,200],['unknown-place','&destination_id=999999999',null,200],
            ['missing-draft','&draft='.str_repeat('e',48),null,404],['invalid-draft','&draft=invalid',null,400],
            ['missing-plan','',['account_type'=>'business','csrf'=>$csrf,'registration_draft'=>str_repeat('d',48)],400],
            ['missing-identifier','',['account_type'=>'business','csrf'=>$csrf,'registration_draft'=>str_repeat('d',48),'plan_code'=>'free'],400],
            ['csrf','',['account_type'=>'business','csrf'=>'invalid'],303],
            ['csrf-draft','',['account_type'=>'business','csrf'=>'invalid','draft_action'=>'save'],303],
            ['categories','&search=1&destination_id=999999999&category=__categories__',null,200],
            ['experiences','&search=1&destination_id=999999999&category=__experiences__',null,200]];
        foreach($cases as [$name,$suffix,$post,$expected]){
            [$status,$html,$headers]=auth_http_request($session,$route.$suffix,$post);
            if($status!==$expected)throw new RuntimeException('Estado del registro comercial incorrecto: '.$name.' / '.$locale.' / '.$status);
            if($status===303){
                if(($headers['location']??'')!=='/negocios/acceso/?sesion=expirada')throw new RuntimeException('CSRF del registro comercial no rechazado.');
                $checks[]=['case'=>$name,'locale'=>$locale,'http'=>$status,'location'=>$headers['location']];continue;
            }
            if(in_array($name,['categories','experiences'],true)){
                $json=json_decode($html,true,32,JSON_THROW_ON_ERROR);if(!isset($json['results']))throw new RuntimeException('Búsqueda sin contrato de resultados.');
                $checks[]=['case'=>$name,'locale'=>$locale,'http'=>$status,'json_sha256'=>hash('sha256',$html)];continue;
            }
            if(!str_contains($html,'business-registration-form')){
                $checks[]=['case'=>$name,'locale'=>$locale,'http'=>$status,'body_sha256'=>hash('sha256',$html)];continue;
            }
            if(!preg_match('/name="registration_draft" value="([a-f0-9]{48})"/',$html,$token))throw new RuntimeException('Borrador ausente.');
            $html=str_replace($token[1],str_repeat('0',48),$html);
            [$xpath,$digest]=profile_http_markup($html,$csrf,$route);
            foreach(['//html[@lang="'.$locale.'"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//form[@id="business-registration-form"]','//main//input[@name="csrf" and @value="synthetic-csrf"]','//dialog[@id="business-map-dialog"]'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Composición de registro incorrecta: '.$expression);
            if($xpath->query('//input[@name="plan_code"]')->length!==4||$xpath->query('//*[@data-product-slot]')->length!==5||$xpath->query('//textarea[@name="summary"]')->length!==1)throw new RuntimeException('Planes o campos del registro alterados.');
            if($xpath->query('//main//input[@name="password" or @name="display_name" or @name="promotion_code"]')->length)throw new RuntimeException('Registro vuelve a pedir identidad o cupón.');
            $checks[]=['case'=>$name,'locale'=>$locale,'http'=>$status,'canonical_dom_sha256'=>$digest,'signed_form_plans_files_and_map'=>true];
        }
    }
});
file_put_contents('/srv/state/business-registration-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'businesses_accounts_payments_and_drafts_written'=>false,'real_mail_sent'=>false,'test_sessions_removed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo $previousCheckOutput.'OK HTTP/MariaDB: '.count($checks)." casos de registro comercial, planes, formularios, mapa, búsqueda, rechazos y CSRF; sin guardar solicitudes ni borradores.\n";

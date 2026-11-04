<?php
declare(strict_types=1);
ob_start();require '/srv/state/check-business-registration-http.php';$previousCheckOutput=ob_get_clean();
$checks=[];
foreach(['es','en','pt-br'] as $locale)layout_http_session($pdo,$account,'valid',function(string $session,string $csrf)use(&$checks,$locale):void{
        $route='/business/checkout.php?lang='.$locale;
        $cases=[['missing-selection','',400],['invalid-plan','&plan=forged',400],['invalid-period','&plan=page&period=weekly',400],['free-operator','&plan=free&operator=1',400],['invalid-experience','&plan=page&experience=999999999',400]];
        foreach(['free','featured','page','page_premium'] as $plan)foreach(['monthly','quarterly','semiannual','annual'] as $period)$cases[]=[$plan.'/'.$period,'&plan='.$plan.'&period='.$period,200];
        $cases[]=['foreign-business','&business=999999999',400];
        foreach($cases as [$name,$suffix,$expected]){
            [$status,$html,$headers]=auth_http_request($session,$route.$suffix,null);
            if($status!==$expected)throw new RuntimeException('Estado de selección incorrecto: '.$name.' / '.$locale.' / '.$status);
            if(!str_contains($headers['cache-control']??'','no-store')||!str_contains($headers['x-robots-tag']??'','noindex'))throw new RuntimeException('Faltan encabezados privados de selección.');
            [$xpath,$digest]=profile_http_markup($html,$csrf,$route);
            foreach(['//html[@lang="'.$locale.'"]','//body[@class="portal-account-page"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main','//main//a[@href="/negocios-locales/suscripciones/"]'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Composición de selección incorrecta: '.$name.' / '.$expression);
            if($xpath->query('//main//p[@role="alert"]')->length!==($status===400?1:0))throw new RuntimeException('Aviso de selección incorrecto.');
            if($xpath->query('//main//input[@type="file"] | //main//input[@name="payment_reference"]')->length)throw new RuntimeException('La selección incorporó una operación de pago.');
            $checks[]=['case'=>$name,'locale'=>$locale,'http'=>$status,'canonical_dom_sha256'=>$digest,'private_headers_shell_and_canonical_simulator'=>true];
        }
});
foreach(['guest','expired','invalid_proof'] as $mode)layout_http_session($pdo,$account,$mode,function(string $session,string $csrf)use(&$checks,$mode):void{
    foreach(['es','en','pt-br'] as $locale){
        [$status,$html,$headers]=auth_http_request($session,'/business/checkout.php?plan=free&lang='.$locale,null);
        if($status!==303||($headers['location']??'')!=='/negocios/acceso/?sesion=expirada'||str_contains($html,'checkout-businesses'))throw new RuntimeException('Selección privada no protegida: '.$mode);
        $checks[]=['case'=>$mode,'locale'=>$locale,'http'=>$status,'location'=>$headers['location']];
    }
});
file_put_contents('/srv/state/business-selection-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'accounts_businesses_payments_and_subscriptions_written'=>false,'real_mail_sent'=>false,'test_sessions_removed'=>true,'production_accessed'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");
echo $previousCheckOutput.'OK HTTP/MariaDB: '.count($checks)." casos de selección, planes, períodos, permisos y encabezados; sin crear pagos ni suscripciones.\n";

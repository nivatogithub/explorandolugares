<?php
declare(strict_types=1);
ob_start();require '/srv/state/check-business-selection-http.php';$previous=ob_get_clean();$checks=[];
layout_http_session($pdo,$account,'valid',function(string $session,string $csrf)use(&$checks):void{
    foreach(['es','en','pt-br'] as $locale){
        [$status,$html,$headers]=auth_http_request($session,'/business/payment.php?lang='.$locale);
        if($status!==200||!str_contains($html,'Pagar mis negocios')||str_contains($html,'name="payment_proof"'))throw new RuntimeException('Invalid payment selector.');
        [$xpath,$digest]=profile_http_markup($html,$csrf,'/business/payment.php');foreach(['//html[@lang="'.$locale.'"]','//header[@data-portal-shell="1"]','//footer[@data-portal-footer="1"]','//main'] as $expression)if($xpath->query($expression)->length!==1)throw new RuntimeException('Invalid payment shell.');
        $checks[]=['case'=>'selection','locale'=>$locale,'http'=>200,'canonical_dom'=>$digest];
        [$status,$html]=auth_http_request($session,'/business/payment.php?order='.str_repeat('f',48).'&lang='.$locale);if($status!==400||!str_contains($html,'no está disponible para tu cuenta'))throw new RuntimeException('Unknown order exposed.');$checks[]=['case'=>'unknown-order','locale'=>$locale,'http'=>400];
    }
});
layout_http_session($pdo,$account,'guest',function(string $session,string $csrf)use(&$checks):void{
    [$status,$html,$headers]=auth_http_request($session,'/business/payment.php');if($status!==303||str_contains($html,'Pagar mis negocios'))throw new RuntimeException('Guest payment access.');$checks[]=['case'=>'guest','http'=>303];
});
file_put_contents('/srv/state/business-payment-page-http-report.json',json_encode(['driver'=>'MariaDB','real_http_sessions'=>true,'commercial_rows_written'=>false,'checks'=>$checks],JSON_PRETTY_PRINT)."\n");echo $previous.'OK: '.count($checks)." native payment page checks.\n";

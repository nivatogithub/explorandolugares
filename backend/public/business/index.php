<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/bootstrap.php';
header_remove('X-Robots-Tag');

$locale=match($_GET['lang']??'') {'en'=>'en','pt-br'=>'pt-br',default=>'es'};
$localizedPages=['es'=>'negocios-locales/index.html','en'=>'en/negocios-locales/index.html','pt-br'=>'pt-br/negocios-locales/index.html'];
$page=dirname(__DIR__).'/'.$localizedPages[$locale];
$html=is_file($page)?file_get_contents($page):false;
$marker='<!-- BUSINESS_ACCOUNT_ACTION -->';
if(!is_string($html)||!str_contains($html,$marker)){http_response_code(503);exit('La página informativa para negocios no está disponible.');}
$entry='';
if(business_portal_available()){
    $labels=match($locale){
        'en'=>['Create a business account','I already have an account'],
        'pt-br'=>['Criar conta empresarial','Já tenho uma conta'],
        default=>['Crear cuenta de negocio','Ya tengo cuenta'],
    };
    $entry='<p class="account-entry-strip"><a href="../negocios/registro/">'.esc($labels[0]).'</a> · <a href="../negocios/acceso/">'.esc($labels[1]).'</a></p>';
}
echo str_replace($marker,$entry,$html);

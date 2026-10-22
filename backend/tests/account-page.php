<?php
declare(strict_types=1);
// Exercise emitted HTML, including compatibility with the packaging head injector.
$dir=sys_get_temp_dir().'/el-account-page-'.bin2hex(random_bytes(6));mkdir($dir);mkdir($dir.'/assets');
try{
    // Use the real shared shell and its versioned components, not a copied orphan.
    require_once __DIR__.'/../public/_account-page.php';
    PortalPresentation::setPublicRoot($dir);
    file_put_contents($dir.'/assets/account-page.css','/* fixture */');
    file_put_contents($dir.'/index.html','<!doctype html><html><head><link rel="stylesheet" href="assets/style.css"></head><body><header><a href="destinos/">Lugares</a></header><main id="contenido"><h1>Explora</h1><img src="assets/logo.png"></main><footer><a href="privacidad/">Privacidad</a></footer></body></html>');

    $page='<html><head><title>Acceso</title></head><body><main class="account-portal" data-plan-prices="safe"><a class="brand" href="/">Old duplicate</a><section class="account-portal-card"><h1>Inicia sesión</h1><form method="post"><input name="csrf" value="test"><input type="password" name="password"></form></section></main><script src="/assets/business.js"></script></body></html>';
    foreach([false,true] as $floating){
        ob_start();account_page($floating);echo $page;ob_end_flush();$html=ob_get_clean();
        foreach(['<header','<footer','name="csrf"','name="password"','/assets/business.js','href="/lugares/"','href="/privacidad/"'] as $required)if(!str_contains($html,$required))throw new RuntimeException('Shared account page lost '.$required);
        if(substr_count($html,'<form')!==1||str_contains($html,'Old duplicate'))throw new RuntimeException('Duplicate presentation or form');
        if($floating!==str_contains($html,'data-account-auth-dialog'))throw new RuntimeException('Wrong account surface');
        if(!$floating&&!str_contains($html,'data-plan-prices="safe"'))throw new RuntimeException('Lost business workflow attributes');
    }
    echo "OK: shared header/footer, one server form, modal authentication, complete business page, root-relative links and preserved workflow scripts.\n";
}finally{foreach([$dir.'/_account-page.php',$dir.'/index.html',$dir.'/assets/account-page.css'] as $file)if(is_file($file))unlink($file);rmdir($dir.'/assets');rmdir($dir);}

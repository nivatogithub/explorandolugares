<?php
declare(strict_types=1);
$package=$argv[1]??'';if(!is_file($package.'/build-manifest.json'))throw new RuntimeException('Expected a built package');
$_SESSION=[];$_GET=['lang'=>'en'];$_SERVER['REQUEST_URI']='/negocios/registro/';
ob_start();require $package.'/public/_account-page.php';account_page(false);
echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Crear cuenta</title></head><body><main id="contenido"><h1>Crear cuenta</h1><form method="post"><input type="hidden" name="csrf" value="unchanged-test-token"><label>Tu nombre<input name="name" required></label></form></main></body></html>';
ob_end_flush();$html=ob_get_clean();
foreach(['data-portal-shell="1"','data-portal-footer="1"','unchanged-test-token','noindex,nofollow','Create account','Your name','/assets/portal-shell.css?v='] as $expected)if(!str_contains($html,$expected))throw new RuntimeException('Missing composed package output: '.$expected);
if(substr_count($html,'data-portal-shell="1"')!==1)throw new RuntimeException('Duplicate header');
echo "OK: packaged account composition preserves CSRF/noindex, translates initial HTML and uses one shared shell.\n";

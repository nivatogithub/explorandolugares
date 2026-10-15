<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/BusinessPortalAccess.php';

function access_check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "OK: {$message}\n";
}

$secret = str_repeat('ab', 32);
$privateConfig = ['business_accounts_enabled'=>false, 'business_accounts_test_key'=>$secret];
$closedConfig = ['business_accounts_enabled'=>false, 'business_accounts_test_key'=>''];
$session = [];
access_check(!BusinessPortalAccess::available($privateConfig, $session, 1000), 'El portal empresarial permanece cerrado sin llave de prueba en la sesión.');
access_check(!BusinessPortalAccess::unlockForTesting($privateConfig, 'incorrecta', $session, 1000), 'Una llave incorrecta no abre el portal.');
access_check(BusinessPortalAccess::unlockForTesting($privateConfig, $secret, $session, 1000), 'La llave privada válida inicia la sesión de pruebas.');
access_check(BusinessPortalAccess::available($privateConfig, $session, 1001), 'La sesión puede navegar las rutas empresariales sin repetir la llave.');
access_check(!BusinessPortalAccess::available($privateConfig, $session, 15400), 'La sesión de pruebas caduca después de cuatro horas.');
$session = ['business_portal_test_until'=>9999];
access_check(!BusinessPortalAccess::available($closedConfig, $session, 1000), 'Quitar la llave de configuración revoca sesiones de prueba existentes.');
$enabledConfig = ['business_accounts_enabled'=>true, 'business_accounts_test_key'=>''];
access_check(BusinessPortalAccess::available($enabledConfig, $session, 1000), 'La activación normal funciona cuando se habilite el registro públicamente.');

foreach (['public/business/register.php','public/business/login.php','public/business/logout.php','public/business/dashboard.php','public/reclamar-negocio.php'] as $route) {
    $source = file_get_contents(dirname(__DIR__).'/'.$route);
    access_check(is_string($source) && strpos($source, 'require_business_portal_access();') !== false, "La ruta {$route} exige habilitación privada.");
}
$adminSource = file_get_contents(dirname(__DIR__).'/public/admin/index.php');
access_check(is_string($adminSource) && strpos($adminSource, 'require_business_portal_access();') === false, 'El panel administrativo conserva su acceso independiente por contraseña.');

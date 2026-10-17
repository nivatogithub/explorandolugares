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

$session=['business_portal_test_until'=>PHP_INT_MAX,'business_portal_test_key_hash'=>hash('sha256',str_repeat('a',64))];
access_check(!BusinessPortalAccess::available(['business_accounts_enabled'=>false,'business_accounts_test_key'=>str_repeat('a',64)],$session),'Una llave y sesión antiguas nunca abren el registro cerrado.');
access_check($session===[],'Las sesiones antiguas de prueba se invalidan.');
access_check(BusinessPortalAccess::available(['business_accounts_enabled'=>true],$session),'El registro habilitado conserva su acceso normal.');
access_check(!method_exists(BusinessPortalAccess::class,'unlockForTesting'),'Se eliminó la función de apertura por parámetro.');

foreach (['public/business/register.php','public/business/login.php','public/business/logout.php','public/business/dashboard.php','public/reclamar-negocio.php'] as $route) {
    $source = file_get_contents(dirname(__DIR__).'/'.$route);
    access_check(is_string($source) && strpos($source, 'require_business_portal_access();') !== false, "La ruta {$route} exige habilitación privada.");
}
$adminSource = file_get_contents(dirname(__DIR__).'/public/admin/index.php');
access_check(is_string($adminSource) && strpos($adminSource, 'require_business_portal_access();') === false, 'El panel administrativo conserva su acceso independiente por contraseña.');

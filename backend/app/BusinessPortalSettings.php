<?php
declare(strict_types=1);

final class BusinessPortalSettings
{
    /** Atomically update only the two business-portal access settings in private config. */
    public static function save(string $configPath, bool $publicEnabled, string $testKey): void
    {
        if (is_link($configPath) || !is_file($configPath) || !is_readable($configPath) || !is_writable($configPath)) {
            throw new RuntimeException('El archivo privado no está disponible para actualizar.');
        }
        if ($publicEnabled && $testKey !== '') {
            throw new InvalidArgumentException('El registro público no puede conservar una llave privada de prueba.');
        }
        if ($testKey !== '' && strlen($testKey) < 32) {
            throw new InvalidArgumentException('La llave de prueba debe tener al menos 32 caracteres.');
        }
        $config = require $configPath;
        if (!is_array($config)) {
            throw new RuntimeException('El archivo privado no contiene una configuración válida.');
        }
        $config['business_accounts_enabled'] = $publicEnabled;
        $config['business_accounts_test_key'] = $testKey;

        $directory = dirname($configPath);
        $temporary = tempnam($directory, '.business-access-');
        if (!is_string($temporary)) {
            throw new RuntimeException('No se pudo preparar el guardado privado.');
        }
        if (realpath(dirname($temporary)) !== realpath($directory)) {
            @unlink($temporary);
            throw new RuntimeException('El guardado temporal no quedó dentro del directorio privado.');
        }
        $mode = fileperms($configPath);
        if (is_int($mode)) @chmod($temporary, $mode & 0777);
        $source = "<?php\ndeclare(strict_types=1);\nreturn ".var_export($config, true).";\n";
        try {
            if (file_put_contents($temporary, $source, LOCK_EX) !== strlen($source)) {
                throw new RuntimeException('No se pudo escribir la configuración privada.');
            }
            if (!rename($temporary, $configPath)) {
                throw new RuntimeException('No se pudo activar la configuración privada.');
            }
            if (function_exists('opcache_invalidate')) @opcache_invalidate($configPath, true);
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }
}

<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = is_file(dirname(__DIR__).'/app/PlaceContentMigration.php') ? dirname(__DIR__).'/app' : dirname(__DIR__);
require $app.'/PlaceContentMigration.php';
$options = getopt('', ['config:', 'public:', 'apply']);
if (empty($options['config']) || empty($options['public'])) {
    fwrite(STDERR, "Uso: php migrate-place-content.php --config=/ruta/privada/config.php --public=/ruta/public [--apply]\nSin --apply solo se comprueba; requiere esquema 060.\n");
    exit(2);
}
try {
    $config = require $options['config'];
    $pdo = new PDO($config['dsn'], $config['db_user'] ?? null, $config['db_password'] ?? null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $plan = json_decode(file_get_contents($app.'/content/destination-content-060.json'), true, 512, JSON_THROW_ON_ERROR);
    $result = (new PlaceContentMigration($pdo))->run($plan, $options['public'], array_key_exists('apply', $options));
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Migración detenida; no se confirmó ningún cambio de contenido. " . $error->getMessage() . "\n");
    exit(1);
}

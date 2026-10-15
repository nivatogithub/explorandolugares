<?php
// Copiar fuera de la carpeta pública: release/private/config.php. Nunca versionar el archivo real.
return [
 'dsn'=>'mysql:host=localhost;dbname=BASE_CPANEL;charset=utf8mb4',
 'db_user'=>'USUARIO_CPANEL', 'db_password'=>'',
 'app_key'=>'', // 32 bytes aleatorios: bin2hex(random_bytes(32)).
 'admin_user'=>'', 'admin_password_hash'=>'', // password_hash; nunca contraseña en texto plano.
 'registration_enabled'=>false, 'business_accounts_enabled'=>false,
 // Acceso temporal para pruebas privadas. Usa una llave aleatoria de al menos 32 caracteres; nunca la publiques ni la reutilices.
 'business_accounts_test_key'=>'', 'local_test'=>false,
 'privacy'=>['operator'=>'','contact'=>'','retention'=>'','retention_en'=>''],
];

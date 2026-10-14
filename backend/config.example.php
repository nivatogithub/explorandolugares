<?php
// Copiar fuera de la carpeta pública: release/private/config.php. Nunca versionar el archivo real.
return [
 'dsn'=>'mysql:host=localhost;dbname=BASE_CPANEL;charset=utf8mb4',
 'db_user'=>'USUARIO_CPANEL', 'db_password'=>'',
 'app_key'=>'', // 32 bytes aleatorios: bin2hex(random_bytes(32)).
 'admin_user'=>'', 'admin_password_hash'=>'', // password_hash; nunca contraseña en texto plano.
 'registration_enabled'=>false, 'local_test'=>false,
 // Configuración independiente; remitente real del dominio verificado. No versionar secretos.
 'resend'=>['enabled'=>false,'api_key'=>'','from'=>''],
 'privacy'=>['operator'=>'','contact'=>'','retention'=>'','retention_en'=>''],
];

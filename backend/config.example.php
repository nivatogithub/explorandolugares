<?php
// Copiar fuera de la carpeta pública: release/private/config.php. Nunca versionar el archivo real.
return [
 'dsn'=>'mysql:host=localhost;dbname=BASE_CPANEL;charset=utf8mb4',
 'db_user'=>'USUARIO_CPANEL', 'db_password'=>'',
 'app_key'=>'', // 32 bytes aleatorios: bin2hex(random_bytes(32)).
 'admin_user'=>'', 'admin_password_hash'=>'', // password_hash; nunca contraseña en texto plano.
 'registration_enabled'=>false, 'business_accounts_enabled'=>false,
 'local_test'=>false,
 // Optional actual account quota readings; never use whole-server disk totals as account quota.
 'hosting_storage'=>['quota_bytes'=>null,'used_bytes'=>null,'measured_at'=>null],
 'privacy'=>['operator'=>'','contact'=>'','retention'=>'','retention_en'=>''],
];

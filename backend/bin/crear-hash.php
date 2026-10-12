<?php
// Uso: introducir contraseña por stdin; no pasar como argumento de shell.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$password=rtrim(stream_get_contents(STDIN), "\r\n");
if (strlen($password)<14 || strlen($password)>72) {fwrite(STDERR,"Usa una contraseña de 14 a 72 bytes.\n");exit(1);}
echo password_hash($password, PASSWORD_DEFAULT),PHP_EOL;

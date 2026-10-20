<?php
declare(strict_types=1);
/** Hash the packaged resource, not a manually maintained deployment date. */
function admin_asset(string $name): string
{
    if (!preg_match('~^[a-zA-Z0-9/_-]+\.(?:css|js)$~D', $name)) throw new InvalidArgumentException('Recurso inválido.');
    $file=dirname(__DIR__).'/assets/'.$name;
    if (!is_file($file)) throw new RuntimeException('Falta un recurso del panel.');
    return '/assets/'.$name.'?v='.substr(hash_file('sha256',$file),0,16);
}

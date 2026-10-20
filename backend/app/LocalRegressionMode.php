<?php
declare(strict_types=1);

final class LocalRegressionMode
{
    public static function enabled(array $config, array $server): bool
    {
        return ($config['local_test'] ?? false) === true
            && getenv('EL_PREPROD_LOCAL') === '1'
            && in_array(parse_url('http://'.($server['HTTP_HOST'] ?? ''), PHP_URL_HOST), ['127.0.0.1', 'localhost', '[::1]'], true)
            && ($server['HTTP_X_EL_LOCAL_REGRESSION'] ?? '') === '1';
    }
}

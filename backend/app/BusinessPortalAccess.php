<?php
declare(strict_types=1);
final class BusinessPortalAccess
{
    public static function available(array $config, array &$session, ?int $now = null): bool
    {
        unset($session['business_portal_test_until'], $session['business_portal_test_key_hash']);
        return !empty($config['business_accounts_enabled']);
    }
}

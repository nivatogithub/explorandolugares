<?php
declare(strict_types=1);

final class BusinessPortalAccess
{
    private const TEST_SESSION_SECONDS = 14400;

    public static function available(array $config, array &$session, ?int $now = null): bool
    {
        if (!empty($config['business_accounts_enabled'])) return true;
        $key = $config['business_accounts_test_key'] ?? '';
        $expires = (int)($session['business_portal_test_until'] ?? 0);
        $now ??= time();
        if (is_string($key) && strlen($key) >= 32 && $expires > $now) return true;
        unset($session['business_portal_test_until']);
        return false;
    }

    public static function unlockForTesting(array $config, string $providedKey, array &$session, ?int $now = null): bool
    {
        if (!empty($config['business_accounts_enabled'])) return true;
        $expectedKey = $config['business_accounts_test_key'] ?? '';
        if (!is_string($expectedKey) || strlen($expectedKey) < 32 || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) return false;
        $now ??= time();
        $session['business_portal_test_until'] = $now + self::TEST_SESSION_SECONDS;
        return true;
    }
}

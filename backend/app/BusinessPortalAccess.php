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
        $fingerprint = is_string($key) && strlen($key) >= 32 ? hash('sha256', $key) : '';
        $sessionFingerprint = $session['business_portal_test_key_hash'] ?? '';
        if ($fingerprint !== '' && is_string($sessionFingerprint) && hash_equals($fingerprint, $sessionFingerprint) && $expires > $now) return true;
        unset($session['business_portal_test_until'], $session['business_portal_test_key_hash']);
        return false;
    }

    public static function unlockForTesting(array $config, string $providedKey, array &$session, ?int $now = null): bool
    {
        if (!empty($config['business_accounts_enabled'])) return true;
        $expectedKey = $config['business_accounts_test_key'] ?? '';
        if (!is_string($expectedKey) || strlen($expectedKey) < 32 || $providedKey === '' || !hash_equals($expectedKey, $providedKey)) return false;
        $now ??= time();
        $session['business_portal_test_until'] = $now + self::TEST_SESSION_SECONDS;
        $session['business_portal_test_key_hash'] = hash('sha256', $expectedKey);
        return true;
    }
}

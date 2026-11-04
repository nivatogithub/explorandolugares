<?php
declare(strict_types=1);

require_once __DIR__.'/AccountSessionSecurity.php';
require_once __DIR__.'/AccountAuthorization.php';

/** Shared session validation for private HTTP boundaries; never writes account data. */
final class AccountSessionValidator
{
    /** @param callable(): PDO $connection Open a connection only for account-backed sessions. */
    public static function synchronize(callable $connection, array &$session, bool $unifiedAdminOnly): void
    {
        try {
            if (!empty($session['business_account_id'])) {
                AccountSessionSecurity::synchronize($connection(), $session);
            }
            if ($unifiedAdminOnly && empty($session['admin_account_id'])) {
                unset($session['admin'], $session['admin_account_id'], $session['admin_user'], $session['last_seen']);
            }
            if (!empty($session['admin_account_id'])) {
                AccountAuthorization::synchronize($connection(), $session);
            }
        } catch (Throwable $error) {
            AccountSessionSecurity::clear($session);
            throw $error;
        }
    }

    public static function isActiveAdmin(array $session): bool
    {
        return !empty($session['admin']) && time() - (int)($session['last_seen'] ?? 0) <= 1800;
    }
}

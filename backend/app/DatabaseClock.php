<?php
declare(strict_types=1);

/** Stored business dates are UTC. Never inherit the hosting server's time zone. */
final class DatabaseClock
{
    public static function configure(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            // Numeric offset works without installed MySQL time-zone tables.
            // Session-only: does not change the server or rewrite existing data.
            $pdo->exec("SET SESSION time_zone = '+00:00'");
        }
    }
}

<?php
declare(strict_types=1);
require_once __DIR__.'/BusinessResearchContactImporter.php';

/** Name/destination matching stays compatible with public-directory research packages. */
final class BusinessChannelImporter
{
    public static function import(PDO $pdo,array $package): array
    {
        return BusinessResearchContactImporter::import($pdo,$package);
    }
}

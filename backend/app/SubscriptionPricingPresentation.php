<?php
declare(strict_types=1);
require_once __DIR__.'/PortalPresentation.php';

/** Places prepared public pricing inside its explicit components. */
final class SubscriptionPricingPresentation
{
    public static function table(string $table):void
    {
        require __DIR__.'/views/subscription-price-table.php';
    }
    public static function operator(string $text):void
    {
        require __DIR__.'/views/subscription-operator-price.php';
    }
}

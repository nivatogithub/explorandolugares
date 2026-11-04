<?php
declare(strict_types=1);
require_once __DIR__.'/HomeAttractions.php';

/** Emits the attraction component directly, with the prepared fallback if empty. */
final class HomeAttractionsPresentation
{
    public static function render(?string $content,string $fallback):void
    {
        require __DIR__.'/views/home-attractions.php';
    }
}

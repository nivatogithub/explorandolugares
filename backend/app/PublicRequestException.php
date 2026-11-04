<?php
declare(strict_types=1);

/** Stable reason for a rejected public request; Spanish message stays compatible. */
final class PublicRequestException extends RuntimeException
{
    public function __construct(public readonly string $reason,string $message)
    {
        parent::__construct($message);
    }
}

<?php
declare(strict_types=1);

/** Stable reason for a claim failure; the message remains compatible in Spanish. */
final class BusinessClaimException extends RuntimeException
{
    public function __construct(public readonly string $reason,string $message,?Throwable $previous=null)
    {
        parent::__construct($message,0,$previous);
    }
}

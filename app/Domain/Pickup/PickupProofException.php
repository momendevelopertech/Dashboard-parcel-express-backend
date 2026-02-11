<?php

namespace App\Domain\Pickup;

use Exception;

/**
 * Exception thrown when pickup proof handling fails.
 * 
 * This exception should trigger transaction rollback to ensure
 * no pickup is marked successful without proof persistence.
 */
class PickupProofException extends Exception
{
    public function __construct(string $message, int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

<?php

namespace App\Exceptions\Shipping;

use RuntimeException;

/**
 * Raised when a courier's API rejects or fails an operation that has no
 * safe fallback — creating a shipment or fetching tracking, unlike quote()
 * which degrades to a flat rate instead of throwing.
 */
class ShippingProviderException extends RuntimeException
{
    public static function requestFailed(string $carrier, string $operation, string $reason): self
    {
        return new self("{$carrier} {$operation} request failed: {$reason}");
    }
}

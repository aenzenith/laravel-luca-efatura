<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

use Throwable;

/**
 * No response arrived: DNS, TCP, TLS or timeout. Luca resets connections
 * from IP addresses it has not registered, so the usual cause is running
 * the call from a server whose outgoing IP is not on Luca's list.
 *
 * For a write operation (Send*, Cancel*, ...) the document may still have
 * been issued; look it up before sending it again.
 */
final class ConnectionException extends TransportException
{
    public static function wrap(string $operation, string $url, Throwable $previous): self
    {
        return new self(
            "Luca: no response from {$url} for [{$operation}]: {$previous->getMessage()}. "
            .'Luca accepts calls only from registered IP addresses; check that the outgoing IP of this server is registered.',
            $operation,
            null,
            null,
            $previous,
        );
    }
}

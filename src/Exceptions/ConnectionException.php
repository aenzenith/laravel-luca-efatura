<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

use Throwable;

/**
 * No response arrived: DNS, TCP, TLS or timeout. Luca serves only the IP
 * addresses registered on its application form, so the usual cause is a
 * server whose outgoing IP is not on Luca's list: the connection is reset
 * or never opens. A network problem on either side looks the same.
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
            .'Luca serves only registered IP addresses: check that the outgoing IP of this server is registered '
            .'and that this server can open HTTPS connections at all.',
            $operation,
            null,
            null,
            $previous,
        );
    }
}

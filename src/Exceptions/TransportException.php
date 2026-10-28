<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

use Throwable;

/**
 * The service answered, but not with a SOAP response the package can read
 * (HTTP error page, empty body, malformed XML).
 */
class TransportException extends LucaException
{
    public function __construct(
        string $message,
        public readonly string $operation = '',
        public readonly ?int $status = null,
        public readonly ?string $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

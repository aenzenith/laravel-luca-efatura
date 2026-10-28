<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Events;

use Aenzenith\LucaEFatura\Exceptions\TransportException;
use Aenzenith\LucaEFatura\Transport\SoapRequest;

/**
 * Dispatched when no readable response arrived: connection failure, SOAP
 * fault, HTTP error page. For a write operation the document may still
 * have been issued.
 */
final class RequestFailed
{
    public function __construct(
        public readonly string $connection,
        public readonly SoapRequest $request,
        public readonly TransportException $exception,
    ) {}
}

<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

/**
 * The service answered with a SOAP fault (deserialisation error, server
 * exception, unknown action).
 */
final class SoapFaultException extends TransportException
{
    public function __construct(
        string $operation,
        public readonly string $faultCode,
        public readonly string $faultString,
        public readonly ?string $detail,
        ?int $status,
        ?string $body,
    ) {
        parent::__construct("Luca: [{$operation}] SOAP fault {$faultCode}: {$faultString}", $operation, $status, $body);
    }
}

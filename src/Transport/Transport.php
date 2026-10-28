<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Transport;

use Aenzenith\LucaEFatura\Exceptions\ConnectionException;

/**
 * Moves one SOAP envelope to Luca and back.
 *
 * Returns whatever arrived, whatever the HTTP status (WCF sends faults
 * with 500); throws ConnectionException only when nothing arrived.
 */
interface Transport
{
    /**
     * @throws ConnectionException
     */
    public function send(SoapRequest $request): SoapResponse;
}

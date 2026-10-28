<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Events;

use Aenzenith\LucaEFatura\Result;
use Aenzenith\LucaEFatura\Transport\SoapRequest;

/**
 * Dispatched for every parsed response, successful or Result=Failed.
 */
final class ResponseReceived
{
    public function __construct(
        public readonly string $connection,
        public readonly SoapRequest $request,
        public readonly Result $result,
    ) {}
}

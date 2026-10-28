<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Events;

use Aenzenith\LucaEFatura\Transport\SoapRequest;

/**
 * Dispatched right before an envelope goes out. The payload still holds
 * document content and passwords; redact before you log it.
 */
final class RequestSending
{
    public function __construct(
        public readonly string $connection,
        public readonly SoapRequest $request,
    ) {}
}

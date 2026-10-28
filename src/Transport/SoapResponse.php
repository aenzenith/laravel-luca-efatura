<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Transport;

final class SoapResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {}
}

<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Transport;

use Aenzenith\LucaEFatura\Company;
use Aenzenith\LucaEFatura\Schema\Operation;

final class SoapRequest
{
    /**
     * @param  array<string, mixed>  $payload  the request members the envelope was built from
     */
    public function __construct(
        public readonly Operation $operation,
        public readonly string $url,
        public readonly string $body,
        public readonly array $payload,
        public readonly ?Company $company,
    ) {}

    public function action(): string
    {
        return $this->operation->action();
    }
}

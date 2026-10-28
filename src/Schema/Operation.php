<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Schema;

/**
 * One SOAP operation of a Luca service.
 */
final class Operation
{
    /**
     * Name prefixes of operations that only read. Every other operation is
     * treated as a write: it is never retried, because a lost response does
     * not mean the document was not issued.
     */
    private const READ_PREFIXES = ['Search', 'Get', 'Validate'];

    public function __construct(
        public readonly string $name,
        public readonly string $service,
        public readonly string $request,
        public readonly ?string $response,
    ) {}

    /**
     * SOAPAction of a WCF basicHttpBinding endpoint.
     */
    public function action(): string
    {
        return 'http://tempuri.org/I'.$this->service.'/'.$this->name;
    }

    public function isMutating(): bool
    {
        foreach (self::READ_PREFIXES as $prefix) {
            if (str_starts_with($this->name, $prefix)) {
                return false;
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

/**
 * The operation acts for a company but none is scoped. Thrown before
 * anything is sent.
 */
final class MissingCompanyException extends LucaException
{
    public static function for(string $operation): self
    {
        return new self(
            "Luca: [{$operation}] acts for a company but none is set. "
            .'Use Luca::company($taxCode) or set luca.connections.*.company.tax_code.'
        );
    }
}

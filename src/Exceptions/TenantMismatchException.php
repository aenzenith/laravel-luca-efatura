<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

/**
 * The request names a company other than the scoped one, or a UBL document
 * was issued by another supplier. Thrown before anything is sent.
 */
final class TenantMismatchException extends LucaException
{
    public static function field(string $operation, string $field, string $given, string $scoped): self
    {
        return new self("Luca: [{$operation}] {$field} [{$given}] does not match the scoped company [{$scoped}].");
    }

    public static function supplier(string $operation, string $party, ?string $found, string $scoped): self
    {
        $found ??= 'none';

        return new self("Luca: [{$operation}] document {$party} VKN/TCKN [{$found}] does not match the scoped company [{$scoped}].");
    }
}

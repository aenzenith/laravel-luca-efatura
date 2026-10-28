<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Support;

use InvalidArgumentException;

/**
 * ETTN (document UUID) checks.
 *
 * The search operations treat an empty Ettn as "no filter" and return the
 * first document of the company, so a lookup by ETTN must never go out
 * with a blank or malformed one.
 */
final class Ettn
{
    public static function isValid(string $ettn): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($ettn)) === 1;
    }

    public static function assert(string $ettn): string
    {
        if (! self::isValid($ettn)) {
            throw new InvalidArgumentException("Luca: [{$ettn}] is not an ETTN (UUID).");
        }

        return strtolower(trim($ettn));
    }

    public static function same(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strcasecmp(trim($a), trim($b)) === 0;
    }
}

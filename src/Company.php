<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura;

use InvalidArgumentException;

/**
 * The company (VKN, 10 digits) or sole proprietor (TCKN, 11 digits) a call
 * acts for, plus the optional sub-dealer ("alt bayi") vendor number.
 */
final class Company
{
    public readonly string $taxCode;

    public readonly ?string $vendorNumber;

    public function __construct(string $taxCode, ?string $vendorNumber = null)
    {
        $taxCode = trim($taxCode);

        if (! self::isTaxCode($taxCode)) {
            throw new InvalidArgumentException("Luca: [{$taxCode}] is not a VKN (10 digits) or TCKN (11 digits).");
        }

        $vendorNumber = $vendorNumber !== null ? trim($vendorNumber) : null;

        $this->taxCode = $taxCode;
        $this->vendorNumber = $vendorNumber === '' ? null : $vendorNumber;
    }

    public static function isTaxCode(string $value): bool
    {
        return preg_match('/^\d{10,11}$/', $value) === 1;
    }

    public function is(self $other): bool
    {
        return $this->taxCode === $other->taxCode && $this->vendorNumber === $other->vendorNumber;
    }

    public function __toString(): string
    {
        return $this->vendorNumber === null ? $this->taxCode : $this->taxCode.'/'.$this->vendorNumber;
    }
}

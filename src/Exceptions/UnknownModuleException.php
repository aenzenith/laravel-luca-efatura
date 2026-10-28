<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

final class UnknownModuleException extends LucaException
{
    public static function named(string $name): self
    {
        return new self("Luca: no module is registered as [{$name}].");
    }
}

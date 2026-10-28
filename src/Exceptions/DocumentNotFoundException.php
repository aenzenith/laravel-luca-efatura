<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

final class DocumentNotFoundException extends LucaException
{
    public static function ettn(string $ettn): self
    {
        return new self("Luca: no document with ETTN [{$ettn}].");
    }
}

<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

use Aenzenith\LucaEFatura\Result;

/**
 * The service answered Result=Failed (or no Result at all).
 */
final class OperationFailedException extends LucaException
{
    public function __construct(public readonly Result $result)
    {
        $error = $result->errorMessage() ?? 'no error message';

        parent::__construct("Luca: [{$result->operation->name}] failed: {$error}");
    }
}

<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

/**
 * A request does not fit the data contract: unknown operation, unknown
 * field, or a value of the wrong shape. Thrown before anything is sent.
 */
final class SchemaException extends LucaException {}

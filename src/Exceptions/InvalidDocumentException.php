<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Exceptions;

/**
 * A UBL document could not be read, or does not say what the call needs
 * (profile, supplier, receiver). Thrown before anything is sent.
 */
final class InvalidDocumentException extends LucaException {}

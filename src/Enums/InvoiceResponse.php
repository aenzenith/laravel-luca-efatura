<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Enums;

/**
 * Answer to an incoming commercial invoice (TICARIFATURA).
 */
enum InvoiceResponse: string
{
    case Accept = 'KABUL';
    case Reject = 'RED';
}

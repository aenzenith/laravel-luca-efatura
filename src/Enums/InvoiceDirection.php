<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Enums;

enum InvoiceDirection: string
{
    case Incoming = 'Incoming';
    case Outgoing = 'Outgoing';
}

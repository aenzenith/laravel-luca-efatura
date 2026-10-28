<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Enums;

/**
 * InvoiceDocumentType of GetDocumentViewerLink.
 */
enum DocumentType: string
{
    case EInvoice = 'EInvoice';
    case EArchiveInvoice = 'EArchiveInvoice';
}

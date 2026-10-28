<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Support;

use Aenzenith\LucaEFatura\Exceptions\InvalidDocumentException;
use DOMDocument;
use DOMXPath;

/**
 * Read-only view on a UBL-TR document: what it is and who issued it.
 *
 * Queries use local-name() so the reader does not depend on the prefixes a
 * generator picked.
 */
final class UblDocument
{
    private function __construct(
        private readonly DOMXPath $xpath,
        public readonly string $root,
    ) {}

    public static function parse(string $xml): self
    {
        if (trim($xml) === '') {
            throw new InvalidDocumentException('Luca: the UBL document is empty.');
        }

        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new InvalidDocumentException('Luca: the UBL document carries a DTD; refused.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
            $error = libxml_get_last_error();
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $document->documentElement === null) {
            $reason = $error !== false ? trim($error->message) : 'unreadable';

            throw new InvalidDocumentException("Luca: the UBL document is not well-formed XML: {$reason}.");
        }

        return new self(new DOMXPath($document), (string) $document->documentElement->localName);
    }

    /**
     * cbc:ProfileID: TEMELFATURA, TICARIFATURA, EARSIVFATURA, TEMELIRSALIYE, EARSIVBELGE, ...
     */
    public function profileId(): ?string
    {
        return $this->value('/*/*[local-name()="ProfileID"]');
    }

    /**
     * cbc:ID, the document number. Empty for "without number" sends.
     */
    public function number(): ?string
    {
        return $this->value('/*/*[local-name()="ID"]');
    }

    public function uuid(): ?string
    {
        return $this->value('/*/*[local-name()="UUID"]');
    }

    /**
     * VKN or TCKN of a party: AccountingSupplierParty, AccountingCustomerParty,
     * DespatchSupplierParty, DeliveryCustomerParty, ...
     */
    public function partyTaxCode(string $party): ?string
    {
        return $this->value(
            '/*/*[local-name()="'.$party.'"]/*[local-name()="Party"]/*[local-name()="PartyIdentification"]'
            .'/*[local-name()="ID"][@schemeID="VKN" or @schemeID="TCKN"]'
        );
    }

    private function value(string $query): ?string
    {
        $nodes = $this->xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $value = trim((string) $nodes->item(0)?->nodeValue);

        return $value === '' ? null : $value;
    }
}

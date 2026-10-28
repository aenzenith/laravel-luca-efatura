<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Modules;

use Aenzenith\LucaEFatura\Data\SentDocument;
use Aenzenith\LucaEFatura\Enums\DocumentType;
use Aenzenith\LucaEFatura\Enums\InvoiceDirection;
use Aenzenith\LucaEFatura\Exceptions\DocumentNotFoundException;
use Aenzenith\LucaEFatura\Exceptions\InvalidDocumentException;
use Aenzenith\LucaEFatura\Support\Ettn;
use Aenzenith\LucaEFatura\Support\UblDocument;

/**
 * Document-type independent helpers.
 */
class Documents extends Module
{
    private const ARCHIVE_PROFILES = ['EARSIVFATURA'];

    private const ESMM_PROFILES = ['EARSIVBELGE'];

    /**
     * Viewer URL of an e-Fatura or e-Arşiv invoice.
     */
    public function viewerLink(
        string $ettn,
        DocumentType $type = DocumentType::EInvoice,
        InvoiceDirection $direction = InvoiceDirection::Outgoing,
    ): string {
        $result = $this->call('GetDocumentViewerLink', [
            'Ettn' => Ettn::assert($ettn),
            'InvoiceDirection' => $direction,
            'InvoiceDocumentType' => $type,
        ]);

        $link = self::findUrl($result->data()) ?? throw DocumentNotFoundException::ettn($ettn);

        return $this->connection->forcesHttpsLinks() ? self::https($link) : $link;
    }

    /**
     * Send any UBL-TR document through the operation its ProfileID calls
     * for: EARSIVFATURA to e-Arşiv, EARSIVBELGE to e-SMM, *IRSALIYE to
     * e-İrsaliye, every other profile to e-Fatura. A document without a
     * number (empty cbc:ID) goes to the "without number" operation.
     *
     * For e-Fatura and e-İrsaliye the receiver alias is looked up when not
     * given; a receiver with several aliases must be given one explicitly.
     *
     * The profile is not changed: a TEMELFATURA to a taxpayer that is not
     * registered for e-Fatura fails at Luca. Decide e-Fatura or e-Arşiv
     * with TaxPayers::find() when you build the UBL.
     */
    public function sendUbl(string $ubl, ?string $receiverTag = null, bool $sendMail = false): SentDocument
    {
        $document = UblDocument::parse($ubl);
        $profile = strtoupper((string) $document->profileId());
        $numbered = $document->number() !== null;

        if ($profile === '') {
            throw new InvalidDocumentException('Luca: the UBL document has no cbc:ProfileID.');
        }

        if (in_array($profile, self::ARCHIVE_PROFILES, true)) {
            $archive = $this->connection->eArchive();

            return $numbered ? $archive->sendXml($ubl, $sendMail) : $archive->sendXmlWithoutNumber($ubl, $sendMail);
        }

        if (in_array($profile, self::ESMM_PROFILES, true)) {
            return $this->connection->esmm()->sendXml($ubl);
        }

        if (str_ends_with($profile, 'IRSALIYE')) {
            $despatches = $this->connection->despatches();
            $tag = $receiverTag ?? $this->onlyAlias($document, 'DeliveryCustomerParty', despatch: true);

            return $numbered ? $despatches->sendXml($ubl, $tag) : $despatches->sendXmlWithoutNumber($ubl, $tag);
        }

        $invoices = $this->connection->eInvoices();
        $tag = $receiverTag ?? $this->onlyAlias($document, 'AccountingCustomerParty', despatch: false);

        return $numbered ? $invoices->sendXml($ubl, $tag) : $invoices->sendXmlWithoutNumber($ubl, $tag);
    }

    private function onlyAlias(UblDocument $document, string $party, bool $despatch): string
    {
        $taxCode = $document->partyTaxCode($party)
            ?? throw new InvalidDocumentException("Luca: the UBL document has no {$party} VKN/TCKN to look the receiver alias up with.");

        $taxPayers = $this->connection->taxPayers();
        $aliases = ($despatch ? $taxPayers->findDespatch($taxCode) : $taxPayers->find($taxCode))->inboxTags ?? [];

        if (count($aliases) !== 1) {
            throw new InvalidDocumentException(
                "Luca: receiver [{$taxCode}] has ".count($aliases).' aliases'
                .($aliases === [] ? ' (not registered for '.($despatch ? 'e-İrsaliye' : 'e-Fatura').')' : ': '.implode(', ', $aliases))
                .'; pass the receiver tag explicitly.'
            );
        }

        return $aliases[0];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function findUrl(array $data): ?string
    {
        foreach ($data as $value) {
            if (is_string($value) && preg_match('#^https?://#i', trim($value)) === 1) {
                return trim($value);
            }

            if (is_array($value) && ($url = self::findUrl($value)) !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * "http://host:80/path" → "https://host/path"
     */
    private static function https(string $url): string
    {
        return (string) preg_replace('#^http://([^/:]+)(:80)?(?=/|$)#i', 'https://$1', $url);
    }
}

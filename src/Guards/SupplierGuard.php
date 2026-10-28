<?php

declare(strict_types=1);

namespace Aenzenith\LucaEFatura\Guards;

use Aenzenith\LucaEFatura\Company;
use Aenzenith\LucaEFatura\Exceptions\InvalidDocumentException;
use Aenzenith\LucaEFatura\Exceptions\MissingCompanyException;
use Aenzenith\LucaEFatura\Exceptions\TenantMismatchException;
use Aenzenith\LucaEFatura\Schema\Operation;
use Aenzenith\LucaEFatura\Support\UblDocument;

/**
 * The XML send operations carry no company field: Luca issues the document
 * under whatever VKN/TCKN the UBL names as its issuer. This guard makes the
 * issuer match the scoped company before anything is sent, so one tenant's
 * document cannot go out under another tenant's account.
 */
final class SupplierGuard
{
    /**
     * operation => [path of the document content in the payload, issuing party]
     *
     * @var array<string, array{string, string}>
     */
    private const DOCUMENTS = [
        'SendInvoiceXml' => ['Invoices.*.InvoiceContent', 'AccountingSupplierParty'],
        'SendInvoiceXmlWithoutInvoiceNumber' => ['Invoices.*.InvoiceContent', 'AccountingSupplierParty'],
        'SendArchiveInvoiceXml' => ['ArchiveInvoices.*.ArchiveInvoiceContent', 'AccountingSupplierParty'],
        'SendArchiveInvoiceXmlWithoutInvoiceNumber' => ['ArchiveInvoices.*.ArchiveInvoiceContent', 'AccountingSupplierParty'],
        'SendDespatchAdviceXml' => ['DespatchAdvices.*.DespatchAdviceContent', 'DespatchSupplierParty'],
        'SendDespatchAdviceXmlWithoutDespatchAdviceNumber' => ['DespatchAdvices.*.DespatchAdviceContent', 'DespatchSupplierParty'],
        'SendReceiptAdviceXml' => ['ReceiptAdvices.*.ReceiptAdviceContent', 'DeliveryCustomerParty'],
        'SendReceiptAdviceXmlWithoutReceiptAdviceNumber' => ['ReceiptAdvices.*.ReceiptAdviceContent', 'DeliveryCustomerParty'],
        'SendESMMXml' => ['ESMM', 'AccountingSupplierParty'],
    ];

    public static function covers(Operation $operation): bool
    {
        return isset(self::DOCUMENTS[$operation->name]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function check(Operation $operation, array $payload, ?Company $company): void
    {
        if (! self::covers($operation)) {
            return;
        }

        if ($company === null) {
            throw MissingCompanyException::for($operation->name);
        }

        [$path, $party] = self::DOCUMENTS[$operation->name];

        $contents = data_get($payload, $path);
        $contents = is_array($contents) ? $contents : [$contents];

        if ($contents === [] || $contents === [null]) {
            throw new InvalidDocumentException("Luca: [{$operation->name}] carries no document ({$path}).");
        }

        foreach ($contents as $content) {
            if (! is_string($content)) {
                throw new InvalidDocumentException("Luca: [{$operation->name}] {$path} must be the UBL XML string.");
            }

            $issuer = UblDocument::parse($content)->partyTaxCode($party);

            if ($issuer !== $company->taxCode) {
                throw TenantMismatchException::supplier($operation->name, $party, $issuer, $company->taxCode);
            }
        }
    }
}

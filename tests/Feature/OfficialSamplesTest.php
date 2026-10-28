<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Exceptions\SchemaException;
use Aenzenith\LucaEFatura\Luca;
use Aenzenith\LucaEFatura\Schema\Schema;
use Aenzenith\LucaEFatura\Soap\EnvelopeBuilder;
use Aenzenith\LucaEFatura\Soap\ValueCodec;

/*
 * Luca's own request samples (integration kit + Postman collection) are
 * read into payload arrays and rebuilt by the envelope builder. The rebuilt
 * request must equal the sample element for element: same names, same
 * namespaces, same order, same values. WCF drops an element that arrives
 * out of order or in the wrong namespace without an error, so a drift here
 * would silently strip fields (tax lines, exemptions) from issued documents.
 */

/**
 * @return array{0: string, 1: DOMElement}
 */
function officialRequest(string $file): array
{
    $raw = (string) file_get_contents(__DIR__.'/../Fixtures/official/'.$file);

    preg_match('/<(\w+):Envelope\b.*?<\/\1:Envelope>/su', $raw, $match);

    $xml = strtr($match[0], [
        '{{XmlB64}}' => 'AA==',
        '{{InvoiceDate}}' => '2026-10-28T00:00:00',
        '{{ExternalInvoiceCode}}' => 'EXT-1',
        '{{ReceiverInboxTag}}' => 'urn:mail:defaultpk@example.com',
        '{{ReceiverTag}}' => 'urn:mail:defaultpk@example.com',
        '{{ReceiverTaxCode}}' => '11111111111',
    ]);

    $document = new DOMDocument;
    $document->preserveWhiteSpace = false;
    $document->loadXML($xml);

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('soap', EnvelopeBuilder::SOAP_ENVELOPE);
    $xpath->registerNamespace('tem', EnvelopeBuilder::TEMPURI);

    $operation = $xpath->query('/soap:Envelope/soap:Body/tem:*')->item(0);
    $request = $xpath->query('tem:request', $operation)->item(0);

    return [$operation->localName, $request];
}

/**
 * Read a sample element back into the payload a caller would pass.
 */
function samplePayload(DOMElement $element, string $type, Schema $schema): mixed
{
    $children = array_values(array_filter(iterator_to_array($element->childNodes), fn ($n) => $n instanceof DOMElement));

    if (Schema::isPrimitive($type)) {
        return $type === 'base64Binary' ? base64_decode($element->textContent) : $element->textContent;
    }

    $definition = $schema->type($type);

    if ($definition['kind'] === 'enum') {
        return $element->textContent;
    }

    if ($definition['kind'] === 'array') {
        return array_map(fn (DOMElement $child) => samplePayload($child, $definition['of'], $schema), $children);
    }

    $types = array_column($schema->fields($type), 'type', 'name');
    $payload = [];

    foreach ($children as $child) {
        $payload[$child->localName] = isset($types[$child->localName])
            ? samplePayload($child, $types[$child->localName], $schema)
            : $child->textContent;
    }

    return $payload;
}

/**
 * Element names, namespaces and text in document order.
 *
 * @return list<string>
 */
function canonical(DOMElement $element): array
{
    $lines = [];
    $children = array_values(array_filter(iterator_to_array($element->childNodes), fn ($n) => $n instanceof DOMElement));

    foreach ($children as $child) {
        $hasElements = array_filter(iterator_to_array($child->childNodes), fn ($n) => $n instanceof DOMElement) !== [];
        $lines[] = $child->namespaceURI.' '.$child->localName.($hasElements ? '' : ' = '.trim($child->textContent));
        array_push($lines, ...array_map(fn ($line) => '  '.$line, canonical($child)));
    }

    return $lines;
}

function rebuilt(string $operation, mixed $payload): DOMElement
{
    $schema = app(Luca::class)->schema();
    $xml = (new EnvelopeBuilder($schema, new ValueCodec))->request($schema->operation($operation), $payload);

    $document = new DOMDocument;
    $document->loadXML($xml);

    return $document->getElementsByTagNameNS(EnvelopeBuilder::TEMPURI, 'request')->item(0);
}

it('rebuilds the official request sample exactly', function (string $file) {
    $schema = app(Luca::class)->schema();
    [$operation, $request] = officialRequest($file);

    $payload = samplePayload($request, $schema->operation($operation)->request, $schema);

    expect(canonical(rebuilt($operation, $payload)))->toBe(canonical($request));
})->with([
    'InvoiceService__CancelArchiveInvoice.xml',
    'InvoiceService__GetCompanyBalance.xml',
    'InvoiceService__GetCompanyVendor.xml',
    'InvoiceService__GetDocumentViewerLink.xml',
    'InvoiceService__ValidateUserCompany.xml',
    'InvoiceService__SearchAllInvoice.txt',
    'InvoiceService__SearchInvoice_ETTN_Gelen.xml',
    'InvoiceService__SearchInvoice_ETTN_Go_nderilen.xml',
    'InvoiceService__SearchInvoice_Gelen.xml',
    'InvoiceService__SearchInvoice_Go_nderilen.xml',
    'InvoiceService__SearchInvoice_soapui.txt',
    'InvoiceService__basit_kdv_iskontolu_istisna_fatura.xml',
    'InvoiceService__basit_kdv_iskontolu_sat_s_fatura.xml',
    'InvoiceService__basit_kdv_iskontolu_tevkifat_fatura.xml',
    'InvoiceService__basit_kdvli_sat_s_fatura.xml',
    'InvoiceService__otvli_earsiv_fatura.xml',
    'InvoiceService__gelen_ticari_fatura_kabul.xml',
    'InvoiceService__gelen_ticari_fatura_red.xml',
    'AddressBookService__GetAllBySearchTaxOfficeNameAndCityCode.xml',
    'AddressBookService__GetCityList.xml',
    'AddressBookService__GetDespatchTaxPayer.xml',
    'AddressBookService__GetTaxOfficeList.xml',
    'AddressBookService__GetTaxPayer.xml',
    'AddressBookService__GetTaxPayerWithPaging.xml',
    'AddressBookService__GetTownlist.xml',
    'postman__ArchiveInvoice.xml',
    'postman__CancelArchiveInvoice.xml',
    'postman__GetDocumentViewerLink.xml',
    'postman__SearchDespatchAdvice.xml',
    'postman__SearchESMM.xml',
    'postman__SearchInvoice.xml',
    'postman__SendArchiveInvoice.xml',
    'postman__SendArchiveInvoiceReport.xml',
    'postman__SendArchiveInvoiceXml.xml',
    'postman__SendArchiveInvoiceXmlWithoutInvoiceNumber.xml',
    'postman__SendDespatchAdvice.xml',
    'postman__SendDespatchAdviceXmlWithoutDespatchAdviceNumber.xml',
    'postman__SendESMMXml.xml',
    'postman__SendInvoiceXmlWithoutInvoiceNumber.xml',
    'postman__ValidateUserCompany.xml',
]);

/*
 * Two of Luca's own samples spell IsArchiveIncluded with a Turkish dotless
 * "ı". WCF would ignore that element and return results without the
 * archived invoices; strict mode refuses it instead.
 */
it('refuses a field the contract does not define', function (string $file) {
    $schema = app(Luca::class)->schema();
    [$operation, $request] = officialRequest($file);

    rebuilt($operation, samplePayload($request, $schema->operation($operation)->request, $schema));
})->with([
    'InvoiceService__SearchAllInvoice_Gelen.xml',
    'InvoiceService__SearchAllInvoice_Go_nderilen.xml',
])->throws(SchemaException::class, 'IsArchiveıncluded');

<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Data\SentDocument;
use Aenzenith\LucaEFatura\Exceptions\DocumentNotFoundException;
use Aenzenith\LucaEFatura\Exceptions\InvalidDocumentException;
use Aenzenith\LucaEFatura\Exceptions\OperationFailedException;
use Aenzenith\LucaEFatura\Facades\Luca;
use Aenzenith\LucaEFatura\Testing\FakeResponse;

const ETTN = 'f47ac10b-58cc-4372-a567-0e02b2c3d479';
const OTHER_ETTN = '0b8f2c1e-7d4a-4c55-9e1f-3a6b5c4d2e10';
const PDF = "%PDF-1.4\n\x00\xff\xfe binary \x01 body";

/*
 * Search operations treat an empty Ettn as "no filter" and return the
 * company's first document. A lookup must never go out without a valid
 * ETTN, and a record with another ETTN must never be handed back as the
 * one asked for: its PDF would reach the wrong customer.
 */
it('refuses a lookup without a valid ETTN', function (string $ettn) {
    $fake = Luca::fake();

    expect(fn () => Luca::eArchive()->pdf($ettn))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Luca::eInvoices()->find($ettn))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Luca::esmm()->pdf($ettn))->toThrow(InvalidArgumentException::class);

    $fake->assertNothingSent();
})->with(['', '   ', 'ABC2026000000001', 'f47ac10b58cc4372a5670e02b2c3d479']);

it('does not take another document for the one asked for', function () {
    Luca::fake([
        'SearchArchiveInvoice' => ['ArchiveInvoices' => [['ETTN' => OTHER_ETTN, 'InvoicePdf' => PDF]]],
        'SearchInvoice' => ['Invoices' => [['ETTN' => OTHER_ETTN, 'InvoicePdf' => PDF]]],
    ]);

    expect(Luca::eInvoices()->find(ETTN))->toBeNull()
        ->and(fn () => Luca::eArchive()->pdf(ETTN))->toThrow(DocumentNotFoundException::class);
});

it('returns the PDF bytes of the document asked for', function () {
    $fake = Luca::fake([
        'SearchArchiveInvoice' => ['ArchiveInvoices' => [['ETTN' => strtoupper(ETTN), 'InvoicePdf' => PDF]]],
    ]);

    expect(Luca::eArchive()->pdf(ETTN))->toBe(PDF);

    $fake->assertSent('SearchArchiveInvoice', fn (array $payload) => $payload['Ettn'] === ETTN
        && $payload['ResultSet']['IsPDFIncluded'] === true);
});

/*
 * "Not registered for e-Fatura" decides e-Arşiv over e-Fatura. Reading an
 * outage as "not registered" would issue the wrong document type, which
 * cannot be taken back.
 */
it('tells an unregistered taxpayer from a failed lookup', function (FakeResponse $response, ?string $expected) {
    Luca::fake(['GetTaxPayer' => $response]);

    $lookup = fn () => Luca::taxPayers()->find('1111111111');

    $expected === OperationFailedException::class
        ? expect($lookup)->toThrow(OperationFailedException::class)
        : expect($lookup()?->inboxTags)->toBe($expected === null ? null : explode(',', $expected));
})->with([
    'not found' => [FakeResponse::failure('Mükellef bulunamadı.'), null],
    'not registered' => [FakeResponse::failure('Kayıtlı değil'), null],
    'empty success' => [FakeResponse::success(['TaxPayers' => []]), null],
    'other taxpayer' => [FakeResponse::success(['TaxPayers' => [['TaxPayerTaxCode' => '2222222222', 'InboxTagList' => ['urn:mail:defaultpk@b.com']]]]), null],
    'service error' => [FakeResponse::failure('Servis geçici olarak kullanılamıyor.'), OperationFailedException::class],
    'registered' => [FakeResponse::success(['TaxPayers' => [
        ['Taxpayertaxcode' => '1111111111', 'Inboxtaglist' => ['urn:mail:defaultpk@a.com']],
        ['TaxPayerTaxCode' => '1111111111', 'InboxTagList' => ['urn:mail:faturapk@a.com', 'urn:mail:defaultpk@a.com']],
    ]]), 'urn:mail:defaultpk@a.com,urn:mail:faturapk@a.com'],
]);

it('refuses a taxpayer lookup that would list every taxpayer', function (string $taxCode) {
    $fake = Luca::fake();

    expect(fn () => Luca::taxPayers()->find($taxCode))->toThrow(InvalidArgumentException::class);

    $fake->assertNothingSent();
})->with(['', '12345', '1234567890123']);

/*
 * sendUbl picks the operation from the ProfileID; a wrong pick issues the
 * wrong document type.
 */
it('routes a UBL document by its profile', function (string $ubl, string $operation) {
    $fake = Luca::fake([
        'GetTaxPayer' => ['TaxPayers' => [['TaxPayerTaxCode' => '11111111111', 'InboxTagList' => ['urn:mail:defaultpk@a.com']]]],
        'GetDespatchTaxPayer' => ['TaxPayers' => [['TaxPayerTaxCode' => '11111111111', 'InboxTagList' => ['urn:mail:irsaliyepk@a.com']]]],
    ]);

    expect(Luca::documents()->sendUbl($ubl))->toBeInstanceOf(SentDocument::class);

    $fake->assertSent($operation);
})->with([
    'e-Arşiv' => [ubl(), 'SendArchiveInvoiceXml'],
    'e-Arşiv without number' => [ubl(number: ''), 'SendArchiveInvoiceXmlWithoutInvoiceNumber'],
    'e-Fatura' => [ubl(profile: 'TICARIFATURA'), 'SendInvoiceXml'],
    'e-Fatura without number' => [ubl(profile: 'TEMELFATURA', number: ''), 'SendInvoiceXmlWithoutInvoiceNumber'],
    'e-İrsaliye' => [ubl(profile: 'TEMELIRSALIYE', root: 'DespatchAdvice', supplierParty: 'DespatchSupplierParty', customerParty: 'DeliveryCustomerParty'), 'SendDespatchAdviceXml'],
    'e-SMM' => [ubl(profile: 'EARSIVBELGE'), 'SendESMMXml'],
]);

it('refuses to guess the alias of a receiver with several', function () {
    $fake = Luca::fake([
        'GetTaxPayer' => ['TaxPayers' => [['TaxPayerTaxCode' => '11111111111', 'InboxTagList' => ['urn:mail:defaultpk@a.com', 'urn:mail:muhasebepk@a.com']]]],
    ]);

    expect(fn () => Luca::documents()->sendUbl(ubl(profile: 'TEMELFATURA')))->toThrow(InvalidDocumentException::class);

    $fake->assertNotSent('SendInvoiceXml');
});

<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Exceptions\MissingCompanyException;
use Aenzenith\LucaEFatura\Exceptions\TenantMismatchException;
use Aenzenith\LucaEFatura\Facades\Luca;
use Aenzenith\LucaEFatura\Tests\TestCase;
use Aenzenith\LucaEFatura\Transport\SoapRequest;

/*
 * The XML operations carry no company field: Luca issues the document under
 * the VKN written in the UBL. One tenant's document must not go out under
 * another tenant's account, and none of these checks may cost a request.
 */

const OTHER_COMPANY = '9999999999';
const ALIAS = 'urn:mail:defaultpk@example.com';

function sendDocument(string $kind, string $ubl): mixed
{
    return match ($kind) {
        'e-Arşiv' => Luca::eArchive()->sendXml($ubl),
        'e-Arşiv numarasız' => Luca::eArchive()->sendXmlWithoutNumber($ubl),
        'e-Fatura' => Luca::eInvoices()->sendXml($ubl, ALIAS),
        'e-Fatura numarasız' => Luca::eInvoices()->sendXmlWithoutNumber($ubl, ALIAS),
        'e-İrsaliye' => Luca::despatches()->sendXml($ubl, ALIAS),
        'irsaliye yanıtı' => Luca::despatches()->sendReceiptXml($ubl, ALIAS),
        'e-SMM' => Luca::esmm()->sendXml($ubl),
        'sendUbl' => Luca::documents()->sendUbl($ubl, ALIAS),
        default => throw new LogicException($kind),
    };
}

dataset('documents', [
    'e-Arşiv' => ['e-Arşiv', fn (string $supplier) => ubl($supplier)],
    'e-Arşiv numarasız' => ['e-Arşiv numarasız', fn (string $supplier) => ubl($supplier, number: '')],
    'e-Fatura' => ['e-Fatura', fn (string $supplier) => ubl($supplier, 'TICARIFATURA')],
    'e-Fatura numarasız' => ['e-Fatura numarasız', fn (string $supplier) => ubl($supplier, 'TEMELFATURA', number: '')],
    'e-İrsaliye' => ['e-İrsaliye', fn (string $supplier) => ubl($supplier, 'TEMELIRSALIYE', root: 'DespatchAdvice', supplierParty: 'DespatchSupplierParty', customerParty: 'DeliveryCustomerParty')],
    'irsaliye yanıtı' => ['irsaliye yanıtı', fn (string $supplier) => ubl($supplier, 'TEMELIRSALIYE', root: 'ReceiptAdvice', supplierParty: 'DeliveryCustomerParty', customerParty: 'DespatchSupplierParty')],
    'e-SMM' => ['e-SMM', fn (string $supplier) => ubl($supplier, 'EARSIVBELGE')],
    'sendUbl' => ['sendUbl', fn (string $supplier) => ubl($supplier, 'TEMELFATURA')],
]);

it('refuses a document issued by another company', function (string $kind, Closure $document) {
    $fake = Luca::fake();

    expect(fn () => sendDocument($kind, $document(OTHER_COMPANY)))->toThrow(TenantMismatchException::class);

    $fake->assertNothingSent();
})->with('documents');

it('sends a document issued by the scoped company', function (string $kind, Closure $document) {
    $fake = Luca::fake();

    sendDocument($kind, $document(TestCase::COMPANY));

    $fake->assertSentCount(1);
})->with('documents');

it('refuses a payload that names another company', function () {
    $fake = Luca::fake();

    expect(fn () => Luca::call('SearchInvoice', ['CompanyTaxCode' => OTHER_COMPANY]))
        ->toThrow(TenantMismatchException::class);

    $fake->assertNothingSent();
});

it('refuses to act without a company', function (string $kind) {
    config()->set('luca.connections.luca.company.tax_code', null);
    Luca::purge();
    $fake = Luca::fake();

    expect(fn () => match ($kind) {
        'company field' => Luca::eArchive()->search(),
        'XML document' => Luca::eArchive()->sendXml(ubl()),
    })->toThrow(MissingCompanyException::class);

    $fake->assertNothingSent();
})->with(['company field', 'XML document']);

it('sends the scoped company and leaves the default connection alone', function () {
    $fake = Luca::fake();

    Luca::company(OTHER_COMPANY)->eArchive()->search();
    Luca::eArchive()->search();

    $companies = array_map(fn (SoapRequest $request) => $request->payload['CompanyTaxCode'], $fake->recorded());

    expect($companies)->toBe([OTHER_COMPANY, TestCase::COMPANY])
        ->and($fake->recorded()[0]->body)->toContain('<model:CompanyTaxCode>'.OTHER_COMPANY.'</model:CompanyTaxCode>');
});

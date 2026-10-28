<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Exceptions\ConnectionException;
use Aenzenith\LucaEFatura\Exceptions\OperationFailedException;
use Aenzenith\LucaEFatura\Exceptions\SoapFaultException;
use Aenzenith\LucaEFatura\Exceptions\TransportException;
use Aenzenith\LucaEFatura\Facades\Luca;
use Aenzenith\LucaEFatura\Testing\FakeResponse;

/*
 * A send that did not go through must never come back as a SentDocument:
 * the caller would mark the sale invoiced and nobody would notice. Only an
 * explicit Result=Success counts; everything else throws.
 */
it('accepts nothing but Result=Success', function (FakeResponse $response, string $exception) {
    Luca::fake(['SendArchiveInvoiceXml' => $response]);

    expect(fn () => Luca::eArchive()->sendXml(ubl()))->toThrow($exception);
})->with([
    'Result=Failed' => [FakeResponse::failure('Fatura numarası daha önce kullanılmış.'), OperationFailedException::class],
    'no Result member' => [FakeResponse::raw(
        '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
        .'<SendArchiveInvoiceXmlResponse xmlns="http://tempuri.org/"><SendArchiveInvoiceXmlResult xmlns:a="http://schemas.datacontract.org/2004/07/EInvoice.Service.Model">'
        .'<a:ArchiveInvoices/></SendArchiveInvoiceXmlResult></SendArchiveInvoiceXmlResponse></s:Body></s:Envelope>'
    ), OperationFailedException::class],
    'SOAP fault' => [FakeResponse::fault('Object reference not set to an instance of an object.'), SoapFaultException::class],
    'HTML error page' => [FakeResponse::raw('<!DOCTYPE html><html><body>502 Bad Gateway</body></html>', 502), TransportException::class],
    'XML without a SOAP body' => [FakeResponse::raw('<html><body>Service Unavailable</body></html>', 503), TransportException::class],
    'empty body' => [FakeResponse::raw('', 200), TransportException::class],
    'another operation answered' => [FakeResponse::raw(
        '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body>'
        .'<GetCompanyBalanceResponse xmlns="http://tempuri.org/"><GetCompanyBalanceResult/></GetCompanyBalanceResponse></s:Body></s:Envelope>'
    ), TransportException::class],
    'connection reset' => [FakeResponse::connectionError(), ConnectionException::class],
]);

it('returns what Luca issued on success', function () {
    Luca::fake(['SendArchiveInvoiceXml' => FakeResponse::success([
        'ArchiveInvoices' => [['ArchiveInvoiceNumber' => 'ABC2026000000001', 'Ettn' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479']],
    ])]);

    $sent = Luca::eArchive()->sendXml(ubl());

    expect($sent->ettn)->toBe('f47ac10b-58cc-4372-a567-0e02b2c3d479')
        ->and($sent->number)->toBe('ABC2026000000001');
});

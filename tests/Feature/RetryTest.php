<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Exceptions\ConnectionException;
use Aenzenith\LucaEFatura\Facades\Luca;
use Aenzenith\LucaEFatura\Testing\FakeResponse;
use Illuminate\Support\Sleep;

/*
 * A lost response does not mean a lost request: Luca may have issued the
 * invoice before the connection dropped. Retrying a write would issue it
 * twice, so only reads are retried.
 */
it('retries reads but never writes after a connection failure', function (string $operation, Closure $call, int $attempts) {
    Sleep::fake();
    $fake = Luca::fake([$operation => FakeResponse::connectionError()]);

    expect($call)->toThrow(ConnectionException::class);

    $fake->assertSentCount($attempts, $operation);
})->with([
    'read: SearchArchiveInvoice' => ['SearchArchiveInvoice', fn () => Luca::eArchive()->search(), 3],
    'read: GetTaxPayer' => ['GetTaxPayer', fn () => Luca::taxPayers()->find('1111111111'), 3],
    'write: SendArchiveInvoiceXml' => ['SendArchiveInvoiceXml', fn () => Luca::eArchive()->sendXml(ubl()), 1],
    'write: SendInvoiceXml' => ['SendInvoiceXml', fn () => Luca::eInvoices()->sendXml(ubl(profile: 'TEMELFATURA'), 'urn:mail:defaultpk@example.com'), 1],
    'write: CancelArchiveInvoice' => ['CancelArchiveInvoice', fn () => Luca::eArchive()->cancel('f47ac10b-58cc-4372-a567-0e02b2c3d479', 'iptal'), 1],
]);

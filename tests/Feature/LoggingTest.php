<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Facades\Luca;

/*
 * Log lines outlive the request: portal passwords must not reach them, and
 * document bodies (customer names, amounts, PDFs) are replaced by sizes.
 */
it('keeps passwords and document bodies out of the log', function () {
    $file = tempnam(sys_get_temp_dir(), 'luca-log');

    config()->set('logging.channels.luca-test', ['driver' => 'single', 'path' => $file]);
    config()->set('luca.logging', ['enabled' => true, 'channel' => 'luca-test', 'bodies' => true, 'redact' => ['UserPassword', 'Password']]);
    Luca::purge();

    Luca::fake([
        'SearchArchiveInvoice' => ['ArchiveInvoices' => [['ETTN' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479', 'InvoicePdf' => '%PDF-1.4 Ayşe Yılmaz 1.234,56 TL']]],
    ]);

    Luca::account()->validateUser('11111111111', 'S3cret-Parola!');
    Luca::eArchive()->sendXml(ubl());
    Luca::eArchive()->pdf('f47ac10b-58cc-4372-a567-0e02b2c3d479');

    $log = (string) file_get_contents($file);
    unlink($file);

    expect($log)->toContain('Luca ValidateUserCompany', 'Luca SendArchiveInvoiceXml', '***', '[binary ')
        ->not->toContain('S3cret-Parola!')
        ->not->toContain('ProfileID')
        ->not->toContain('Ayşe Yılmaz')
        ->not->toContain(base64_encode('%PDF-1.4 Ayşe Yılmaz 1.234,56 TL'));
});

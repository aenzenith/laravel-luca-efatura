<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Schema\Schema;
use Aenzenith\LucaEFatura\Schema\SchemaCompiler;

/*
 * `luca:schema` replaces the bundled schema with what it compiles from the
 * live WSDL. A compiler that reorders members, loses a base type or files a
 * type under the wrong namespace would make every later envelope drop
 * fields silently. The bundled schema comes from an independent source (a
 * WSDL import made with another toolchain), so the two must agree.
 */
it('compiles a WCF WSDL into the same definitions as the bundled schema', function () {
    $compiled = (new SchemaCompiler)->compile($this->fixture('wsdl/InvoiceService.singleWsdl.xml'));
    $bundled = Schema::fromFile(Schema::resourcePath('invoice-service-2022.php'))->toArray();

    expect($compiled['operations'])->toBe(array_intersect_key($bundled['operations'], $compiled['operations']))
        ->and(array_keys($compiled['operations']))->toBe(['SendArchiveInvoiceXml', 'ArchiveInvoice', 'GetCompanyBalance']);

    foreach ($compiled['types'] as $key => $type) {
        expect($type)->toBe($bundled['types'][$key], $key);
    }

    expect(array_keys($compiled['types']))->toContain('Model.BaseRequest', 'Model.ResultType', 'Arrays.ArrayOfstring', 'Model.ArchiveInvoiceRequest')
        ->and($compiled['namespaces'])->toBe(array_intersect_key($bundled['namespaces'], $compiled['namespaces']));
});

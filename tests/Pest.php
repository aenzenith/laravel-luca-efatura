<?php

declare(strict_types=1);

use Aenzenith\LucaEFatura\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/**
 * A minimal UBL-TR document issued by $supplier.
 */
function ubl(string $supplier = TestCase::COMPANY, string $profile = 'EARSIVFATURA', string $number = 'ABC2026000000001', string $customer = '11111111111', string $root = 'Invoice', string $supplierParty = 'AccountingSupplierParty', string $customerParty = 'AccountingCustomerParty'): string
{
    $scheme = strlen($supplier) === 11 ? 'TCKN' : 'VKN';
    $customerScheme = strlen($customer) === 11 ? 'TCKN' : 'VKN';

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<{$root} xmlns="urn:oasis:names:specification:ubl:schema:xsd:{$root}-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
  <cbc:CustomizationID>TR1.2</cbc:CustomizationID>
  <cbc:ProfileID>{$profile}</cbc:ProfileID>
  <cbc:ID>{$number}</cbc:ID>
  <cbc:UUID>f47ac10b-58cc-4372-a567-0e02b2c3d479</cbc:UUID>
  <cac:{$supplierParty}>
    <cac:Party>
      <cac:PartyIdentification><cbc:ID schemeID="MERSISNO">0123456789000015</cbc:ID></cac:PartyIdentification>
      <cac:PartyIdentification><cbc:ID schemeID="{$scheme}">{$supplier}</cbc:ID></cac:PartyIdentification>
    </cac:Party>
  </cac:{$supplierParty}>
  <cac:{$customerParty}>
    <cac:Party>
      <cac:PartyIdentification><cbc:ID schemeID="{$customerScheme}">{$customer}</cbc:ID></cac:PartyIdentification>
    </cac:Party>
  </cac:{$customerParty}>
</{$root}>
XML;
}

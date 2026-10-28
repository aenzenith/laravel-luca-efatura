<?php

declare(strict_types=1);

/*
 * Definitions the 2022 InvoiceService WSDL does not carry. Merged over
 * invoice-service-2022.php; fields are inserted at their data-contract
 * (ordinal) position, enum values are added, operations are replaced.
 *
 * Sources, per block:
 *   [ornek]   SoapUI/Postman request samples Luca ships with the integration
 *             kit ("GÜNCEL WEB SERVİS", Postman collection dated 2026-04).
 *             Field names, order and namespaces are as Luca sends them.
 *   [dokuman] Luca's 2023 interface documents. Request fields follow the
 *             tables; namespaces of AddressBook/Product types are not in
 *             the documents and are assumed to be EInvoice.Service.Model.
 *
 * A response type left as null is parsed without a schema (see
 * ResponseParser): every element is kept, nothing is dropped.
 *
 * `php artisan luca:schema` compiles the live WSDL and supersedes all of
 * this for whatever the live contract defines.
 */

return [

    'namespaces' => [
        'AddressBook' => 'http://schemas.datacontract.org/2004/07/EInvoice.Service.Model.DataContract.AddressBook',
    ],

    'operations' => [

        // InvoiceService — [ornek]
        'GetCompanyVendor' => ['service' => 'InvoiceService', 'request' => 'Model.GetCompanyVendorRequest', 'response' => null],
        'GetDocumentViewerLink' => ['service' => 'InvoiceService', 'request' => 'Model.GetDocumentViewerLinkRequest', 'response' => null],
        'ValidateUserCompany' => ['service' => 'InvoiceService', 'request' => 'Model.ValidateUserCompanyRequest', 'response' => null],

        // AddressBookService — GetTaxPayer, GetDespatchTaxPayer, GetTaxPayerWithPaging,
        // GetCityList, GetTownList, GetTaxOfficeList, GetAllBySearch... [ornek];
        // the address book entry operations [dokuman].
        'GetTaxPayer' => ['service' => 'AddressBookService', 'request' => 'Model.GetTaxPayerRequest', 'response' => null],
        'GetDespatchTaxPayer' => ['service' => 'AddressBookService', 'request' => 'Model.GetTaxPayerRequest', 'response' => null],
        'GetTaxPayerWithPaging' => ['service' => 'AddressBookService', 'request' => 'Model.GetTaxPayerWithPagingRequest', 'response' => null],
        'GetCityList' => ['service' => 'AddressBookService', 'request' => 'Model.EmptyRequest', 'response' => null],
        'GetTownList' => ['service' => 'AddressBookService', 'request' => 'Model.EmptyRequest', 'response' => null],
        'GetTaxOfficeList' => ['service' => 'AddressBookService', 'request' => 'Model.EmptyRequest', 'response' => null],
        'GetAllBySearchTaxOfficeNameAndCityCode' => ['service' => 'AddressBookService', 'request' => 'AddressBook.GetAllBySearchTaxOfficeNameAndCityCodeRequest', 'response' => null],
        'SaveAddressBookEntry' => ['service' => 'AddressBookService', 'request' => 'Model.SaveAddressBookEntryRequest', 'response' => null],
        'DeleteAddressBookEntry' => ['service' => 'AddressBookService', 'request' => 'Model.DeleteAddressBookEntryRequest', 'response' => null],
        'GetAddressBook' => ['service' => 'AddressBookService', 'request' => 'Model.GetAddressBookRequest', 'response' => null],

        // ProductService — [dokuman]
        'SaveProduct' => ['service' => 'ProductService', 'request' => 'Model.SaveProductRequest', 'response' => null],
        'DeleteProduct' => ['service' => 'ProductService', 'request' => 'Model.SaveProductRequest', 'response' => null],
        'GetProduct' => ['service' => 'ProductService', 'request' => 'Model.GetProductRequest', 'response' => null],
    ],

    'types' => [

        // ------------------------------------------------------------------
        // InvoiceService additions [ornek]
        // ------------------------------------------------------------------

        'Model.SendArchiveInvoiceXmlRequest' => ['kind' => 'complex', 'fields' => [
            'CompanyVendorNumber' => 'string',
        ]],
        'Model.Invoice' => ['kind' => 'complex', 'fields' => [
            'Exemptions' => 'Model.ArrayOfExemption',
            'EydepBilgisi' => 'string',
        ]],
        'Model.ArrayOfExemption' => ['kind' => 'array', 'item' => 'Exemption', 'of' => 'Model.Exemption'],
        'Model.Exemption' => ['kind' => 'complex', 'fields' => [
            'TaxExemptionReasonCode' => 'string',
            'TaxExemptionReasonName' => 'string',
            'TaxExemptionReasonType' => 'string',
        ]],
        'Model.InvoiceDetail' => ['kind' => 'complex', 'fields' => [
            'ExpenditureUnit' => 'Model.ExpenditureUnit',
        ]],
        'Model.ExpenditureUnit' => ['kind' => 'complex', 'fields' => [
            'Code' => 'string',
            'ProductTraceId' => 'string',
            'SerialId' => 'string',
        ]],
        'Model.DespatchAdvice' => ['kind' => 'complex', 'fields' => [
            'DespatchAdditionalIdentityInfo' => 'Model.DespatchAdditionalIdentityInfo',
        ]],
        'Model.DespatchAdditionalIdentityInfo' => ['kind' => 'complex', 'fields' => [
            'ShipmentNo' => 'string',
        ]],
        'Model.DespatchAdviceDetail' => ['kind' => 'complex', 'fields' => [
            'IdisTagNumberList' => 'Model.ArrayOfIdisTag',
        ]],
        'Model.ArrayOfIdisTag' => ['kind' => 'array', 'item' => 'IdisTag', 'of' => 'Model.IdisTag'],
        'Model.IdisTag' => ['kind' => 'complex', 'fields' => [
            'IdisTagNumber' => 'string',
        ]],
        'Model.DespatchAdviceScenarioType' => ['kind' => 'enum', 'values' => ['IDISIRSALIYE']],
        'Model.SearchESMMRequest' => ['kind' => 'complex', 'fields' => [
            'VNKTCKN' => 'string',
        ]],

        'Model.GetCompanyVendorRequest' => ['kind' => 'complex', 'base' => 'Model.BaseRequest', 'fields' => [
            'CompanyTaxCode' => 'string',
        ]],
        'Model.GetDocumentViewerLinkRequest' => ['kind' => 'complex', 'base' => 'Model.BaseRequest', 'fields' => [
            'CompanyTaxCode' => 'string',
            'Ettn' => 'string',
            'InvoiceDirection' => 'Model.InvoiceDirectionType',
            'InvoiceDocumentType' => 'Model.InvoiceDocumentType',
        ]],
        'Model.InvoiceDocumentType' => ['kind' => 'enum', 'values' => ['EInvoice', 'EArchiveInvoice']],
        'Model.ValidateUserCompanyRequest' => ['kind' => 'complex', 'base' => 'Model.BaseRequest', 'fields' => [
            'CompanyTaxCode' => 'string',
            'CompanyVendorNumber' => 'string',
            'UserPassword' => 'string',
            'UserTaxCode' => 'string',
        ]],

        // ------------------------------------------------------------------
        // AddressBookService
        // ------------------------------------------------------------------

        'Model.EmptyRequest' => ['kind' => 'complex', 'fields' => []],

        'Model.GetTaxPayerRequest' => ['kind' => 'complex', 'fields' => [
            'MaxRegistrationDate' => 'dateTime',
            'MinRegistrationDate' => 'dateTime',
            'TaxPayerName' => 'string',
            'TaxPayerTaxCode' => 'string',
        ]],
        'Model.GetTaxPayerWithPagingRequest' => ['kind' => 'complex', 'fields' => [
            'MaxRegistrationDate' => 'dateTime',
            'MinRegistrationDate' => 'dateTime',
            'PagingRequest' => 'Model.PaginationInfoRequest',
            'TaxPayerName' => 'string',
            'TaxPayerTaxCode' => 'string',
        ]],

        'AddressBook.GetAllBySearchTaxOfficeNameAndCityCodeRequest' => ['kind' => 'complex', 'fields' => [
            'CityCode' => 'decimal',
            'TaxOfficeName' => 'string',
        ]],

        'Model.SaveAddressBookEntryRequest' => ['kind' => 'complex', 'fields' => [
            'AddressBookEntires' => 'Model.ArrayOfAddressBookEntry',
            'CompanyTaxCode' => 'string',
            'CompanyType' => 'decimal',
            'CompanyVendorNumber' => 'string',
        ]],
        'Model.DeleteAddressBookEntryRequest' => ['kind' => 'complex', 'fields' => [
            'AddressBookEntires' => 'Model.ArrayOfAddressBookEntry',
            'CompanyTaxCode' => 'string',
            'CompanyVendorNumber' => 'string',
        ]],
        'Model.GetAddressBookRequest' => ['kind' => 'complex', 'fields' => [
            'CompanyTaxCode' => 'string',
            'CompanyVendorNumber' => 'string',
            'ExternalReceiverCode' => 'string',
            'ReceiverName' => 'string',
            'ReceiverTaxCode' => 'string',
            'VendorNumber' => 'string',
        ]],
        'Model.ArrayOfAddressBookEntry' => ['kind' => 'array', 'item' => 'AddressBookEntry', 'of' => 'Model.AddressBookEntry'],

        // ------------------------------------------------------------------
        // ProductService [dokuman]
        // ------------------------------------------------------------------

        'Model.SaveProductRequest' => ['kind' => 'complex', 'fields' => [
            'CompanyTaxCode' => 'string',
            'CompanyVendorNumber' => 'string',
            'Products' => 'Model.ArrayOfProduct',
        ]],
        'Model.GetProductRequest' => ['kind' => 'complex', 'fields' => [
            'CompanyTaxCode' => 'string',
            'CompanyVendorNumber' => 'string',
            'ExternalProductCode' => 'string',
            'MaxUnitPrice' => 'decimal',
            'MeasureUnit' => 'string',
            'MinUnitPrice' => 'decimal',
            'ProductName' => 'string',
        ]],
        'Model.ArrayOfProduct' => ['kind' => 'array', 'item' => 'Product', 'of' => 'Model.Product'],

    ],

];

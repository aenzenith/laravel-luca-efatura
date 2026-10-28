<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default connection
    |--------------------------------------------------------------------------
    |
    | The connection Luca::... calls use when no connection is named.
    |
    */

    'default' => env('LUCA_CONNECTION', 'luca'),

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | One connection = one set of endpoints (test or production) plus the
    | transport settings. The services authenticate by source IP only: the
    | outgoing IP of the server that runs these calls must be registered
    | with Luca ("Entegratör Web Servis Hizmet Başvurusu"). Calls from any
    | other IP get their TCP connection reset.
    |
    | environment: "test" or "production"; picks the base URL below.
    | services:    path of each SOAP service under the base URL.
    | company:     default company (VKN/TCKN) the calls act for. Leave empty
    |              when every call is scoped with Luca::company(...).
    | proxy:       route the calls through a proxy, e.g. an SSH SOCKS tunnel
    |              to a whitelisted server: "socks5h://127.0.0.1:1080".
    | retry:       retries for READ operations (Search*, Get*, Validate*)
    |              on connection failures. Write operations (Send*, Cancel*,
    |              Save*, Delete*, ...) are never retried: a lost response
    |              does not mean the document was not issued.
    |
    */

    'connections' => [

        'luca' => [
            'environment' => env('LUCA_ENVIRONMENT', 'test'),

            'base_urls' => [
                'test' => 'https://einvoiceserviceturmobtest.luca.com.tr',
                'production' => 'https://einvoiceserviceturmob.luca.com.tr',
            ],

            'services' => [
                'InvoiceService' => '/InvoiceService/ServiceContract/InvoiceService.svc',
                'AddressBookService' => '/AddressBookService/ServiceContract/AddressBookService.svc',
                'ProductService' => '/ProductService/ServiceContract/ProductService.svc',
            ],

            'company' => [
                'tax_code' => env('LUCA_COMPANY_TAX_CODE'),
                'vendor_number' => env('LUCA_COMPANY_VENDOR_NUMBER'),
            ],

            'timeout' => (int) env('LUCA_TIMEOUT', 60),
            'connect_timeout' => (int) env('LUCA_CONNECT_TIMEOUT', 10),
            'verify' => true,
            'proxy' => env('LUCA_PROXY'),

            'retry' => [
                'times' => 2,
                'sleep_ms' => 500,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | dateTime values are sent without an offset, as Luca's own samples do.
    | DateTimeInterface values are converted to this timezone first.
    |
    */

    'timezone' => 'Europe/Istanbul',

    /*
    |--------------------------------------------------------------------------
    | Schema
    |--------------------------------------------------------------------------
    |
    | The services are WCF data contracts: elements must arrive in the
    | contract's order, and an element the contract does not know is dropped
    | silently. Every request is therefore built from a schema, never by hand.
    |
    | path:   schema compiled from the live WSDL by `php artisan luca:schema`.
    |         Merged over the bundled schema when the file exists.
    | strict: refuse request fields the schema does not know (a typo such as
    |         "VatRate" for "VATRate" would otherwise vanish on Luca's side).
    | extend: extra definitions merged last, same shape as the schema files.
    |
    */

    'schema' => [
        'path' => env('LUCA_SCHEMA_PATH'),
        'strict' => true,
        'extend' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    |
    | supplier_matches_company: before an XML document (UBL) is sent, its
    | supplier VKN/TCKN must equal the scoped company. Stops one tenant's
    | invoice from being issued under another tenant's account.
    |
    */

    'guards' => [
        'supplier_matches_company' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Document viewer links
    |--------------------------------------------------------------------------
    |
    | GetDocumentViewerLink is known to answer with "http://host:80/..."
    | while the host only serves HTTPS. force_https rewrites such links.
    |
    */

    'viewer_link' => [
        'force_https' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | enabled: one log line per call (operation, company, duration, result).
    | bodies:  add the request payload and the parsed response. Binary
    |          content (UBL, PDF) is replaced by its size and the keys in
    |          "redact" by "***".
    |
    */

    'logging' => [
        'enabled' => (bool) env('LUCA_LOG', false),
        'channel' => env('LUCA_LOG_CHANNEL'),
        'bodies' => false,
        'redact' => ['UserPassword', 'Password'],
    ],

];

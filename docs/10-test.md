# 10 - Test

```php
use Aenzenith\LucaEFatura\Facades\Luca;
use Aenzenith\LucaEFatura\Testing\FakeResponse;

$fake = Luca::fake([
    // dizi: Result=Success + bu alanlar
    'SendArchiveInvoiceXml' => ['ArchiveInvoices' => [['Ettn' => $ettn, 'ArchiveInvoiceNumber' => 'ABC2026000000001']]],

    // Result=Failed
    'GetTaxPayer' => FakeResponse::failure('Mükellef bulunamadı.'),

    // sırayla tüketilir, sonuncusu tekrarlanır
    'SearchArchiveInvoice' => [FakeResponse::connectionError(), ['ArchiveInvoices' => []]],

    // isteğe göre yanıt
    'GetCompanyBalance' => fn ($request) => ['Balance' => $request->company?->taxCode === '1234567801' ? 100 : 0],
]);
```

Yanıtlar gerçek zarf kurucu ve ayrıştırıcıdan geçer: base64 alanlar, listeler ve `nil` canlı yanıttaki
gibi okunur. Diğer yanıt türleri: `FakeResponse::fault($mesaj)`, `FakeResponse::raw($xml, $status)`.

Stub'ı olmayan operasyon boş `Success` döner. Bunu kapatmak için:

```php
$fake->preventStrayRequests();
```

## Doğrulamalar

```php
$fake->assertSent('SendArchiveInvoiceXml');
$fake->assertSent('SendInvoiceReply', fn (array $payload) => $payload['InvoiceReplies'][0]['InvoiceResponse']->value === 'RED');
$fake->assertNotSent('CancelArchiveInvoice');
$fake->assertSentCount(2, 'SearchInvoice');
$fake->assertNothingSent();
$fake->recorded('SendArchiveInvoiceXml');   // list<SoapRequest>: payload, body, company, url
```

Payload, paketin firma alanlarını doldurduğu son hâlidir; enum değerleri `BackedEnum` olarak kalır.

Laravel `Http::fake()` da çalışır: varsayılan taşıma Laravel HTTP istemcisidir.

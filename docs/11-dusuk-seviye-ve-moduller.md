# 11 - Düşük seviyeli erişim ve yeni modüller

## Her operasyon

Modüllerde karşılığı olmayan her operasyon şemadaki adıyla çağrılabilir:

```php
$result = Luca::call('SearchExternalInvoice', [
    'InvoiceDirection' => 'Incoming',
    'MinInvoiceDate' => now()->subMonth(),
]);

$result = Luca::company('1234567801')->call('CreateReturnReceipt', [...]);
```

Firma alanları, düzenleyen kontrolü, şema denetimi ve tekrar deneme kuralları modül çağrılarındaki
gibi uygulanır. Operasyon listesi: `Luca::schema()->operations()`; bir tipin alanları:
`Luca::schema()->fields('Model.SearchInvoiceRequest')`.

## Modül ekleme

```php
use Aenzenith\LucaEFatura\Connection;
use Aenzenith\LucaEFatura\Modules\Module;

final class Raporlar extends Module
{
    public function kisaltma(\DateTimeInterface $gun): array
    {
        return $this->call('GetAbbreviationReport', ['InvoiceDate' => $gun])->list('Reports');
    }
}

Luca::extend('raporlar', Raporlar::class);            // ya da fn (Connection $c) => new Raporlar($c)

Luca::module('raporlar')->kisaltma(now());
Luca::company('1234567801')->module('raporlar');
```

Var olan bir modül aynı adla değiştirilebilir (`eArchive`, `eInvoices`, `despatches`, `esmm`,
`taxPayers`, `addressBook`, `products`, `account`, `documents`).

## Şemaya operasyon ekleme

```php
Luca::extendSchema([
    'operations' => [
        'GetReceiverInboxTags' => ['service' => 'AddressBookService', 'request' => 'Model.GetReceiverInboxTagsRequest', 'response' => null],
    ],
    'types' => [
        'Model.GetReceiverInboxTagsRequest' => ['kind' => 'complex', 'fields' => ['ReceiverTaxCode' => 'string']],
    ],
]);
```

`response => null` yanıtı şemasız okur.

## Taşıma

`Transport` arayüzü tek metottur (`send(SoapRequest): SoapResponse`). Başka bir taşıma
`Luca::useTransport($transport)` ile verilir.

# 7 - Mükellef, adres defteri, ürün, hesap

## Mükellef sorgusu

```php
$taxPayer = Luca::taxPayers()->find('1111111111');    // ?TaxPayer
$taxPayer?->name;
$taxPayer?->inboxTags;       // e-Fatura alias'ları
$taxPayer?->registeredAt;    // CarbonImmutable

Luca::taxPayers()->isEInvoiceUser('1111111111');
Luca::taxPayers()->findDespatch('1111111111');        // e-İrsaliye kaydı
Luca::taxPayers()->search('ÖRNEK', page: 1, perPage: 50);
```

- Boş ya da 10/11 hane olmayan vergi numarası reddedilir: servis boş `TaxPayerTaxCode` ile **tüm**
  mükellefleri listeler.
- Kayıtlı olmayan numara için servis `Result=Failed` döner. Hata mesajı "bulunamadı", "not found",
  "kayıtlı değil" ya da "mevcut değil" içeriyorsa `find()` `null` döner; başka her hata
  `OperationFailedException` fırlatır. Kesinti "mükellef değil" diye okunursa e-Fatura yerine e-Arşiv
  kesilir; bu yüzden tanınmayan mesaj sessizce `null`'a çevrilmez.
- Aynı VKN için birden fazla kayıt dönerse alias'lar birleştirilir.

## Adres kodları ve adres defteri

```php
$book = Luca::addressBook();

$book->cities();
$book->towns();
$book->taxOffices();
$book->searchTaxOffices(cityCode: 6, name: 'Çankaya');
$book->entries(['ReceiverTaxCode' => '1111111111']);
$book->save([$entry], companyType: 1);
$book->delete([$entry]);
```

Bu servisin yanıt şekilleri Luca'nın yayımladığı WSDL'de yoktur; kayıtlar okunduğu gibi dizi olarak
döner.

## Ürün kataloğu

```php
Luca::products()->save([['ProductName' => 'Kalem', 'UnitPrice' => '12.50', 'MeasureUnit' => 'C62']]);
Luca::products()->find(['ProductName' => 'Kalem']);
Luca::products()->delete([['ExternalProductCode' => 'KLM-1']]);
```

## Hesap

```php
Luca::account()->balance();                              // kalan kontör
Luca::account()->validateUser('11111111111', $parola);  // portal kullanıcısı firmaya ait mi
Luca::account()->vendors();                              // alt bayiler
```

## Görüntüleme linki

```php
use Aenzenith\LucaEFatura\Enums\DocumentType;

Luca::documents()->viewerLink($ettn, DocumentType::EArchiveInvoice);
```

Servis linki `http://host:80/...` olarak döndürür; `viewer_link.force_https` açıkken
`https://host/...` yapılır.

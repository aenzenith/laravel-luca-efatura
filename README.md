# Laravel Luca e-Fatura

[![Packagist Version](https://img.shields.io/packagist/v/aenzenith/laravel-luca-efatura)](https://packagist.org/packages/aenzenith/laravel-luca-efatura)
[![PHP Version](https://img.shields.io/packagist/dependency-v/aenzenith/laravel-luca-efatura/php)](https://packagist.org/packages/aenzenith/laravel-luca-efatura)
[![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Tests](https://github.com/aenzenith/laravel-luca-efatura/actions/workflows/tests.yml/badge.svg)](https://github.com/aenzenith/laravel-luca-efatura/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/aenzenith/laravel-luca-efatura)](LICENSE)

TÜRMOB Luca e-Fatura entegratör web servisleri için Laravel istemcisi. Luca'nın servisleri İşNet
NetteFatura altyapısı üzerinde çalışan WCF SOAP servisleridir. Paket üç servisi de kapsar:
InvoiceService, AddressBookService ve ProductService.

- e-Fatura, e-Arşiv, e-İrsaliye, irsaliye yanıtı ve e-SMM gönderimi (UBL-TR XML ya da Luca'nın alan
  modeli), arama, PDF, iptal, kabul/red, e-posta, e-Arşiv raporu
- GİB mükellef sorgusu ve alias listesi, il/ilçe/vergi dairesi kodları, adres defteri, ürün kataloğu
- Kontör bakiyesi, portal kullanıcısı doğrulama, alt bayi listesi
- Belgeyi ProfileID'sine göre doğru operasyona yönlendiren `documents()->sendUbl()`

| Gereksinim | Sürüm |
| --- | --- |
| PHP | 8.2+ (`ext-dom`, `ext-xmlwriter`) |
| Laravel | 12, 13 |

## Kurulum

```bash
composer require aenzenith/laravel-luca-efatura
php artisan vendor:publish --tag=luca-config
```

```dotenv
LUCA_ENVIRONMENT=test            # test | production
LUCA_COMPANY_TAX_CODE=1234567801 # varsayılan firma VKN/TCKN (çok firmalı kullanımda boş bırakılabilir)
```

Luca'da kullanıcı adı ve parola yoktur: servisler isteği gönderen sunucunun **IP adresiyle** kimlik
doğrular. Kayıtlı olmayan IP'den gelen bağlantı TCP seviyesinde kesilir. Kurulumdan sonra:

```bash
php artisan luca:check
```

Ayrıntı: [IP erişimi ve bağlantı](docs/02-ip-erisimi-ve-baglanti.md). Diğer komutlar:
`luca:taxpayer {vkn}` (mükellef ve alias sorgusu), `luca:schema` (canlı WSDL'den şema derleme).

## Kullanım

```php
use Aenzenith\LucaEFatura\Facades\Luca;

// Alıcı e-Fatura mükellefi mi?
$taxPayer = Luca::taxPayers()->find('1111111111');   // null ise e-Arşiv kesilir

// UBL-TR belgeyi ProfileID'sine göre gönder (EARSIVFATURA, TEMELFATURA, TEMELIRSALIYE, EARSIVBELGE...)
$sent = Luca::documents()->sendUbl($ubl);
$sent->ettn;     // belge UUID
$sent->number;   // fatura numarası

// Doğrudan modül
Luca::eArchive()->sendXml($ubl, sendMail: true);
Luca::eInvoices()->sendXml($ubl, 'urn:mail:defaultpk@alici.com.tr');

// PDF, iptal, kabul/red
$pdf = Luca::eArchive()->pdf($ettn);
Luca::eArchive()->cancel($ettn, 'Hatalı tutar');
Luca::eInvoices()->reject($ettn, 'Sipariş iptal edildi');

// Çok firmalı: her çağrı açıkça bir firma adına
Luca::company($shop->tax_code)->eArchive()->sendXml($ubl);
```

Her çağrı aynı hattan geçer ve aşağıdaki kontroller istek gönderilmeden önce yapılır:

- **Firma kapsamı.** `CompanyTaxCode` / `CompanyVendorNumber` kapsamdaki firmadan doldurulur; başka
  firma yazan istek `TenantMismatchException` ile reddedilir.
- **Düzenleyen kontrolü.** XML gönderimlerinde firma alanı yoktur; Luca belgeyi UBL'deki VKN adına
  keser. UBL'deki düzenleyen VKN/TCKN kapsamdaki firmayla eşleşmezse belge gönderilmez.
- **Şema.** Zarflar WCF veri sözleşmesinin sırası ve isim alanlarıyla kurulur. Sözleşmede olmayan alan
  (ör. `VatRate` yerine `VATRate`) Luca'da sessizce düşeceği için reddedilir.
- **Tekrar deneme.** Okuma işlemleri bağlantı hatasında tekrar denenir; gönderim, iptal gibi yazma
  işlemleri **asla** tekrar denenmez.
- **ETTN.** Arama servisleri boş ETTN'i "filtre yok" sayıp firmanın ilk belgesini döndürür; geçersiz
  ETTN'le arama yapılmaz, dönen kaydın ETTN'i istenenle aynı değilse kayıt kullanılmaz.
- **Sonuç.** Yalnız `Result=Success` başarıdır. `Failed`, SOAP fault, HTML hata sayfası, boş yanıt
  ve bağlantı hatası istisna fırlatır.

## Test

```php
use Aenzenith\LucaEFatura\Facades\Luca;
use Aenzenith\LucaEFatura\Testing\FakeResponse;

$fake = Luca::fake([
    'SendArchiveInvoiceXml' => ['ArchiveInvoices' => [['Ettn' => $ettn, 'ArchiveInvoiceNumber' => 'ABC2026000000001']]],
    'GetTaxPayer' => FakeResponse::failure('Mükellef bulunamadı.'),
]);

// ...

$fake->assertSent('SendArchiveInvoiceXml');
```

## Dokümantasyon

Ayrıntılı kılavuz [docs/](docs/README.md) altındadır.

## Doğrulanmamış kısımlar

Paket Luca'nın yayımladığı 2022 WSDL içe aktarımı, 2023 arayüz dokümanları ve Luca'nın örnek
istekleri (SoapUI / Postman, 2026) üzerine kuruludur. İstek tarafı bu örneklerin 40'ıyla birebir
doğrulanır. Şunlar canlı servise karşı henüz denenmedi:

- AddressBookService ve ProductService yanıt şekilleri (şemasız okunur, hiçbir alan atılmaz)
- `GetDocumentViewerLink`, `ValidateUserCompany`, `GetCompanyVendor` yanıtları
- Mükellef bulunamadı mesajının tam metni (`TaxPayers::isNotFoundMessage()`)

Kayıtlı IP'li sunucuda `php artisan luca:schema` canlı WSDL'den güncel şemayı üretir; bkz.
[Şema](docs/08-sema-ve-luca-schema.md).

## Lisans

MIT

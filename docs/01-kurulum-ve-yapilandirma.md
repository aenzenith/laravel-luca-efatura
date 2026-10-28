# 1 - Kurulum ve yapılandırma

```bash
composer require aenzenith/laravel-luca-efatura
php artisan vendor:publish --tag=luca-config
```

Servis sağlayıcı ve `Luca` facade'ı otomatik kaydolur.

## Ortam değişkenleri

| Değişken | Varsayılan | Açıklama |
| --- | --- | --- |
| `LUCA_CONNECTION` | `luca` | Varsayılan bağlantı adı |
| `LUCA_ENVIRONMENT` | `test` | `test` ya da `production` |
| `LUCA_COMPANY_TAX_CODE` | — | Varsayılan firma VKN (10) / TCKN (11) |
| `LUCA_COMPANY_VENDOR_NUMBER` | — | Alt bayi numarası |
| `LUCA_TIMEOUT` | `60` | İstek zaman aşımı (sn) |
| `LUCA_CONNECT_TIMEOUT` | `10` | Bağlantı zaman aşımı (sn) |
| `LUCA_PROXY` | — | Ör. `socks5h://127.0.0.1:1080` |
| `LUCA_SCHEMA_PATH` | — | `luca:schema` çıktısı |
| `LUCA_LOG` | `false` | Her çağrı için bir log satırı |
| `LUCA_LOG_CHANNEL` | — | Log kanalı |

Uç adresleri:

| Ortam | Adres |
| --- | --- |
| test | `https://einvoiceserviceturmobtest.luca.com.tr` |
| production | `https://einvoiceserviceturmob.luca.com.tr` |

Servis yolları `config/luca.php` içindeki `services` dizisindedir
(`/InvoiceService/ServiceContract/InvoiceService.svc` vb.).

## Birden fazla bağlantı

`connections` altına yeni bir anahtar eklenir; her bağlantının ortamı, firması ve taşıma ayarları
ayrıdır.

```php
'connections' => [
    'luca' => [/* test */],
    'canli' => [
        'environment' => 'production',
        // diğer anahtarlar luca bağlantısındaki gibi
    ],
],
```

```php
Luca::connection('canli')->account()->balance();
```

## Diğer ayarlar

| Anahtar | Varsayılan | Açıklama |
| --- | --- | --- |
| `timezone` | `Europe/Istanbul` | `DateTimeInterface` değerleri bu saat dilimine çevrilip ofsetsiz yazılır |
| `schema.strict` | `true` | Sözleşmede olmayan istek alanını reddet |
| `schema.extend` | `[]` | Şemaya eklenecek tanımlar |
| `guards.supplier_matches_company` | `true` | XML belgede düzenleyen = kapsamdaki firma kontrolü |
| `viewer_link.force_https` | `true` | `http://host:80/...` görüntüleme linkini `https://host/...` yap |
| `logging.bodies` | `false` | Log satırına istek ve yanıt gövdesi ekle (maskeli) |
| `logging.redact` | `UserPassword`, `Password` | `***` ile maskelenen anahtarlar |

`php artisan about` çıktısında "Luca e-Fatura" bölümü bağlantı, ortam, firma ve şema kaynağını
gösterir.

## Komutlar

| Komut | İş |
| --- | --- |
| `luca:check [--connection=] [--company=]` | Erişimi ve kontör bakiyesini kontrol eder |
| `luca:taxpayer {vkn} [--despatch]` | e-Fatura (ya da e-İrsaliye) kaydını ve alias'ları gösterir |
| `luca:schema [--from=*] [--output=] [--dry-run]` | Canlı WSDL'den şema derler, bkz. [Şema](08-sema-ve-luca-schema.md) |

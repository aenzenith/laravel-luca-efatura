# 9 - Hatalar, olaylar ve loglama

## İstisnalar

Hepsi `Aenzenith\LucaEFatura\Exceptions\LucaException` (`RuntimeException`) altındadır.

| İstisna | Ne zaman | İstek gitti mi |
| --- | --- | --- |
| `SchemaException` | Bilinmeyen operasyon/alan, tipe uymayan değer | Hayır |
| `MissingCompanyException` | Firma gereken işlemde kapsam yok | Hayır |
| `TenantMismatchException` | İstekte ya da UBL'de başka firma | Hayır |
| `InvalidDocumentException` | UBL okunamıyor, ProfileID yok, alias belirsiz | Hayır |
| `InvalidConfiguration` | Bağlantı/ortam/servis yolu tanımsız | Hayır |
| `ConnectionException` | Yanıt gelmedi (DNS, TCP reset, TLS, zaman aşımı) | **Bilinmiyor** |
| `SoapFaultException` | SOAP fault (`faultCode`, `faultString`, `detail`) | Evet |
| `TransportException` | Yanıt SOAP değil (HTML hata sayfası, boş gövde, başka operasyonun yanıtı) | Evet |
| `OperationFailedException` | `Result=Failed` ya da `Result` yok (`$e->result`) | Evet |
| `DocumentNotFoundException` | `pdf()`/`viewerLink()` belgeyi bulamadı | Evet |

`ConnectionException` yazma işleminde alındıysa belge kesilmiş olabilir; tekrar göndermeden önce
arayın.

`InvalidArgumentException` (PHP): geçersiz ETTN, VKN/TCKN ya da 1–100 dışında belge sayısı.

## `call()` ve `attempt()`

```php
$result = Luca::call('GetCompanyBalance');      // Failed ise OperationFailedException
$result = Luca::attempt('GetCompanyBalance');   // Failed ise Result döner
$result->successful();
$result->errorMessage();
$result->get('Balance');
$result->list('ArchiveInvoices');
$result->raw;           // yanıt XML'i
$result->durationMs;
```

## Olaylar

| Olay | Ne zaman |
| --- | --- |
| `Events\RequestSending` | Zarf gönderilmeden hemen önce (`$event->request->payload`, `->body`) |
| `Events\ResponseReceived` | Her ayrıştırılmış yanıtta, `Success` ya da `Failed` (`$event->result`) |
| `Events\RequestFailed` | Okunabilir yanıt yoksa (`$event->exception`) |

Olay yükündeki payload maskelenmemiştir: UBL ve parolaları içerir.

## Loglama

```dotenv
LUCA_LOG=true
LUCA_LOG_CHANNEL=luca
```

Her çağrı için bir satır: `Luca {Operasyon}` ve bağlantı, firma, süre, sonuç, hata. Başarısız
sonuçlar `warning`, diğerleri `info` seviyesindedir. `logging.bodies` açıkken istek ve yanıt
eklenir; `logging.redact` anahtarları `***`, belge içerikleri (`...Content`, `...Pdf`, `...Html`,
`...Xml`, `...File`, `ESMM`) `[binary N bytes]` olarak yazılır.

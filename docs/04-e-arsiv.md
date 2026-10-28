# 4 - e-Arşiv

```php
$archive = Luca::eArchive();
```

## Gönderim

| Metot | Operasyon | Not |
| --- | --- | --- |
| `sendXml(string $ubl, bool $sendMail = false)` | `SendArchiveInvoiceXml` | Numara UBL'de (`cbc:ID`) |
| `sendXmlBatch(array $ubls, bool $sendMail = false)` | `SendArchiveInvoiceXml` | 1–100 belge |
| `sendXmlWithoutNumber(string $ubl, bool $sendMail = false)` | `SendArchiveInvoiceXmlWithoutInvoiceNumber` | Numarayı Luca verir |
| `send(array $invoice)` / `sendBatch(array $invoices)` | `SendArchiveInvoice` | Luca'nın `ArchiveInvoice` alan modeli |

UBL ham XML olarak verilir; base64'e paket çevirir. Base64 verilirse UBL okunamaz ve
`InvalidDocumentException` fırlar.

Dönen `SentDocument`:

```php
$sent->ettn;          // Luca'nın döndürdüğü ETTN, yoksa UBL'deki cbc:UUID
$sent->number;        // Luca'nın döndürdüğü numara, yoksa UBL'deki cbc:ID
$sent->externalCode;  // ExternalArchiveInvoiceCode
$sent->raw;           // yanıt kaydı
```

## Arama ve PDF

```php
$record = $archive->find($ettn, withPdf: true);           // ?Record
$record = $archive->findByExternalCode('SIP-1001');       // ?Record
$page   = $archive->search(['MinInvoiceDate' => now()->subDays(7)], page: 1, perPage: 50);
$pdf    = $archive->pdf($ettn);                           // PDF baytları
```

`find()` geçersiz ETTN ile çağrılırsa istek gönderilmez (`InvalidArgumentException`); dönen kaydın
ETTN'i istenenle aynı değilse `null` döner. `pdf()` belge bulunamazsa
`DocumentNotFoundException` fırlatır.

`Record` erişimcileri: `ettn()`, `number()`, `externalCode()`, `status()`, `date()`, `pdf()`,
`xml()`, `html()`, `get('Receiver.Name')`, `toArray()`.

`Page`: `items`, `page`, `perPage`, `totalPages`, `totalRecords`, `hasMore()`; `foreach` ile
gezilebilir.

## İptal, e-posta, rapor

```php
$archive->cancel($ettn, 'Hatalı tutar');
$archive->cancelMany([$ettn1 => 'Mükerrer', $ettn2 => 'Hatalı alıcı']);
$archive->mail($ettn, 'musteri@ornek.com');
$group = $archive->sendReport(now());            // ArchiveInvoiceReportGroupNumber
$reports = $archive->reports($group);
```

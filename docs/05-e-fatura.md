# 5 - e-Fatura

```php
$invoices = Luca::eInvoices();
```

## Alıcı alias'ı

e-Fatura alıcının GİB posta kutusu etiketine (alias) gönderilir:

```php
$aliases = Luca::taxPayers()->aliases('1111111111');  // ['urn:mail:defaultpk@alici.com.tr']
```

Alıcı kayıtlı değilse liste boştur; bu durumda e-Arşiv kesilir.

## Gönderim

| Metot | Operasyon |
| --- | --- |
| `sendXml(string $ubl, string $receiverTag)` | `SendInvoiceXml` |
| `sendXmlBatch(array $documents)` | `SendInvoiceXml` (`[['ubl' => ..., 'receiverTag' => ...], ...]`, 1–100) |
| `sendXmlWithoutNumber(string $ubl, string $receiverTag)` | `SendInvoiceXmlWithoutInvoiceNumber` |
| `send(array $invoice)` / `sendBatch(array $invoices)` | `SendInvoice` (Luca'nın `Invoice` alan modeli) |

Boş `receiverTag` verilirse istek gönderilmez.

## Arama ve PDF

```php
use Aenzenith\LucaEFatura\Enums\InvoiceDirection;

$invoices->find($ettn);                                   // giden
$invoices->find($ettn, InvoiceDirection::Incoming);       // gelen
$invoices->search(['MinInvoiceDate' => '2026-10-01'], perPage: 100);
$invoices->incoming(['InvoiceType' => 'SATIS']);
$invoices->pdf($ettn, InvoiceDirection::Incoming);
$invoices->ettns(['MinInvoiceDate' => '2026-10-01']);    // GetEttnList
```

## Gelen ticari faturaya yanıt

```php
$invoices->accept($ettn);
$invoices->reject($ettn, 'Sipariş iptal edildi');
```

## Arşivleme

```php
$invoices->archive([$ettn1, $ettn2], InvoiceDirection::Incoming);    // en fazla 300
$invoices->unarchive([$ettn1]);
```

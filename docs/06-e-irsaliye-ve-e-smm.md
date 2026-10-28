# 6 - e-İrsaliye ve e-SMM

## e-İrsaliye

```php
$despatches = Luca::despatches();

$despatches->sendXml($ubl, 'urn:mail:irsaliyepk@alici.com.tr');
$despatches->sendXmlWithoutNumber($ubl, $alias);
$despatches->send($despatchAdvice);                    // Luca'nın DespatchAdvice alan modeli
$despatches->find($ettn, withPdf: true);
$despatches->search(['MinDespatchAdviceDate' => '2026-10-01']);
$despatches->pdf($ettn);
```

e-İrsaliye alias'ı e-Fatura alias'ından farklı olabilir:

```php
Luca::taxPayers()->findDespatch('1111111111')?->inboxTags;
```

### İrsaliye yanıtı

```php
$despatches->sendReceiptXml($receiptAdviceUbl, $alias);
$despatches->searchReceipts(['MinReceiptAdviceDate' => '2026-10-01']);
```

Düzenleyen kontrolü irsaliye yanıtında `DeliveryCustomerParty` tarafına bakar.

## e-SMM (serbest meslek makbuzu)

```php
$esmm = Luca::esmm();

$sent = $esmm->sendXml($ubl);                 // ProfileID EARSIVBELGE
$esmm->send($esmmModel);                      // Luca'nın ESMM alan modeli
$esmm->find($ettn, withPdf: true);
$esmm->search(['MinDocumentDate' => '2026-10-01']);
$esmm->pdf($ettn);
$esmm->cancel($ettn, 'Hatalı tutar');
```

## ProfileID'ye göre gönderim

```php
$sent = Luca::documents()->sendUbl($ubl);
```

| ProfileID | Operasyon |
| --- | --- |
| `EARSIVFATURA` | `SendArchiveInvoiceXml` |
| `EARSIVBELGE` | `SendESMMXml` |
| `*IRSALIYE` | `SendDespatchAdviceXml` |
| diğerleri (`TEMELFATURA`, `TICARIFATURA`, `IHRACAT`, ...) | `SendInvoiceXml` |

`cbc:ID` boşsa "numarasız" operasyon kullanılır. e-Fatura ve e-İrsaliyede alias verilmezse alıcının
VKN'siyle sorgulanır; alıcının tek alias'ı yoksa `InvalidDocumentException` fırlar ve belge
gönderilmez. ProfileID değiştirilmez: e-Fatura mükellefi olmayan alıcıya `TEMELFATURA` gönderimi
Luca'da reddedilir.

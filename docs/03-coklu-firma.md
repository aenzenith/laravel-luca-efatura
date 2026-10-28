# 3 - Çok firmalı kullanım ve korumalar

## Firma kapsamı

```php
Luca::company('1234567801')->eArchive()->sendXml($ubl);
Luca::company('1234567801', vendorNumber: '0001')->eInvoices()->search();
```

`company()` bağlantının kapsamlı bir **kopyasını** döndürür; varsayılan bağlantı değişmez. Kuyruk
işçisi ya da Octane gibi uzun yaşayan süreçlerde bir kiracının firması diğer isteğe sızmaz.

Kapsam şöyle uygulanır:

- İstek tipinde `CompanyTaxCode` varsa kapsamdaki firmanın VKN'si yazılır. İstekte farklı bir değer
  varsa `TenantMismatchException` fırlar.
- Kapsamda firma yoksa ve istekte de `CompanyTaxCode` yoksa `MissingCompanyException` fırlar.
- `CompanyVendorNumber` kapsamda tanımlıysa aynı kuralla doldurulur.

## Düzenleyen kontrolü (`guards.supplier_matches_company`)

`SendInvoiceXml`, `SendArchiveInvoiceXml`, `SendDespatchAdviceXml`, `SendReceiptAdviceXml`,
`SendESMMXml` ve "numarasız" türevlerinde firma alanı yoktur. Luca belgeyi UBL içindeki VKN/TCKN
adına keser. Paket gönderimden önce UBL'yi okur ve şu tarafın VKN/TCKN'sini kapsamdaki firmayla
karşılaştırır:

| Belge | Taraf |
| --- | --- |
| Fatura, e-Arşiv, e-SMM | `AccountingSupplierParty` |
| e-İrsaliye | `DespatchSupplierParty` |
| İrsaliye yanıtı | `DeliveryCustomerParty` |

Eşleşmezse `TenantMismatchException`, kapsamda firma yoksa `MissingCompanyException` fırlar.
UBL okunamıyorsa (boş, bozuk XML, base64 olarak verilmiş) `InvalidDocumentException` fırlar.

Kontrol kapatılabilir ama kapatılması önerilmez:

```php
'guards' => ['supplier_matches_company' => false],
```

Bu kontrollerin hiçbiri ağ isteği yapmaz; hepsi zarf kurulmadan önce çalışır.

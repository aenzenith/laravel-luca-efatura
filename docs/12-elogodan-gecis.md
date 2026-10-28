# 12 - eLogo'dan geçiş notları

Bu notlar eLogo PostboxService kullanan ve UBL'yi kendisi üreten bir uygulama içindir.

## Ne aynı kalır

- **UBL üretimi.** Luca, UBL-TR 2.1 belgeyi olduğu gibi alır (`SendInvoiceXml`,
  `SendArchiveInvoiceXml`). eLogo için üretilen UBL değişmeden gönderilebilir.
- **Numaralandırma.** Uygulama numarayı kendisi veriyorsa `sendXml()`; Luca'nın vermesi isteniyorsa
  `sendXmlWithoutNumber()` kullanılır.
- **ETTN.** Belge ETTN'i UBL'deki `cbc:UUID`'dir.

## Ne değişir

| eLogo | Luca |
| --- | --- |
| Kullanıcı adı/parola, oturum açma | Kayıtlı IP + `CompanyTaxCode`; oturum yok |
| UBL zip'lenip `SendDocument` ile gönderilir | UBL ham XML olarak verilir, paket base64'e çevirir |
| Firma kimliği oturumdan gelir | XML gönderimlerinde firma UBL'deki düzenleyen VKN'den gelir |
| GİB sorgusu (`GetUserList` vb.) | `Luca::taxPayers()->find($vkn)` |
| PDF ayrı servis | `Luca::eArchive()->pdf($ettn)`, `Luca::eInvoices()->pdf($ettn)` |
| e-Arşiv iptali | `Luca::eArchive()->cancel($ettn, $neden)` |

## Çok firmalı uygulama

Firma başına kullanıcı adı/parola saklamak yerine her çağrı firma VKN'siyle kapsanır:

```php
Luca::company($shop->tax_code)->eArchive()->sendXml($ubl);
```

Düzenleyen kontrolü, UBL'deki satıcı VKN'si `$shop->tax_code` değilse belgeyi göndermez.

Luca tarafında her firmanın entegratör olarak Luca'yı seçmiş ve GİB'de kayıtlı olması gerekir;
firma adına çağrı yapmak için ayrı kimlik bilgisi yoktur, yetki IP kaydıyla verilir.

## Örnek

```php
// eLogo handler'ı UBL'yi göndermeden döndürebiliyorsa (ör. "yalnız UBL üret" bayrağı):
$xml = $handler->earsiv_gonder($fatura, $benimFirmam, $musteri, true);

$sent = Luca::company($saticiVkn)->eArchive()->sendXml($xml, sendMail: true);

$sent->ettn;     // kaydedilecek ETTN
$sent->number;   // kaydedilecek fatura numarası
```

Gönderimde `ConnectionException` alınırsa sipariş "kesildi" işaretlenmeden önce belge ETTN ile
aranmalıdır (`Luca::eArchive()->find($ettn)`).

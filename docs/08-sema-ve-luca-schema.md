# 8 - Şema ve `luca:schema`

## Neden şema

Luca servisleri WCF `DataContractSerializer` kullanır:

- Alanlar sözleşme sırasıyla gelmelidir: önce taban tipin alanları, sonra her seviyede ordinal (büyük
  harf önce) alfabetik sıra.
- Her alan, onu tanımlayan tipin isim alanında olmalıdır.
- Sırası ya da isim alanı yanlış ya da adı bilinmeyen eleman **hata vermeden atlanır**.

Bu yüzden zarflar elle değil, şemadan kurulur. Payload'daki anahtar sırası önemsizdir. `null` alanlar
yazılmaz, boş string boş eleman olarak yazılır. `schema.strict` açıkken sözleşmede olmayan anahtar,
en yakın alan adı önerisiyle `SchemaException` fırlatır. Luca'nın kendi iki örneği bile
`IsArchiveIncluded` yerine noktasız ı ile `IsArchiveıncluded` yazar; bu alan Luca'da sessizce düşer.

## Değer dönüşümleri

| XSD tipi | Gönderirken | Okurken |
| --- | --- | --- |
| `base64Binary` | ham bayt verilir, paket kodlar | ham bayt döner |
| `decimal` | `int`, `float` ya da `"1234.56"` (virgül reddedilir) | string |
| `int`, `long` | `int` ya da tam sayı string | `int` |
| `boolean` | `bool`, `0/1`, `"true"/"false"` | `bool` |
| `dateTime` | `DateTimeInterface` (`timezone`'a çevrilir, ofsetsiz) ya da ISO string (aynen) | string |
| enum | string ya da `BackedEnum` | string |

## Kaynaklar

1. `resources/schema/invoice-service-2022.php`: InvoiceService'in 2022 WSDL içe aktarımından üretilmiş
   358 tip, 39 operasyon.
2. `resources/schema/overlay.php`: 2022'den sonra eklenen alanlar (`Exemptions`, `EydepBilgisi`,
   `ExpenditureUnit`, `IdisTagNumberList`, `VNKTCKN`...), yeni operasyonlar (`GetDocumentViewerLink`,
   `ValidateUserCompany`, `GetCompanyVendor`), AddressBookService ve ProductService. Her bloğun
   kaynağı (örnek istek ya da doküman) dosyada yazılıdır.
3. `LUCA_SCHEMA_PATH`: `luca:schema` çıktısı. Varsa ortak tipleri bütünüyle değiştirir.
4. `schema.extend` ve `Luca::extendSchema([...])`: ekleme. Mevcut tipe alan ordinal sırasındaki
   yerine eklenir, enum değerleri birleştirilir.

İstek tarafı, Luca'nın örnek isteklerinden 40'ı ile testte eleman eleman karşılaştırılır.

## Canlı WSDL'den derleme

Kayıtlı IP'li sunucuda (ya da `LUCA_PROXY` ile):

```bash
php artisan luca:schema --dry-run          # farkları göster
php artisan luca:schema                    # storage/app/luca-schema.php yaz
```

```dotenv
LUCA_SCHEMA_PATH=/var/www/app/storage/app/luca-schema.php
```

Komut her servisin `?singleWsdl` çıktısını okur, yeni/eksik operasyonları ve alanı değişen tipleri
listeler. WSDL dosyadan da okunabilir:

```bash
php artisan luca:schema --from=InvoiceService.wsdl --from=AddressBookService.wsdl
```

Yanıt tipi şemada olmayan operasyonların yanıtı şemasız okunur: hiçbir eleman atılmaz, tekrarlanan
ya da liste adlı (`...List`, çoğul, `ArrayOf...`) elemanlar liste olur.

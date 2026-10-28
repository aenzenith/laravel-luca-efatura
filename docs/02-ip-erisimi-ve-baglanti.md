# 2 - IP erişimi ve bağlantı

Luca servislerinde kullanıcı adı/parola yoktur. Kimlik, isteği gönderen sunucunun çıkış IP'si ile
istekteki `CompanyTaxCode` (VKN/TCKN) üzerinden kurulur. IP, "Entegratör Web Servis Hizmet Başvurusu"
ile Luca'ya bildirilir. Kayıtlı olmayan IP'den gelen bağlantı TCP seviyesinde kesilir:

```
cURL error 56: Recv failure: Connection reset by peer
```

Paket bu durumu `ConnectionException` olarak fırlatır; mesajda IP kaydı hatırlatılır.

## Kontrol

```bash
php artisan luca:check
php artisan luca:check --company=1234567801 --connection=canli
```

Komut bağlantıyı, ortamı, uç adresini, firmayı ve proxy'yi yazar, ardından `GetCompanyBalance`
çağırır. Bağlantı kesilirse sunucunun çıkış IP'sini (`curl -s https://api.ipify.org`) başvurudaki
IP ile karşılaştırın.

## Geliştirme makinesinden erişim

Yerel makinenin IP'si kayıtlı olmadığı için istekler kayıtlı sunucu üzerinden tünellenir:

```bash
ssh -N -D 1080 kullanici@kayitli-sunucu
```

```dotenv
LUCA_PROXY=socks5h://127.0.0.1:1080
```

`socks5h` DNS çözümlemesini de tünelin karşı ucuna bırakır.

## Tekrar deneme

`retry.times` (varsayılan 2) ve `retry.sleep_ms` (varsayılan 500; ikinci denemede 1000) yalnız
**okuma** işlemlerine (`Search*`, `Get*`, `Validate*`) ve yalnız bağlantı hatasına uygulanır. Diğer
tüm işlemler yazma sayılır ve tekrar denenmez: yanıtın kaybolması isteğin işlenmediği anlamına
gelmez, ikinci gönderim aynı faturayı iki kez kesebilir. Yazma işleminde `ConnectionException`
alındığında belgeyi ETTN ya da dış referansla arayıp sonra karar verin.

# PayTR ödeme bildirimi ve kesinleştirme

15 Kasım 2026. Bu aşama callback altyapısıdır. Devamında token/iFrame kodu [iFrame bağlantısı](paytr-iframe-2026-11-15.md) çalışmasıyla eklendi; gerçek PayTR testi henüz yapılmadı. Kart seçeneği varsayılan olarak kapalıdır.

## Adres ve doğrulama

Bildirim adresi `POST /payments/paytr/callback`, route adı `payments.paytr.callback`.

Route, `routes/payments.php` üzerinden web/auth/session/CSRF middleware grubuna girmeden yüklenir. Kullanıcı oturumu, apartman seçimi, üyelik sözleşmesi veya tarayıcı yönlendirmesi gerektirmez. Genel CSRF korumasına istisna eklenmedi; diğer formlar korunur. Yalnızca POST gövdesi okunur, query string ödeme kanıtı değildir.

Güncel [PayTR iFrame 2. adım](https://dev.paytr.com/iframe-api/iframe-api-2-adim) ve yerel Türkçe PHP örneğindeki formül kullanıldı:

`base64(HMAC-SHA256(merchant_oid + merchant_salt + status + total_amount, merchant_key))`

Karşılaştırma `hash_equals` ile yapılır. Alan tipleri/uzunlukları, `success|failed` durumu ve kuruş tutarı doğrulanır. Eksik konfigürasyon veya geçersiz imza hiçbir siparişi değiştirmez.

PayTR örneğinin imzaladığı alanlar yalnızca merchant_oid, status ve total_amount'tır. `payment_amount`, `currency`, `payment_type`, `test_mode` ek tutarlılık kontrolleridir; imzalı oldukları varsayılmaz ve yerel ödeme denemesini değiştiremezler.

## Sonuç işleme

- Callback, merchant_oid ile PayTR denemesini bulur. Önce apartmanlar, sonra sipariş, sonra deneme kilitlenir; mevcut manuel işlem yollarıyla aynı sıra kullanılır.
- Sunucuya gönderildiği kaydedilmemiş (`submitted_at` boş) bir deneme aktifleştirilemez. Geçmiş denemeler geriye dönük gönderilmiş sayılmadı.
- İmzalı toplam tutar, deneme, sipariş ve sipariş kalemlerinin kuruş toplamıyla tam eşleşmelidir. TRY/TL ve `card` beklenir. Kalem tutarları tam sayı kuruş üzerinden toplanır.
- İlk sürüm tek çekimdir. Taksit/vade farkıyla yüksek toplam dahil herhangi bir fark otomatik kabul edilmez; incelemeye gider. Token adımında da tek çekim ayarı gönderilmelidir.
- Başarı, `FinalizeSubscriptionPayment::paytr` üzerinden tek transaction içinde tahsilat, dönem aktivasyonu, deneme sonucu ve bildirim kaydı oluşturur. Herhangi bir kayıt hatasında hepsi geri alınır, OK verilmez.
- Aynı başarı tekrar geldiğinde yeni tahsilat veya dönem uzaması oluşmaz. Sonradan idari olarak sonlandırılmış sipariş tekrar açılmaz. Soft delete edilmiş tahsilat da ikinci ödeme üretmez.
- Başarısızlık yalnızca denemeyi `failed` yapar. Sipariş ödenmemiş/bekleyen olarak kalır; sonraki token aşamasında yeni merchant_oid ile tekrar denenebilir. Aynı başarısız bildirim tekrar gelirse OK döner.
- Başarı/başarısızlık çelişkisi, iptal/silme sonrası sonuç, arşivlenmiş apartman, sona erdirilmiş kalıcı abonelik, değişmiş fiyat/kalemler veya başka açık deneme otomatik aktivasyon üretmez.
- İnceleme gerektiren denemede `review_required` kalıcı olarak işaretlenir. Açık deneme `unknown` olur; başarılı/başarısız terminal durum geriye çevrilmez. Yeni deneme, manuel tahsilat, sipariş iptali/silme ve idari sonlandırma engellenir.
- Sonradan düzgün görünen callback incelemeyi kendiliğinden kaldırmaz. Durum sorgusu ve yetkili mutabakat akışı sonraki aşamadır; şu anda incelemeyi kaldıran bir kullanıcı işlemi yoktur.

## Bildirim geçmişi ve yönetici görünümü

`subscription_payment_notifications`, doğrulanmış bildirimlerin sınırlı alanlarını tutar: sağlayıcı sonucu, imzalı toplam, bildirilen sipariş tutarı/para birimi/yöntem/test ortamı, hata kodu, işleme sonucu ve inceleme kodu. Aynı bildirim için teslim sayısı artar. Anahtar, salt, hash, ham POST, kart verisi, ödeme yapan kişinin bilgileri ve serbest hata mesajı bu tabloya/loglara kopyalanmaz.

Yönetici sipariş detayında PayTR denemesi, test/canlı bilgisi, beklenen ve bildirilen tutar, son bildirim ve inceleme kodu gösterilir. İnceleme varsa bölüm açık gelir. İnceleme başlangıcında loga yalnızca deneme ID ve dahili neden kodu yazılır.

Yanıtlar HTML/redirect içermeyen, önbelleğe alınmayan düz metindir:

| HTTP | Anlam |
| --- | --- |
| 200, tam `OK` | Başarı/başarısızlık güvenli işlendi veya aynı terminal sonuç tekrarlandı |
| 400 | Eksik/bozuk alanlar |
| 403 | İmza uyuşmazlığı |
| 404 | Bu mağazanın yerel PayTR denemesi bulunamadı |
| 409 | İnceleme gerekiyor; otomatik finansal/hizmet değişikliği yapılmadı |
| 503 | Konfigürasyon eksikliği veya geçici işleme/DB hatası; başarı onayı verilmedi |

409/503 yanıtları OK değildir; PayTR tekrar bildirim gönderebilir. Canlıya çıkmadan önce incelemelerin izlenmesi ve mutabakatla çözülmesi tamamlanmalıdır.

## Ortam ayarları

Gerçek değerler repoya veya tarayıcıya değil, sunucu ortamına yazılır. `.env.example` yalnızca boş alanları içerir:

```dotenv
PAYTR_MERCHANT_ID=
PAYTR_MERCHANT_KEY=
PAYTR_MERCHANT_SALT=
PAYTR_TEST_MODE=true
PAYTR_ALLOW_TEST_SETTLEMENT=false
```

`PAYTR_TEST_MODE`, yeni denemenin ortamını belirler. Callback, sonradan değişmiş ödeme başlatma ayarından bağımsız olarak denemede kayıtlı ortamı kullanır. Kart seçeneğinin kapatılması mevcut canlı denemelerin bildirimini durdurmaz.

Test ödemesiyle aktivasyon varsayılan olarak kapalıdır. İzole local/testing/staging ortamı yanında, kullanıcının talebiyle production için yalnızca kapalı yeni üyelik ve izinli aktif test hesaplarıyla sınırlı geçici bir akış eklendi. Tek başına `PAYTR_ALLOW_TEST_SETTLEMENT=true` production izni vermez. Güncel ayarlar ve hesaplar: [aidatcep.com test kurulumu](paytr-aidatcep-test-kurulumu.md). Test tahsilatları test olarak işaretli denemeye bağlıdır.

Yeni migration `2026_11_15_000002_add_paytr_callback_tracking` yerel veritabanına uygulandı. Üretim/test sunucusunda ayrıca uygulanmalıdır. Ödeme geçmişi oluşunca migration rollback ile kayıtlar silinmemelidir.

## Sonraki aşama ve doğrulama sınırı

Token/iFrame aşamasında sunucu, denemeyi ve sabit tutar/ortam bilgilerini oluşturup dış isteği başlatmadan önce `submitted_at` bilgisini kaydetmelidir. Dış HTTP isteği DB transaction içinde bekletilmemeli; timeout sonucu kesin başarısızlık sayılmamalıdır. Kullanıcı başarı/başarısızlık sayfasına dönünce bu sayfalar abonelik açmamalıdır.

Uçtan uca sağlayıcı testi için dışarıdan erişilebilir HTTPS adresi ve PayTR panelinde bu adresin Bildirim URL olarak tanımlanması gerekir. Yerel `.test` adresiyle PayTR'den gerçek callback alınmadı. Hiçbir dış tahsilat/iade isteği gönderilmedi.

Feature testleri SQLite üzerinde imza/alan/tutar/ortam kontrollerini, tekrarları, çelişkileri, bekleyen siparişte yeni denemeyi, tek dönem uzamasını, finansal kayıt/audit hatasında rollback'i ve yönetici korumalarını sınar. Bu testler çok worker altında MySQL kilit davranışını kanıtlamaz; canlıya geçmeden aynı DB motoruyla eşzamanlı callback/manuel işlem senaryoları ayrıca doğrulanmalıdır.

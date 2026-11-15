# PayTR iFrame bağlantısı

15 Kasım 2026. Yerel uygulama tamamlandı; mağaza bilgileri ve dış HTTPS test adresi henüz tanımlanmadığı için gerçek PayTR isteği/ödemesi yapılmadı. `PAYTR_CARD_ENABLED` varsayılanı kapalıdır. Kullanıcının tercihi doğrultusunda yerelde hazırlanıp test sunucusuna bırakıldı; frontend build kullanıcı tarafından yapılacak.

## Akış

1. Kart seçilen sipariş ve satış onayları kaydedilir. Ödeyen kişinin bilgileri ödeme denemesinde şifreli tutulur.
2. Son onay POST'u sonrasında token hazırlanır. Deneme, merchant_oid, kuruş tutarı, test/canlı bilgisi ve gönderim zamanı DB'ye yazılıp transaction tamamlanır.
3. Sabit `https://www.paytr.com/odeme/api/get-token` adresine sunucudan form POST yapılır. Tutar/sepet fiyatları sipariş kopyasından gelir. TRY/TL ve tek çekim kullanılır.
4. Token şifreli saklanır; kullanıcı AidatCep popup'ında PayTR iFrame'ini görür. Kart numarası ve CVV AidatCep tarafından alınmaz/saklanmaz.
5. Başarı ve hata dönüşleri aynı, salt okunur sonuç ekranına gider. Sonuç ekranı tarayıcı parametresine güvenmez; doğrulanmış yerel durumu gösterir. En fazla beş dakika, beş saniyede bir durum kontrol edilir. Daha sonra kullanıcı manuel yenileyebilir.
6. Aboneliği yalnızca doğrulanmış callback aktifleştirir. Dönüş ekranı, token alınması veya iframe gösterilmesi tahsilat değildir.

Kaynak: [PayTR iFrame 1. adım](https://dev.paytr.com/iframe-api/iframe-api-1-adim). Token HMAC girdisi: merchant_id + user_ip + merchant_oid + email + payment_amount + user_basket + no_installment + max_installment + currency + test_mode + merchant_salt. HMAC-SHA256 merchant_key ile hesaplanıp base64 kodlanır.

## Tekrarlar ve hatalar

- Her deneme için dış token isteği bir kez gönderilir. Sonraki POST'lar mevcut denemeyi/token'ı kullanır. GET ekranları ödeme isteği oluşturmaz.
- İstek 5 saniye bağlantı / 20 saniye toplam timeout ile gönderilir. HTTP retry ve HTTP redirect kapalıdır, TLS doğrulaması açıktır.
- Timeout, bozuk JSON/token veya HTTP hatası `unknown` olur. Körlemesine yeni deneme oluşturulmaz; callback veya sonraki mutabakat aşaması beklenir.
- PayTR'nin açık token reddi `failed` olur. Kullanıcının yeniden denemesinde yeni merchant_oid açılır; şifreli ödeyen bilgileri önceki denemeden devralınır.
- Token süresi dolduğunda otomatik yeni ödeme başlatılmaz. Mevcut işlemin sonucu beklenir; doğrulanmış başarısızlık veya mutabakat olmadan yeni tahsilat denenmez.
- Callback HTTP cevabından önce gelebilir. Token cevabı terminal sonucu veya inceleme işaretini geriye çevirmez.
- Token/payer alanları model JSON çıktısından gizlenir. Token ekranı private/no-store ve no-referrer yanıtıdır. Ham sağlayıcı hata mesajları/anahtarlar/token'lar loglanmaz veya kullanıcıya gösterilmez.
- Sonuç ve iframe ekranları oturum ve sipariş sahipliği gerektirir. Callback ayrı, oturumsuz route'ta kalır.
- Dashboard'dan başlanan işlemin Kapat hedefi, siparişe ait tarayıcı session bilgisinde korunur. Bu session bilgisi ödeme doğrulamasında kullanılmaz.

## HTTPS test sunucusuna geçerken

Mağaza bilgileri sohbet veya repoya yazılmadan sunucunun `.env` ayarına konulmalı:

```dotenv
PAYTR_MERCHANT_ID=...
PAYTR_MERCHANT_KEY=...
PAYTR_MERCHANT_SALT=...
PAYTR_PUBLIC_URL=https://TEST-ALAN-ADINIZ
PAYTR_TEST_MODE=true
PAYTR_ALLOW_TEST_SETTLEMENT=true
PAYTR_CARD_ENABLED=true
```

Bu örnek izole veritabanı olan local/staging test ortamı içindir. Kullanıcının daha sonraki talebiyle production için kapalı üyelik ve yalnızca iki aktif test hesabına izin veren sınırlı bir akış eklendi: [aidatcep.com test kurulumu](paytr-aidatcep-test-kurulumu.md). Ortam ayarları yüklenince config cache yenilenmelidir. Canlı ödeme açılması ayrı, kontrollü geçiştir; henüz yapılmadı.

- `PAYTR_PUBLIC_URL`, path/query içermeyen, dışarıdan erişilebilir HTTPS origin olmalıdır. `.test`, localhost ve özel IP uygun değildir.
- PayTR panelindeki Bildirim URL: `https://TEST-ALAN-ADINIZ/payments/paytr/callback`.
- APP_URL/session cookie ayarları da tarayıcının kullandığı bu adresle uyumlu olmalıdır; kullanıcı ödeme dönüşünde oturumunu koruyabilmelidir.
- Müşteri IP'si Laravel `Request::ip()` üzerinden alınır. Ham X-Forwarded-For/Client-IP başlıkları koşulsuz kabul edilmez. Reverse proxy/tünel kullanılıyorsa yalnızca gerçek proxy'ler güvenilir olarak yapılandırılmalıdır. Local loopback veya özel müşteri IP'si ile PayTR isteği yapılmaz.
- `php artisan paytr:check` gizli değerleri göstermeden yerel ayarları kontrol eder; ağ isteği göndermez. Bu komut DNS/HTTPS erişilebilirliğini veya mağaza panelini doğrulamaz.
- Migration `2026_11_15_000003_add_paytr_iframe_token` yerel DB'ye uygulandı. Test sunucusunda da migration ve kullanıcı tarafından frontend build yapılmalıdır.

## Doğrulama ve kalan işler

Laravel HTTP fake ile imza/sepet/tutar, HTTP sırasında transaction'ın kapalı olması, tek token isteği, token şifreleme, tekrar, timeout, açık ret, yeni merchant_oid, oturum/sahiplik kontrolleri, dönüşte sahte başarı engeli, token süresi ve callback/token yarışı sınandı. Mevcut sipariş ve callback testleri de çalıştırıldı.

Gerçek sağlayıcı token'ı, PayTR kart ekranı, 3D Secure dönüşü ve dış callback henüz denenmedi. Bunun için mağaza bilgileri, HTTPS test adresi ve PayTR panel ayarı gereklidir. Durum sorgusu ile belirsiz/eskimiş denemelerin mutabakatı ve iade sonraki aşamalardır. Çok worker/MySQL eşzamanlılık kontrolü canlıya geçmeden ayrıca yapılmalıdır.

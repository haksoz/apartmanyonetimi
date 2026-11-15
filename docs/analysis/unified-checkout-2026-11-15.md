# Ortak abonelik satın alma ve ödeme hazırlığı

Tarih: 15 Kasım 2026.

## Kullanıcı akışı

- Abone panelindeki Ücretliye Geç/Yenile, dashboard ve Siparişlerim aynı `subscriber.subscriptions.create` adresini kullanır.
- `apartment_id` varsa yetkili ve siparişe uygun apartman için popup açılır. Tek apartman varsa otomatik seçilir; birden fazlaysa önce seçim gösterilir.
- Eski `?type=renew` adresi de aynı akışı açar. İşlem türü URL parametresinden değil, apartmanın abonelik durumundan hesaplanır.
- Apartman kartları artık gizli satın alma formları ve sözleşme metinleri taşımaz. Popup `checkout-modal`, davranışlar `checkout-script` içinde ortaktır.
- Adımlar: abonelik/dönem, fatura, sipariş özeti ve ödeme yöntemi/onay. Form son onaydan önce sipariş açmaz.
- Fatura adımında yeni bilgiler girilmişse Devam ile mevcut fatura profili endpoint'ine JSON isteği gönderilir. Başarılı kayıttan sonra üçüncü adıma kayıtlı profil seçimiyle geçilir. Sipariş tamamlanmasa da profil kullanıcıya ait olarak saklanır; aynı içerikle yinelenen istek aktif profili tekrar kullanır. Doğrulama veya bağlantı hatasında fatura adımında kalınır.
- Bekleyen kendi siparişi varsa sonuç/ödeme popup'ına gidilir; başka kullanıcının bekleyen siparişi varsa özel bilgilere erişim verilmez.
- Havale/EFT banka bilgileri ve ödeme/onay durumu, aynı markalı popup düzeninde gösterilir.
- Kart ödemesi `config/payments.php` altında kapalıdır. Ekran ve sunucu aynı ayarı kullanır. PayTR client/callback tamamlanmadan açılmamalıdır.

## Kayıt ve güvenlik

- Yeni form UUID işlem anahtarı ve fiyat/vergi/sözleşme sürümlerinin HMAC imzasını taşır.
- Aynı anahtar ve içerik mevcut siparişe döner; farklı içerik veya silinmiş siparişle anahtar kullanımı reddedilir. Farklı anahtarla aynı apartmana tekrar gelirse açık siparişe devam edilir.
- Fiyat, vergi veya satış metni değişirse yeniden onay gerekir. Sunucu fiyatı yeniden hesaplar; tarayıcı tutarına güvenmez.
- Geriye uyumluluk için eski havale POST'larında token/imza opsiyoneldir. Yeni arayüz her zaman gönderir. Kart hazırlığı açıkken token, imza ve ödeyen kişi bilgileri zorunludur.
- Kullanıcı/apartman satırları kilitlenir; pending sipariş kontrolü kilit altında tekrarlanır. Sipariş, profil kopyası, iki satış onayı ve ödeme denemesi aynı transaction içinde yazılır.
- Sipariş kaydı başarısızsa bu isteğin yüklediği dekont temizlenir.
- `subscription_payment_attempts` ayrı işlem geçmişidir. Yeni havale siparişinde bank_transfer denemesi oluşturulur. İleride PayTR için alfanümerik unique merchant_oid, kuruş tutarı, para birimi, ortam ve şifreli ödeyen bilgisi alanları hazırdır.
- Ödeme kesinleştirme `FinalizeSubscriptionPayment::manual` üzerinden yapılır. Tahsilat ile aktivasyon atomiktir. Aynı sonuç bir kez etkili olur; soft delete edilmiş ödeme de tekrar tahsilat üretmez.
- `SubscriptionCheckout::activate` kendi başına da tekrar çağrılmaya dayanıklıdır. İptal edilmiş sipariş aktifleştirilemez.
- İptal, dekont güncelleme, manuel onay ve sipariş silme yolları ortak apartman→sipariş kilit sırasına uyar.
- Bilinmeyen sonuçlar ve açık PayTR denemeleri manuel onay/iptal/silmeyi engeller. Yeni denemeye bağlı tahsilat geçmişi silinerek düzeltilmez. Eski manuel kayıtların mevcut silme davranışı korunur.

## Veritabanı ve geçiş

`2026_11_15_000001_add_checkout_payment_foundation` yerel veritabanına uygulandı:

- Siparişte nullable unique checkout_token ve fingerprint.
- Yeni subscription_payment_attempts tablosu.
- Tahsilatta nullable unique payment_attempt_id ilişkisi.

Geçmiş siparişlere uydurma deneme veya onay kaydı eklenmedi. Üretim yayını sırasında migration ayrıca uygulanmalıdır. Finansal kayıtlar oluştuğunda geri dönüş tablo silerek yapılmamalıdır; yeni ödeme başlatma kapatılıp sonuç işleme korunmalıdır.

## Doğrulama

Feature testleri; ortak giriş, bekleyen siparişe devam, tekrar gönderim, anahtarın farklı içerikle kullanımı, fiyat/sözleşme değişimi, onay hatasında rollback, tutarın kuruşa dönüşümü, tekrar kesinleştirme, iptal sonrası onay engeli ve belirsiz işlemin korunmasını kapsar. Mevcut sipariş/faturalama/abonelik/kurulum testleri de çalıştırıldı.

Gerçek tarayıcıda, finansal istek gönderimi engellenmiş test HTML'iyle 320px, 390px ve 1366px genişlikler kontrol edildi. Üç adımda yatay taşma görülmedi. Dönem→fatura→özet→iki sözleşme onayı→tek submit akışı çalıştı; ikinci tıklama engellendi.

Test DB'si SQLite'tır. Kilitlerin çok worker altında MySQL davranışı bu testlerle kanıtlanmış değildir; PayTR canlıya geçiş öncesi aynı DB motoruyla eşzamanlılık senaryoları ayrıca çalıştırılmalıdır.

## PayTR bağlantısında kalan işler

- Sunucu token isteği, iFrame ve sağlayıcı hata yönetimi.
- Dışarıdan erişilebilir HTTPS callback, HMAC doğrulaması, sağlayıcı sonuçlarının ortak kesinleştirmeye bağlanması.
- Durum sorgulama, geciken/çelişen sonuçların mutabakatı, iade akışı.
- Yayındaki sözleşmeler, mağaza ayarları, gerçek test ödemesi ve canlı mod kontrolü.

Bu çalışma gerçek PayTR tahsilatı veya iadesi başlatmadı. Kart ayarı kapalı bırakıldı.

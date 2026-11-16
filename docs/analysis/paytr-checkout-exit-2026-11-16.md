# Kart ödeme ekranından çıkış

16 Kasım 2026. Kullanıcı kararlı çıkışta bekleyen sipariş/devam et bağlantısı istemiyor; yönetici işlem geçmişini korumalı.

- Kart ekranındaki Kapat, dışarı tıklama ve Escape aynı onayı kullanır. Onay verilirse CSRF korumalı, sipariş sahipliği kontrol edilen POST ile çıkış kaydedilmeden yönlendirme yapılmaz. Çıkış isteği başarısızsa kullanıcıya hata gösterilir.
- Tarayıcıdan ayrılma için pagehide/sendBeacon ek bildirim gönderir. Tarayıcı kapanışı, mobil uygulamanın zorla sonlandırılması veya ağ kesilmesinde bu bildirimin teslimi garanti değildir. Yedek olarak süre aşımını işleyen `paytr:expire-checkouts` komutu her dakika schedule edilir. Sunucuda Laravel scheduler çalışmalıdır.
- `checkout_abandoned_at`, kullanıcı siparişlerini silmeden yarım kalan girişimi ayırır. Müşteri sipariş listesi, dashboard ve apartman bekleyen sipariş özeti bu kayıtları göstermez. Eski kart ekranına dönüş yeni seçimin ilk adımına yönlenir; aynı girişim yeniden başlatılmaz.
- Yönetici tarafında Yarım bırakıldı durumu, çıkış tarihi, ödeme denemesi ve callback geçmişi korunur. Yarım bırakılan girişim manuel onay kuyruğuna dahil edilmez. Tamamlanmış tahsilat çıkış isteğiyle gizlenmez veya geri alınmaz.
- PayTR'ye hiç gönderilmemiş veya sonucu kesin başarısız olan girişim güvenle iptal edilir; kullanıcı yeni dönem/fiyat/sözleşme seçimiyle yeni sipariş oluşturabilir.
- PayTR'ye gönderilmiş, henüz sonucu kesinleşmemiş deneme başarısız sayılmaz. Kullanıcı yeni seçim ekranına dönebilir, ancak son onayda yeni bir tahsilat başlatılmadan önce önceki denemenin sonucu kesinleşmelidir. Eski ekranı tekrar açan bağlantı sunulmaz.
- Kapat, PayTR'ye iptal talebi göndermez. Geç başarı bildirimi gelirse ödeme atomik olarak kaydedilir ve gerçek sipariş müşterinin listesine geri gelir; tekrarlanan callback ikinci dönem açmaz. Geç başarısızlık bildirimi yarım kalan girişimi kapatır ve yeni satın almayı serbest bırakır.
- PayTR'nin sonuç sayfasını iframe içinde açıp üst pencereye yönlendirdiği durumda normal dönüşü çıkış saymamak için parent çıkış bildirimi bastırılır. Doğrudan üst pencere yönlendirmesiyle yarışan çıkış/callback de güvenlidir; sonuç ekranı geç kesin sonucu izlemeyi sürdürür.
- Migration `2026_11_16_000001_add_checkout_abandonment` yerel DB'ye uygulandı. Yayında migration ve scheduler gereklidir. Build bu çalışmada çalıştırılmadı.

Yerel testte callback hâlâ canlı alan adına giderse iki veritabanı aynı işlem durumunu paylaşmaz. Yerel token/iFrame testi, gerçek callback/final sonuç doğrulaması anlamına gelmez. Sonucu belirsiz bir girişimi yalnızca zaman geçti diye başarısız sayıp yeni tahsilata açmıyoruz; mutabakat akışı ayrı çalışmadır.

## Token isteğinde hata

Token açılış hatası, kartın reddedilmesinden ayrı izlenir. `subscription_payment_attempts.token_failure_code`, kişisel veri/anahtar içermeyen teknik neden kodunu saklar. Açık sağlayıcı reddinde deneme failed, sipariş sistem tarafından cancelled olur; müşteri listesine girmez. Sonuç ekranı ödeme ekranının açılamadığını söyler ve yeni fiyat/dönem/sözleşme akışına yönlendirir. Aynı eski sipariş üzerinde tekrar token açılmaz.

Timeout/bozuk yanıt sonucu unknown kalır; kayıt yine müşteri siparişlerinden ayrılır. Sonuç ekranı bunu tamamlanmış ödeme veya kart hatası olarak sunmaz; otomatik tekrar veya yeni tahsilat düğmesi açılmaz. Geç callback başarıyı doğrularsa tahsilat kaybolmadan sipariş görünür hale gelir; başarısızlık doğrulanırsa yeni akış serbest bırakılır. Yönetici listesinde bu kayıtlar kullanıcı vazgeçmiş gibi değil, Ödeme ekranı açılamadı olarak gösterilir.

Migration `2026_11_16_000002_add_paytr_token_failure_code` yerel veritabanına uygulandı; sunucuda ayrıca uygulanmalıdır. Geçmiş kayıtların hata nedeni tahmin edilerek doldurulmadı.

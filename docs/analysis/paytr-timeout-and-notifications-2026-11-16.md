# PayTR zaman aşımı ve bildirim

Kaynaklar:
- https://dev.paytr.com/iframe-api/iframe-api-1-adim
- https://dev.paytr.com/iframe-api/iframe-api-2-adim

`timeout_limit` ödeme işleminin tamamlanma süresidir. Bildirimin aynı süre içinde mutlaka ulaşacağı garantisi değildir. PayTR 6 kodunu, sürede tamamlanmayan veya müşteri tarafından kapatılan ödeme için açıklar. Bildirim ulaşmazsa süreye bakarak tahsilatı başarısız kabul etmeyiz.

Yerel süre dolumu ödeme ekranını müşteri listesinden ayırır; ödeme sonucunu değiştirmez. Scheduler, arayüz için 10 saniye erken tutulan token son kullanımına değil, gönderim zamanı + PayTR işlem süresine bakar. Bildirim ve sorgu sonuçları olmadan finansal engel kaldırılmaz. Sağlayıcıyla otomatik mutabakat henüz uygulanmamıştır.

İmzası ve işlem eşleşmesi doğrulanmış `status=failed`, `failed_reason_code=6` bildirimi girişimi başarısız, sipariş kalemlerini iptal yapar. Yeni satın alma açılabilir. Pasif silinen ama hâlâ bekleyen sipariş de doğrulanmış başarısızlıkla kapanabilir; silinmiş olarak kalır. Aynı bildirimin tekrarı `OK` alır. Başarı, yanlış imza ve inceleme gerektiren uyuşmazlıklar bu yoldan başarısız sayılmaz. Başarısızlık kodu tek başına imzalanmadığı için ödeme sonucunun yerine geçmez.

Yalnızca test girişimini admin tarafından kapatmak ayrı bir işlemdir; geç bildirimleri otomatik hizmet açmak yerine incelemeye alır. Gerçek girişimleri süre doldu diye bu yöntemle kapatmayız.

Canlı tanı: Mağaza panelinden ilgili merchant_oid için bildirim sonucu, uygulamadaki girişim/bildirim kaydı ve Forge scheduler incelenmeli. Yerel testte production bildirim URL'sine gönderilen sonuç yerel veritabanını güncelleyemez. Bu çalışma canlı mağaza veya kayıtlarına erişerek doğrulama yapmadı.

## Eksik alanlı başarısız bildirimler (23.11.2026)

Canlıdan alınan yerel kopyada hata kodu 6 olan imzası doğrulanmış başarısız bildirimin `currency` ve `payment_amount` alanları bulunmadığı görüldü. Bu alanların yokluğu başarısız sonuçta inceleme nedeni değildir. Gönderilmiş ek alanlar eşleşmeye devam etmeli; başarılı sonuçta alan/tutar/test aktivasyonu kontrolleri korunur. Başarısız sonuç test aktivasyonu iznine bağlı değildir; hiçbir abonelik açmaz.

Ekran kapatılmış veya eski girişim yeni bir denemeyle değiştirilmiş olsa da başarısız callback eski girişimi sonuçlandırır ve düz metin `OK` alır. Yeni deneme değiştirilmez. Geçerli başarısız bildirimin tekrarları da `OK` alır.

Eski `review_required` kayıtları yalnızca bütün bildirimleri başarısızsa, inceleme nedeni eksik alanla açıklanıyorsa ve kalan güvenlik kontrolleri geçiyorsa yeniden işlenebilir. Başarı bildirimi, mevcut tahsilat, gerçek alan/tutar uyuşmazlığı veya başka bir inceleme sebebi bu düzeltmeyle aşılmaz. Sonraki gerçek PayTR tekrarı uygun kaydı otomatik sonuçlandırabilir. Tekrar gelmiyorsa, önceden imza kontrolünden geçmiş veritabanı bildirimini yeniden işlemek için:

```sh
php artisan paytr:resolve-failed-notifications --attempt=12
php artisan paytr:resolve-failed-notifications --attempt=12 --apply
```

İlk komut kontrol eder, ikinci komut uygular. Girişim numarası ilgili ortamdaki kayıtla doğrulanmalıdır. Komut dışarı ödeme isteği göndermez, teslim sayısını artırmaz ve yeni başarı bildirimi üretmez. Çözüm geçmişe ayrıca kaydedilir. Canlı ve yerel veritabanları birbirinden bağımsızdır.

# PayTR zaman aşımı ve bildirim

Kaynaklar:
- https://dev.paytr.com/iframe-api/iframe-api-1-adim
- https://dev.paytr.com/iframe-api/iframe-api-2-adim

`timeout_limit` ödeme işleminin tamamlanma süresidir. Bildirimin aynı süre içinde mutlaka ulaşacağı garantisi değildir. PayTR 6 kodunu, sürede tamamlanmayan veya müşteri tarafından kapatılan ödeme için açıklar. Bildirim ulaşmazsa süreye bakarak tahsilatı başarısız kabul etmeyiz.

Yerel süre dolumu ödeme ekranını müşteri listesinden ayırır; ödeme sonucunu değiştirmez. Scheduler, arayüz için 10 saniye erken tutulan token son kullanımına değil, gönderim zamanı + PayTR işlem süresine bakar. Bildirim ve sorgu sonuçları olmadan finansal engel kaldırılmaz. Sağlayıcıyla otomatik mutabakat henüz uygulanmamıştır.

İmzası ve işlem eşleşmesi doğrulanmış `status=failed`, `failed_reason_code=6` bildirimi girişimi başarısız, sipariş kalemlerini iptal yapar. Yeni satın alma açılabilir. Pasif silinen ama hâlâ bekleyen sipariş de doğrulanmış başarısızlıkla kapanabilir; silinmiş olarak kalır. Aynı bildirimin tekrarı `OK` alır. Başarı, yanlış imza ve inceleme gerektiren uyuşmazlıklar bu yoldan başarısız sayılmaz. Başarısızlık kodu tek başına imzalanmadığı için ödeme sonucunun yerine geçmez.

Yalnızca test girişimini admin tarafından kapatmak ayrı bir işlemdir; geç bildirimleri otomatik hizmet açmak yerine incelemeye alır. Gerçek girişimleri süre doldu diye bu yöntemle kapatmayız.

Canlı tanı: Mağaza panelinden ilgili merchant_oid için bildirim sonucu, uygulamadaki girişim/bildirim kaydı ve Forge scheduler incelenmeli. Yerel testte production bildirim URL'sine gönderilen sonuç yerel veritabanını güncelleyemez. Bu çalışma canlı mağaza veya kayıtlarına erişerek doğrulama yapmadı.

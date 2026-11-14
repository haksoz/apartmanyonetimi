# PayTR iFrame API hazırlık incelemesi

Tarih: 14 Kasım 2026. Kapsam: yerel kaynak kod ve PayTR resmi dokümantasyonu. Canlı sunucu, PayTR mağaza ayarları ve gerçek ödeme işlemleri incelenmedi. Bu çalışma entegrasyon uygulaması değildir.

Yerel PayTR PDF'leri ve PHP örneklerinin incelenmesiyle hazırlanan devam planı: [PayTR öncesi ödeme altyapısı hazırlık planı](../plans/paytr-payment-foundation-plan.md). Bu plan iade, durum sorgulama, belirsiz sonuçlar ve uygulama aşamalarını ayrıntılandırır.

## Sonuç ve kapsam

Mevcut abonelik satın alma modeli üzerine iFrame API kurulabilir; ancak kredi kartından tahsilat yapan bir entegrasyon henüz bulunmuyor. Öncelik ödeme denemesi modeli, doğrulanmış bildirim işleme ve eşzamanlı işlemlerin korunması olmalıdır.

Projede iki ayrı finansal alan var:

- AidatCep hizmet bedeli: `UserSubscription` siparişi, `SubscriptionItem` hizmet kalemi, `SubscriptionPayment` tahsilatı ve `Subscription` apartmanın kalıcı abonelik kimliği.
- Apartman aidatı: `Payment`, borçlara tahsisler ve apartman kasa/cari hareketleri.

Bu rapor ilk alanı hedefler. Sakinlerin aidat ödemesi amaçlanıyorsa tahsilatın alıcısı, mağaza yapısı ve apartman hesaplarına aktarım ayrıca tasarlanmalıdır. Abonelik entegrasyonu bu ihtiyacı kendiliğinden karşılamaz.

## Kullanılabilecek mevcut parçalar

- `SubscriptionCheckout::openPending()` fiyatları sunucuda hesaplıyor; kalem, kampanya, vergi ve fatura bilgilerini sipariş anındaki değerleriyle saklıyor.
- `SubscriberSubscriptionController::store()` apartman sahipliği ve satış onayını kontrol ediyor; sipariş oluşumu transaction içinde.
- `SubscriptionCheckout::activate()` hizmet dönemini ve apartman kapsamını güncelliyor.
- `LegalConsent` sürümlü sözleşme/onay kaydı ve kabul edilen içerik kopyasını destekliyor.
- Abonelik ve satın alma akışları için mevcut feature testleri var.

## Bulgular

### P0 — Gerçek kart ödemesi akışı eksik

`resources/views/subscriber/subscriptions/create.blade.php:130` kart seçimine izin veriyor. Controller kart siparişini kabul ediyor fakat `SubscriberSubscriptionController.php:178` entegrasyonun daha sonra açılacağını bildiriyor. `receipt.blade.php:126` kart ödemesinin aktif olmadığını söylüyor. Token alma, iFrame gösterimi ve PayTR bildirim endpointi yok; `config/services.php` içinde PayTR yapılandırması bulunmuyor.

Kullanıcı halen ödeme yapamayacağı bekleyen sipariş oluşturabiliyor. Bu siparişin pending kalemi yeni siparişi de engelleyebiliyor. Entegrasyon açılana kadar kullanılabilirlik hem sunucuda hem arayüzde açık bir ayarla yönetilmeli.

### P0 — Tekrarlı ve eşzamanlı bildirimlere uygun kesinleştirme yok

`SubscriptionCheckout.php:151` içindeki `activate()` kendi başına tekrar çağrılmaya karşı korumalı değil. Aktif ücretli kalemlerden en ileri bitiş tarihini alıyor; aynı siparişin tekrar aktivasyonu hizmet süresini yeniden uzatabilir.

`AdminManagerController::approveSubscriptionOrder()` pending kontrolünü transaction öncesinde yapıyor; transaction içinde siparişi yeniden kilitleyip kontrol etmiyor. PayTR bildirimi ve manuel onay aynı anda gelirse çift tahsilat kaydı/aktivasyon riski doğar. `SubscriptionPayment` üzerinde sağlayıcı işlemine özgü unique bağ da bulunmuyor.

Gereken: ortak kesinleştirme servisi; transaction içinde sipariş ve ödeme denemesi kilidi, durumun yeniden kontrolü, veritabanı unique kısıtı ve tek tahsilat/tek aktivasyon. Apartmana ait farklı siparişlerin dönem hesapları da ortak kilit sırasıyla korunmalı.

### P0 — İptal, silme ve geç gelen başarılı ödeme birlikte tasarlanmalı

`SubscriberSubscriptionController.php:250` pending ve dekontsuz siparişi iptal edebiliyor. Kart ödeme denemesi bilgisi olmadığı için açık iFrame dikkate alınamıyor. İptal güncellemeleri ortak transaction/kilit altında değil.

`AdminOrderController::destroy()` ödemesiz siparişi, `destroyPayment()` ödeme kaydını soft delete edebiliyor. Gerçek PayTR tahsilatı soft delete ile iade olmaz. Silinen siparişe bildirim geldiğinde kayıt bulunabilmeli; finansal kanıt ve tekrar işleme koruması korunmalı.

Öneri: açık veya sonucu belirsiz deneme sırasında doğrudan iptal/silme ve manuel onay engellensin. İptal edilmiş siparişe başarı gelirse para hareketi kaydedilsin ve inceleme/iade sürecine alınsın; sessizce yok sayılmasın. İşlem iptali, hizmet iptali ve para iadesi ayrı durumlar olsun.

### P1 — Siparişten ayrı ödeme denemesi modeli gerekli

Bir siparişin farklı denemeleri olabilir. Önerilen `payment_attempts` alanları: sipariş FK, provider, unique alfanümerik `merchant_oid`, beklenen tutar (kuruş), para birimi, test/canlı bilgisi, durum, sonuç/tahsilat tutarı, hata kodu, oluşturulma ve sonuçlanma zamanları. Başarılı `SubscriptionPayment` ile unique ilişki kurulmalı.

Sipariş numarası `SIP-26-...` biçiminde (`SubscriptionCheckout.php:369`); PayTR `merchant_oid` yalnızca alfanümerik kabul ediyor. Sipariş numarasını doğrudan göndermek yerine her deneme için ayrı uygun kimlik üretilmeli.

Token isteği zaman aşımı kesin ödeme başarısızlığı sayılmamalı. Sonucu belirsiz işlem netleştirilmeden yeni aktif deneme açılmamalı. Bir sipariş için tek aktif deneme kuralı atomik uygulanmalı; buna rağmen iki başarılı tahsilat oluşursa ikinci para hareketi incelemeye alınmalı.

### P1 — PayTR alanları ve para hesabı uyarlanmalı

`BillingProfile::rules()` telefon/e-postayı isteğe bağlı bırakıyor; alan uzunlukları da PayTR sınırlarıyla aynı değil. Ödeme öncesinde alıcı adı, e-posta, telefon ve adres için ayrı doğrulama/eşleme gerekli. Kurumsal fatura unvanı ile ödeyen kişinin adı ayrı değerlendirilmeli; bilgi uydurularak gönderilmemeli.

PayTR tutarı kuruş cinsinden integer istiyor. Mevcut checkout toplamları float ile toplanıyor; sağlayıcı sınırında decimal değerden kesin kuruş dönüşümü ve sepet/toplam tutarlılığı kurulmalı. Fiyat ve para birimi tarayıcıdan kabul edilmemeli; siparişin saklanan değerleri kullanılmalı.

Bildirimdeki `total_amount` taksit farkı içerebilir. Sipariş bedeli, karttan çekilen tutar ve ileride banka hesabına yatacak net tutar aynı kavram değildir. Hash için dokümandaki `total_amount` aynen kullanılmalı; tutar eşleştirmesi taksit politikasına göre yapılmalı. Callback içindeki her alanın hash kapsamında olduğu varsayılmamalı.

### P1 — Bildirim erişimi ve yapılandırma eksik

`routes/web.php` içinde PayTR callback yok. `bootstrap/app.php` mevcut haliyle bu endpoint için CSRF istisnası tanımlamıyor. Bildirim POST yolu auth, apartman seçimi, üyelik yönlendirmesi ve CSRF kontrolüne takılmamalı; istisna yalnızca bu yola uygulanmalı. Erişim koruması PayTR HMAC doğrulamasıyla sağlanmalı.

`merchant_id`, `merchant_key`, `merchant_salt` sunucu ortamından/config üzerinden alınmalı. Anahtarlar frontend, repository ve loglara taşınmamalı. Test/canlı ayrımı ve entegrasyon açma ayarı açık olmalı. Token HTTP isteğinde TLS doğrulaması, bağlantı/yanıt timeoutları ve hata yönetimi bulunmalı. Gerçek müşteri IP'si yalnızca güvenilen proxy yapılandırması üzerinden alınmalı.

Callback HTTPS üzerinden dışarıdan erişilebilir olmalı; yerel test için erişilebilir test ortamı gerekir. PayTR panelindeki bildirim URL'si ayrıca tanımlanmalı. Başarılı yönlendirme sayfası callback'ten önce açılabileceği için 'sonuç bekleniyor' durumunu desteklemeli.

### P1 — Onay kaydı ve testlerde uyumsuzluk

`SubscriberSubscriptionController.php:176` satış onayını sipariş transaction'ı tamamlandıktan sonra kaydediyor. Bu kayıt başarısız olursa onay kaydı eksik sipariş kalabilir. Yeni akışta onay ve sipariş aynı atomik işlemde oluşmalı; ödeme denemesi açılmadan gerekli kayıtlar doğrulanmalı.

`tests/Feature/Subscriber/SubscriptionOrderTest.php:112` kart seçeneğinin disabled olmasını bekliyor; mevcut Blade kart seçeneği enabled. Bu statik bir test/arayüz uyumsuzluğu. PayTR açma ayarına göre beklenen davranış netleştirilmeli.

## Önerilen akış

1. Yetkili kullanıcı siparişi oluşturur; fiyat/fatura/onay kayıtları atomik saklanır.
2. Sunucu siparişi kilitleyip ödenebilirliğini kontrol eder ve tek aktif deneme oluşturur.
3. DB transaction dışında PayTR token isteği yapılır; sonuç denemeye güvenli biçimde bağlanır. Token alma tahsilat değildir.
4. Kullanıcı PayTR iFrame içinde kart bilgisini girer.
5. Callback alanları ve HMAC `hash_equals` ile doğrulanır. Ardından transaction ve kilitler altında tutar, ortam, sipariş/deneme durumları kontrol edilir.
6. Başarı halinde tek tahsilat kaydı ve tek aktivasyon yapılır; commit tamamlandıktan sonra düz metin `OK` döner. Geçerli tekrarlı bildirim aynı finansal etkiyi oluşturmaz ve `OK` alır. Geçersiz hash reddedilir; DB hatası başarı olarak onaylanmaz.
7. Sonuç ekranı yalnızca sunucudaki durumu gösterir. Eksik/geciken bildirimler durum sorgusu ve yönetici mutabakatıyla izlenir.

## Uygulama sırası ve kabul koşulları

1. Kapsamı AidatCep abonelik bedeli olarak netleştir; ilk sürüm için tek çekim ve her yenilemede kullanıcının ödeme başlatmasını öneriyorum. iFrame seçimi kendiliğinden otomatik kart çekimi sağlamaz.
2. Ödeme denemesi tablosu, unique kısıtlar ve ortak kesinleştirme/iptal korumaları.
3. PayTR config/client, token endpointi, iFrame ve yetkili sonuç sayfaları.
4. Callback, hata/tekrar işleme ve manuel onay/silme uyarlamaları.
5. Yönetici işlem görünürlüğü, sonucu belirsiz işlemler için mutabakat ve iade prosedürü.
6. İzole testler: bozuk hash, tekrarlı/eşzamanlı callback, tutar uyumsuzluğu, timeout, callback öncesi dönüş, kullanıcı dönmeden başarı, başarısız denemeden sonra yeniden ödeme, iptal/silme/manuel onay yarışı, test/canlı karışması, ikinci başarılı tahsilat, transaction rollback. Eşzamanlılık üretimle aynı DB motorunda ayrıca doğrulanmalı.
7. PayTR test ödemesi, panelde başarılı bildirim kontrolü ve canlı mod onayı. HTTPS, ödeme erişilebilirliği ve yayınlanmış satış metinlerinin gerçek içeriği canlı ortamda ayrıca kontrol edilmeli.

## Çalıştırılan doğrulama

`php vendor/phpunit/phpunit/phpunit --filter "SubscriptionOrderTest|PurchaseItemFlowTest"` çalıştırıldı. Sonuç: 40 test, 38 başarılı, 2 başarısız, 315 assertion.

- `test_subscriber_can_create_havale_order_without_reference`: beklenen 1500, gerçekleşen 1800.
- `test_subscriber_can_create_credit_card_pending_order`: beklenen 150, gerçekleşen 180.

Her iki fark da yüzde 20 artışa karşılık geliyor; vergi dahil fiyat beklentileriyle ilişkili olabilir. Kök neden bu incelemede kesinleştirilmedi. PayTR tutar eşleştirmesine geçmeden fiyat/vergi beklentileri netleştirilmeli. Yukarıda belirtilen disabled seçici uyumsuzluğu, bu çalıştırmada hata olarak raporlanmadı; ayrı bir statik tespittir. Uygulama kodu ve veritabanı şeması değiştirilmedi.

## Resmi kaynaklar

- Token, zorunlu alanlar, sipariş no kısıtı ve yönlendirme/bildirim ayrımı: https://dev.paytr.com/iframe-api/iframe-api-1-adim
- Bildirim hash'i, tekrar bildirimler, `OK` yanıtı ve taksitli toplam: https://dev.paytr.com/iframe-api/iframe-api-2-adim
- Panel bilgileri, test ve canlı moda geçiş: https://dev.paytr.com/home/iframe-api-entegrasyon-sureci

Bu rapordaki tablo/servis adları ve durum tasarımı proje için öneridir; PayTR'ın hazır Laravel bileşenleri değildir.

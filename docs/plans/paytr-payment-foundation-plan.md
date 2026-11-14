# PayTR öncesi ödeme altyapısı hazırlık planı

Tarih: 14 Kasım 2026. Durum: planlama; uygulama kodu veya veritabanı şeması değiştirilmedi.

## Amaç ve sınırlar

AidatCep abonelik satışını; sipariş, ödeme denemesi, gerçekleşmiş tahsilat, iade ve hizmet hakkını ayrı takip eden yapıya taşımak. PayTR bu yapının ödeme sağlayıcısı olacak. Mevcut Laravel uygulamasında sınırlı servisler ve ek tablolar yeterli; ayrı mikroservis veya kapsamlı bir muhasebe sistemi kurmak gerekmiyor.

Çalışma varsayımı AidatCep hizmet bedelinin tahsil edilmesi. Apartman sakinlerinin aidatlarının kartla tahsili bu planın kapsamına dahil değil. İkinci amaç varsa tahsilat alıcısı ve PayTR mağaza/para aktarımı modeli ayrıca netleştirilmeli.

İlk sürüm önerisi: TRY, tek çekim, kullanıcının başlattığı aylık/yıllık satın alma veya yenileme. Kart saklama ve otomatik dönemsel çekim sonraki ve ayrı kapsamdır. Bunlar henüz kullanıcı tarafından kararlaştırılmış ürün kuralları değildir.

## İncelenen yerel kaynaklar

`docs/PayTR_IFrame_API` altında Türkçe belgelerin tamamı ve ilgili PHP örnekleri okundu:

- `PayTR IFrame API/iFrame API - Türkçe/Paytr Beni Oku.pdf`
- `PayTR IFrame API/iFrame API - Türkçe/1.ADIM/1. ADIM.pdf` ve `iframe_ornek.php`
- `PayTR IFrame API/iFrame API - Türkçe/2. ADIM/2. ADIM.pdf` ve `bildirim_url_ornek.php`
- `PayTR İade API/Türkçe/PayTR İade API.pdf` ve `iade_ornek.php`
- `PayTR Mağaza Durum Sorgulama/Türkçe/PayTR Durum Sorgu API.pdf` ve `durum_sorgu_ornek.php`

İngilizce çeviriler ve diğer dil örnekleri envanterde görüldü; tamamı ayrıca okunmadı. PDF'ler metin olarak incelendi. Durum sorgusu PDF'si 07.03.2021 tarihli v1.0; güncel resmi web belgeleriyle karşılaştırıldı. Canlı API'ye istek yapılmadı, gerçek ödeme/iade başlatılmadı.

## İki adımlı web anlatımıyla karşılaştırma

14 Kasım 2026'da resmi iFrame 1. ve 2. adım sayfaları yeniden kontrol edildi. Ana protokol yerel PDF'lerle uyumlu: ilk adım ödeme formunu açmak, ikinci adım sunucudan gelen kesin sonucu işlemek içindir. İki farklı müşteri sayfası veya iki ödeme anlamına gelmez. Bildirim yolu müşteri oturumu kullanmaz; başarılı/başarısız dönüş yolları yalnızca bilgilendirir. Bu ayrım mevcut planı değiştirmiyor.

Birebir aynı doküman oldukları varsayılmamalı: güncel ilk adım tablosunda merchant_id string olarak, yerel PDF'de integer olarak yazılmış; web e-posta kısıtı Türkçe karakter bulunmamasını ayrıca belirtiyor. Güncel ikinci adım tablosunun Token sütunuyla örnek hash kapsamı da tam örtüşmüyor: örnekler HMAC girdisi olarak yalnızca merchant_oid + merchant_salt + status + total_amount kullanıyor. Bu nedenle bütün callback alanları imzalı kabul edilmeyecek; farklı hash formülü uydurulmayacak. Belirsiz kısımlar uygulama sırasında PayTR testi/destek teyidiyle doğrulanacak.

## Belgelerin tasarımı etkileyen sonuçları

| Konu | Belgede görülen davranış | Projedeki karşılığı |
| --- | --- | --- |
| Ödeme açılışı | Sunucu token alır, müşteri iFrame kullanır | Token alma tahsilat sayılmayacak |
| Kesin sonuç | Bildirim ayrı POST isteğidir; yönlendirme kesin sonuç değildir | Sonuç ekranı beklemeyi destekleyecek |
| Tekrar bildirim | Aynı işlem tekrar bildirilebilir | Tek tahsilat ve tek hizmet aktivasyonu zorunlu |
| İşlem kimliği | `merchant_oid` en fazla 64 karakter ve alfanümerik | `SIP-...` yerine ayrı deneme kimliği |
| Ödeme tutarı | Token ve callback tutarı kuruş integer | Sağlayıcıya özgü kesin dönüşüm |
| İade tutarı | `return_amount` noktalı tutar; örnek `11.97` | Ödeme isteğindeki ölçek iadeye kopyalanmayacak |
| İade referansı | İsteğe bağlı alfanümerik referans sorguda dönebilir | Her iade için kalıcı referans kullanılacak |
| Durum sorgusu | Tutarlar ve iadeler döner; başarılı ödeme bulunamadığında hata olabilir | Sorgu hatası kesin başarısızlık kabul edilmeyecek |

`reference_no` için sunucunun aynı isteği tekilleştirdiğine dair garanti belgelerde yok. Referans korelasyon içindir; idempotency garantisi sayılmayacak. İade timeout alırsa körlemesine tekrar gönderilmeyecek.

Eski PHP örneklerinde `$result[status]` gibi tırnaksız anahtarlar var; modern PHP'ye doğrudan kopyalanamaz. Örneklerdeki proxy IP başlıklarını koşulsuz okuma veya TLS doğrulamasını kapatma önerileri uygulamaya taşınmayacak.

## Hedef veri yapısı

| Kayıt | Sorumluluk | Yapılacak değişiklik |
| --- | --- | --- |
| `UserSubscription` | Ticari sipariş ve fiyat/fatura kopyası | Mevcut model korunacak; ödeme durumu hizmet durumundan ayrı okunacak |
| `SubscriptionItem` | Satın alınan hizmet dönemi | Ödeme sonucuyla en fazla bir defa aktifleştirilecek |
| `Subscription` | Apartmanın kalıcı abonelik kimliği | Ödeme denemesi yerine kullanılmayacak |
| Yeni `PaymentAttempt` | PayTR işlem denemesi | Sipariş FK, provider, mağaza/ortam kimliği, unique merchant_oid, tutar/para birimi, durum, zamanlar, hata ve sonuç bilgisi |
| `SubscriptionPayment` | Gerçekleşmiş tahsilat | Nullable ve unique payment_attempt_id; manuel tahsilatlar için mevcut yol korunacak |
| Yeni `PaymentRefund` | Tahsilata bağlı iade talebi ve sonucu | Unique referans, tutar, durum, talep eden, sebep, zamanlar ve sağlayıcı sonucu |
| Yeni `PaymentEvent` | Bildirim, sorgu ve durum değişikliği geçmişi | Kaynak, ilişki, zaman, doğrulama/işleme sonucu; seçilmiş ve maskelenmiş alanlar |

Tablo adları öneridir. Fiziksel FK adları mevcut `subscription_id` kullanımının ticari siparişi gösterdiği dikkate alınarak açık seçilmeli. Beklenen ve tahsil edilen tutarlar ayrı saklanmalı. Sağlayıcı komisyonu ve net tutar, müşteri borcunu azaltan tahsilat tutarıyla karıştırılmamalı.

Yeni para alanlarında kuruş integer kullanılmalı. Mevcut decimal alanlar korunarak sipariş kopyasından kesin dönüşüm yapılmalı. PayTR iadeye giderken noktalı iki basamaklı string üretilmeli. Güncel fiyat/vergi ayarları eski siparişe tekrar uygulanmamalı.

Durumlar önerisi:

- Deneme: `created`, `token_ready`, `pending`, `succeeded`, `failed`, `unknown`.
- İade: `requested`, `processing`, `succeeded`, `failed`, `unknown`.
- İnceleme ihtiyacı ayrı alan: nedeni ve çözülme bilgisi. Gerçekleşen tahsilat, hizmet verilemedi diye başarısız ödemeye dönüştürülmeyecek.

İade tutarı sıfırdan büyük olmalı; başarılı iadeler ve halen sonucu beklenen/belirsiz iadeler toplamı tahsil edilen iade edilebilir bakiyeyi aşmamalı. İadeler tek tahsilat üzerinden kilitle korunmalı. Başarılı veya belirsiz iadeyi silerek limit açılmamalı.

## Değişmez işlem kuralları

1. Tek PayTR işlemi en fazla bir `SubscriptionPayment` oluşturur; hizmet hakkı en fazla bir defa verilir. Unique kısıtlar soft delete sonrası da geçerli kalır.
2. Aynı siparişte ödeme başlatma iki sekmeden de gelse tek açık deneme oluşturur. Açık/belirsiz işlem sürerken yeni deneme ve manuel onay engellenir. Gerçekleşen ikinci tahsilat olursa ayrıca kaydedilip incelemeye alınır.
3. HMAC, sipariş/deneme/ortam ve tutar doğrulanmadan aktivasyon yapılmaz. Callback'teki bütün alanların imzalı olduğu varsayılmaz.
4. Callback ve durum sorgusundan ulaşılan doğrulanmış sonuçlar aynı kesinleştirme servisini kullanır. Sorgunun `status=success` alanı tek başına aktivasyon koşulu değildir; yanıt şeması, işlem kimliği, tutar, para birimi ve ortam birlikte kontrol edilir.
5. Kesinleştirmede DB transaction, tutarlı kilit sırası ve transaction içi durum kontrolü bulunur. Deneme, sipariş ve apartman hizmet dönemi yarışları kapsanır. Dış HTTP isteği boyunca DB kilidi tutulmaz.
6. Bildirim sonucu DB'ye kalıcı yazıldıktan sonra `OK` döner. İşlenmiş geçerli tekrar da `OK` alır; DB hatası başarı cevabıyla kapatılmaz.
7. İptal ve silme aynı kilit/durum kurallarına uyar. Geç başarı bildirimi veya silinmiş kayıt finansal kanıtıyla bulunabilir; para hareketi sessizce atlanmaz.
8. Kart tahsilatını silmek veya aboneliği kapatmak para iadesi değildir. İade ve hizmet sonlandırma açık ve ayrı işlemlerdir.
9. Yerel timeout, 'başarılı ödeme bulunamadı' yanıtı ve tarayıcının kapanması kesin başarısızlık oluşturmaz. Aynı denemeyi ne zaman yeniden açabileceğimiz test ortamı/PayTR teyidiyle tanımlanır.
10. İade API timeout sonrasında referans ve sorgu kayıtlarıyla mutabakat yapılır. İadenin sorguda görünmemesi tek başına güvenli yeniden gönderim kanıtı sayılmaz; belirsiz işlem incelemede tutulur.

## Aşamalı uygulama

### Aşama 0 — Ürün kuralları ve fiyat doğruluğu

- Abonelik bedeli kapsamı, TRY/tek çekim ve kullanıcı başlatmalı yenileme önerisini ürün kararı olarak kesinleştir.
- Tam/kısmi iadenin hizmet süresine etkisini tanımla. Kısmi iade otomatik olarak süreyi yarıya indirmesin; açık politika olmadan hizmet değiştirilmesin.
- Eski kart pending siparişleri için sahiplik, fatura/onay yeterliliği ve fiyat geçerliliği kontrolü tanımla. Gerekirse iptal edip yeni sipariş aç; eski sözleşme/onay kopyasını sessizce değiştirme.
- `SubscriptionTax` varsayılan yüzde 20 ve `PriceQuote` vergi ekleme davranışı, önceki testlerdeki 150→180 ve 1500→1800 farklarını açıklayabilecek kod yoludur. Test fixture'ları ve brüt/net beklentileri doğrulansın; test geçsin diye vergi kaldırılmasın.

Çıkış koşulu: ödeme için kullanılacak nihai fiyat ve hizmet/iade kuralları açık; ilgili fiyat testleri tutarlı.

### Aşama 1 — PayTR olmadan finansal temel

- Ek tablolar ve nullable ilişkiler; eski havale/nakit ödemelerini uydurma PayTR denemelerine çevirmeden uyumluluğu koru.
- Ortak `FinalizeSubscriptionPayment` servisi: tahsilatı oluşturma, pending kontrolü ve `SubscriptionCheckout::activate()` çağrısı tek transaction içinde.
- Manuel onay, iptal, ret ve silme yollarını aynı kurallara geçir. Mevcut controller'ın transaction öncesi pending kontrolü yeterli değil.
- Sipariş oluşturma ile `LegalConsent::recordSale()` kayıtlarını atomik hale getir; onayı eksik siparişe deneme açma.
- Para dönüşümü, sağlayıcı işlemine unique bağ ve denetim geçmişi.

Çıkış koşulu: PayTR çağırmadan çift onay, iptal/onay yarışı ve rollback testleri geçer; havale/nakit akışları çalışır.

### Aşama 2 — Ödeme başlatma ve bildirim

- `config/services.php`, `.env.example`: gizli değer içermeyen PayTR alanları; kapalı entegrasyon ayarı ve açık test/canlı ayrımı.
- Sınırlı `PaytrClient`: token, sorgu, iade operasyonları; her operasyon için ayrı imza/tutar eşlemesi. Genel bir çok sağlayıcılı framework kurulmasın.
- Ödeme öncesi kişi/e-posta/telefon/adres doğrulaması ve siparişten sepet üretimi.
- Yetkili ödeme başlatma endpointi, iFrame ve sonucu bekleyen/başarılı/başarısız ekranları.
- Oturum istemeyen, yalnızca kendi yolu için CSRF istisnalı callback; HMAC ve ortak kesinleştirme.
- Token isteği sırasında callback gelmesi gibi ters sıralarda sonuç geriye alınmasın: başarılı deneme daha sonra token yanıtıyla pending yapılmasın.

Çıkış koşulu: PayTR test modunda gerçek uçtan uca akış; kullanıcı dönmese de tek tahsilat/aktivasyon; hatalı hash reddi.

### Aşama 3 — Durum sorgulama ve operasyon görünürlüğü

- Mevcut `routes/console.php` schedule yapısına, belirli aralıkla bekleyen/belirsiz denemeleri kontrol eden görev ekle. Aynı görev çakışmasın; işlem başına tekrar sıklığı ve toplam deneme sayısı sınırlı olsun.
- Başarılı ödeme bulunamamasını hatalı kimlik, henüz sonuçlanmama ve kesin başarısızlıkla karıştırma. Geçici hataları sınırlı gecikmeyle yeniden sorgula; çözülemeyeni incelemeye çıkar.
- Yönetici sipariş detayında denemeler, merchant_oid, ortam, beklenen/çekilen tutar, son bildirim/sorgu ve inceleme nedeni gösterilsin.
- Yönetici sorgusu yetkili, sınırlı ve kayıtlı olsun; para tahsil/iadelerini tetiklemesin.

Çıkış koşulu: callback gecikmesi, callback/sorgu yarışı ve belirsiz sonucun incelemeye taşınması test edilir. Cron/worker'ın canlıda çalıştığı ayrıca doğrulanır.

### Aşama 4 — İade ve finansal kayıt koruması

- Yetkili yönetici iade başlatabilir; tekrar istek aynı yerel iade kaydını kullanır. Kullanıcı iade sebebi ve tutarını görür; teknik provider alanlarını doldurmaz.
- Tam/kısmi iade bakiyesi kilit altında kontrol edilir, benzersiz referans saklanır. PayTR HTTP çağrısı transaction dışında yapılır; sonuç ikinci transaction ile kaydedilir.
- Timeout veya yanıt bozukluğunda durum unknown olur; otomatik yeniden iade isteği gönderilmez.
- PayTR panelinden dışarıda başlatılmış iadeler de sorgu yoluyla ayrı kaynak bilgisiyle tanınır; çift yazılmaz. Eşleştirilemeyen iade otomatik tahmin edilmez.
- Gerçek tahsilat/iade geçmişi silme yerine düzeltme ve incelemeyle korunur. Havale/nakit geçmişi için mevcut silme davranışı ayrıca değerlendirilir; PayTR koruması sessizce bütün işlemlere yayılmaz.

Çıkış koşulu: iki eşzamanlı kısmi iade toplam limiti aşamaz; iade timeout yeniden gönderim üretmez; dış iade mutabakatı çift kayıt oluşturmaz.

### Aşama 5 — Kontrollü canlıya geçiş

- Test/canlı kimlikleri ve finansal toplamlar ayrılır. Kart numarası/CVV saklanmaz; token ve anahtarlar loglanmaz.
- HTTPS bildirim yolu, güvenilen proxy/IP ayarı, dış erişim, uygulama yönlendirmeleri ve gerekli iframe/script izinleri doğrulanır.
- Sözleşme metni, satış onayları, fatura verisi ve iade/hizmet politikası gerçek yayın içeriği üzerinden kontrol edilir.
- Yetkili kişinin PayTR panelindeki test/canlı geçiş adımları tamamlanır. İlk canlı işlem ve gerekirse kontrollü iade mutabakatı ayrıca yetkilendirilir.
- Geri dönüş: yeni ödeme başlatma kapatılır; mevcut callback ve mutabakat çalışmaya devam eder. Açık işlemler varken tablolar/drop migration geri alınmaz.

## Özellikle sınanacak senaryolar

| Senaryo | Beklenen sonuç |
| --- | --- |
| Aynı callback seri veya eşzamanlı tekrarlanır | Tek tahsilat, tek dönem uzaması |
| Manuel onayla callback aynı anda gelir | Ortak kilit/durum kuralı; çift kayıt yok |
| Müşteri başarı sayfasına callback'ten önce döner | Sonuç bekleniyor; erken hizmet yok |
| Kullanıcı hiç geri dönmez | Callback ile tamamlanır |
| Başarılı denemeye eski başarısız yanıt ulaşır | Başarı geriye alınmaz |
| Geç başarı iptal/silmeden sonra gelir | Tahsilat kanıtı korunur, inceleme açılır |
| İki sekme ödeme başlatır | Tek açık deneme |
| Sonuç belirsiz veya sorgu 004 döner | Kesin failed yapılmaz; kontrol/inceleme |
| Ödeme ve iade tutar ölçekleri karışır | Dönüşüm testleri hatayı yakalar |
| Callback/sorgu eşzamanlı başarı görür | Tek kesinleştirme |
| Aynı siparişe iki farklı başarılı tahsilat ulaşır | Her para hareketi kaydedilir; hizmet bir kez verilir |
| İki yönetici kısmi iade başlatır | Toplam başarılı ve rezerve iade bakiyesi aşılmaz |
| İade cevabı kaybolur | Yeni iade otomatik gönderilmez |
| DB commit başarısız olur | Başarı onayı verilmez; tekrar güvenle işlenir |
| Ödeme başlatma ayarı kapatılır | Yeni deneme durur; eski bildirimler işlenir |

Eşzamanlılık SQLite ile kanıtlanamaz; üretim DB motorunda ayrı test gerekir. Önceki incelemede çalıştırılan 40 testin sonucu 38 başarılı/2 fiyat beklentisi hatasıydı. Bu belge hazırlanırken uygulama kodu değişmediği için testler tekrar çalıştırılmadı.

## Uygulamadan önce netleşmesi gerekenler

1. Tahsilat yalnızca AidatCep abonelik bedeli mi?
2. İlk sürüm tek çekim TRY olabilir mi; yıllık plan da tek çekim mi?
3. Tam ve kısmi iade hangi koşullarda yapılacak, hizmete etkisi ne olacak?
4. Mevcut kart pending siparişlerinin fiyatları ne kadar süre geçerli olacak?

Bunlar hazırlık incelemesini engellemez; ilgili ödeme/iade davranışı yazılmadan karara bağlanmalıdır. İlk uygulanacak iş Aşama 0 ve Aşama 1'dir.

## İlişkili inceleme ve güncel kaynaklar

- Mevcut kod bulguları: [PayTR hazırlık incelemesi](../analysis/paytr-iframe-readiness-2026-11-14.md)
- Token/iFrame: https://dev.paytr.com/iframe-api/iframe-api-1-adim
- Bildirim: https://dev.paytr.com/iframe-api/iframe-api-2-adim
- Durum sorgulama: https://dev.paytr.com/durum-sorgu
- İade: https://dev.paytr.com/iade-api

API'nin kabul ettiği davranışlar resmi belgelerden alınmıştır; tablo/servis adları, durumlar ve uygulama aşamaları proje için tasarım önerisidir. Belgelerde garanti edilmeyen gecikme/yeniden deneme ve iade tekilleştirme davranışları PayTR test ortamı veya destek teyidi olmadan varsayılmayacaktır.

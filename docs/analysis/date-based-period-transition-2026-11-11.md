# Tarih esaslı kayıt ve raporlama geçişi

Tarih: 11 Kasım 2026. Durum: uygulandı; yerel MySQL veritabanında migration tamamlandı. Canlı ortama uygulanmadı. Aşağıdaki uygulama sırası başlangıç planını belgeler.

## Uygulanan geçiş

Kayıt formlarından bağımsız dönem alanları kaldırıldı. Giderlerde gider tarihi, borçlarda kullanıcı oluşturulma tarihi esas alınıyor. Raporlar, kiracı uygunluğu, eksik aidat ve mükerrer üretim kontrolleri aynı tarih kuralını kullanıyor. Vade tarihi ay belirlemiyor. Raporlardaki ay aralığı seçicileri korunuyor.

`2026_11_11_000002_use_effective_dates_instead_of_record_periods` migration'ı `expenses.period_month`, `dues.period` ve `due_batches.period` sütunlarını kaldırır; borç gruplarında `assessment_date` kullanır. Eksik esas tarih veya eski dönemle çelişen tarih varsa işlem durur. Planların üretim/atlanan ay anahtarları korunmuştur.

Yerel geçiş öncesi yedek: `storage/app/private/date-period-transition-backup-2026-11-11.json`. Bu dosya sürüm kontrolüne alınmaz. Yedek alındıktan sonra yerelde yeni ödeme/tahsisler ve gider düzenlemeleri bulunduğundan eski parmak izleriyle güncel verinin birebir eşitliği doğrulanamaz. Bu kullanıcı işlemleri geri alınmadı. Migration mali kayıt tutarlarını veya ödeme tahsislerini güncellemez.

Yeni regresyon testleri tarih önceliğini, şema geçişini, çelişkili veride durmayı ve geri dönüş/tekrar çalıştırmayı kapsıyor. İlgili rapor, borç, gider, kiracı seçimi ve plan testleri de güncellendi. JavaScript dönem seçici testleri ve üretim derlemesi başarılı.

Son toplu doğrulama: 247 test, 245 başarılı, 2 önceden tanımlı atlanan test; 2.723 assertion. Tüm test deposu yerine değişiklikten etkilenen 18 test dosyası çalıştırıldı.

## Kararlar

- Giderin ait olduğu ay, `expenses.expense_date` üzerinden hesaplanacak.
- Aidat ve Kat Maliki Payı borcunun ait olduğu ay, kullanıcının girdiği `dues.created_at_manual` üzerinden hesaplanacak.
- `created_at`, kaydın sisteme gerçekten eklendiği zamanı koruyacak. Geriye tarihli girişte bu alan değiştirilmeyecek.
- `due_date`, son ödeme/vade tarihi olarak kalacak. Ay belirlemede kullanılmayacak.
- Ödeme ve gelirlerin tarihi `payments.payment_date` olarak kalacak.
- Kayıt giriş ve düzenleme formlarında ayrı dönem seçimi veya gizli dönem gönderimi olmayacak.
- Raporlardaki beğenilen ay aralığı popup'ı korunacak: seçimi ilgili esas tarihin başlangıç ve bitiş sınırlarına dönüştüren bir filtre olacak.
- Gider İlişkisi sınıflandırması bu çalışmadan bağımsız olarak korunacak.

## Güncel yerel canlı kopyasında salt okunur başlangıç kontrolü

MySQL READ ONLY transaction kullanıldı ve rollback ile kapatıldı. Sayılar kontrol anındaki yerel kopyaya aittir; canlıya bağlanılmadı.

| Kontrol | Sonuç |
|---|---:|
| Silinmemiş gider | 320 |
| Gider tarihi eksik aktif gider | 0 |
| Dönemi gider tarihinin ayından farklı aktif gider | 0 |
| Silinmişler dahil gider / gider tarihi eksik | 323 / 0 |
| Silinmemiş borç | 1.126 |
| Kullanıcı oluşturulma tarihi eksik aktif borç | 0 |
| Ayrı dönem alanı boş aktif borç | 1.055 |
| Dönemi dolu olup esas tarihin ayından farklı aktif borç | 0 |
| Dönemi dolu olup vade ayından farklı aktif borç | 1 |
| Silinmişler dahil borç / iki oluşturulma tarihi de eksik | 1.154 / 0 |

Bu sayılar eski raporların aynı ayı zaten doğru kullandığı anlamına gelmez: bazı raporlar boş borç dönemini son ödeme tarihinden tamamlıyor. Yeni tarih kuralının oluşturacağı aylık farklar ayrıca karşılaştırılacak.

## Uygulama sırası

1. Tarih kuralları için ortak sorgu/erişim yöntemleri oluşturulacak. Tarih aralıkları bütün günü kapsayacak; ay sonu ve artık yıl sınırları korunacak. Sessizce vade tarihine veya bugüne dönülmeyecek.
2. Gider ve borç oluşturma/düzenleme formlarından dönem alanları ve bunları değiştiren JavaScript kaldırılacak. Tarih değişikliği ilgili ayı yeniden belirleyecek; vade değişikliği ilgili ayı etkilemeyecek. Tarih doğrulamaları sunucuda yapılacak.
3. Gider listesi, gider raporu ve giderden borçlandırma kaynak sorguları gider tarihine geçirilecek. Kaynak gider seçimi bir tarih filtresi olarak kalabilecek; gidere ikinci bir dönem atamayacak.
4. Aidat listesi, aylık borç tablosu, borç/tahsilat raporları, yıllık raporlar ve dışa aktarmalar kullanıcı oluşturulma tarihine geçirilecek. Açıklama veya referans metninden tarih çıkarılmayacak.
5. Kiracı seçimi, eksik aidat tespiti ve tekrar üretim kontrolleri esas tarihin ayından hesaplanacak. Mevcut aynı-ay kiracı çakışması ve borç türü/kategori/plan kapsamı kuralları korunacak.
6. Aidat, gider ve Kat Maliki Payı planları kontrol edilecek. Plan üretim anahtarları ve atlanan aylar planlama mekanizmasıdır; körlemesine kaldırılmayacak. Üretilen kaydın esas tarihi doğru plan ayına yazılacak. Gerçek sistem kayıt zamanı ayrıca korunacak.
7. İçe aktarma, hesap ekstreleri, otomatik açıklamalar, toplu borçlandırma grupları ve aktarım işlemleri aynı kurala geçirilecek. Gruplarda yalnız ay saklayan alanlar yerine gerektiğinde işlemin esas tarihi kullanılacak.
8. Yeni kodun eski dönem sütunlarına bağımlılığı kalmadığı doğrulanacak. Sonrasında kontrollü migration ile gereksiz mali kayıt dönem sütunları kaldırılacak. Önceden uygulanmış migration dosyaları yeniden yazılmayacak.

## Veri geçişi

- Kontrol edilen güncel kopyada eksik esas tarih bulunmadığından mevcut parasal veriyi veya tarihleri topluca değiştirmek gerekmiyor.
- Canlıya uygulama öncesinde aynı kontroller migration/ön kontrol ile tekrar çalıştırılacak. Yeni eksik veya çelişkili kayıt çıkarsa sessizce tarih uydurulmayacak.
- Geçmiş borç grupları ve plan/taksit kayıtlarının tarih dönüşümü ayrıca değerlendirilecek; grubun gerçek sistem kayıt ayı geçmiş borcun ait olduğu ay yerine kullanılmayacak.
- Tutarlar, ödenen/kalan bakiyeler, ödeme tahsisleri, hesap kimlikleri ve Gider İlişkisi sınıflandırmaları değiştirilmeyecek.
- Şema kaldırma adımı veri yedeği ve doğrulama sonrasında uygulanacak. Silinen bağımsız dönemlerin tamamı yalnız bir down migration ile geri getirilemeyeceği için geri dönüş veri yedeğini de gerektirir.

## Kabul ölçütleri

- Kullanıcı kayıt oluştururken/düzenlerken ayrı dönem seçmez; tarih yeterlidir.
- Eylül tarihli borç bugün girilse bile Eylül'de görünür. Bugünün sistem kayıt zamanı korunur.
- Eylül tarihli giderin vadesi Ekim'e taşınınca gider Eylül'de kalır.
- Ayın 15'inde giren tek uygun kiracıyı seçme ve aynı ayda iki uygun kiracı varsa işlemi durdurma davranışı korunur; bu çalışma gün bazlı paylaştırma eklemez.
- Planın ikinci kez çalıştırılması mükerrer borç/gider oluşturmaz; eksik hesapları tamamlamaya devam eder.
- Gider, borç, rapor, PDF ve Excel aynı tarih kuralını kullanır.
- Genel tutarlar, bakiyeler ve tahsisler önce/sonra karşılaştırılır. Aylık sınıflandırma farkları eski vade fallback'i gibi belirli nedenlerle açıklanır.
- Başka apartman verileri dahil edilmez; silinmiş kayıtların kapsamı korunur.

Abonelikteki aylık/yıllık süre seçimi gibi mali kayıt ayıyla ilgisiz `period` alanları bu kapsamın dışındadır.

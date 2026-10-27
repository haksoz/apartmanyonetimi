# Due oluşturma kuralı geliştirmesi

## Davranış

- `dues.target_audience`: nullable string, varsayılan ve geçmiş veri güncellemesi yok.
- Toplu borçlar: uygulanan batch kuralı; aidat planı: tenant_priority; diğer borçlandırma planları: uygulanan batch kuralı.
- Birebir manuel ve içe aktarılan borçlar: null. Batch, hesap türü veya kategori üzerinden geçmiş kayıt çıkarımı yapılmaz.
- Model oluşturulurken değer doğrulanır; model üzerinden sonraki kural değişiklikleri reddedilir. Düzenleme, aktarım ve ödeme akışları kuralı değiştirmez.
- Detay ekranında Oluşturma Kuralı gösterilir.

## Bu geliştirmedeki dosyalar

- `database/migrations/2026_10_27_000001_add_target_audience_to_dues.php`
- `app/Models/Due.php`
- `app/Http/Controllers/DueController.php`
- `app/Http/Controllers/DuePlanController.php`
- `app/Http/Controllers/AccountController.php`
- `app/Support/AssessmentPlanGenerator.php`
- `resources/views/dues/show.blade.php`
- `tests/Feature/DueTargetAudienceTest.php`
- `tests/Feature/FinancialPlanningTest.php`
- `tests/Feature/ImportDueTypeTest.php`
- `docs/analysis/akbey-due-target-audience.sql`

Önceki görevden kalan ReportController.php ve MonthlyBoardReportTest.php değişikliklerine bu geliştirmede dokunulmadı.

## Doğrulama

- SQLite :memory: kullanıldı; APP_ENV=testing, DB_CONNECTION=sqlite ve DB_URL boş olarak zorlandı. Canlı veritabanına bağlanılmadı.
- İlk grup: 44 test, 782 doğrulama başarılı.
- Son odaklı regresyon: 114 test; 112 başarılı, 2 atlandı; 1321 doğrulama; hata yok.
- Yeni 6 test: toplu tenant_priority / owner_only ve malik fallback; birebir null; düzenleme, aktarım, kısmi/tam ödeme; eski satırları koruyan migration; geçersiz değer; model değişmezliği.
- FinancialPlanningTest içinde aidat otomasyonu, diğer planların her iki hedefleme kuralı ve plan değişikliği/pasifleştirme sonrası koruma doğrulandı.
- ImportDueTypeTest içinde gerçek içe aktarma yolunun null bırakması doğrulandı.

Odaklı pakette çalıştırılan sınıflar:

- Tests\Feature\DueTargetAudienceTest: 6 test, 0 başarısız, 0 hata, 0 atlandı.
- Tests\Feature\DuePageTest: 35 test, 0 başarısız, 0 hata, 2 atlandı.
- Tests\Feature\DueFilterTest: 1 test, 0 başarısız, 0 hata, 0 atlandı.
- Tests\Feature\FinancialPlanningTest: 40 test, 0 başarısız, 0 hata, 0 atlandı.
- Tests\Feature\ImportDueTypeTest: 1 test, 0 başarısız, 0 hata, 0 atlandı.
- Tests\Feature\PaymentPageTest: 3 test, 0 başarısız, 0 hata, 0 atlandı.
- Tests\Feature\PaymentMultiAllocateTest: 3 test, 0 başarısız, 0 hata, 0 atlandı.
- Tests\Feature\ExpenseFullPaymentTest: 18 test, 0 başarısız, 0 hata, 0 atlandı.
- Tests\Feature\BusinessRules\AccountTransactionDirectionTest: 7 test, 0 başarısız, 0 hata, 0 atlandı.

Geniş paket: 552 test, 538 başarılı, 12 başarısız, 2 atlandı; 4894 doğrulama. Bu nedenle bütün test paketinin temiz olduğu iddia edilmez. Borçlandırma/ödeme/muhasebe testleri ayrı çalıştırıldığında yukarıdaki odaklı sonuç temizdir. XML ile yapılan tekrar da aynı sonucu verdi: 552 test, 12 başarısız. Süreçler kendileri tamamlandı; sonlandırılan süreç olmadı. Geniş paket hatalarının önceki kodda da mevcut olduğu doğrulanmadı.

PHP model/migration sözdizimi ve git diff --check geçti. MySQL concurrency testleri çalıştırılmadı.

## AKBEY analizi

akbey-due-target-audience.sql yalnızca SELECT içerir ve çalıştırılmadı. Doğrulanmış apartman kimliği ile gerçek devreye alma zamanı parametreleri gerekir. Soft delete kayıtları dahildir. Null olan her kayıt eski kabul edilmez; batch bilgisi yalnızca kaynak dağılımı için kullanılır, hedefleme kuralı türetilmez.

Deployment, canlı migration, geçmiş sınıflandırması, yedek geri yükleme veya hesap birleştirme yapılmadı.

Geniş pakette başarısız sınıflar: Admin/ManagerSubscriptionOrderTest (1), Admin/SubscriptionItemListTest (3), CashPageTest (2), CommercialBillingTest (1), ExampleTest (1), LandingPageTest (1), PublishedSaleDocumentsTest (1), RevertAllocationModalTest (2).

RevertAllocationModalTest önceki (git HEAD) borç detay ekranıyla SQLite bellek içi ortamda ayrıca çalıştırıldı: 7 test, 5 başarılı, aynı 2 başarısız. Ekran dosyası finally bloğunda bu geliştirmedeki haline geri yüklendi. Bu iki hata yeni Oluşturma Kuralı alanından önce de mevcut; diğer 10 hata için baseline karşılaştırması yapılmadı.

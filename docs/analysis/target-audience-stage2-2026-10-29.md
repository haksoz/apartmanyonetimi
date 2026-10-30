# Borç türü sadeleştirmesi: ikinci aşama ön kontrolü

Tarih: 29 Ekim 2026. Sonuç: **DURDURULDU — tarihsel NULL hedefleme kayıtları var.**

## Kapsam ve yöntem

`.env` içindeki APP_ENV=local, DB_CONNECTION=mysql, DB_HOST=127.0.0.1 bağlantısı incelendi. Laravel başlatılmadan doğrudan PDO üzerinden REPEATABLE READ, WITH CONSISTENT SNAPSHOT, READ ONLY transaction kullanıldı; sonunda ROLLBACK yapıldı. Hiçbir model eventi, migration, veri düzeltmesi veya canlı bağlantı çalıştırılmadı. Sunucudaki yerel verinin canlı ortamla eşdeğerliği doğrulanmış değildir.

`tools/audit-target-audience.php` tekrar kullanılabilir salt okunur denetleyicidir. Ayrıntılı sonuçlar `target-audience-stage2-preflight-2026-10-29.json` dosyasındadır. Tüm tablolar doğrudan SQL ile, apartman/aktiflik/soft-delete filtresi olmadan tarandı. Mevcut apartman ID'leri: 8, 10. Yerel SQLite dosyası eski şemadadır (due_type ve hedefleme sütunları/plan tabloları eksik); bu nedenle güncel veri denetiminde kullanılmadı.

## Veri sonuçları

| Apartman | Silinmemiş borç | Soft-deleted borç | Uyumlu borç | NULL hedefleme |
| --- | ---: | ---: | ---: | ---: |
| 8 | 1.114 | 17 | 1.131 | 0 |
| 10 | 52 | 48 | 52 | 48 |
| Toplam | 1.166 | 65 | 1.183 | 48 |

NULL kayıtların tamamı apartman 10'dadır: 40 aidat, 8 malik_payi. Kayıt ID'leri JSON raporunun `tables.dues.issues` bölümündedir. Bu kayıtların eski hedefleme davranışı yalnızca due_type üzerinden tarihsel olarak kanıtlanmış sayılmaz. Soft-deleted olmaları kontrol dışı bırakılmalarını sağlamaz.

- Boş string hedefleme: 0.
- Tanımsız hedefleme: 0.
- Dolu hedefleme ile due_type uyuşmazlığı: 0.
- Boş/geçersiz due_type: 0.
- due_batches: 20 kayıt uyumlu (apartman 8: 5 aidat; apartman 10: 10 aidat, 5 malik_payi).
- due_plans: apartman 10'da 1 aidat planı, uyumlu.
- assessment_plans: apartman 10'da 1 malik_payi planı, uyumlu.
- Desteklenmeyen plan türü: 0.

Bu sonuç yalnızca saklanan tür–hedefleme alanlarının uyumudur. Her tarihsel borcun doğru borçlu hesaba yazıldığı, eski kiracılık zaman çizelgelerinin eksiksizliği, planların çalıştırılması ve finansal referans bütünlüğü bu kontrolde kanıtlanmadı. NULL bulgusunda durma talimatı nedeniyle sonraki aşamalara geçilmedi.

## Kod bağımlılık envanteri

| Yer | Bağımlılık |
| --- | --- |
| app/Models/Due.php | Fillable; oluştururken geçerli hedefleme kontrolü ve türe göre yazma; güncellemede hedefleme değişmezliği; due_type tabanlı target_audience_label accessor |
| app/Models/DueBatch.php | Fillable ve saving eventiyle türe göre yazma |
| app/Models/DuePlan.php | Fillable ve saving eventiyle aidat hedeflemesi yazma |
| app/Models/AssessmentPlan.php | Fillable ve saving eventiyle malik_payi hedeflemesi yazma |
| app/Http/Controllers/DueController.php | İstek alanını türe göre üretme; required doğrulaması; batch ve borç oluşturma yazmaları |
| app/Http/Controllers/DuePlanController.php | Plan doğrulama/önizleme ve plan üretimindeki batch/borç yazmaları |
| app/Http/Controllers/AssessmentPlanController.php | Doğrulanmış veriye hedefleme ekleme |
| app/Support/AssessmentPlanGenerator.php | Batch ve borç oluşturma yazmaları |
| app/Http/Controllers/AccountController.php | Excel içe aktarma borç oluşturma yazması |
| app/Http/Controllers/ReportController.php | Eski target_audience URL parametresinin doğrulaması ve due_type seçimine uyarlama; türe göre türetilen targetAudience ile boş satırlarda hesap tercihi |
| resources/views/dues/show.blade.php | target_audience_label accessor gösterimi; sütundan doğrudan okumuyor |
| app/Enums/DueType.php | targetAudience() dönüşümü; hedefleme alanına yazan tüm akışlarda ve raporda kullanılıyor |
| resources/views/dues/batch-create.blade.php | targetAudienceSummary adlı türe göre açıklama; DB sütunu bağımlılığı değil, davranış açıklaması korunmalı |

Tarihsel migrationlar: 2026_05_16_000003_add_target_audience_to_due_batches, 2026_05_17_000001_create_due_plans_table, 2026_10_20_122000_create_assessment_plan_tables, 2026_10_27_000001_add_target_audience_to_dues. Bunlar değiştirilmedi ve ileride de değiştirilmemeli.

Test bağımlılıkları: DueTypeTargetingTest, DueTargetAudienceTest, DuePageTest, FinancialPlanningTest, ImportDueTypeTest, ImportedDueSelectionTest, DueFilterTest, CategoryUsagePermissionTest, ThreeCategoryPermissionsTest, BusinessRules/AccountTransactionDirectionTest, ReceivableListReportTest, MonthlyBoardAudienceTest. Bunlar eski sütun assertion, fixture, sorgu, migration testi veya eski rapor parametrelerini içeriyor. docs/analysis içindeki önceki raporlar ve SQL dosyaları da tarihsel alan referansları içeriyor; aktif uygulama kodu değiller.

## İlk aşama ve aylık tablo incelemesi

- DueType: aidat → tenant_priority; malik_payi → owner_only.
- DueTargeting hesap seçiminde doğrudan DueType kullanıyor; dönem içindeki kiracılık, uygun hesap ve birden çok kiracı korumaları mevcut.
- Due modeli kaydedilmiş borcun due_type değişikliğini engelliyor. DueController düzenlemede aynı türü doğruluyor; aktarımda DueTargeting::assertAccount çağrıları var.
- DuePlan yalnız aidat; AssessmentPlan yalnız malik_payi kabul ediyor. Üretim akışları türe göre hedefleme kullanıyor; mevcut plan verileri bu sınırlara uyuyor. Dinamik çalıştırma doğrulaması yapılmadı.
- Category modelinde allow_aidat, allow_malik_payi, allow_expense izinleri mevcut. ImportedDueSelection kategori kullanım iznini ve malik_payi için malik hesabını doğruluyor.
- Aylık tablo borçları raw due_type üzerinden sınıflandırıyor; malik_payi kapsamından kategoriye göre dışlama görülmedi. Mevcut altı sütun, dönem, hesaplama, PDF ve Excel akışları değiştirilmedi. Görsel/çıktı regresyon testleri çalıştırılmadı.
- Dört sütun yeni hedefleme kararının kaynağı olmaktan çıkmış görünüyor; ancak uyumluluk yazmaları ve Due modelindeki guard hâlen sütunları gerekli kılıyor. Mevcut kodla doğrudan sütun düşürmek güvenli değil.

## Finansal veri anlık özeti

Soft-deleted dahil, snapshot içindeki değerler:

| Tablo | Kayıt | Tutar toplamı | Ek toplam |
| --- | ---: | ---: | --- |
| dues | 1.231 | 677.109,08 | remaining_amount: 123.204,66 |
| payments | 877 | 1.125.711,55 | unallocated_amount: 4.869,66 |
| payment_allocations | 1.663 | 1.120.841,89 | |
| account_transactions | 2.376 | 2.358.765,41 | |
| cash_boxes | 5 | — | |
| cash_transactions | 889 | 1.143.985,80 | |

Bu toplamlar finansal mutabakat veya satır bazlı bütünlük kanıtı değildir. Denetim transaction'ı salt okunurdu; uygulama ve finansal veriler değiştirilmedi.

## Yapılan değişiklikler ve testler

Yalnızca bu rapor, JSON veri raporu ve salt okunur denetim aracı eklendi. Uygulama kodu/testler değiştirilmedi. Önceden değiştirilmiş ReportController.php, monthly-board-pdf.blade.php, monthly-board.blade.php ve MonthlyBoardAudienceTest.php korundu.

Denetim aracı yerel MySQL üzerinde başarıyla çalıştı. Uygulama testleri ve tam paket çalıştırılmadı: tarihsel NULL hedefleme bulgusu, kullanıcının uygulama öncesi durma koşulunu tetikledi. Yeni test hatası veya önceki hataların güncel durumu hakkında sonuç çıkarılamaz. Eski test-failures-audit.md raporu bu çalıştırmanın sonucu değildir.

## Devam ve geri dönüş koşulları

1. Önce 48 NULL tarihsel kaydın durumu incelenmeli; nasıl ele alınacağı açıkça kararlaştırılmalı. Bu görevde otomatik sınıflandırma veya düzeltme yapılmadı.
2. Güvenlik kontrolü tamamlandıktan sonra uygulama bağımlılıkları kaldırılıp tüm istenen senaryolar izole test veritabanında doğrulanmalı. Testlerin kendi migration akışı dahi bu durdurulan aşamada çalıştırılmadı.
3. Ayrı, açık isimli sütun düşürme migration'ı ancak bu aşamadan sonra hazırlanmalı. Bu görevde henüz oluşturulmadı, hiçbir migration çalıştırılmadı.
4. Migration öncesi tüm apartmanlar ve soft-deleted kayıtlar üzerinde denetim tekrar edilmeli; borçlu hesap/dönem, kategori izinleri, borç–batch–plan tür tutarlılığı ve finansal referans/kalıntı bakiyeleri ayrıca doğrulanmalı.
5. Tam veritabanı yedeği ve geri yükleme denemesi; dört alan için tablo/ID/değer bazlı yedek; finansal tablolar için satır bazlı önce/sonra karşılaştırma hazırlanmalı. Üreticiler ve eski sürüm uygulama süreçleri dağıtım sırasında koordineli durdurulmalı.
6. Sütunları rollback ile yeniden eklemek eski değerleri geri getirmez. Eski uygulamaya dönüş için sütunların özgün şemasının ve değerlerinin doğrulanmış yedekten geri yüklenmesi gerekir. İşlem aralığında oluşan yeni finansal kayıtlar kaybedilmemeli; hedefleme sütunları için kontrollü geri yükleme planı hazırlanmalı.

Commit, deployment, veri düzeltmesi ve şema değişikliği yapılmadı.

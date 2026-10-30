# İkinci aşama devam sonucu — 29 Ekim 2026

Kod temizliği ve ayrı migration hazırlığı tamamlandı. Yerel `apartman_db` ve production üzerinde migration veya veri yazması yapılmadı. Migration yalnız testlerin geçici SQLite `:memory:` bağlantısında çalıştı. Commit ve deployment yapılmadı.

Bu rapor, önceki `target-audience-stage2-2026-10-29.md` raporundaki durma kararının devamıdır. Kullanıcının yeni kararı uyarınca apartman 10'daki geçerli türlere sahip 48 silinmiş NULL kayıt kabul edilen tarihsel istisnadır; hiçbirine dokunulmadı.

## 1. Veri kontrolü

Yerel `.env`: local, MySQL, loopback. Laravel başlatılmadan READ ONLY / REPEATABLE READ snapshot alındı; işlem sonunda ROLLBACK yapıldı. Eloquent scope veya soft-delete filtresi kullanılmadı. Bu kontrolün production verisini temsil ettiği iddia edilmez.

| Apartman | Tüm borçlar | Dolu ve uyumlu hedefleme | NULL hedefleme |
| --- | ---: | ---: | ---: |
| 8 (AKBEY) | 1.131 | 1.131 | 0 |
| 10 | 100 | 52 | 48 |

48 kaydın tümü soft-deleted; 40 aidat, 8 malik_payi. Hiçbirinin ödeme tahsisi veya Due bağlantılı hesap hareketi yok. Kalanları tutarlarına eşit. Hiçbir kayıt doldurulmadı, silinmedi veya geri yüklenmedi.

Dolu hedefleme değerleri arasında tür uyuşmazlığı, geçersiz/boş string hedefleme ve geçersiz due_type bulunmadı. 20 batch, bir Aidat Planı ve bir Taksitli Borç Planı uyumlu. Borç–batch ve batch–plan apartman/tür bağlantıları da uyumlu.

15 bağımsız referans ve aritmetik kontrolde sorun yok:

- Borç ve ödeme hesaplarının varlığı ve apartman eşleşmesi.
- Tahsislerin ödeme/borç/gider referansları, tek hedefi, pozitif tutarı, hesap/apartman uyumu.
- Borç için `amount - remaining_amount = tahsisler`; ödeme için `amount - unallocated_amount = tahsisler` (kuruş toleransı).
- Hesap hareketlerinin hesap ve Due/Payment/Expense polimorfik referansları.
- Kasa hareketlerinin kasa, ödeme ve hesap referansları; ödeme hesabı uyumu.
- Silinmiş borçlara bağlı tahsis bulunmaması.
- Borç–batch ve batch–plan tür/apartman tutarlılığı.

Çalışma öncesi/sonrası **dokuz tablonun tüm satırlarını ve tüm sütunlarını içeren SHA256 parmak izleri aynı**: dues, due_batches, due_plans, assessment_plans, payments, payment_allocations, account_transactions, cash_boxes, cash_transactions. AKBEY ve 48 NULL kayıt değişmedi. Dört hedefleme sütunu yerel MySQL'de hâlâ mevcut.

Finansal sayımlar: 1.231 borç, 877 ödeme, 1.663 tahsis, 2.376 hesap hareketi, 5 kasa, 889 kasa hareketi. Tutar ve kalan bakiye toplamları da önceki denetimle aynı. Bu kontroller muhasebesel bağımsız denetim veya her tarihsel kiracılık kararının doğruluğu iddiası değildir.

Kanıtlar:

- `target-audience-stage2-continuation-preflight-2026-10-29.json`
- `target-audience-stage2-continuation-postflight-2026-10-29.json`

## 2. Kaldırılan bağımlılıklar

- Dört modelin fillable hedefleme alanları ve türe göre uyumluluk yazmaları kaldırıldı.
- Due oluşturmadaki eski hedefleme değer doğrulaması ve güncellemedeki sütun değişmezlik guard'ı kaldırıldı. **due_type değişmezliği korundu.**
- DueController istek birleştirmesi, required hedefleme doğrulaması ve borç/batch yazmaları kaldırıldı.
- Plan controller'ları, AssessmentPlanGenerator ve Excel içe aktarma borç yazmalarındaki hedefleme alanları kaldırıldı.
- ReportController eski target_audience URL doğrulaması ve fallback'inden arındırıldı. Rapor seçimi ve boş satır hesap tercihi doğrudan due_type kullanıyor.
- Kullanılmayan DueType::targetAudience dönüşümü kaldırıldı. Görsel açıklama accessor'ı targeting_rule_label olarak adlandırıldı; açıklama hâlâ due_type üzerinden üretiliyor.
- Test fixture'larının sütun yazmaları ve sütuna bağlı assertion/pluck sorguları kaldırıldı; borç türü, hesap ve finansal sonuç beklentileri kullanıldı.
- Eski sütunu testlerde yalnız negatif UI/input güvenliği kontrolleri, sütunun yokluğu ve açık migration/rollback testi referanslıyor. Bunlar aktif sütun bağımlılığı değil.

DueTargeting hesap seçimi, borç aktarımı, ödeme/tahsis, kasa işlem kodu ve kategori izinleri değiştirilmedi. Aidat aktif dönem kiracısına, kiracı yoksa malike; malik_payi malike yönlenmeye devam ediyor. Plan tür kısıtları ve kaydedilmiş borcun türünün değişmezliği korundu.

## 3. Aylık tablo

Adlar: Aidat Borç Tablosu; Kat Malikleri Demirbaş Borç Tablosu. İkinci tablo yalnız borç türüyle kapsamlanıyor; Ceza, Faiz ve Diğer kategorileri rapor/çıktı fixture'larına dahil edilerek hesaplamalar doğrulandı.

Altı sütun, dönem seçimi, güncel/hareket görünümleri, bakiye hesaplamaları ve yazdırma şablonları korunuyor. Excel'de görünür tam tablo başlığı korunurken sekme adı önceki kısa `Kat Maliki Borç Tablosu` olarak tutuldu; tam başlık Excel'in 31 karakter sınırını aşıyor.

Başlangıçtaki ReportController ve MonthlyBoardAudienceTest değişiklikleri korundu. Başlangıçta değiştirilmiş iki rapor Blade dosyasına bu devam çalışmasında ek değişiklik yapılmadı.

## 4. Migration

`database/migrations/2026_10_29_000001_drop_target_audience_from_due_tables.php`

Up yalnız dört sütunu düşürür. Down özgün nullable/default tanımlarını geri ekler. Eski migrationlar değiştirilmedi. Migration içinde DML, backfill veya finansal alan güncellemesi yok. Down eski satırların özgün değerlerini geri getirmez.

İzole test, aktif ve soft-deleted borçlar, iki plan ve batch'leri, ödeme, tahsis, hesap hareketi ve kasa hareketleriyle snapshot alıyor. Sütun kaldırma ve down sonrasında hedefleme dışındaki tüm satır alanları birebir aynı kalıyor. Eski hedefleme işaretinin rollback ile geri gelmediği de doğrulanıyor.

## 5. Testler

Tüm testlerde bağlantı SQLite `:memory:` olarak zorlandı; DB_URL boşaltıldı. Yerel MySQL ve production'a test/migration yazması yapılmadı.

| Kapsam | Test | Başarılı | Failure | Error | Skip |
| --- | ---: | ---: | ---: | ---: | ---: |
| Değişiklik öncesi tam paket | 633 | 597 | 13 | 0 | 23 |
| Değişiklik sonrası tam paket | 634 | 598 | 13 | 0 | 23 |
| İlgili akışlar, son tam paketten | 189 | 186 | 1 | 0 | 2 |
| Migration + borç davranışı + PDF/Excel regresyon grubu | 41 | 41 | 0 | 0 | 0 |

Son tam pakette 5.577 assertion var. Yeni başarısız test yok; aynı 13 test değişiklik öncesinde de başarısızdı. İlk ilgili çalışmada bulunan iki Excel sekme adı hatası düzeltildi ve son tam pakette yok. PHP lint ve git diff --check geçti.

Doğrulanan akışlar: birebir/toplu Aidat, malik_payi, hesap seçimi, düzenleme ve aktarım, tür değişmezliği, otomatik Aidat, taksitli plan, Excel import, kategori izinleri, geçmiş/silinmiş kayıtlar, tahsis ve kalan bakiye, aylık rapor ve altı sütunlu PDF/Excel verisi.

Önceden mevcut 13 failure:

- Admin/ManagerSubscriptionOrderTest: ücretsiz apartmana hediye süre sonrası aktif görünümü (1).
- Admin/SubscriptionItemListTest: item alanları, iki apartman/geçmiş item satırları, silinmiş apartman snapshot adı (3).
- CashPageTest: boş kasa listesi ve apartman kapsamlı liste görünümü (2).
- CommercialBillingTest: aboneliksiz ücretsiz apartman erişimi beklentisi (1).
- ExampleTest: misafirin login'e yönlendirilmesi beklentisi (1).
- LandingPageTest: aktif fiyat aralıklarının görünümü (1).
- MonthlyBoardReportTest: `test_monthly_board_limits_accounts_to_the_selected_residence_month_and_includes_deleted_accounts_when_requested` (1). Hedefleme temizliğinden önce de başarısız; borcu olmayan eski silinmiş hesabın görünürlüğü beklentisi, `Former tenant`.
- PublishedSaleDocumentsTest: satış belgelerinin kabul edilen sürümü görünümü (1).
- RevertAllocationModalTest: geri alma modalı ve çok tahsisli ödemede silme kontrolü (2).

İlgili gruptaki bir failure yukarıdaki MonthlyBoardReportTest'tir; yeni regresyon olarak değerlendirilmedi ve kapsam dışı beklentiler değiştirilmedi. Tam test kimlikleri `target-audience-stage2-test-results-2026-10-29.json` içindedir.

23 skip: ayrı boş MySQL test veritabanı için opt-in ayarı bulunmayan ExpensePaymentConcurrencyTest (21) ve SQLite DATE_FORMAT sınırlaması nedeniyle önceden skip edilmiş iki DuePageTest dashboard popup testi. Diğer otomatik üretim testleri geçti.

PDF şablonu/verisi, altı sütun ve response sözleşmesi mock PDF builder ile doğrulandı; gerçek dış PDF servisi/render motoruyla uçtan uca görsel çıktı testi yapılmadı. Excel gerçek xlsx çıktısı oluşturulup PhpSpreadsheet ile okunarak doğrulandı.

## 6. Yedekleme ve rollback

`tools/backup-target-audience.php` ve `target-audience-backup-rollback.md` uygulanabilir yöntemi içeriyor. Yerel-only, READ ONLY snapshot; dört tablo için ID/apartman/tür/hedefleme ve CREATE TABLE tanımı; depo dışı özel dizine AES-256-GCM şifreli çıktı; anahtar ortam değişkeninden ve ayrı kasada saklanmalı. Plaintext yedek diske yazılmaz. Bu görevde gerçek yedek dosyası oluşturulmadı; araç lint edildi ve OpenSSL desteği doğrulandı.

Tam DB yedeği ve geri yükleme denemesi ayrıca şart. Down sadece şemayı getirir; özgün hedefleme değerleri ID bazında doğrulanmış yedekten, ayrı yetkilendirilmiş işlemle geri yüklenmeli. NULL'lar NULL kalmalı. Yeni finansal kayıtlar varsa eski tam DB yedeği körlemesine geri yüklenmemeli. Ayrıntılar ayrı prosedürde.

## 7. Bu devam çalışmasında değişen dosyalar

Uygulama:

- app/Enums/DueType.php
- app/Models/Due.php
- app/Models/DueBatch.php
- app/Models/DuePlan.php
- app/Models/AssessmentPlan.php
- app/Http/Controllers/DueController.php
- app/Http/Controllers/DuePlanController.php
- app/Http/Controllers/AssessmentPlanController.php
- app/Http/Controllers/AccountController.php
- app/Http/Controllers/ReportController.php
- app/Support/AssessmentPlanGenerator.php
- resources/views/dues/show.blade.php

Testler:

- tests/Feature/BusinessRules/AccountTransactionDirectionTest.php
- tests/Feature/CategoryUsagePermissionTest.php
- tests/Feature/DueFilterTest.php
- tests/Feature/DuePageTest.php
- tests/Feature/DueTargetAudienceTest.php
- tests/Feature/DueTypeTargetingTest.php
- tests/Feature/FinancialPlanningTest.php
- tests/Feature/ImportDueTypeTest.php
- tests/Feature/ImportedDueSelectionTest.php
- tests/Feature/MonthlyBoardAudienceTest.php
- tests/Feature/ReceivableListReportTest.php
- tests/Feature/ThreeCategoryPermissionsTest.php
- tests/Feature/TargetAudienceRemovalMigrationTest.php (yeni)

Migration, araç ve raporlar:

- database/migrations/2026_10_29_000001_drop_target_audience_from_due_tables.php (yeni)
- tools/audit-target-audience.php (genişletildi)
- tools/backup-target-audience.php (yeni)
- docs/analysis/target-audience-backup-rollback.md (yeni)
- docs/analysis/target-audience-stage2-continuation-2026-10-29.md (bu rapor)
- docs/analysis/target-audience-stage2-continuation-preflight-2026-10-29.json (yeni)
- docs/analysis/target-audience-stage2-continuation-postflight-2026-10-29.json (yeni)
- docs/analysis/target-audience-stage2-test-results-2026-10-29.json (yeni)

## 8. Hazırlık durumu ve riskler

Kod ve migration **incelemeye hazır**. Bu görev migration çalıştırma onayı değildir. Çalıştırmadan önce:

1. Mevcut 13 test failure'ı ve 23 skip'in kabul edilip edilmediği değerlendirilmeli; tam paket tamamen yeşil değil.
2. Güncel veri denetimi hedef ortamda ayrıca yetkilendirilmiş salt okunur süreçle tekrarlanmalı. Production incelenmedi.
3. Doğrulanmış tam DB yedeği, depo dışı şifreli sütun yedeği ve izole geri dönüş denemesi tamamlanmalı.
4. MySQL kopyasında DDL süre/kilit ve dört tablo arasında kısmi başarısızlık koşulları denenmeli. SQLite testi MySQL operasyonel kilit süresini kanıtlamaz.
5. Tüm web/queue/scheduler/import yazıcıları ve eski kod süreçleri koordineli durdurulmalı; migration öncesi/sonrası hedefleme dışı finansal satır karşılaştırması yapılmalı.
6. Eski `target_audience=owner_only` rapor URL'leri artık rapor seçmez; bağlantılar `due_type=malik_payi` kullanmalı. Uygulamanın mevcut rapor bağlantıları zaten due_type kullanıyor.
7. Yeni kod eski sütunlar dururken de çalışır; ancak eski kod sütun düşürüldükten sonra çalışamaz. Geri dönüşte önce şema ve yedek değerler geri getirilmeli.

48 NULL istisnası dışında yeni veri tutarsızlığı bulunmadı. Kullanıcının sonuçları değerlendirmesi için çalışma burada tamamlandı; veri veya şema değişikliğine geçilmedi.

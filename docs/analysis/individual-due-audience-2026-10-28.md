# Birebir borçlandırmada açık kapsam seçimi — 28 Ekim 2026

- Borç listesindeki Borçlandır ve hesap başlığındaki + Borçlandır menüleri Daire Sakini / Kat Maliki seçeneklerini sunar. Aynı partial masaüstü ve mobilde kullanılır.
- Daire Sakini → tenant_priority; Kat Maliki → owner_only. Seçim GET ile forma taşınır; formda kapsam ve başlık görünür. Doğrudan /dues/create erişiminde başlangıç seçimi yoktur; kapsam required.
- Kat Maliki kapsamı yalnızca aktif malik hesabıyla kabul edilir. Kiracı hesabının menüsünde ve kilitli hesabın formunda seçenek devre dışıdır. GET/POST istekleri de sunucuda doğrulanır; hesap sessizce değiştirilmez.
- Daire Sakini kapsamı malik veya kiracı hesabına yazılabilir. Borç türü/kategori seçimleri devam eder; birebir borç türünden veya hesap türünden otomatik hedefleme kuralı üretilmez.
- Birebir kayıtlar açık seçimi DueBatch.target_audience ve Due.target_audience alanlarında saklar. Oluşturulmuş borçların değişmezlik kuralı korunur. İçe aktarma ve geçmiş null kayıtlar değiştirilmez.
- Hesap seçili formda borç türü değiştiğinde seçili hesap otomatik olarak serbest bırakılmaz/değiştirilmez; uyumsuz durumda açıklama gösterilir ve sunucu reddeder.

## Değişen dosyalar

- app/Http/Controllers/DueController.php
- resources/views/dues/_create-menu.blade.php (yeni)
- resources/views/dues/index.blade.php
- resources/views/dues/create.blade.php
- resources/views/accounts/_tabs.blade.php
- tests/Feature/DueTargetAudienceTest.php
- tests/Feature/DuePageTest.php (geçersiz/pasif hesap kimliğiyle GET artık reddedilir beklentisi)

## Doğrulama

- SQLite :memory: ortamında 97 test: 95 başarılı, 2 mevcut atlama, 752 doğrulama.
- Çalıştırılanlar: DueTargetAudienceTest, DuePageTest, AccountPageTest, ImportDueTypeTest, MonthlyBoardAudienceTest, MonthlyBoardReportTest, BusinessRules/AccountTransactionDirectionTest, CategoryUsagePermissionTest.
- Her iki kuralın birebir borca/batch kaydı, ilgili panoda görünme ve diğer panodan dışlanma, düzenlemede kuralın korunması, null/eksik/geçersiz kapsamın reddi, kiracı-owner_only uyuşmazlığının reddi, menülerin masaüstü/mobil bağlantıları, kilitli hesap, mevcut hesap/borç ekranları ve içe aktarmada null korunması doğrulandı.
- Formdaki JavaScript node --check ile sözdizimi kontrolünden geçti. Tarayıcıyla uçtan uca test yapılmadı.
- Bu geliştirmede yerel AKBEY veya production veritabanına bağlanılmadı; migration/deployment/veri güncellemesi yapılmadı. Önceki pano geliştirmesi çalışma ağacında korundu.

## Daire Sakini / Malik Payı uyumu

Daire Sakini kapsamında birebir Malik Payı oluşturulması formda ve sunucuda engellendi. Malik Payı seçeneği gizli/devre dışı; kapsam değiştirilince seçenekler güncellenir ve uyumsuz mevcut tür temizlenir. Kat Maliki kapsamında Malik Payı kullanılabilir. Toplu borçlandırmanın Malik Payı için owner_only kullanmasıyla uyumludur; toplu kayıt mantığı değiştirilmedi.

Son doğrulama: DueTargetAudienceTest, DuePageTest, CategoryUsagePermissionTest; SQLite :memory: üzerinde 53 test, 51 başarılı, 2 mevcut atlama, 412 doğrulama. Yeni test, iki kapsamın form seçeneklerini ve doğrudan geçersiz POST reddini kontrol eder. PHP ve JavaScript sözdizimi kontrolleri geçti. Veri güncellemesi veya migration yapılmadı.

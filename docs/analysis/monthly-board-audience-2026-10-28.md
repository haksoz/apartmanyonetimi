# Aylık Aidat / Malik pano ayrımı — 28 Ekim 2026

## Değişiklik

- Mevcut rapor ekranındaki forma Tablo seçimi eklendi. Varsayılan Aylık Aidat Tablosu (tenant_priority), diğer seçim Aylık Malik Tablosu (owner_only). GET parametresi target_audience; ay, hesap türü ve durum seçimleri korunuyor.
- Her iki tablo yalnızca kendi hedefleme kuralındaki borçları okuyor. due_type, kategori veya borçlu hesabın türünden sorumluluk çıkarılmıyor.
- Mevcut altı sütunlu tablo aynı şablondan üretiliyor. Ekran ve PDF şablonlarının <table> bölümünden dosya sonuna kadar içeriği önceki commit ile birebir karşılaştırıldı ve aynı bulundu. Tablo sınıfları, sütunlar ve PDF ortak stilleri değişmedi.
- Ekran, PDF ve Excel monthlyBoardData üzerinden aynı hesap listesi ve tutarları kullanıyor. PDF A4/margins davranışı ve Excel sütun genişlikleri korunuyor. Başlıklar seçime göre değişiyor; dışa aktarma bağlantıları kural seçimini taşıyor.
- Daire Sakinleri filtresi, aynı dairede seçili rapora ait borç bulunan bütün uygun hesapları ayrı mevcut satırlar halinde koruyor. Borç yoksa daire başına önceki sade tek satır davranışı sürüyor (Aidat tablosunda kiracı önceliği, Malik tablosunda malik önceliği). Açık hesap/durum ve seçilen ayda geçerlilik filtreleri korunuyor.
- Seçilen ay borçları ve geçmiş açık borçlar kendi rapor kapsamında hesaplanıyor. Sonradan yapılan tahsisler güncel ödenen/kalan değerlerine yansıyor. Gelecek ay borçları kapsam dışı.
- NULL kuralı iki tabloya da dahil edilmiyor. Seçili ay ve geçmiş açık borçlar içinde, uygun hesaplara ait sınıflandırılmamış kayıt varsa kısa adet bilgisi gösteriliyor; PDF ve Excel de aynı bilgiye sahip.
- Ayın 31. gününde kısa ayların taşmasını önlemek için ay başlangıcına sabitlenmiş tarih çözümlemesi kullanıldı; URL ile seçilmiş eski ay seçenek listesinde korunuyor.

## Değişen uygulama dosyaları

- app/Http/Controllers/ReportController.php
- resources/views/reports/monthly-board.blade.php
- resources/views/reports/monthly-board-pdf.blade.php
- tests/Feature/MonthlyBoardAudienceTest.php (yeni)

## İzole testler

APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL boş kullanıldı. Yerel AKBEY test veritabanı olarak kullanılmadı.

Son paket: 63 test, 63 başarılı, 569 doğrulama.

Çalıştırılan sınıflar: MonthlyBoardReportTest, MonthlyBoardAudienceTest, ReceivableListReportTest, DueTargetAudienceTest, PaymentPageTest, PaymentMultiAllocateTest, BusinessRules/AccountTransactionDirectionTest.

Doğrulanan durumlar: aynı hesapta iki kural, aynı dairede birden fazla borçlu hesap, malik üzerindeki kiracı öncelikli borç, null dışlama ve bilgi notu, gelecek/silinmiş borç dışlama, due_date fallback, başka apartman izolasyonu, aktif/pasif ve hesap türü seçimleri, ay geçerliliği, ay sonu taşmaması, PDF/Excel ekran eşitliği ve finansal satırların değişmemesi.

PDF testinde gerçek Blade şablonu render edildi; dış PDF tarayıcı motoru izole stub ile temsil edildi. Motorun kendisi yeniden uçtan uca çalıştırılmadı. Yeni tam paket çalıştırılmadı; daha önce raporlanan 12 geniş paket hatasının düzeltildiği iddia edilmiyor.

## Yerel AKBEY mutabakatı

APP_ENV=local, MySQL 127.0.0.1:3306, apartman_db, AKBEY ID=8 doğrulandı. Yerel mysqld port sahipliği yeniden kontrol edildi. Analiz READ ONLY transaction içinde çalıştırıldı.

Şubat/Haziran/Eylül/Ekim 2026 × iki kural × resident/all/owner/tenant × active/inactive/all = 96 birleşim. Hesap geçerliliği ve borç/tahsis tutarları ayrı ham SQL ile hesaplanıp gerçek controller verileriyle hesap başına ve toplamda karşılaştırıldı: tamamı eşleşti.

| Ay | Tablo | Geçmiş borç | Ay borcu | Ödenen | Kalan |
|---|---|---:|---:|---:|---:|
| 2026-02 | Aidat | 0,00 | 2.720,00 | 2.720,00 | 0,00 |
| 2026-02 | Malik | 43.098,82 | 280,00 | 0,00 | 43.378,82 |
| 2026-06 | Aidat | 0,00 | 3.769,16 | 3.769,16 | 0,00 |
| 2026-06 | Malik | 45.380,82 | 0,00 | 0,00 | 45.380,82 |
| 2026-09 | Aidat | 123,00 | 5.920,00 | 4.490,00 | 1.553,00 |
| 2026-09 | Malik | 45.380,82 | 0,00 | 0,00 | 45.380,82 |
| 2026-10 | Aidat | 1.553,00 | 0,00 | 0,00 | 1.553,00 |
| 2026-10 | Malik | 45.380,82 | 0,00 | 0,00 | 45.380,82 |

Varsayılan resident/active görünümde bu örneklerin hepsinde 12 hesap ve 0 sınıflandırılmamış kayıt var. Silinmiş 17 borç rapor hesaplarına dahil edilmedi.

Veriye dokunmama: tüm apartmanların dues, accounts, payments, payment_allocations, account_transactions, cash_transactions, due_batches tablolarının önce/sonra SHA-256 özetleri aynı. Uygulama mantığı hiçbir finansal kayıt yazmıyor. Production bağlantısı, deployment, migration veya veri sınıflandırması yapılmadı.

Makine okunur kanıt: monthly-board-akbey-verification-2026-10-28.json.

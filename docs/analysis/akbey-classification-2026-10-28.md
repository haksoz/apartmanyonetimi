# AKBEY geçmiş borç oluşturma kuralı sınıflandırması — 28 Ekim 2026

## Yetki ve kapsam

Kullanıcının AKBEY geçmiş hesap birleştirme bilgisi ve bu apartmana özel açık eşleştirme onayı esas alındı. Genel uygulama mantığına türden hedefleme kuralı üreten kod eklenmedi. Pano filtreleri değiştirilmedi. Canlı veritabanına bağlanılmadı; deployment yapılmadı.

## Yerel bağlantı ve ön kontrol

- APP_ENV=local, MySQL 127.0.0.1:3306 / apartman_db. Portu yerel mysqld PID 6044 dinliyor; hostname DESKTOP-HJKM5UL. DB_URL/socket/read-write yönlendirmesi bulunmuyor.
- Apartman kimliği 8, Akbey Apartmanı, 12 daire.
- Enum ve fiziksel değerler: aidat / malik_payi. Başka borç türü yok.
- Silinmemiş malik borçlarında 174 malik_payi ve 675 aidat bulunması verilen birleştirme geçmişiyle sayısal olarak örtüşüyor. Bu inceleme hesap birleştirme işlemini tekrar yapmadı.
- Toplam 179 malik_payi = 174 silinmemiş + 5 önceden silinmiş. Bu 5 yeni/ek aktif borç değil; tamamı içe aktarılmış, batch bağlantısız, ödeme tahsisi olmayan kayıtlar. Aynı daire ve aynı açıklama veya aynı tarih/tutarla başka borç eşleşmesi bulunmadı. Neden silindikleri mevcut veriyle kesinleştirilemedi; silinme durumları korundu.
- 60 batch bağlantılı aidat kaydının batch hedeflemesi tenant_priority; eşleştirmeyle çelişki yok. Batch 13 içindeki 12 silinmiş aidat da buna dahil.
- Karşıt mevcut hedefleme, desteklenmeyen tür veya batch çelişkisi yok. Başlangıçta 1.131 target_audience NULL.

## 174 / 179 farkını oluşturan beş kayıt

| Due ID | Daire ID | Tutar (TL) | Vade | Silinme zamanı (DB) |
|---|---:|---:|---|---|
| 2270 | 61 | 731.67 | 2021-06-10 | 2026-09-27 14:14:24 |
| 2280 | 71 | 1011.67 | 2021-06-10 | 2026-09-27 14:18:34 |
| 2402 | 61 | 12.50 | 2023-04-18 | 2026-09-27 14:15:24 |
| 2409 | 68 | 12.50 | 2023-04-18 | 2026-09-27 14:16:00 |
| 2412 | 71 | 12.50 | 2023-04-18 | 2026-09-27 14:16:19 |

İlk iki borcun açıklaması 2021 Ayedaş Avukatı / Logar / SuSayacı; diğer üçü 2023 Nisan Ozalit İşi Mimari Proje. Toplam 1.780,84 TL; kalan aynı; tahsis adedi 0.

## Yapılan işlem

- InnoDB transaction, REPEATABLE READ; apartman ve batch kaynakları paylaşımlı, AKBEY borçları FOR UPDATE kilidiyle doğrulandı.
- Güncellemeden önce kimlikler, eski/yeni target_audience, tür ve deleted_at değerleri yerel rollback.json dosyasına yazıldı; dosya flush/fsync ve tekrar okuma ile doğrulandı. Koşullu geri alma SQL dosyası da hazırlandı; çalıştırılmadı.
- Yalnızca apartment_id=8, target_audience IS NULL ve ön kontroldeki kilitli kimlik/tür eşleşen kayıtlar güncellendi.
- Tek SET alanı target_audience. updated_at dahil diğer borç alanlarına dokunulmadı. Eloquent genel oluşturma/değişmezlik kuralı değiştirilmedi; açıkça onaylanan tek seferlik veri işlemi PDO ile gerçekleştirildi.
- 1.131 kayıt güncellendi, atlanan/belirsiz/çelişkili kayıt 0. Silinmiş 17 kaydın deleted_at değeri aynen korundu.

## Son dağılım

| Kural | Silinmemiş | Silinmiş | Toplam |
|---|---:|---:|---:|
| tenant_priority | 940 | 12 | 952 |
| owner_only | 174 | 5 | 179 |
| NULL | 0 | 0 | 0 |
| Toplam | 1.114 | 17 | 1.131 |

Silinmiş aidat kimlikleri 3469–3480; 12 kayıt, 5.890,00 TL. Silinmiş malik_payi kimlikleri 2270, 2280, 2402, 2409, 2412; 5 kayıt, 1.780,84 TL. Silinmiş toplam 17 / 7.670,84 TL. Normal raporlar için yeniden etkinleştirilmediler.

## Önce / sonra mutabakatı

| Ölçü | Önce | Sonra |
|---|---:|---:|
| Tüm borç adedi | 1.131 | 1.131 |
| Tüm borç tutarı | 601.909,08 TL | 601.909,08 TL |
| Borçlara ödenen (amount - remaining_amount) | 547.304,42 TL | 547.304,42 TL |
| Tüm borçların kalanı (silinmiş dahil) | 54.604,66 TL | 54.604,66 TL |
| Silinmemiş borç tutarı | 594.238,24 TL | 594.238,24 TL |
| Silinmemiş borçların kalanı | 46.933,82 TL | 46.933,82 TL |
| AKBEY payments adedi / toplamı | 871 / 1.119.111,55 TL | 871 / 1.119.111,55 TL |
| Tahsis edilmemiş payment toplamı | 4.869,66 TL | 4.869,66 TL |
| Tüm payment tahsisleri | 1.654 / 1.114.241,89 TL | 1.654 / 1.114.241,89 TL |
| Borçlara tahsisler | 1.336 / 547.304,42 TL | 1.336 / 547.304,42 TL |
| Cari debit | 1.421 / 1.161.175,71 TL | 1.421 / 1.161.175,71 TL |
| Cari credit | 877 / 1.131.789,70 TL | 877 / 1.131.789,70 TL |
| Kasa income | 565 / 558.174,08 TL | 565 / 558.174,08 TL |
| Kasa expense | 310 / 573.211,72 TL | 310 / 573.211,72 TL |

Payments ve tüm payment tahsisleri yalnızca aidat tahsilatları değildir; borç tahsilatı için ayrıca 547.304,42 TL ve borç tahsisleri gösterildi.

Transaction içinde ve commit sonrasında mevcut tüm değerlerin SHA-256 karşılaştırması yapıldı: accounts, payments, payment_allocations, account_transactions, cash_transactions ve due_batches tabloları aynı; tüm apartmanların dues tablosunda target_audience dışındaki her alan aynı. AKBEY dışındaki dues kayıtları target_audience dahil tamamen aynı.

## Geri alma dosyaları

- JSON: `C:\Users\Hasancan\Documents\GitHub\KapitalOnlineApartmanYonetim\storage\app\private\maintenance\akbey-target-audience-20261028-001145-061055\rollback.json`
- SQL: `C:\Users\Hasancan\Documents\GitHub\KapitalOnlineApartmanYonetim\storage\app\private\maintenance\akbey-target-audience-20261028-001145-061055\rollback.sql`

Dosyalar yereldir, git dışında tutulur. SQL yalnızca kaydedilmiş kimlikler ve beklenen yeni hedefleme değeri eşleşirse eski NULL değerini geri yazar; bu incelemede geri alma yapılmadı.

## Kanıt dosyaları

- akbey-classification-preflight-2026-10-28.json: ön kontrol, silinmiş 17 kayıt ve batch incelemesi.
- akbey-classification-result-2026-10-28.json: commit sonucu, değişiklik adedi, dağılım ve önce/sonra özet/hash değerleri.

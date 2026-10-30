# B — Yerel AKBEY borç analizi

## Kapsam ve güvenlik

- Bağlantı: APP_ENV=local, 127.0.0.1:3306, apartman_db; yerel mysqld PID 6044 port sahipliği doğrulandı. Sunucu DESKTOP-HJKM5UL, MySQL 8.0.45. URL, socket, read/write yönlendirmesi yok.
- Apartman: id=8, Akbey Apartmanı, 12 daire; isim aramasında tek eşleşme.
- Yeni alan yerel şemada yoktu. Yalnızca 2026_10_27_000001_add_target_audience_to_dues migration uygulandı. Alan nullable ve default NULL; geçmiş UPDATE yapılmadı.
- Migration öncesi ve sonrası 1131 borç, 5 batch, 54 hesap, 871 ödeme, 2298 cari hareket, 875 kasa hareketi ve 1654 tahsis kaydının sayı/SHA-256 karşılaştırması aynı. Borçlarda yalnızca yeni sütun hash dışında tutularak eski tüm değerler karşılaştırıldı.
- Analiz PDO ile SET SESSION TRANSACTION READ ONLY ve transaction/rollback altında SELECT sorgularıyla yapıldı. İsim dışında kişisel veri rapora eklenmedi. Production bağlantısı veya deployment yok.

## Sayılar ve tutarlar

Aksi belirtilmedikçe soft delete kayıtları dahil fiziksel toplamdır. Silinmiş borçların kalanını güncel tahsil edilecek bakiye gibi yorumlamayın.

| Kapsam | Adet | Borç tutarı (TL) | Kalan (TL) |
|---|---:|---:|---:|
| Tümü | 1.131 | 601.909,08 | 54.604,66 |
| Silinmemiş | 1.114 | 594.238,24 | 46.933,82 |
| Silinmiş | 17 | 7.670,84 | 7.670,84 |

Kural: tenant_priority=0, owner_only=0, null=1.131. Bu sonuç geçmiş kayıtların kuralını temsil etmez; alan yeni eklendi ve geçmiş değer atanmadı. Mevcut 1.131 kaydın tamamı bu yerel migration öncesinde vardı.

| Null kaynak | Adet | Tutar (TL) | Kalan (TL) |
|---|---:|---:|---:|
| Batch bağlantılı | 60 | 23.641,00 | 7.443,00 |
| Batch bağlantısız / içe aktarılmış | 1.071 | 578.268,08 | 47.161,66 |

| Borç türü | Adet | Tutar (TL) | Kalan (TL) | Silinmiş adet |
|---|---:|---:|---:|---:|
| aidat | 952 | 385.664,34 | 7.443,00 | 12 |
| malik_payi | 179 | 216.244,74 | 47.161,66 | 5 |

| Mevcut hesap türü | Hesap | Borç adedi | Tutar (TL) | Kalan (TL) |
|---|---:|---:|---:|---:|
| Malik | 12 | 863 | 506.130,05 | 52.800,66 |
| Kiracı | 8 | 268 | 95.779,03 | 1.804,00 |

Tüm malik borçları null: 863 adet / 506.130,05 TL. Silinmemiş malik null borçları 849 adet / 499.853,21 TL / kalan 46.523,82 TL; silinmişler 14 adet / 6.276,84 TL. Hesap türü oluşturma kuralı için kanıt olarak kullanılmadı.

## Batch kaynak kanıtı

| Batch | Dönem | Kaynak | Kaydedilmiş kural | Borç | Tutar (TL) | Durum |
|---|---|---|---|---:|---:|---|
| 8 | 2026-06 | expenses | tenant_priority | 12 | 3.769,16 | Silinmemiş |
| 9 | 2026-07 | expenses | tenant_priority | 12 | 3.973,84 | Silinmemiş |
| 12 | 2026-08 | expenses | tenant_priority | 12 | 4.088,00 | Silinmemiş |
| 13 | 2026-09 | expenses | tenant_priority | 12 | 5.890,00 | Tamamı silinmiş |
| 14 | 2026-09 | fixed | tenant_priority | 12 | 5.920,00 | Silinmemiş |

Batch kaydına dayanarak incelenebilecek yüksek güvenli adaylar: 60/1.131 = %5,31. Normal panoya girebilecek silinmemiş adaylar 48/1.114 = %4,31; toplam tutar 17.751,00 TL, kalan 1.553,00 TL. Bunlar henüz sınıflandırılmadı.

Varsayılan riski: eski batch migration alanı tenant_priority varsayılanıyla ekliyor. Mevcut 5 batch bu değerde. Migration tablosu uygulama zamanı saklamıyor; dosya adı veya kuralın tenant_priority olması tek başına geçmiş seçimi kanıtlamaz. Öte yandan batchler Temmuz–Eylül 2026 oluşturulmuş; Temmuz öncesi git e04ecf9 ve Ağustos 076192b sürümleri kuralı required doğrulayıp batch alanına açıkça yazıyor ve aynı değeri hesap seçiminde kullanıyor. Bu 5 batch için yalnızca migration defaultundan üretildiğini gösteren bir kanıt bulunmadı. Bu yüzden 60 kayıt kaynak destekli adaydır; geçmiş runtime/deployment kaydı olmadan mutlak tarihsel kanıt iddiası yapılmaz. Doğrudan Due üzerinde dondurulmuş eski kuralı kanıtlanan kayıt 0; bu katı ölçütte oran %0.

Plan kanıtı: AKBEY için DuePlan=0, AssessmentPlan=0, installment line=0; batchlerde due_plan_id / assessment_plan_id / assessment_installment_id tamamen null. Plan bağlantısıyla ek sınıflandırılabilir kayıt 0. Eksik hesap, bozuk batch bağlantısı veya başka apartmana ait batch 0.

## Belirsiz kayıtlar

- İçe aktarılmış 1.071 kayıt (%94,69) batch veya hedefleme seçimi taşımıyor: 892 aidat (639 malik, 253 kiracı) ve 179 malik_payi (tamamı malik).
- Tür, kategori, açıklama veya mevcut borçlu hesabından oluşturma kuralı türetilmedi. Özellikle ithal malik_payi kayıtlarını owner_only olarak doldurmak uygulanmış uygulama kuralını kanıtlamaz.
- Kaynak Excel/karar belgesi varsa ayrı incelemeyle hedefleme seçimi belgelenmeli. Böyle bir kanıt yoksa null korunmalı. Bir iş kararıyla sonradan sınıflandırma yapılması, orijinal oluşturma kuralının tespitiyle aynı şey değildir.
- Batch 13 silinmiş Eylül borçlarıdır; batch 14 aynı dönem için sonradan oluşturulmuştur. 12 silinmiş borç normal panoya geri alınmamalı.

## Sonraki pano önerisi (uygulanmadı)

- Mevcut altı sütun ve hesap/ay geçerliliği kuralları korunsun. Kural filtresi doğrudan dues.target_audience üzerinde çalışsın.
- tenant_priority → Aylık Aidat Tablosu; owner_only → Aylık Malik Tablosu; null → Henüz sınıflandırılmamış kayıt.
- Geçmiş borç, seçilen ay borcu, o borçlara ödenen ve kalan aynı kural kapsamından hesaplansın. Mevcut hesap türüyle kural türetilmesin; malik hesabındaki tenant_priority kayıt aidat tablosunda kalsın.
- Silinmiş borçlar normal hesaplamaya alınmasın. Kategori veya due_type değiştirilmesin. Null tutarlar iki sınıflandırılmış tablodan birine sessizce eklenmesin; görünür ayrı seçim/sayaç olsun.
- Mevcut toplamlara mutabakat için üç kural kümesi birbirini dışlasın ve birleşimleri mevcut kapsamı versin. Ödeme, tahsis, kasa ve cari kayıtlara dokunulmasın; mevcut güncel kalan hesabı korunsun.
- Önce 60 batch destekli aday için karar alınmalı; 1.071 ithal borç kanıtsız sınıflandırılmamalı. Geçmiş kuralı değiştirmeyi engelleyen Due modelini aşan herhangi bir toplu işlem ayrıca açık kapsam, ön izleme ve onay gerektirir.

Ham kanıtlar: akbey-local-preflight.json, akbey-local-analysis.json, akbey-fingerprint-before.json, akbey-fingerprint-after.json. Hazır SQL dosyasının ilk iki SELECT sorgusu çalıştırıldı; eski kayıt cutoff sorgusuna uydurma devreye alma tarihi verilmedi.

# İkinci aşama — gerçek MySQL doğrulaması

29 Ekim 2026. **Başarılı: MySQL 8.0.45 üzerinde izole kopyada migration, uygulama akışları ve rollback doğrulandı.** Ana `apartman_db` ve production üzerinde migration veya veri yazması yapılmadı. Commit ve deployment yok. Test ortamları inceleme için korunuyor; henüz silinmedi.

## Güvenli izolasyon yöntemi

1. Kaynak `.env` dosyasının local/loopback MySQL ve `apartman_db` olması zorunlu tutuldu. Kaynağa PDO ile READ ONLY / REPEATABLE READ snapshot açıldı; kaynak bağlantıda yalnız SELECT, SHOW ve transaction yönetimi kullanıldı.
2. 52 tablonun tamamının InnoDB olduğu, view/trigger/event/routine bulunmadığı doğrulandı. Cross-schema kaynak referansı içeren DDL reddediliyor. Kaynağa tablo kilidi veya geçici yazma uygulanmadı.
3. Rastgele son ekli, önceden mevcut olmayan veritabanı ve kullanıcı oluşturuldu. CREATE DATABASE/USER için IF NOT EXISTS kullanılmadı. Yönetici bağlantısı yalnız yeni test veritabanını/kullanıcısını hazırlamak için kullanıldı.
4. Geçici kullanıcıya yalnız kendi test veritabanında SELECT/INSERT/UPDATE/DELETE/CREATE/DROP/INDEX/ALTER/REFERENCES verildi. Bu kullanıcının `apartman_db.dues` okumasının MySQL tarafından reddedildiği ayrıca test edildi; kaynakta yazma denemesi yapılmadı.
5. Tüm 52 tablonun CREATE TABLE tanımı ve satırları, tutarlı kaynak snapshot'ından doğrudan kopyaya aktarıldı. İlişki kaybını önlemek için yalnız borç tabloları yerine bütün ilişkili uygulama şeması kopyalandı. Parola, plaintext dump veya satır bazlı kişisel veri dosyaya yazılmadı; raporlar sayım/toplam/SHA256 içeriyor.
6. Kopyadaki tüm tablo satırlarının parmak izleri kaynakla birebir karşılaştırıldı. Laravel ancak sonra, yalnız kısıtlı test kullanıcısının ortam değişkenleriyle başlatıldı. Config cache bulunması halinde araç çalışmayı reddediyor; Laravel'de yalnız test MySQL bağlantısı bırakılıyor. `.env` dosyası değiştirilmedi.
7. Mail/cache/session/broadcast için dış servis veya kalıcı kaynak depolama kullanılmadı. Akışlar HTTP middleware zinciri yerine gerçek controller, model ve generator çağrılarıyla sınandı. Dış PDF servisine erişilmedi.

Araçlar:

- `tools/verify-target-audience-mysql.php`: preflight, kopyalama, tek migration, karşılaştırma, rollback ve geri yükleme doğrulaması. `--resume` yalnız raporla sahipliği ve değişmemiş kaynak parmak izi doğrulanan test kopyasını kullanabilir.
- `tools/verify-target-audience-mysql-flows.php`: yalnız kopya veritabanı ve açık transaction içinde çalışabilen akış kontrolleri.

## Kopyalar ve denemeler

| Kopya | Durum |
| --- | --- |
| `aidatcepstage2test20261029bb0c62e0f077` | İlk deneme; migration başarılı, akış fixture'ında varsayılan Ceza kategorisini yeniden oluşturma hatasında durdu. Fixture transaction'ı geri alındı. Migration uygulanmış durumda korunuyor. |
| `aidatcepstage2test2026102962403db0b982` | İkinci kopya; ilk akış denemesinde toplu Aidat girdisinde zorunlu kategori eksikti. Test girdisi tamamlandı, aynı kopya güvenli resume ile kaynağın tüm satırlarına eşit duruma getirilip bütün doğrulama başarıyla tekrarlandı. Son durumda migration uygulanmış, dört sütun kaldırılmış ve test fixture satırları geri alınmış durumda. |

Bu iki hata hazırlanan doğrulama aracının test girdileriydi; ürün kodu veya migration hatası değildi. Her denemede AKBEY başlangıç snapshot'ı aynıydı. Uygulama kodu/migration dosyası bu görevde değiştirilmedi.

Her iki geçici hesap işlem sonunda **ACCOUNT LOCK** ile kilitlendi:

- `aidatst2_bb0c62e0f077@localhost`
- `aidatst2_62403db0b982@localhost`

Rastgele parolalar dosyaya veya rapora yazılmadı. Kopyalar yerel yönetici bağlantısıyla incelenebilir. Bu çalışma kapsamında DROP DATABASE/USER veya test ortamı temizliği yapılmadı. Silme işlemi değerlendirmeden sonra ayrı adım olmalı.

## Migration sonucu

Yalnız `2026_10_29_000001_drop_target_audience_from_due_tables` migration'ı, açık `--path` ve test MySQL bağlantısıyla çalıştırıldı; diğer pending migrationlar çalıştırılmadı. Başarılı son denemede up yaklaşık **0,1697 saniye** sürdü. Bu yerel, küçük veri seti ölçümüdür; production kilit süresinin garantisi değildir.

| Sütun | Test kopyası | Ana apartman_db |
| --- | --- | --- |
| dues.target_audience | Kaldırıldı | Hâlâ mevcut |
| due_batches.target_audience | Kaldırıldı | Hâlâ mevcut |
| due_plans.target_audience | Kaldırıldı | Hâlâ mevcut |
| assessment_plans.target_audience | Kaldırıldı | Hâlâ mevcut |

Migration registry satırı dışında, kopyadaki **tüm tabloların hedefleme dışındaki tüm satır alanları aynı kaldı**. Tür, hesap, tutar, kalan bakiye, ödeme, tahsis, hesap/kasa hareketi ve plan satırları değişmedi. Son kaynak kontrolünde **52 tablonun bütün satırları başlangıç parmak izleriyle aynıydı**.

## AKBEY — önce ve sonra

Tüm sayımlar soft-deleted kayıtları da içerir; ayrıca aktif borç alt kümesi gösterilmiştir. Aşağıdaki her satır için miktarların yanında satır bazlı parmak izi de karşılaştırıldı. Borçlarda karşılaştırmadan yalnız kaldırılması istenen hedefleme sütunu çıkarıldı.

| Veri | Migration öncesi | Migration sonrası |
| --- | ---: | ---: |
| Tüm borçlar | 1.131 | 1.131 |
| Borç tutarı toplamı | 601.909,08 TL | 601.909,08 TL |
| Tüm borçların kalan bakiyesi | 54.604,66 TL | 54.604,66 TL |
| Silinmemiş borçlar | 1.114 | 1.114 |
| Silinmemiş borçların kalan bakiyesi | 46.933,82 TL | 46.933,82 TL |
| Ödemeler | 871 | 871 |
| Ödeme tutarı toplamı | 1.119.111,55 TL | 1.119.111,55 TL |
| Ödemelerin tahsis edilmemiş toplamı | 4.869,66 TL | 4.869,66 TL |
| Ödemelere bağlı tahsis satırları (borç/gider) | 1.654 | 1.654 |
| Hesap hareketleri | 2.298 | 2.298 |
| Kasa hareketleri | 875 | 875 |
| Kasalar | 3 | 3 |
| Hesaplar (silinmişler dahil) | 54 | 54 |

54 hesabın model üzerinden hesaplanan ledger_balance değerleri de ID bazında aynı kaldı. 49 dönem × 2 borç türü × 2 görünüm olmak üzere **196 aylık rapor senaryosunun accountData değerleri değişmedi**.

Apartman 10'daki 48 soft-deleted NULL kayıt da kopyalamada ve hedefleme dışı satır karşılaştırmalarında korundu; kaynakta hiçbirine yazılmadı. İstisna için backfill veya veri düzeltmesi yapılmadı.

## Migration sonrası akış kontrolleri

Yeni bir test apartmanında, rollback edilen transaction içinde **31 kontrol geçti**:

- Birebir Aidat kiracıya yazıldı; aktif kiracı varken malike Aidat yazılması reddedildi.
- Birebir malik_payi malike yazıldı; kiracı hesabı reddedildi.
- Toplu Aidat kiracılı dairede kiracıya, kiracısız dairede malike yazıldı.
- Toplu malik_payi yalnız maliklere dağıtıldı.
- Kaydedilmiş borcun türü değiştirilemedi.
- Gerçek PaymentController ile 100 TL borca 40 TL ödeme/tahsis oluşturuldu; borç bakiyesi 60 TL, ödeme tahsis edilmemiş tutarı 0 TL oldu. Ödeme hesabı ve kasa hareketi ilişkileri doğrulandı.
- Aidat Planı iki borç üretti; doğru kiracı/malik hesapları seçildi; aynı dönem yeniden çalıştırılınca ek borç üretilmedi.
- Taksitli Plan üç taksitte altı borç üretti; toplam 900 TL ve tüm borçlular malik; tekrar üretim ek borç oluşturmadı.
- Aidat ve malik_payi raporları beklenen tutarları verdi. Ceza, Faiz ve Diğer malik_payi kategorileri dışlanmadı.
- Dört rapor varyantında HTML ve PDF şablonu altı sütunla render edildi; gerçek Excel export callback'i geçerli ZIP/XLSX baytları üretti. Üretilen çıktı dosyaya dökülmedi. Gerçek PDF servisiyle görsel uçtan uca render yapılmadı.

Testlerin sonunda outer transaction rollback edildi. Kaynak kopyadaki bütün hedefleme dışı tablo satırlarının başlangıçla aynı kaldığı ayrıca doğrulandı; yalnız test kopyasının auto-increment sayaçlarında boşluk oluşabilir. İlk aşamalardaki tam PHPUnit paketi burada yeniden çalıştırılmadı; bu görev gerçek MySQL üzerinde migration ve controller/generator doğrulamasıydı.

## Rollback ve yedekten geri yükleme

Test kopyasında migrate:rollback, aynı açık migration path'i ve step=1 ile çalıştırıldı. Dört sütun doğru nullable/default tanımlarıyla geri geldi. Finansal alanlar değişmedi; **özgün hedefleme değerleri geri gelmedi**:

| Tablo | Rollback sonrası özgün değerden farklı kayıt |
| --- | ---: |
| dues | 1.183 |
| due_batches | 5 |
| due_plans | 0 |
| assessment_plans | 0 |

Borçlar nullable sütun yeniden eklenince NULL oldu. Batch'lerde malik_payi kayıtları varsayılan tenant_priority aldığı için beş değer farklıydı. Planların bu veri setindeki değerleri varsayılanla tesadüfen aynı; bu durum rollback'in geçmiş değerleri sakladığı anlamına gelmez.

Kaynak READ ONLY snapshot'ından alınmış, yalnız RAM'de tutulan ID/hedefleme yedeğiyle **sadece test kopyasının dört sütunu** geri yüklendi. Bundan sonra migration registry dahil **52 tablonun bütün özgün satır değerleri kaynak snapshot ile birebir eşleşti**. Ardından migration yalnız kopyada yeniden uygulanarak test ortamı son göç edilmiş durumda bırakıldı.

Bu test kalıcı/şifreli üretim yedeği oluşturmaz. Gerçek geçişte `target-audience-backup-rollback.md` prosedürü uygulanmalı: doğrulanmış tam DB yedeği, repo dışında şifreli ID/değer yedeği, ayrı anahtar saklama ve izole geri yükleme denemesi. Rollback tek başına veri geri dönüşü değildir.

## Kanıt dosyaları

- `target-audience-mysql-verification-2026-10-29-103301.json`: ilk kopyalama ve başarılı DDL; kategori fixture hatası.
- `target-audience-mysql-verification-2026-10-29-103547.json`: ikinci tam kopyalama; zorunlu kategori girdisi eksikliği; kaynak 52 tablo değişmezlik kanıtı.
- **`target-audience-mysql-verification-2026-10-29-103810.json`**: başarılı son doğrulama; dört sütunun yokluğu, AKBEY öncesi/sonrası değerler, 196 rapor karşılaştırması, 31 akış kontrolü, rollback fark sayıları, birebir geri yükleme ve kaynak korunumu.

Bu görevde yalnız iki doğrulama aracı, bu rapor ve üç JSON kanıt dosyası eklendi. Ürün kodu, `.env`, mevcut migrationlar ve ana veritabanı değiştirilmedi.

## Değerlendirme

Migration'ın mevcut yerel şema/veri kopyası üzerinde **MySQL 8.0.45 uyumluluğu doğrulandı**. Yeni ciddi veri veya şema riski bulunmadı. Ana veritabanında çalıştırma yetkisi kullanılmadı ve bu rapor production geçişi yerine geçmez.

Gerçek geçişten önce hedef ortam preflight'ı, kalıcı güvenli yedekler, yazıcı süreçlerin durdurulması ve cutover/rollback planı gerekir. MySQL DDL dört tablo arasında tek atomik transaction değildir; farklı veri hacmi ve eşzamanlı trafik altında kilit süresi ayrıca değerlendirilmelidir. Test veritabanları ve kilitli hesaplar inceleme bitene kadar korunuyor.

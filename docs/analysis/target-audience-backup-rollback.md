# Hedefleme sütunları: yedekleme ve geri dönüş

Bu prosedür henüz uygulanmadı. Migration çalıştırılması ayrıca değerlendirilecek; bu görev production erişimi veya veri yazma yetkisi vermez.

## Migration öncesinde

1. Veritabanının tamamı için kurumun mevcut güvenli yedekleme yöntemiyle yedek alın ve izole ortamda geri yüklenebilirliğini doğrulayın. MySQL DDL işlemleri atomik bir dört-tablo transaction'ı değildir; yarım kalan sütun düşürme olasılığını da planlayın.
2. Web işlemleri, queue worker'lar, scheduler ve içe aktarma dahil tüm veri üreticilerini durdurun. Eski kodla çalışan süreç bırakmayın. Yedek, denetim ve migration arasında yazma yapılmamalı.
3. `tools/audit-target-audience.php` yerel MySQL içindir: tüm apartmanları ve soft-deleted satırları içerir. 48 onaylı NULL dışındaki yeni bulguları değerlendirin. Production denetimi ayrı yetkilendirilmiş süreçle yapılmalı.
4. Erişimi yalnızca operatör hesabına açık, Git deposunun dışında ve web üzerinden sunulmayan bir dizin oluşturun. Dizin ACL izinlerini kontrol edin; OneDrive/Dropbox gibi herkese açık veya paylaşımlı dizinleri kullanmayın. Araç repository ve bilinen web-root dizinlerini reddeder, ancak bir dizinin fiilen paylaşılıp paylaşılmadığını tespit edemez.
5. Yerel sütun yedeği için PowerShell örneği (yol önceden oluşturulmuş özel dizin olmalı):

```powershell
$backupBytes = New-Object byte[] 32
$backupRng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$backupRng.GetBytes($backupBytes)
$backupRng.Dispose()
$env:AIDAT_BACKUP_KEY = [Convert]::ToBase64String($backupBytes)
# Anahtarı parolalar kasasına ayrı ve kalıcı kaydedin; terminale/loga yazdırmayın.
# Kasaya kaydetme işlemini tamamlamadan devam etmeyin.
php tools/backup-target-audience.php 'D:\PrivateBackups\AidatCep'
Remove-Item Env:AIDAT_BACKUP_KEY
```

Araç `.env` dosyasından yalnızca local/loopback MySQL bağlantısını kabul eder, Laravel'i başlatmaz, READ ONLY snapshot kullanır. Dört tablonun ID, apartman, borç türü ve hedefleme değerlerini (NULL ve soft-deleted dahil) ve özgün CREATE TABLE tanımlarını alır. AES-256-GCM ile şifreler; plaintext diske yazılmaz. Yeni dosya exclusive-create ile açılır, şifre çözme ve diskten geri okuma kontrol edilir. Anahtarı yedekle birlikte saklamayın. SHA256 değerini ayrı güvenli kayda alın. Bu sütun yedeği tam finansal yedeğin yerine geçmez.

6. İzole bir kopyada anahtarla yedeği açın, tablo başına kayıt adetleri ve NULL dağılımlarını doğrulayın. JSON envelope içindeki nonce/tag/ciphertext base64 olarak çözülür; AES-256-GCM additional authenticated data değeri `AidatCep-target-audience-v1` olmalı. Format 1 JSON içindeki `tables.<table>.rows` ve `schema` alanları geri dönüş kaynağıdır.
7. Kod ve test raporunu gözden geçirin; yalnız açık onaydan sonra migration çalıştırın. Sütunlar kaybolmadan önce ve sonra finansal satır parmak izlerini karşılaştırın; dues için hedefleme sütununu hariç tutarak karşılaştırma yapın. Eski değer yedeğini repoya eklemeyin.

## Geri dönüş

- Yazma yapan süreçleri durdurun; başarısız dağıtım sırasında yeni kayıt oluşup oluşmadığını belirleyin. Yeni finansal verileri kaybettirecek biçimde eski tam yedeği körlemesine geri yüklemeyin.
- Migration down yalnızca sütunların tanımlarını geri ekler. `dues` NULL, diğer tabloların eski varsayılanları eski satır değerlerini geri getirmez.
- Güvenli yedek anahtarını kasadan alın, GCM etiketi doğrulanarak şifreyi çözün. Kaynak veritabanı, tablo, ID, apartman ve due_type eşleşmelerini doğrulayın. Eksik/fazladan satırları raporlayın.
- Ayrı yetkilendirilmiş geri yükleme işlemiyle yalnız dört sütunu kayıt ID'sine göre yedekteki özgün değerlerine döndürün; NULL değerler NULL kalmalı. Borç türü, hesap, tutar, tahsis, ödeme ve kasa verilerini değiştirmeyin. Bu görev otomatik geri yükleme aracı içermez.
- Migration sonrası oluşmuş, yedekte olmayan satırlar için eski uygulamanın gereksinimlerini ayrıca değerlendirin. Eski koda dönüş bu değerlendirme ve doğrulanmış sütun geri yüklemesi tamamlanmadan yapılmamalı.
- Adet, NULL dağılımı ve hedefleme dışı finansal parmak izi karşılaştırmalarını tamamlayın; eski uygulamayı ancak sonrasında yeniden açın.

Yarım kalan MySQL DDL durumunda önce hangi sütunların gerçekten silindiğini inceleyin; migration'ı veya down işlemini körlemesine tekrar çalıştırmayın.

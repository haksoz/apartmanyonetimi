# Tedarikçi gider tam ödeme düzeltmesi — doğrulama notu

## Kapsam

- Tekil ve toplu tam ödeme korunur; kısmi tahsisli gider bu ekranlardan ödenmez.
- Tekil endpoint gönderilen tutarı güncel, kilitli giderin tam tutarıyla karşılaştırır.
- Toplu endpoint seçilen bütün kayıtları aktif apartman/tedarikçi kapsamında yeniden bulur; ID'leri tekilleştirir; eksik, ödenmiş, kısmi veya tutarsız kayıt varsa bütün işlemi reddeder.
- Bakiye ve tahsis kontrolü `ExpensePaymentGuard` içinde paylaşılır. Para karşılaştırmaları kuruş üzerinden yapılır.
- Manuel ve plan kaynaklı yeni ödenmemiş giderler `paid_amount=0`, `remaining_amount=amount` ile başlar. Oluşturulurken ödenen giderin mevcut ödeme kaydı korunur.
- İçe aktarma yolu zaten aynı başlangıç standardını kullanır; değiştirilmedi.
- Eski NULL/NULL, ödenmemiş, tahsissiz giderler ödeme sırasında hesaplama düzeyinde desteklenir. Başarısız işlem bunları güncellemez. Backfill yoktur.
- Genel tedarikçi ödeme butonu gizlidir; route'lar ve tahsis ekranları açıktır.

## Kilitler ve sınırlar

İlgili tedarikçi mutasyonları transaction içinde önce tedarikçi hesap satırını kilitler. Var olan Payment kayıtları ve Expense kayıtları ID sırasıyla kilitlenir; yeni ödeme ancak bütün giderler doğrulandıktan sonra oluşturulur. Aynı tedarikçinin bu yolları seri çalışır.

Yalnız tam ödeme controller'larını kilitlemek yeterli olmadığından mevcut tedarikçi tahsis ekleme/kaldırma ve ödeme düzenleme/silme yollarına da kilit sonrası yeniden okuma eklendi. Malik/kiracı dallarına yeni ödeme kuralı eklenmedi. Yeni FIFO, tahsis özelliği, idempotency tablosu/token veya migration yoktur. Merkezi idempotency planı uygulanmadı.

Bu koruma başarılı yanıt replay'i sağlamaz. Ödeme/tahsis iptalinden sonra gider yeniden geçerli biçimde açılmışsa yeni tam ödeme yapılabilir. Genel tedarikçi ödeme/tahsis endpoint'leri açık olduğu için kısmi kayıtlar hâlâ oluşabilir; tam ödeme endpoint'leri bunları reddeder.

## Çalıştırılan doğrulama

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/ExpenseFullPaymentTest.php --do-not-cache-result
```

Sonuç: **18 test, 193 assertion, tamamı başarılı.**

Test sınıfı provider'lar başlamadan bütün DB bağlantılarını SQLite `:memory:` bağlantısıyla değiştirir ve `PRAGMA database_list` ile dosya kullanılmadığını doğrular. Şema test içinde kurulur; migration veya gerçek apartman fixture'ı kullanılmaz. Gerçek route/controller ve model işlemleri kullanılır; üyelik/abonelik middleware'leri bu dar controller testlerinde devre dışıdır. Test şeması asgari bir şemadır; üretim indeks/FK ve middleware entegrasyonunun tam doğrulaması değildir.

Doğrulananlar: manuel/plan gideri, oluşturulurken ödenen gider, eski NULL bakiyeler, eksik/fazla tutar, tekrar ödeme, kısmi ve tutarsız bakiye, toplu seçimde yabancı/silinmiş/eksik/geçersiz/tekrar ID, ilk ve ikinci tahsiste hata sonrası rollback, gider/tahsis/kasa/cari tutarları, mevcut tahsis bakiyesi kontrolü, aynı gidere birden fazla tahsisin ödeme silmede geri alınması, malik/kiracı tahsilat yönleri ve buton kapsamı.

Değişen PHP dosyaları sözdizimi kontrolünden; takip edilen değişiklikler `git diff --check` kontrolünden geçti.

## Gerçek MySQL eşzamanlılık testi — çalıştırılmadı

`ExpensePaymentConcurrencyTest` iki bağımsız PHP süreciyle tekil–tekil, tekil–toplu ve örtüşen toplu–toplu çakışmaları test eder. İlk süreç kilidi tutarken ikinci süreç ortak kilidi almaya çalışır. Sonunda tek başarılı ödeme ve tek kasa/cari hareket beklenir. Aynı sınıf temel iş kuralı testlerini de MySQL üzerinde çalıştırabilir.

Bu ortamda doğrulanmış izole MySQL ayarı yoktu; testler çalıştırılmadı. SQLite sonucu satır kilidi kanıtı değildir.

Test yalnız şu açık ayarlarla etkinleşir:

- `AIDATCEP_PAYMENT_MYSQL_ISOLATED=1`
- `AIDATCEP_PAYMENT_MYSQL_DATABASE=aidatcep_payment_test_<ayri_test_adi>`
- `AIDATCEP_PAYMENT_MYSQL_USERNAME`, `AIDATCEP_PAYMENT_MYSQL_PASSWORD`
- İsteğe bağlı `AIDATCEP_PAYMENT_MYSQL_PORT`

Host yalnız `127.0.0.1` olabilir. Uygulamanın `DB_*` bilgileri kullanılmaz. Veritabanı kullanıcı tarafından ayrılmış ve tamamen boş olmalıdır; dolu veritabanında test reddedilir. Test kendi tablolarını oluşturur ve sonunda yalnız oluşturduğu tabloları kaldırır. Migration kullanılmaz. Aynı test veritabanına paralel suite çalıştırılmamalıdır.

Bu ayarlar doğrulanmadan MySQL testi çalıştırılmamalıdır. Kullanılabilir olduğunda komut:

```powershell
php vendor/phpunit/phpunit/phpunit tests/Feature/ExpensePaymentConcurrencyTest.php --do-not-cache-result
```

Canlıya alma öncesinde bu doğrulama eksiktir. Raporlama kodunda bu çalışma nedeniyle değişiklik yapılmadı.

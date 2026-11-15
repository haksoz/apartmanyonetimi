# aidatcep.com üzerinde sınırlı PayTR testi

15 Kasım 2026. Kullanıcının belirttiği hesaplar:

- aidatcep01@ko.com.tr
- aidatcep02@ko.com.tr

Kullanıcı PayTR panelinde Bildirim URL'yi `https://aidatcep.com/payments/paytr/callback` olarak tanımladı. Bu adresin sunucudan gerçekten cevap verdiği henüz doğrulanmadı. Bu çalışma yerel kodu hazırladı; canlı sunucuya deploy veya `.env` değişikliği yapılmadı.

## Sunucu ayarları

APP_ENV production kalır; tüm uygulama staging'e çevrilmez. APP_DEBUG kapalı kalır. Ayrı test ortamı kurulana kadar yeni public üyelikler kapalıdır. İlk kontrolde kart kapalı tutulur:

```dotenv
APP_ENV=production
APP_DEBUG=false
REGISTRATION_ENABLED=false
PAYTR_PUBLIC_URL=https://aidatcep.com
PAYTR_TEST_MODE=true
PAYTR_ALLOW_TEST_SETTLEMENT=true
PAYTR_TEST_ACCOUNTS_ONLY=true
PAYTR_TEST_ACCOUNT_EMAILS="aidatcep01@ko.com.tr,aidatcep02@ko.com.tr"
PAYTR_CARD_ENABLED=false
```

Mağaza ID, key ve salt yalnızca sunucunun `.env` ayarına girilir: `PAYTR_MERCHANT_ID`, `PAYTR_MERCHANT_KEY`, `PAYTR_MERCHANT_SALT`. Gerçek değerler sohbet, Git veya ekran görüntüsüne eklenmez.

Son kod ve kullanıcı tarafından alınan frontend build sunucuya yüklendikten sonra:

```sh
php artisan migrate --force
php artisan config:cache
php artisan paytr:check
```

Komut test hesaplarının mevcut/aktif olmasını ve yerel ayarları gösterir; gizli anahtarları veya şifreleri yazdırmaz. Hesapları oluşturmaz. DNS/TLS veya PayTR panelinin erişilebilirliğini test etmez. Hesap eksikse yeni üyeliği herkese açmak yerine yetkili hesap yönetimi üzerinden tamamlanmalıdır.

Kontroller tamamlanınca yalnızca `PAYTR_CARD_ENABLED=true` yapılıp `php artisan config:cache` yeniden çalıştırılır. PayTR paneli test modunda kalır.

## Uygulanan erişim kuralları

- Üyelik kapalıyken GET `/register`, eski paketli register adresi ve POST `/register` reddedilir. Mevcut giriş/şifre sıfırlama çalışır. Microsite ve giriş ekranındaki kayıt bağlantıları gizlenir. Oturumlu yöneticinin mevcut davet/portal işlemleri public kayıt değildir ve bu değişiklikle kapatılmadı.
- Kart seçeneği yalnızca tanımlı ve aktif test hesaplarında görünür; diğer kullanıcının sahte kart POST'u da reddedilir. Havale yolları korunur.
- Test hesabı, sipariş sahibinin users tablosundaki e-postasıyla belirlenir. Kartı ödeyen kişinin/fatura profilinin e-postası veya callback verisi izin vermez.
- Production test aktivasyonu için allow_test_settlement, test_accounts_only, kapalı yeni üyelik ve izinli aktif sipariş sahibi birlikte zorunludur. Genel bir production test izni yoktur.
- Test hesabı modunda yeni başlatılacak deneme de test olmalıdır. Konfigürasyon yanlışlıkla canlı moda alınırsa test hesapları için canlı ödeme açılmaz. Önceden var olan gerçek ödeme bildirimleri kaybolmasın diye callback işleme, yeni ödeme başlatma anahtarından bağımsızdır.
- Test işaretleri denemede saklanır. Test sonrası bu iki hesaba ait dönem/tahsilat kayıtları normal müşteriler açılmadan değerlendirilip kontrollü biçimde ayrılmalıdır; finansal tablolar topluca silinmez.

## Test sırası

1. Birinci hesapla giriş, apartman kurulumu, fatura profili ve kart siparişi.
2. PayTR'nin hazır test kartıyla başarılı ödeme; PayTR panelinde sonuç ve uygulamada aboneliğin tek kez açılması kontrolü.
3. İkinci hesapla başarısızlık, vazgeçme ve tekrar deneme. Sonucu belirsiz işlemde tekrar tahsilat yapılmadığı kontrolü.
4. Yeni üyelik ve kartın diğer hesaplara kapalı olduğunun kontrolü.

Henüz gerçek sağlayıcı testi veya tahsilat yapılmadı. PayTR mağazasını canlı moda geçirmek bu çalışma kapsamında değildir. Sonraki kalıcı çözüm ayrı test ortamı/veritabanıdır.

# Finansal İşlemlerde Çift Gönderim ve Idempotency Koruması

- **Durum:** PLANLANDI — UYGULAMA ONAYI BEKLİYOR
- **Öncelik:** Yüksek
- **Kapsam:** Finansal işlemler, borçlandırma, tahsilat, kasa, planlama ve ilgili yönetim işlemleri
- **İlk uygulama:** Gider kaydı üzerinde ortak idempotency pilotu
- **Canlı ortam:** Değişiklik yapılmayacak
- **Framework:** Laravel Framework 13.18.0

> Bu belge bir geliştirme planıdır. Buradaki mimari öneriler uygulama onayı verilmiş kararlar değildir. “Açık kararlar” bölümündeki maddeler netleştirilmeden migration veya uygulama geliştirmesi başlatılmamalıdır.

## 1. Amaç ve mevcut durum

Amaç, aynı formun iki kez gönderilmesi veya aynı HTTP isteğinin tekrarlanması sonucunda mükerrer finansal kayıt oluşmasını merkezi, sürdürülebilir ve test edilebilir bir yapıyla engellemektir.

İnceleme sırasında aşağıdaki envanter çıkarılmıştır:

| Alan | Adet | Açıklama |
| --- | ---: | --- |
| Veri değiştiren route | 152 | POST, PUT, PATCH ve DELETE |
| POST formu içeren Blade dosyası | 99 | Yönetici, abone ve admin ekranları dahil |
| Blade içindeki POST formu | 163 | Aynı dosyada birden fazla form olabilir |
| Finans/plan/abonelikle ilişkili POST-capable route | 42 | Önizleme ve form hazırlama route'ları dahil |
| Kalıcı değişiklik yapan çekirdek işlem | 36 | Önizleme ve yalnız sayfa geçişleri hariç |
| Doğrudan kritik/yüksek riskli finansal endpoint | Yaklaşık 18 | Kesin kapsam endpoint tablosunda verilmiştir |

`DB::transaction()` tek başına idempotency sağlamaz. Aynı içerikli iki bağımsız istek iki ayrı transaction olarak başarıyla tamamlanabilir. Arayüzde buton kilitlemek de HTTP tekrarlarını, iki tarayıcı sekmesini veya farklı worker'lara ulaşan eşzamanlı istekleri tek başına engellemez.

## 2. Tasarım ilkeleri

Planlanan çözüm aşağıdaki katmanların birlikte kullanılmasına dayanır:

1. Opt-in ortak arayüz kilidi.
2. Merkezi ve veritabanı destekli işlem token'ı.
3. İdempotency kaydı ile finansal değişikliklerin aynı transaction sınırında olması.
4. Aynı iş kayıtlarını farklı token'larla değiştiren işlemler için `lockForUpdate()`.
5. Plan+dönem gibi doğal iş kimliği bulunan alanlarda kalıcı benzersiz indeks.
6. Başarılı işlem sonucunun tekrar üretilebilmesi.

Özet kombinasyon:

```text
Ortak DB token
+ transaction
+ gerekli satır kilitleri
+ varsa doğal iş anahtarı ve benzersiz indeks
+ opt-in kullanıcı arayüzü kilidi
```

## 3. Önerilen merkezi mimari

### 3.1 Planlanan bileşenler

- `idempotency_requests` tablosu
- `IdempotencyRequest` modeli
- `IdempotencyManager` servisi
- `IdempotencyContext` değer nesnesi
- `IdempotencyResult` değer nesnesi
- `RequestFingerprint` servisi
- Opt-in Blade token alanı/bileşeni
- Ortak submit kilidi JavaScript'i
- Günlük temizleme komutu ve scheduler kaydı

Controller'larda ayrı ayrı cache kontrolü yazılması planlanmamaktadır. Controller doğrulama ve yazma öncesi iş kuralı kontrollerini yaptıktan sonra mutasyon closure'ını ortak servise teslim etmelidir.

### 3.2 Önerilen tablo yapısı

Bu şema henüz onaylanmamıştır.

| Alan | Amaç |
| --- | --- |
| `id` | Birincil anahtar |
| `token` | UUID; global benzersiz indeks |
| `user_id` | İşlemi başlatan kullanıcı |
| `apartment_id` | Apartman kapsamı; bazı abonelik işlemlerinde nullable olabilir |
| `operation` | `expenses.store`, `payments.store` gibi sabit işlem adı |
| `request_hash` | Normalize edilmiş doğrulanmış girdinin SHA-256 özeti |
| `status` | Önerilen değerler: `processing`, `completed` |
| `result_type` | Oluşan ana model veya sonuç türü |
| `result_id` | Expense, Payment, DueBatch gibi ana kayıt ID'si |
| `response_payload` | Güvenli yönlendirme ve flash mesajı bilgileri |
| `started_at` | İşlem başlangıcı |
| `completed_at` | Başarılı tamamlanma zamanı |
| `expires_at` | Temizleme zamanı |
| timestamps | Denetim alanları |

Önerilen indeksler:

- `UNIQUE(token)`
- `(user_id, operation, created_at)`
- `(apartment_id, operation, created_at)`
- `(status, expires_at)`

Token'ın global benzersiz olması önerilmektedir. Aynı UUID'nin farklı kullanıcı veya apartman tarafından gönderilmesi yeni bir işlem oluşturmamalı ve mevcut işlem ayrıntılarını sızdırmadan reddedilmelidir.

### 3.3 Token üretimi ve iletimi

Normal Blade formları için:

- Form GET isteğinde görüntülenirken sunucuda UUIDv4 veya UUIDv7 üretilir.
- Hidden input ile gönderilir.
- Her yeni form görüntüleme yeni token üretir.

AJAX/fetch işlemleri için:

- Token'ın `Idempotency-Key` başlığında gönderilmesi önerilir.
- İstenirse hidden input da korunabilir; başlık öncelikli kabul edilebilir.

Geçiş döneminde mevcut gider formundaki `submission_token` adı kabul edilmelidir. Ortak isim olarak `idempotency_token` kullanılıp kullanılmayacağı açık karardır.

### 3.4 Kullanıcı, apartman ve işlem kapsamı

İdempotency kaydı şu bağlamla ilişkilendirilmelidir:

```text
token
user_id
apartment_id
operation
request_hash
```

`operation` için route adı tercih edilmelidir. Apartman kapsamı merkezi bir resolver tarafından şu kaynaklardan bulunmalıdır:

- `CurrentApartment`
- Route-model binding ile gelen modelin `apartment_id` alanı
- Route üzerindeki Apartment modeli
- Apartman dışı abonelik işlemlerinde kullanıcı/abonelik kapsamı

### 3.5 Request fingerprint

Hash ham POST gövdesinden değil, doğrulanmış ve normalize edilmiş girdiden üretilmelidir.

Hariç tutulması önerilen alanlar:

- `_token`
- `_method`
- `idempotency_token` / `submission_token`
- Sadece görsel amaçlı alanlar

Dahil edilmesi gerekenler:

- Tutarların sabit ondalık biçimi
- ISO tarihleri
- Hesap, kategori, kasa ve hedef kayıt ID'leri
- İşlem türü
- İşlem davranışını değiştiren submit butonu `name/value` bilgisi
- Sıralamanın anlamsız olduğu ID listelerinin sıralanmış hali
- Dosya yüklemelerinde dosya içeriğinin SHA-256 özeti

Aynı token daha önce claim edilmiş veya tamamlanmışken farklı `request_hash` ile gönderilirse işlem yapılmamalı ve `409 Conflict` döndürülmelidir.

### 3.6 Eşzamanlılık garantisi ve transaction sınırı

Önerilen işlem sırası:

1. Request validation tamamlanır.
2. Yazma öncesi iş kuralı kontrolleri yapılır.
3. Dış veritabanı transaction'ı başlatılır.
4. `idempotency_requests` kaydı `processing` durumuyla eklenir.
5. Finansal kayıtlar aynı transaction içinde oluşturulur veya güncellenir.
6. Sonuç bilgisi yazılır ve idempotency kaydı `completed` yapılır.
7. Transaction commit edilir.

MySQL/InnoDB'de iki transaction aynı benzersiz token'ı eklemeye çalışırsa ikinci insert ilk transaction sonucunu bekler:

- İlk transaction commit ederse ikinci istek benzersiz indeks ihlali üzerinden mevcut sonucu okur.
- İlk transaction rollback olursa ikinci insert başarılı olabilir ve işlemi kendisi gerçekleştirir.

Bu davranış MySQL eşzamanlılık testleriyle doğrulanmadan canlı kullanım planlanmamalıdır.

### 3.7 Satır kilitleri

Token yalnızca aynı kullanıcı niyetinin tekrarını engeller. Farklı token'larla aynı Payment, Due veya Expense kaydının eşzamanlı değiştirilmesi mümkündür. Bu nedenle aşağıdaki kayıtlar gerektiğinde `lockForUpdate()` ile kilitlenmelidir:

- Tahsis edilen Payment kayıtları
- Kapatılan Due kayıtları
- Ödenen Expense kayıtları
- Toplu ödeme/tahsis hedefleri
- Plan üretiminde ilgili plan
- Abonelikte ilgili apartmanın aktif kapsama kaydı

Deadlock riskini azaltmak için kilit sırası standartlaştırılmalıdır:

1. Ana hesap veya plan
2. Payment kayıtları, ID sırasıyla
3. Due/Expense kayıtları, ID sırasıyla
4. Tahsis ve hareket kayıtları

### 3.8 Başarılı işlem sonucunun tekrar oynatılması

Tam HTML yanıtı saklanmamalıdır. Güvenli ve yeniden üretilebilir sonuç saklanmalıdır:

```text
result_type: expense
result_id: 912
redirect_route: expenses.show
redirect_parameters: {"expense": 912}
flash_type: status
flash_message: Gider kaydı oluşturuldu.
```

İlk işlem commit olmuş fakat tarayıcı yanıtı alamamışsa aynı token ile gelen ikinci istek:

- Yeni kayıt oluşturmamalı.
- Aynı kaynak ID'sine yönlendirmeli.
- Aynı semantik başarı sonucunu döndürmelidir.

JSON endpointlerinde güvenli sonuç gövdesi yeniden oluşturulmalıdır.

### 3.9 Validation, rollback ve süreç kapanması

**Validation hatası:** İdempotency kaydı validation sonrasında oluşturulmalıdır. Validation başarısızsa token claim edilmez ve kullanıcı düzeltilmiş formu aynı token ile gönderebilir.

**İş kuralı hatası veya exception:** İdempotency kaydı ve finansal değişiklikler aynı transaction'daysa tamamı rollback olur ve token yeniden kullanılabilir.

**PHP süreci kapanması:** Commit gerçekleşmediyse bağlantı kapanınca MySQL transaction'ı rollback eder. Commit gerçekleştiyse `completed` kayıt bulunur ve retry sonucunu replay eder.

**Uzun işlem:** İkinci istek kısa süre DB kilidini bekleyebilir. Bekleme süresi aşılırsa önerilen sonuç `409 Conflict` ve `Retry-After` başlığıdır. `425 Too Early` alternatifi açık karar olarak tutulmaktadır.

### 3.10 Dosya ve dış yan etkiler

Veritabanı transaction'ı dosya sistemini geri alamaz. Aşağıdaki işlemler özel uyarlama gerektirir:

- Gider dokümanı
- Abonelik dekontu
- İçe aktarma dosyası
- E-posta/bildirim
- Gelecekteki ödeme sağlayıcı çağrıları

Önerilen dosya yaklaşımı:

1. Dosyayı geçici alana almak.
2. Dosya hash'ini fingerprint'e dahil etmek.
3. DB transaction'ını tamamlamak.
4. Dosyayı kalıcı konuma taşımak.
5. Hata halinde geçici dosyayı temizlemek.

E-posta ve bildirimler transaction içinde doğrudan gönderilmemeli; `afterCommit` job veya outbox kullanılmalıdır. Job'a aynı idempotency token aktarılmalıdır.

### 3.11 Saklama ve temizleme

Henüz onaylanmamış başlangıç önerisi:

- Finansal tamamlanmış işlemler: 180 gün
- Abonelik/sipariş işlemleri: 365 gün
- Düşük riskli yönetim işlemleri: 30 gün
- Rollback olmuş işlemler: kalıcı kayıt bırakmamalı

Günlük Artisan command, süresi dolan kayıtları örneğin 1.000'lik parçalar halinde temizleyebilir. Plan dönem anahtarları gibi kalıcı iş anahtarları bu temizlemeden etkilenmemelidir.

## 4. Alternatifler ve tercih gerekçesi

| Alternatif | Avantaj | Sorun | Değerlendirme |
| --- | --- | --- | --- |
| Sadece buton kilidi | Kolay ve iyi UX | HTTP tekrarı, iki sekme ve otomasyon için yetersiz | Tek başına kullanılmamalı |
| Laravel session `block()` | Aynı session isteklerini sıraya alır | Farklı session/API istemcisini kapsamaz, sonucu replay etmez | Yardımcı koruma |
| Cache `add/lock` | Hızlı ve atomik olabilir | Cache temizlenebilir; finansal transaction ile atomik değildir | Geçici optimizasyon |
| Her ana tabloya token alanı | Güçlü DB garantisi | Her model/controller için ayrı değişiklik gerekir | Doğal iş anahtarlarında kullanılabilir |
| Merkezi DB idempotency tablosu | Ortak, kalıcı, test edilebilir, replay destekli | Ortak servis ve dikkatli transaction sınırı gerekir | Önerilen yaklaşım |
| Yalnız doğal iş anahtarı | Plan+dönem için güçlü | Manuel ödeme/gider için doğal anahtar yok | Ortak token ile birlikte |

## 5. Mevcut gider koruması ve geçiş

Mevcut `ExpenseController@store` akışında:

- `submission_token` doğrulanmaktadır.
- `Cache::add()` ile bir günlük kullanım kaydı oluşturulmaktadır.
- İkinci gönderim engellenmektedir.
- Arayüzde submit butonu kilitlenmektedir.

Bilinen açıklar:

- Cache temizlenirse token tekrar kullanılabilir.
- Cache kaydı finansal transaction'dan önce oluşur.
- Cache başarılı olup DB transaction rollback olursa token tüketilmiş kalabilir.
- Cache kaydı Expense/Payment/CashTransaction ile aynı atomik sınırda değildir.
- Başarılı işlem sonrası yanıt kaybolursa oluşan Expense ID'si cache'te tutulmaz.
- Ortak olmayan cache store çoklu uygulama sunucusunda garanti vermez.

Planlanan geçiş:

1. İdempotency tablosu additive migration ile eklenir.
2. Ortak servis yalnız `expenses.store` üzerinde pilot olarak devreye alınır.
3. Mevcut hidden token alanı geçiş boyunca kabul edilir.
4. Cache koruması en az mevcut TTL süresince legacy fallback olarak tutulur.
5. Yeni işlemler DB idempotency kaydı üretir.
6. Geçiş doğrulandıktan sonra cache doğruluk katmanı olmaktan çıkarılır.
7. Geçmiş Expense veya muhasebe kayıtlarına backfill yapılmaz.

## 6. Kesin endpoint envanteri

Kısaltmalar:

- **OT:** Ortak token
- **İA:** Kalıcı doğal iş anahtarı
- **SK:** `lockForUpdate()`
- **TX:** Transaction
- **UI:** Submit kilidi

### 6.1 Finansal kayıt ve tahsisler

| Route | Controller | Veritabanı değişiklikleri | Mevcut koruma | Risk | Önerilen koruma | Öncelik |
| --- | --- | --- | --- | --- | --- | --- |
| `POST expenses.store` | `ExpenseController@store` | Expense, AccountTransaction; ödenmişse Payment, CashTransaction, PaymentAllocation | UI + cache token + TX | Kritik | OT + TX; ödenmiş akışta SK | Pilot |
| `POST expenses.payment.store` | `ExpenseController@storePayment` | Payment, CashTransaction, AccountTransaction, PaymentAllocation; Expense bakiye/durum | TX; SK yok | Kritik | OT + TX + Expense/Payment SK | P1 |
| `POST payments.store` | `PaymentController@store` | Payment, CashTransaction, AccountTransaction ve isteğe bağlı tahsisler | TX; tahsislerde kısmi SK | Kritik | OT + TX + Payment/Due/Expense SK | P1 |
| `POST accounts.supplier-payment.store` | `AccountController@storeSupplierPayment` | Payment, CashTransaction, AccountTransaction, isteğe bağlı gider tahsisi | TX; tahsis sırasında Expense SK | Kritik | OT + tek dış TX + Payment/Expense SK | P1 |
| `POST accounts.expenses.multi-pay.store` | `AccountController@storeMultiPayExpenses` | Payment, CashTransaction, AccountTransaction, çoklu PaymentAllocation, Expense durumları | TX; seçim transaction öncesi, SK yok | Kritik | OT + TX + Expense'ları ID sırasıyla SK | P1 |
| `POST dues.payment.store` | `DueController@storePayment` | Payment, CashTransaction, AccountTransaction, PaymentAllocation, Due bakiye/durum | TX; SK yok | Kritik | OT + TX + Due SK | P1 |
| `POST accounts.dues.bulk-pay` | `DueController@bulkPay` | Payment, CashTransaction, AccountTransaction, çoklu Allocation ve Due durumu | TX; SK yok | Kritik | OT + TX + seçili Due kayıtlarında SK | P1 |
| `POST supplier-refunds.store` | `PaymentController@storeSupplierRefund` | CashTransaction ve AccountTransaction | TX | Kritik | OT + TX | P1 |
| `POST cash.store` | `CashController@store` | CashTransaction | Tek insert; token yok | Yüksek | OT + TX | P1 |
| `POST payments.allocations.store` | `PaymentAllocationController@store` | PaymentAllocation, Due bakiye/durum, Payment kalan bakiye | TX; SK yok | Kritik | OT + TX + Payment/Due SK | P1 |
| `POST payments.supplier-allocations.store` | `PaymentAllocationController@supplierStore` | PaymentAllocation, Expense bakiye/durum, Payment kalan bakiye | TX; SK yok | Kritik | OT + TX + Payment/Expense SK | P1 |
| `POST accounts.payments.multi-allocate.store` | `PaymentAllocationController@multiStore` | Çoklu PaymentAllocation, Payment ve Due bakiyeleri | TX; SK yok | Kritik | OT + TX + bütün Payment/Due kayıtlarında SK | P1 |
| `POST accounts.payments.multi-supplier-allocate.store` | `PaymentAllocationController@multiSupplierStore` | Çoklu PaymentAllocation, Payment ve Expense bakiyeleri | TX; SK yok | Kritik | OT + TX + bütün Payment/Expense kayıtlarında SK | P1 |
| `POST dues.store` | `DueController@store` | DueBatch, bir veya çok Due ve AccountTransaction | TX; istek anahtarı yok | Kritik | OT + TX; kaynak türüne göre İA | P2 |
| `POST dues.transfer` | `DueController@transfer` | Due hesap/bölüm ilişkisi ve bağlı cari kayıt güncellemeleri | TX + bazı hedef mükerrer kontrolleri | Yüksek | OT + TX + Due/hesap SK | P2 |

### 6.2 İçe aktarma ve belge işlemleri

| Route | Controller | Veritabanı değişiklikleri | Mevcut koruma | Risk | Önerilen koruma | Öncelik |
| --- | --- | --- | --- | --- | --- | --- |
| `POST accounts.bulk-import-confirm` | `AccountController@bulkImportConfirm` | Account, Unit bağlantıları, Category, CashBox, Due/Expense/Payment ve hareket kayıtları | TX + bazı `firstOrCreate` kontrolleri | Kritik | OT + dosya/import İA + TX + SK | P2 |
| `POST accounts.imported.destroy-all` | `AccountController@destroyAllImported` | İçe aktarılmış finansal kayıtları topluca siler | TX; ikinci çalıştırma çoğunlukla boş | Yüksek | OT + TX + silinecek küme fingerprint'i | P2 |
| `POST expenses.documents.store` | `ExpenseController@storeDocument` | ExpenseDocument ve fiziksel dosya | Token yok | Orta | OT + dosya hash'i + geçici dosya | P3 |

Not: Kod tabanında gider içe aktarma görünümleri bulunsa da inceleme tarihindeki aktif route tablosunda `expenses.import-confirm` bulunmamaktadır. Kapsama eklenmeden önce aktif kullanılabilirliği ayrıca doğrulanmalıdır.

### 6.3 Planlama işlemleri

| Route | Controller | Veritabanı değişiklikleri | Mevcut koruma | Risk | Önerilen koruma | Öncelik |
| --- | --- | --- | --- | --- | --- | --- |
| `POST due-plans.store` | `DuePlanController@store` | Onay aşamasında DuePlan, DueBatch, Due ve AccountTransaction | Session token, fingerprint, TX, Apartment/Plan SK, aktif yıl unique | Yüksek | OT + mevcut token/fingerprint + TX + İA | P2 |
| `POST due-plans.generate-month` | `DuePlanController@generateMonth` | DueBatch, eksik Due ve AccountTransaction | Plan SK + `firstOrCreate`; DB unique eksik | Yüksek | OT + TX + SK + `due_plan_id/period` İA | P2 |
| `POST due-plans.regenerate-period` | `DuePlanController@regeneratePeriod` | Eksik Due ve hareketler | Plan SK ve mevcut kayıt uzlaştırması | Yüksek | OT + TX + SK + dönem İA | P2 |
| `POST due-plans.confirm-past-periods` | `DuePlanController@confirmPastPeriods` | Birçok dönem için DueBatch, Due ve hareketler | Plan üretici kontrolleri | Yüksek | OT + dönem İA + SK | P2 |
| `POST expense-plans.store` | `ExpensePlanController@store` | DB yazmaz; onay özeti/session üretir | Session token/fingerprint | Düşük | UI kilidi; OT gereksiz | P3 |
| `POST expense-plans.confirm` | `ExpensePlanController@confirm` | ExpensePlan, birden fazla Expense ve AccountTransaction | Route `block`, session token, TX, SK, `plan_occurrence_key` unique | Orta | Mevcut İA korunur; OT replay için değerlendirilebilir | P3 |
| `POST expense-plans.generate-month` | `ExpensePlanController@generateMonth` | Expense ve AccountTransaction | Plan SK + kalıcı occurrence key unique | Orta | İA + SK; UI/OT replay için değerlendirilebilir | P3 |
| `POST assessment-plans.store` | `AssessmentPlanController@store` | DB yazmaz; onay özeti/session üretir | Session token/fingerprint | Düşük | UI kilidi; OT gereksiz | P3 |
| `POST assessment-plans.confirm` | `AssessmentPlanController@confirm` | Category, AssessmentPlan, taksitler, satırlar, Due ve AccountTransaction | Route `block`, session token, TX, SK, benzersiz taksit/satır indeksleri | Orta | Mevcut İA/SK + OT replay için değerlendirilebilir | P3 |
| `POST assessment-plans.cancel-future` | `AssessmentPlanController@cancelFuture` | Taksit durumları; uygun Due ve AccountTransaction silme | TX + Plan/Installment SK | Yüksek | OT + TX + SK | P2 |
| `POST apartments.trigger-aidat` | `SubscriberApartmentController@triggerAidatGeneration` | Plan üzerinden DueBatch, Due ve AccountTransaction | Plan uzlaştırması ve kilitleri | Yüksek | OT + dönem İA + SK | P2 |
| `POST subscriber.apartments.trigger-aidat` | Aynı controller metodu | Yukarıdakiyle aynı | Yukarıdakiyle aynı | Yüksek | Aynı operation ailesi, ayrı route kapsamı | P2 |

`due_batches` tablosunda `due_plan_id + period` için DB unique indeksi görünmemektedir. Kod `firstOrCreate` kullansa da eşzamanlı transaction yarışı mümkündür. İndeks önerisi uygulanmadan önce gerçek veride mükerrer dönem denetimi zorunludur.

### 6.4 Abonelik, sipariş ve yönetim işlemleri

| Route | Controller | Veritabanı değişiklikleri | Mevcut koruma | Risk | Önerilen koruma | Öncelik |
| --- | --- | --- | --- | --- | --- | --- |
| `POST subscriber.subscriptions.store` | `SubscriberSubscriptionController@store` | BillingProfile, UserSubscription, SubscriptionItem, LegalAcceptance ve dekont dosyası | TX; rastgele unique sipariş numarası | Kritik | OT + TX + apartman kapsama SK + dosya evreleme | P2 |
| `POST subscriber.subscriptions.payment-info` | `SubscriberSubscriptionController@paymentInfo` | Referans/dekont bilgisi ve dosya | Durum ve “proof var” kontrolü; SK yok | Yüksek | OT + Subscription SK + dosya evreleme | P2 |
| `POST subscriber.subscriptions.cancel` | `SubscriberSubscriptionController@cancel` | Subscription/Item durumları ve kapsama | Durum kontrolleri | Orta | OT veya durum tabanlı idempotent update + SK | P3 |
| `POST admin.managers.subscription.cancel` | `AdminManagerController@cancelSubscription` | Abonelik durumları | Durum kontrolü | Orta | SK + durum tabanlı idempotency | P3 |
| `POST cash-boxes.store` | `CashBoxController@store` | CashBox ve olası açılış hareketi | TX | Orta | OT; açılış varsa aynı TX | P3 |
| `POST apartments.wizard.cash-box.store` | `ApartmentWizardController@storeCashBox` | Apartman kurulumundaki CashBox kaydı/güncellemesi | TX | Düşük | UI + kurulum adımı İA | P3 |

### 6.5 Yazma yapmayan POST'lar

Aşağıdaki endpointler önizleme veya form hazırlama amaçlıdır ve finansal idempotency tablosuna alınmamalıdır:

- `accounts.bulk-import-preview`
- `accounts.expenses.multi-pay`
- `accounts.payments.multi-allocate`
- `accounts.payments.multi-supplier-allocate`
- `assessment-plans.distribution-preview`
- `financial-plans.select-type`

## 7. Ortak arayüz koruması

### 7.1 Opt-in kullanım

Her forma otomatik uygulanmamalıdır. Korunacak formlar açıkça işaretlenmelidir:

```text
data-idempotent-form
data-pending-label="Kaydediliyor..."
```

Ortak Blade parçası token alanını ve operation bilgisini sağlamalıdır.

### 7.2 İlk geçerli submit

- `submit` olayı tarayıcı doğrulamasından sonra işlenir.
- Form `data-submitting=true` yapılır.
- Submit butonları devre dışı bırakılır.
- Tıklanan butonda bekleme durumu gösterilir.
- `aria-busy=true` eklenir.
- İkinci submit olayı engellenir.

### 7.3 Birden fazla submit butonu

`event.submitter` üzerinden tıklanan buton belirlenmelidir. Buton devre dışı kaldığında `name/value` gönderilmeyeceği için önce hidden kopyası oluşturulmalıdır. Bu davranış özellikle plan ekranlarındaki “Özeti Güncelle”, “Geri Dön” ve “Onayla ve Kaydet” seçenekleri için gereklidir.

### 7.4 Doğrulama hataları

- HTML validation başarısızsa form kilitlenmemelidir.
- Özel JavaScript validation başarısızsa ortak mekanizma kilidi açabilmelidir.
- Sunucu 422 döndürürse token tüketilmediği için form tekrar kullanılabilmelidir.
- Redirect-back validation sonucunda token'ın claim edilip edilmediği merkezi servis tarafından belirlenmelidir.

### 7.5 AJAX/fetch

Ortak yardımcı:

- Token'ı `Idempotency-Key` başlığına eklemeli.
- Promise devam ederken ikinci çağrıyı engellemeli.
- 422 sonucunda formu açmalı.
- 409 `processing` sonucunda yeniden deneme bilgisini göstermeli.
- Replay cevabını normal başarı cevabı gibi işlemelidir.

### 7.6 Tarayıcı geri dönüşü

Başarılı submit sonrası bfcache ile dönülen form otomatik yeni token üretmemelidir. Form “daha önce gönderildi” durumunda kalmalı ve yeni işlem için form GET'inin yeniden açılması istenmelidir. Sunucu replay koruması son güvenlik katmanı olmalıdır.

### 7.7 Modal ve özel uyarlamalar

Özel uyarlama gerektirenler:

- Gider ödeme modalı
- Aidat ödeme modalı
- Tahsilat FIFO onayı
- Tedarikçi ödeme FIFO onayı
- Plan onay modalları
- Toplu tahsisler
- Dosya/dekont yükleyen işlemler
- İçe aktarma preview/confirm zincirleri

Aynı sayfadaki farklı finansal modal işlemleri aynı token'ı paylaşmamalıdır.

Ortak mekanizmaya doğrudan uygun ilk formlar:

- Gider oluşturma
- Gider ödeme sayfası
- Tedarikçi iadesi
- Manuel kasa hareketi
- Tekil tahsis sayfaları
- Toplu borçlandırma
- Abonelik siparişi

## 8. Gider pilotu

Pilot yalnız `POST expenses.store` üzerinde uygulanmalıdır.

### 8.1 Pilot bileşenleri

- Additive `idempotency_requests` migration'ı
- Model ve merkezi servis
- Request fingerprint servisi
- Idempotency result/replay yapısı
- Opt-in Blade hidden alanı
- Ortak submit kilidi
- Gider formu entegrasyonu
- Cache korumasından kontrollü geçiş
- Unit, feature ve MySQL concurrency testleri

### 8.2 Doğrulanacak davranışlar

- Aynı token ile yalnız bir Expense
- Ödenmiş giderde yalnız bir Payment
- Yalnız bir CashTransaction
- Beklenen sayıda AccountTransaction ve PaymentAllocation
- Yanıt kaybolduğunda aynı Expense sonucunun replay edilmesi
- Rollback sonrası token'ın yeniden kullanılabilmesi
- Aynı token ve farklı payload'ın reddedilmesi
- Yeni token ile bilinçli ikinci işlemin kabul edilmesi

### 8.3 Pilot tamamlanma kriterleri

- Ardışık ve eşzamanlı çift POST tek finansal sonuç üretir.
- Cache temizlenmesi doğruluğu etkilemez.
- Validation hatası token tüketmez.
- Exception bütün kayıtları ve idempotency claim'ini rollback eder.
- Mevcut gider iş kuralları değişmez.
- Geçmiş gider ve muhasebe kayıtları güncellenmez.

## 9. Test stratejisi

| Senaryo | Otomatik test yaklaşımı |
| --- | --- |
| Aynı token ile ardışık iki gönderim | İlk POST başarılı; ikinci aynı redirect/resource; tablo adetleri değişmez |
| Aynı token ile eşzamanlı iki gönderim | İki bağımsız PHP süreci/DB bağlantısı; yalnız bir ana ve beklenen alt kayıtlar |
| İlk işlem başarılı, HTTP yanıtı kayıp | İlk yanıt okunmadan bağlantı kapatılır; ikinci POST sonucu replay eder |
| İlk işlem validation hatalı | İdempotency kaydı oluşmaz; düzeltilmiş aynı token başarılı olur |
| Exception ve rollback | Test hook ile yazma sonrası exception; finansal kayıtlar ve token rollback olur |
| Aynı token, farklı tutar | İkinci istek 409; veri değişmez |
| Yeni token, bilinçli ikinci işlem | İki ayrı kayıt oluşur |
| Bir işlemden çoklu hareket | Ana kayıt ve Payment/Cash/AccountTransaction/Allocation adetleri doğrulanır |
| Farklı kullanıcı/apartman aynı token | 409/403; mevcut işlem ayrıntıları sızdırılmaz |
| İşlem sürerken yeniden deneme | İkinci istek commit sonrası replay eder veya timeoutta 409 + Retry-After alır |

### 9.1 SQLite kapsamı

SQLite üzerinde çalıştırılabilecek testler:

- Servis sözleşmesi
- Validation
- Hash karşılaştırması
- Replay
- Rollback
- Kullanıcı/apartman kapsamı
- Ardışık tekrar

### 9.2 MySQL eşzamanlılık kapsamı

Gerçek eşzamanlılık MySQL 8 üzerinde doğrulanmalıdır:

1. Ayrı test veritabanı hazırlanır.
2. `migrate:fresh` uygulanır.
3. PHPUnit ana sürecinin transaction wrapper'ı kullanılmaz.
4. İki bağımsız PHP process veya HTTP istemcisi aynı token ile başlatılır.
5. İlk işlem idempotency insert'inden sonra test bariyerinde bekletilir.
6. İkinci istek gönderilir.
7. İlk süreç serbest bırakılır.
8. Yanıtlar ve bütün tablo adetleri doğrulanır.

Bu testler ayrı `mysql-concurrency` grubu/CI işi olarak çalıştırılmalıdır. SQLite testleri eşzamanlılık garantisi olarak kabul edilmemelidir.

## 10. Aşamalı uygulama planı

| Aşama | Kapsam | Yaklaşık endpoint | Dosya/modül | Migration ve test | Geri dönüş | Tamamlanma kriteri |
| --- | --- | ---: | --- | --- | --- | --- |
| 0 | Tasarım ve veri ön denetimi | 0 | Route/indeks/veri denetimleri | Migration yok | Gerekmiyor | Tasarım ve açık kararların onayı |
| 1 | Gider pilotu | 1 | Idempotency modeli/servisi, fingerprint, Blade/JS, ExpenseController | 1 additive migration; 10–15 test | Feature flag; tablo korunur; geçici cache fallback | Aynı token her koşulda tek gider |
| 2 | Kritik ödeme/tahsilat | Yaklaşık 12 | Payment, Expense, Due, Account ve Allocation controllerları; ilgili formlar | Satır kilidi düzenlemeleri; 25–35 test | Endpoint bazlı feature flag | Tek/çoklu ödeme ve tahsislerde mükerrerlik yok |
| 3 | Borçlandırma ve içe aktarma | 4–6 | DueController, Account import, toplu işlem görünümleri | Gerekirse import iş anahtarı; 15–25 test | Token entegrasyonu kapatılabilir; şema korunur | Aynı batch/import ikinci kez oluşmaz |
| 4 | Planlama | Yaklaşık 10 | DuePlan, ExpensePlan, AssessmentPlan, tetikleme route'ları | DueBatch dönem unique indeksi yalnız veri denetimi sonrası; 20–30 test | İndeks öncesi preflight ve yedek | Plan+dönem eşzamanlı üretimde tek sonuç |
| 5 | Abonelik ve dosya | Yaklaşık 4 | SubscriptionCheckout, dekont, iptal işlemleri | Dosya staging/outbox gerekebilir; 15–20 test | Eski dosya akışına feature flag | Tek sipariş ve tek dekont sonucu |
| 6 | Düşük riskli yönetim | 3+ | CashBox ve seçilmiş CRUD formları | Genellikle migration yok | UI özelliği kapatılabilir | Tutarlı kullanıcı deneyimi |
| 7 | Temizlik ve zorunluluk | Korunan tüm route'lar | Scheduler/command, metrik ve loglar | Retention command testleri | Token geçici optional yapılabilir | Korunan route'larda token zorunlu |

Üretimde geri dönüş gerektiğinde finansal kayıtlar veya idempotency kayıtları silinmemelidir. Özellik endpoint bazlı feature flag ile devre dışı bırakılmalı; additive tablo daha sonraki kontrollü bakım kararına kadar korunmalıdır.

## 11. Kritik riskler

1. Ortak token tek başına farklı token'larla oluşan bakiye yarışlarını engellemez; satır kilitleri zorunludur.
2. `payment_allocations` üzerinde mevcut yarış koşulları ayrıca düzeltilmelidir.
3. `due_batches` için plan+dönem benzersiz indeksi bulunmaması eşzamanlı üretim riski taşır.
4. Dosya sistemi DB transaction'ıyla rollback olmaz.
5. Uzun içe aktarmalar job'a taşınırsa `processing + heartbeat` için ayrı tasarım gerekir.
6. Middleware ile bütün response'u otomatik başarılı kabul etmek güvenli değildir; validation sonrası merkezi servis sınırı tercih edilmelidir.
7. Route `block()` ve session tokenları doğal iş fingerprint'inin yerine geçmez.
8. Mevcut plan confirmation tokenları kaldırılmamalıdır; özetin değişmediğini doğrulayan ayrı bir işleve sahiptir.
9. Benzersiz indeks migration'ları gerçek veride ön denetim yapılmadan çalıştırılmamalıdır.
10. İdempotency sonucunda kullanıcıya başka kullanıcı veya apartmana ait kaynak bilgisi sızdırılmamalıdır.

## 12. Açık karar noktaları

Aşağıdaki maddeler uygulama öncesinde ayrıca onaylanmalıdır:

1. UUIDv4 veya UUIDv7 seçimi.
2. Ortak hidden alan adının `idempotency_token` olup olmayacağı.
3. Tamamlanmış finansal token saklama süresi: başlangıç önerisi 180 gün.
4. Abonelik/sipariş token saklama süresi: başlangıç önerisi 365 gün.
5. İşlem sürerken `409 Conflict` veya `425 Too Early` kullanımı.
6. Replay sırasında aynı flash mesajının yeniden gösterilip gösterilmeyeceği.
7. Pilot sonrasında korunan route'larda token'ın ne zaman zorunlu olacağı.
8. Dosya işlemlerinde geçici dosya yaklaşımı veya outbox seçimi.
9. Uzun içe aktarmaların senkron kalması veya job'a taşınması.
10. `due_batches(due_plan_id, period)` benzersiz indeksinin veri denetimi sonrasında eklenip eklenmeyeceği.
11. Feature flag'in yapılandırma/env tabanlı mı yoksa route/özellik tabanlı mı olacağı.
12. MySQL concurrency testlerinin yerel ve CI çalışma biçimi.

## 13. Geçmiş veri ve canlı ortam sınırları

- Geçmiş Expense, Payment, Due, CashTransaction, AccountTransaction veya PaymentAllocation kayıtları değiştirilmemelidir.
- Geçmiş işlemler için idempotency backfill yapılmamalıdır.
- Muhasebe kayıtları yeniden oluşturulmamalıdır.
- Benzersiz iş anahtarı eklenmeden önce yalnızca salt-okunur mükerrerlik analizi yapılmalıdır.
- Canlı ortamda uygulama, migration veya veri değişikliği ayrıca açık onay verilmeden yapılmamalıdır.
- Bu planın kaydedilmesi hiçbir geliştirme aşamasını başlatmaz.

## 14. Devam kontrol listesi

Uygulamaya başlanmadan önce:

- [ ] Teknik mimari ve açık kararlar onaylandı.
- [ ] Pilot migration şeması onaylandı.
- [ ] Token saklama ve temizleme süreleri onaylandı.
- [ ] Replay HTTP davranışı onaylandı.
- [ ] MySQL concurrency test ortamı belirlendi.
- [ ] Gider pilotu feature flag ve geri dönüş yöntemi onaylandı.
- [ ] Pilotun canlıya dağıtımından önce lokal ve staging sonuçları gözden geçirildi.

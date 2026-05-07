# Payment / Due Refactor ve Ledger Geçiş Planı

## Amac
Bu plan, mevcut `Due` / `Payment` birebir ilişkisini `PaymentAllocation` yapısına dönüştürmeyi ve `AccountTransaction` tablosunu merkezi ledger olarak kullanmayı hedefler.

## Hedef mimari

### İlk çalışan versiyon
- `Due` borç belgesi olarak kalır.
- `Payment` ödeme belgesi olarak kalır.
- `PaymentAllocation` ile `Payment` ↔ `Due` ilişkisi many-to-many hale gelir.
- `Due.remaining_amount` ile açık bakiye tutulur.
- `Payment.unallocated_amount` ile ödeme içindeki henüz eşlenmemiş tutar tutulur.
- `AccountTransaction` merkezi ledger olarak kullanılır; her hareket bir belgeyle ilişkilendirilebilir.

### Nihai hedef
- `Due` / `Payment` / `Expense` / `Refund` gibi belgeler business dokümanlarıdır.
- `PaymentAllocation` / `ManualAdjustment` gibi mahsubeler belge seviyesinde eşleştirme sağlar.
- `AccountTransaction` / `LedgerEntry` tüm finansal etkileri merkezi olarak tutar.
- `Due.status` yerine `remaining_amount` üzerinden durum belirlenir.
- `payments.due_id` kaldırılır.

## Adımlar

### 1. Şema genişletme (ilk deploy)
- `payment_allocations` tablosunu oluştur.
- `dues.remaining_amount` kolonu ekle.
- `payments.unallocated_amount` kolonu ekle.
- `account_transactions.transactionable_type` ve `account_transactions.transactionable_id` kolonlarını ekle.

### 2. Mevcut veriyi backfill
- Her `payment` için eğer `due_id` varsa aynı tutarla `payment_allocations` kaydı oluştur.
- `due.remaining_amount = due.amount - sum(payment_allocations.amount)` hesapla.
- `payment.unallocated_amount = payment.amount - sum(payment_allocations.amount)` hesapla.
- Gerekirse `due.status` değerlerini `paid/partial/unpaid` olarak güncelle.

### 3. Model + ilişki güncellemeleri
- `Due` modeli: `allocations()`, `payments()`, `getRemainingAmountAttribute()` gibi ilişkiler ekle.
- `Payment` modeli: `allocations()`, `dues()`, `unallocatedAmount()` gibi ilişkiler ekle.
- `AccountTransaction` modeli: `transactionable()` polimorfik ilişki ekle.

### 4. İş mantığını taşıma
- `DueController@storePayment` ve ilgili ödeme akışlarını `PaymentAllocation` destekli hale getir.
- Ödeme sırasında: `payment_allocations` kaydı oluştur, `due.remaining_amount` ve `payment.unallocated_amount` güncelle.
- Fazla ödeme durumunda `payment.unallocated_amount` bakiye olarak kalsın.
- Sonradan eşleme senaryosu için `payment_allocations` backend UI desteği ekle.

### 5. Backward compatibility koruma
- `payments.due_id`: önce sadece read-only tut, sonra yeni yapı ile tam geçişte kaldır.
- `dues.status`: bir süre mevcut kalsın, ancak yeni kod `remaining_amount` üzerinden karar versin.
- `accounts.balance`: eğer mevcut raporlar ona bağlıysa hemen silme.

### 6. Eski alanları temizleme
- Yeni sistem stabil çalıştıktan sonra `payments.due_id` ve `dues.status` kaldır.
- Gereksiz `balance` hesaplarından kurtul.

## Migration sıralaması
1. `2026_05_07_000009_create_payment_allocations_table.php`
2. `2026_05_07_000010_add_remaining_and_unallocated_fields_to_dues_and_payments.php`
3. `2026_05_07_000011_add_transactionable_to_account_transactions.php`

## Minimum ilk tamamlanabilir adımlar
1. `payment_allocations` tablosu ve yeni kolanlar eklemek.
2. `AccountTransaction` için belge bağlantısını eklemek.
3. Mevcut ödemeleri `payment_allocations` ile backfill etmek.
4. `Payment` modeline ilişkileri eklemek.
5. Ödeme kaydını `PaymentAllocation` ile yazdırmak.

## Riskli noktalar
- `remaining_amount` ile `payment_allocations` toplamları arasında uyumsuzluk.
- Eski `payments.due_id` mantığının yeni sistemle çakışması.
- `dues.status` eski / yeni kod arasında çelişmesi.
- `AccountTransaction` tablosuna eklenen `transactionable` alanlarının eski raporları bozmaması.
- Aynı anda ödeme girilirken `remaining_amount` güncelleme yarışları.

## Test aşaması için önerilen yol
- Önce migrationları çalıştırıp test veritabanında schema genişlet.
- Yeni backfill scriptini test verisiyle çalıştır.
- `DueController@storePayment`’i yeni model ile küçük bir beta route üzerinden test et.
- `payments.due_id` ile yeni `payment_allocations` aynı anda yazılsın; sonra bir süre sadece `payment_allocations` kullan.
- Son olarak `payments.due_id` ve `dues.status` için cleanup migration planla.

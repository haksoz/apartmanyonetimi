# Tedarikçi Toplu Gider Ödeme — Tasarım Notu

## Problem
Şu an sistem "1 gider = 1 ödeme" mantığıyla çalışıyor.
Gerçek kullanım senaryosunda ise aynı tedarikçiye birden fazla gider yazılıp (lamba, kablolama, tamirat vb.) kasadan tek toplu ödeme yapılabiliyor.

## Mevcut Yapı
- `expenses.payment.create` / `storePayment` → tek gideri doğrudan `is_paid = true` yapar
- `CashTransaction (expense)` + `AccountTransaction (debit)` ikili yazılır
- Kısmi ödeme veya fazla ödeme kavramı yok
- `Expense` modelinde `remaining_amount` alanı yok

## Hedef Yapı
Aidat ödemesiyle aynı mantık, ters yönde:

| Aidat Ödemesi | Gider Ödemesi |
|---|---|
| Kiracı → Apartman (tahsilat) | Apartman → Tedarikçi (ödeme) |
| `Payment` oluştur | `SupplierPayment` veya `Payment` genişletilir |
| `PaymentAllocation` → `Due` | `ExpenseAllocation` → `Expense` |

## Tasarım Seçenekleri

### A) Mevcut `Payment` modelini genişlet (önerilen)
- `Payment.type` alanı ekle: `incoming` / `outgoing`
- `PaymentAllocation` polimorfik yapıya geç: `allocatable_type` + `allocatable_id`
- Hem `Due` hem `Expense` tahsis edilebilir

### B) Ayrı `SupplierPayment` modeli
- `supplier_payments` tablosu
- `supplier_payment_allocations` tablosu → `expense_id`
- Temiz ama fazladan iş

### C) `Expense` modeline `remaining_amount` ekle (Payment ile aynı mantık)
- Migration: `expenses.remaining_amount`
- `ExpenseAllocation` modeli: `supplier_payment_id` + `expense_id` + `amount`
- `SupplierPayment`: `account_id`, `amount`, `unallocated_amount`, `payment_date`

## Gerekli Değişiklikler (C seçeneği için)
1. Migration: `supplier_payments` tablosu
2. Migration: `expense_allocations` tablosu
3. Migration: `expenses.remaining_amount` kolonu
4. `SupplierPayment` model
5. `ExpenseAllocation` model
6. `SupplierPaymentController` (create, store, show, destroy)
7. `expenses/show` → tahsis bilgisi göster
8. `accounts/show` tedarikçi bölümü güncelle
9. Route'lar

## İlgili Dosyalar
- `app/Http/Controllers/ExpenseController.php` — `storePayment` metodu değişecek
- `app/Models/Expense.php` — `remaining_amount`, `allocations` ilişkisi eklenecek
- `resources/views/expenses/show.blade.php`
- `resources/views/accounts/show.blade.php`
- `routes/web.php`

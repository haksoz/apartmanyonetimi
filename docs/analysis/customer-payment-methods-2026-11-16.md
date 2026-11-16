# Müşteriye göre ödeme yöntemi izni

16 Kasım 2026.

- `users.bank_transfer_allowed` varsayılan false. Yeni ve mevcut müşterilere otomatik Havale / EFT izni verilmez.
- Yönetici, Müşteriler → müşteri detayı → Ödeme yöntemleri bölümünde Havale / EFT ile ödeme iznini açıp kapatabilir. Ayrı PATCH endpoint mevcut admin yetkilendirmesi/CSRF koruması altındadır; kullanıcı alanı public mass assignment listesine eklenmedi.
- Yeni abonelik siparişi yöntemleri `CheckoutPaymentMethods` üzerinden belirlenir. Varsayılan karttır; havale yalnızca bu müşteriye izin verilmişse eklenir. Kartın genel açık/kapalı ve PayTR test hesabı kısıtları korunur.
- Tek yöntem olduğunda ödeme yöntemi seçici gösterilmez; tek yöntem hidden input ile gönderilir. İki yöntem açıksa seçim gösterilir. Hiç yöntem açılamıyorsa kullanıcı bilgilendirilir ve son onay devre dışı kalır; havale otomatik alternatif yapılmaz. Kapat düğmesi bu durumda da çalışır.
- Kart iletişim alanları yalnızca kart seçiliyken görünür/gönderilir. Sözleşme önizlemesi tek yöntemde de doğru yöntemi kullanır.
- Sunucu POST doğrulaması ve işlem kilidi altında güncel izin tekrar kontrol edilir. SubscriptionCheckout servisi de yeni havale siparişinde izni doğrular. Eski ekranı açık bırakmak veya sahte alan göndermek izin vermez.
- İznin kapatılması, daha önce izinle oluşturulmuş havale siparişini/ödemesini silmez. Mevcut siparişin dekont/referans ve manuel tahsilat akışı korunur; kısıt yeni havale siparişleri içindir.
- Migration `2026_11_16_000003_add_customer_bank_transfer_permission` yerel veritabanına uygulandı. Yayında da uygulanmalıdır.
- Eski havale senaryolarındaki test müşterileri açıkça withBankTransfer factory state'i kullanır; normal müşteri varsayılanı testte de kapalıdır.

Frontend build bu çalışmada çalıştırılmadı.

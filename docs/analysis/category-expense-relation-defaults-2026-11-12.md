# Kategori bazında varsayılan gider ilişkisi

Bu ilk tekli ayar tasarımı aynı gün çoklu kategori ilişkileriyle değiştirildi. Güncel davranış için `category-multiple-expense-relations-2026-11-12.md` dosyasına bakın.

`categories.default_expense_relation` eklendi. Değerleri mevcut `ExpenseRelation` ile aynı: `unassigned`, `aidat` (İşletme Gideri), `malik_payi` (Kat Maliki Gideri).

Ortak Elektrik, Ortak Su, Ortak Temizlik, Ortak Asansör ve Yönetim Giderleri kategorilerinde varsayılan İşletme Gideri ve kategori düzenleme ekranında kilitlidir. Model ve sunucu bu değişikliği de engeller. Yeni oluşturulan aynı adlı kategoriler aynı varsayılanı alır.

Diğer tüm kategorilerde varsayılan seçilebilir. Diğer alanları kilitli olan Demirbaş, Bakım Onarım, Diğer ve Genel Aidat kategorilerinde ilişki ayarının düzenlenmesi diğer kilitleri kaldırmaz. Demirbaş'ın Aidat yetkisi ayrıca düzenlenebilir kalır. Diğer kategorilerin başlangıç varsayılanı Henüz Belirlenmedi'dir.

Gider oluşturma/düzenleme formunda kategori değiştiğinde önerilen ilişki seçilir. Kullanıcı gider üzerinde başka bir seçim yapabilir. Düzenleme sayfası açıldığında mevcut giderin ilişkisi korunur; doğrulama hatası dönüşünde son seçim de korunur. Tedarikçiden otomatik kategori seçimi aynı mekanizmayı tetikler.

Sunucuda yeni gider oluşturulurken açık ilişki gönderilmemişse kategori varsayılanı kullanılır. Açıkça gönderilen Henüz Belirlenmedi dahil tüm geçerli seçimler korunur. Mevcut gider güncellenirken ilişki gönderilmezse eski değer korunur. Yeni planlı giderler de üretim anındaki kategori varsayılanını alır.

Kategori ayarının değişmesi önceki giderleri veya rapor verilerini yeniden sınıflandırmaz. Migration yerelde uygulandı; kategoriye eklenen yeni alan hariç kategori, gider, borç, ödeme, tahsis, hesap hareketi, kasa hareketi ve gider planı verilerinin önce/sonra parmak izleri aynı kaldı. Canlı ortama uygulanmadı.

Doğrulama: ilgili PHP paketinde 116 test başarılı; planlı gider için ek varsayılan kontrolü de başarılı. Kategori değişimi, elle seçim, düzenlemede seçim koruma ve doğrulama dönüşü için 4 JavaScript testi başarılı. Blade ve üretim derlemeleri başarılı.

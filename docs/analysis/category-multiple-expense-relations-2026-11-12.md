# Kategoride çoklu gider ilişkisi

Kategori ayarı `expense_relations` JSON dizisidir. İşletme Gideri (`aidat`) ve Kat Maliki Gideri (`malik_payi`) kutuları bağımsız seçilebilir. Eski tekli `default_expense_relation` alanı yeni migration ile dönüştürülüp kaldırıldı.

Ortak Elektrik, Ortak Su, Ortak Temizlik, Ortak Asansör ve Yönetim Giderleri yalnızca İşletme Gideri seçili ve kilitlidir. Diğer kategorilerde bu ayar, kategorinin kalan alanları kilitli olsa da değiştirilebilir. Gider kullanım yetkisi olmayan kategorilerde alan gizlidir ve liste boş tutulur. Tüm kutuların kaldırılması boş liste olarak kaydedilir.

Kategori üzerindeki liste gider için öneriyi belirler; giderin `expense_relation` alanı tek değer olmaya devam eder. Tek kategori ilişkisi varsa otomatik önerilir. İki ilişki veya boş liste varsa otomatik tercih yapılmaz; Henüz Belirlenmedi seçilir. Gider kaydında kullanıcı açıkça başka bir geçerli seçim yapabilir. Bir gider tutarı iki rapora birden yazılmaz. Eski giderler ve doğrulama hatası sonrası kullanıcının seçimi korunur. Planla yeni üretilen giderlerde de aynı tek öneri kuralı kullanılır.

Yerel migration öncesi tekli ayarlar `storage/app/private/category-multiple-relations-backup-2026-11-12.json` dosyasına yedeklendi. 19 kategori dönüştürüldü; her eski seçimin yeni listeye doğru aktarıldığı ve 12 tablodaki kategori ilişkisi dışındaki verilerin değişmediği doğrulandı. Canlıya uygulanmadı. Geri dönüş tekli alanı geri getirir; iki seçimli listeyi tekli değerde temsil edemediği için bu durumda Henüz Belirlenmedi kullanır.

Doğrulama: ilgili pakette 120 PHP testi, ayrıca 1 migration testi ve 4 JavaScript testi başarılı. Kategori/borç bağlantılarının şema dönüşümünde korunduğu; iki ilişkinin birlikte seçilmesi, seçimin temizlenmesi ve giderde tek seçimin korunması test edildi. Blade ve üretim derlemeleri başarılı.

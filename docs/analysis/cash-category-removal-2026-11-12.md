# Kasa kategorisinin kaldırılması

12 Kasım 2026: `cash_transactions.category_id` kaldırıldı. Gider ve borç kategorileri korunuyor. Bağımsız kasa girişi için mevcut erişim davranışı değiştirilmedi.

Kasa kaydına kategori kopyalayan gider ödeme, aidat tahsilatı, tedarikçi iadesi ve hesap ödeme yolları temizlendi. Kasa formu ve detaylarından kategori alanı, kategori silme denetiminden kasa bağımlılığı kaldırıldı. Kasa açılış bakiyesi sorgusu artık kaldırılan sütunu kullanmıyor.

Muhasebe listesinin kategori filtresi, ödemelerde tahsis edilen gider/borç kategorisini kullanıyor. Bir ödeme birden fazla kategoriye dağıtılmışsa her ilgili kategori aramasında bulunabilir; bu filtre tutarı kategorilere bölerek raporlamaz. Bağlanmamış ödemeler bir kategoriye zorla atanmaz. Kasa açıklaması ve referansı metin aramasında kullanılmaya devam eder; kasa kaydına bağlantılar korunur.

Yerel migration öncesinde 884 kasa kaydı ve 49 dolu kategori bulundu. Bunların 19'unda doğrudan ödeme/gider bağlantısı yoktu. Eski kategori değerleri sürüm kontrolü dışındaki `storage/app/private/cash-category-removal-backup-2026-11-12.json` dosyasına yedeklendi.

Yerel migration uygulandı. Kategori sütunu hariç tüm kasa kayıtları ile kasa, gider, borç, ödeme, tahsis, hesap hareketi, hesap ve kategori tablolarının önce/sonra parmak izleri birebir eşleşti. Canlı ortama uygulanmadı.

Canlı geçişte kod ve migration birlikte yayımlanmalı; sütun kaldırılmadan önce eski kategori değerleri yedeklenmelidir. Migration'ın `down` adımı yalnızca boş kategori sütununu geri ekler. Eski değerler gerekiyorsa yedekten kayıt kimliğiyle geri yüklenmelidir.

Regresyon testi şema kaldırmayı, tekrar çalıştırmayı, geri dönüşü, kasa verisinin korunmasını ve yalnızca eski kasa kategorisiyle bağlı kategorinin silinebilmesini kapsar. Muhasebe filtresinin gider kategorisi değişince güncellenmesi ayrıca test edilir.

# Borçlandırma gruplarından kategori bağlantısının kaldırılması

12 Kasım 2026: `due_batches.category_id` kaldırıldı. Toplu/tekil borçlandırmada seçilen kategori doğrudan oluşturulan borçlara yazılıyor. Aidat ve taksitli borç planları kategoriyi plan tanımından borca aktarmaya devam ediyor. Planların kategori bağlantıları korunuyor.

Grup; kaynak, toplam, dağıtım, üretim tarihi, oluşturan kullanıcı ve plan/taksit bağlantılarını tutmaya devam eder. Borcun kategorisinin değiştirilmesi grup üyeliğini veya diğer borçların kategorilerini değiştirmez. Kategori silme kontrolü artık grupları engel saymaz; mevcut borç, gider, plan ve hesap bağlantılarının korumaları sürer. Varsayılan aidat için mükerrer borç kontrolü kategoriyi artık oluşturma isteğinden okur.

Yerel geçiş öncesinde 17 grup ve 16 dolu kategori bağlantısı vardı. Eski değerler `storage/app/private/due-batch-category-removal-backup-2026-11-12.json` dosyasına yedeklendi; bu dosya sürüm kontrolü dışındadır. Migration yerelde uygulandı; canlıya uygulanmadı. Grup kategori sütunu dışında 13 tablodaki verinin parmak izleri birebir eşleşti. Borç/grup/taksit bağlantıları, tutarlar ve tahsisler korundu.

Migration'ın geri dönüşü boş kategori sütununu oluşturur; özgün değerler gerekirse yedekten kimlikle geri yüklenmelidir. SQLite tablo yeniden oluşturulurken bağlı borçların foreign key etkisiyle gruptan ayrılmaması için migration transaction dışında ve foreign key denetimi geçici kapatılarak çalışır. Bağlantıların korunduğu dolu veriyle regresyon testi bulunur.

Canlı dağıtımda kod ve migration birlikte uygulanmalı, eski kategori değerleri önceden yedeklenmelidir. Bu çalışma mevcut grupları kaldırmaz; tekil borçların grup oluşturma davranışını değiştirmez.

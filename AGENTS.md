# Veritabanı ve test kuralları

- Uygulama MySQL 8.0.45 kullanır.
- Hızlı fonksiyonel doğrulamalar için SQLite kullanılabilir.
- Veritabanı sorgularını, finansal hesaplamaları, migration'ları, transaction'ları veya kayıt kilitleme davranışını etkileyen geliştirmeleri bağımsız bir MySQL 8.0.45 test veritabanında da doğrula.
- Mevcut `apartman_db` veritabanını test amacıyla kullanma.
- Ana ve canlı veritabanlarındaki kayıtları değiştirme.
- Yalnızca yapılan geliştirmeyle doğrudan ilgili testleri çalıştır.
- Test raporlarında kullanılan veritabanı motorunu belirt.

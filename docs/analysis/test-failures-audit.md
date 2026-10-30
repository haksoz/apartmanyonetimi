# A — 12 başarısız testin incelemesi

Tarih: 27 Ekim 2026. Üretim bağlantısı yok. Testler APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL boş olarak çalıştırıldı.

## Karşılaştırma yöntemi

Aynı 8 test dosyası mevcut kodla ve target_audience öncesi kod/şemayla çalıştırıldı. Önceki sürüm için ilgili 6 uygulama dosyası git HEAD içeriğiyle geçici değiştirildi; yeni migration test keşfinden çıkarıldı. Yalnızca bu alanı ekleyen geliştirme geri alındı; önceki pano çalışması korunmuştur. Dosyalar finally bloğunda byte olarak geri yüklendi. Her iki çalışmada 53 test, 41 başarılı, aynı 12 başarısız, 446 doğrulama, 0 error ve 0 skip.

Sonuç: yeni regresyon 0; önceki kodda tekrarlandığı doğrulanan başarısızlık 12. Bunlardan PublishedSaleDocumentsTest ortak bileşen/test fixture kapsamı sorunudur; kalan 11 mevcut kod/test sözleşmesi hatasıdır. Bağlantı, sürücü veya test altyapısı arızası görülmedi. Nedeni kesinleşmeyen hata yok. Önceden mevcut hatalar için düzeltme onayı verilmediğinden uygulama/test beklentileri değiştirilmedi.

## Her testin sonucu

### 1. `Tests.Feature.Admin.ManagerSubscriptionOrderTest::test_free_apartment_is_listed_and_complimentary_months_open_paid_use`

- Beklenti: Ücretsiz apartmana hediye süre verildikten sonra listede Aktif/Ücretli görünmesi.
- Gerçek hata: `contains "Aktif"`.
- Neden: Legacy satırda match(true) içinde STATUS_ACTIVE ve STATUS_CANCELLED string sabitleri karşılaştırmasız kullanılıyor. true ile strict eşleşme olmadığından durum — oluyor. Hediye süre oluşturma, kapsam ve ücretsiz ödeme kontrolleri bu noktadan önce geçiyor.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 2. `Tests.Feature.Admin.SubscriptionItemListTest::test_items_view_lists_each_subscription_item_from_its_own_fields`

- Beklenti: Her abonelik kaleminin kendi tarih, plan, ödeyen ve durumunu göstermesi.
- Gerçek hata: `contains "Aktif"`.
- Neden: Aynı legacyRows match(true) hatası: aktif sabiti boolean koşul değil; durum —.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 3. `Tests.Feature.Admin.SubscriptionItemListTest::test_one_order_with_two_apartments_and_past_items_are_separate_rows`

- Beklenti: Aynı siparişin iki apartmanı ve iptal edilmiş eski kalemin ayrı gösterilmesi.
- Gerçek hata: `contains "İptal"`.
- Neden: Aynı legacyRows match(true) hatası iptal edilmiş satırın durum etiketini — yapıyor.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 4. `Tests.Feature.Admin.SubscriptionItemListTest::test_deleted_apartment_uses_the_item_snapshot_name`

- Beklenti: Silinen apartmanda snapshot adının ve iptal durumunun gösterilmesi.
- Gerçek hata: `contains "İptal"`.
- Neden: Snapshot adı kontrolleri geçiyor; legacyRows durum eşleştirmesi iptal etiketini üretemiyor.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 5. `Tests.Feature.CashPageTest::test_cash_index_opens_empty_for_selected_apartment`

- Beklenti: Seçili apartmanın boş kasa hareket ekranının açılması.
- Gerçek hata: `contains "Henüz kasa hareketi yok."`.
- Neden: cash.index artık kasa tanımlarını listeliyor; boş mesaj Henüz kasa tanımı yok. Hareket listesi ilgili kasa ekranında. Test eski ekran sözleşmesini bekliyor.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 6. `Tests.Feature.CashPageTest::test_cash_index_uses_only_selected_apartment_transactions`

- Beklenti: Seçilen apartmanın hareketlerinin ve gelir/gider toplamlarının gösterilmesi.
- Gerçek hata: `contains "İkinci apartman geliri"`.
- Neden: CashController::index yalnızca cash_box_id seçilmişse hareketleri sorguluyor; index görünümü hareket satırı basmıyor. Test kasa ve cash_box_id oluşturmadan eski genel hareket listesini bekliyor. Bu hata apartmanlar arası veri sızıntısı kanıtı değil.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 7. `Tests.Feature.CommercialBillingTest::test_existing_free_apartment_stays_usable_without_a_subscription`

- Beklenti: Ücretsiz apartmanda temel işlemler açıkken ücretli raporun 403 dönmesi.
- Gerçek hata: `Expected response status code [403] but received 200.`.
- Neden: EnsureSubscriptionFeature ücretli rapora erişim yokken reports.unavailable görünümünü HTTP 200 ile döndürüyor. Rapor verilerinin açılması değil, HTTP durum kodu sözleşmesi uyuşmazlığı.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 8. `Tests.Feature.ExampleTest::test_guests_are_redirected_to_login`

- Beklenti: Misafirin / adresinden login sayfasına yönlendirilmesi.
- Gerçek hata: `Expected response status code [201, 301, 302, 303, 307, 308] but received 200.`.
- Neden: / route LandingController ile herkese açık tanıtım sayfası; test eski giriş yönlendirmesini bekliyor.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 9. `Tests.Feature.LandingPageTest::test_landing_reads_active_price_bands_and_drops_package_pricing`

- Beklenti: Landing sayfasında fiyat bandı bilgisinin bulunmaması, ayrı fiyat sayfasında gösterilmesi.
- Gerçek hata: `does not contain "1–100"`.
- Neden: landing.blade.php 1–100 daireli apartmanda temel kullanım süresizdir metnini içeriyor. Test tüm sayfada bu aralığın bulunmamasını bekliyor.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 10. `Tests.Feature.PublishedSaleDocumentsTest::test_version_one_of_the_sale_documents_is_what_the_order_accepts`

- Beklenti: Yayınlanmış satış belgelerinin içerikleriyle gösterilmesi ve siparişe kabul kaydı bağlanması.
- Gerçek hata: `does not contain "Bu sayfa için içerik hazırlanmaktadır."`.
- Neden: 47. satır subscriber.dashboard yanıtını tüm HTML üzerinde kontrol ediyor. Satış belgeleri yayınlı ve önceki içerik kontrolleri geçiyor; ortak legal-consent-prompt içindeki yayınlanmamış privacy belgesi placeholder metni basıyor. Test fixture yalnızca satış ve ön bilgilendirme belgelerini yayınlıyor; test hazırlığı/ortak bileşen kapsamı sorunu.
- Sınıf: Önceden mevcut olduğu doğrulanan test ortamı/fixture sorunu.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 11. `Tests.Feature.RevertAllocationModalTest::test_due_detail_shows_revert_modal_markup`

- Beklenti: Borç detayında ödeme tahsisini geri alma ve tahsilatı silme seçeneklerinin gösterilmesi.
- Gerçek hata: `contains "Tahsisatı Geri Al"`.
- Neden: Görünümde Aidat Kapamayı Geri Al, Geri Al (Tahsilat Hesapta Kalır), Tahsilatı Sil metinleri var; test eski etiketleri bekliyor. İlgili işlevsel geri alma ve silme testleri geçiyor.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

### 12. `Tests.Feature.RevertAllocationModalTest::test_due_detail_disables_delete_payment_when_payment_has_multiple_allocations`

- Beklenti: Birden fazla tahsisi olan ödemede silmenin engellenmesi ve açıklamanın gösterilmesi.
- Gerçek hata: `contains "Bu tahsilat başka aidatlara/giderlere de tahsis edilmiş; sadece geri alabilirsiniz."`.
- Neden: disabled kontrolü geçiyor; açıklama metni artık Bu tahsilat bu aidatı birebir kapatmadığından silinemez; yalnızca aidat kapamayı geri alabilirsiniz. İş kuralı değişikliği gerektirmeyen metin/test sözleşmesi farkı.
- Sınıf: Önceden mevcut olduğu doğrulanan hata / test-kod sözleşmesi uyuşmazlığı.
- target_audience ilişkisi: yok; aynı test önceki kod ve yeni alanın bulunmadığı şemada da aynı noktada başarısız.

## Düzeltmeler

Bu aşamada uygulama, test beklentisi veya muhasebe kuralı düzeltmesi yapılmadı. Özellikle legacyRows durum eşleştirmesi somut bir önceki kod hatasıdır; düzeltilmesi ayrıca onaya bırakıldı.

Makine okunur kanıt: audit-current.json ve audit-before-target-audience.json. Mesaj sonlarında gerçek exception/stack bilgileri saklıdır.

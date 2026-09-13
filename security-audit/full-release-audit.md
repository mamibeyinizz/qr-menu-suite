# QR Menu Suite — Kapsamlı Kod, Güvenlik ve Mimari Audit

Tarih: 2026-09-13
Branch: `claude/qr-menu-full-audit-bxm610`
Baz alınan commit: `359cc72` (main ile aynı nokta)
Kapsam: **Yalnızca inceleme ve raporlama.** Production koduna, test dosyalarına
dokunulmadı; yeni test yazılmadı; refactor yapılmadı; merge/commit/push
yapılmadı.

---

## 1. Audit kapsamı ve incelenen dosyalar

Bu audit iki kaynaktan beslenir:

1. **Bu oturumda yapılan doğrudan inceleme** — bootstrap/modül yükleme,
   admin/AJAX/REST yüzeyi, import/export akışları, dosya yükleme, uninstall,
   fiyat doğrulama zinciri (`sanitize_price_value` ve tüm çağrı noktaları),
   test suite çalıştırması, `git status` / `git diff --check`.
2. **Önceki oturumlarda üretilmiş, bu turda tek tek yeniden doğrulanan**
   statik/dinamik denetim kayıtları: `DYNAMIC-SECURITY-REPORT.md`,
   `AUTHORIZATION-TESTS.md`, `REST-AJAX-TESTS.md`, `INPUT-VALIDATION-TESTS.md`,
   `KVKK-TECHNICAL-FINDINGS.md`, `ENDPOINT-MATRIX.md`, `release-baseline.md`,
   `EVIDENCE/*`.

Bu belge önceki dinamik test bulgularını **tekrar üretmez**; onları
doğrulayıp güncel commit'e göre teyit eder ve buna ek olarak istenen tüm
mimari/performans/uyumluluk başlıklarını statik kod incelemesiyle kapsar.

İncelenen ana alanlar:

- `qr-menu-suite.php` (bootstrap, `QRMS_VERSION` 1.1.0), `includes/`
  (admin, helpers, lisans istemcisi, modül yükleyici, sihirbaz, shortcode,
  login, query-monitor, hata sayfaları), `uninstall.php`.
- 13 modül + `_qmo-ortak` ortak katmanı (bkz. `release-baseline.md` §2 —
  liste değişmedi).
- Tüm AJAX (`add_action('wp_ajax_*'/'wp_ajax_nopriv_*')`) ve REST
  (`register_rest_route`) uçları — `ENDPOINT-MATRIX.md`'deki envanter bu
  turda dosya/satır seviyesinde örneklemeyle yeniden kontrol edildi.
- CSV/JSON import-export: `modules/restoran-menu/includes/trait-import-export.php`,
  `modules/restoran-menu/includes/urunum-yok/trait-admin.php` (malzeme CSV),
  `modules/qr-ceviri/` (çeviri CSV), `modules/yorum-feedback/` (yorum/ödül
  CSV dışa aktarma).
- Dosya yükleme: `modules/qr-galeri/includes/trait-ajax.php`.
- CPT/taksonomi: `rma_menu_item`, `rma_category`, `rma_allergen` (restoran-menu).
- Özel tablolar: `qrm_reviews`, `qrm_review_media`, `qrm_reward_codes`,
  `qrm_cf_submissions`, `qmo_chatbot_mesajlar`, `qrms_analitik`,
  `qrm_form_fields`, `qrm_cf_forms`, `qrm_cf_fields`, `qrm_tables`,
  `rma_price_campaign_snapshot`, `rma_ceviri` (bkz. `uninstall.php`).
- `uninstall.php`, aktivasyon/deaktivasyon akışları.
- Frontend shortcode/template çıktıları, `assets/js`, `assets/css`.
- Lisans (`includes/class-license-client.php`), Firebase/Firestore
  (`modules/_qmo-ortak/class-qmo-firestore.php`), Gemini (chatbot), webhook
  yok (harici webhook alıcı endpoint tespit edilmedi).
- Cron: `qmo_chatbot_gecmis_temizle`, `qrms_analitik_temizlik`,
  `qrms_daily_license_sync` (dinamik olarak zamanlanmış bulunmuştu, bkz.
  `KVKK-TECHNICAL-FINDINGS.md`).
- PHP 8.1 / WP 6.9 uyumluluğu (test ortamı bu sürümlerde çalıştırılmıştı).

---

## 2. Yönetici özeti

- Bu turda **yeni Kritik veya Yüksek riskli bulgu tespit edilmedi.**
- Önceki turda doğrulanmış tek gerçek güvenlik açığı olan **BULGU-001**
  (ödül yönetimi AJAX uçlarında `edit_posts` ile aşırı geniş yetkilendirme)
  **düzeltilmiş durumda kalıyor** — kod, migration ve regresyon testleri
  bu oturumda tekrar doğrulandı, gerileme yok.
- **BULGU-002** (chatbot prompt injection ikinci katman teorik sınırı) ve
  **BULGU-003** (REST `/analytics` JWT sahteciliği canlı test edilemedi)
  durumları **değişmeden, "doğrulanmamış/teorik" statüsünde** duruyor;
  kod bu turda yeniden okundu, davranışta bir değişiklik yok.
- Üç fiyat doğrulama bypass düzeltmesi (restoran menüsü ana formu, JSON
  yedek geri yükleme, malzeme bazlı CSV) ve fiyat üst sınırı (999999.99)
  **kodda ve testlerde sağlam durumda**, gerileme tespit edilmedi.
- **CSV toplu ürün içe aktarımı (`handle_csv_import`) hâlâ deduplication
  yapmıyor** — aynı dosya iki kez yüklenirse ürünler çoğalır (JSON
  yedeklemenin aksine). Bu, önceden bilinen ve bu görevde **düzeltilmesi
  istenmeyen** bir konu; durumu değişmedi.
- Fiyat üst sınırı aşıldığında gösterilen hata mesajı hâlâ genel
  ("negatif veya sayısal olmayan bir değer") — üst sınırı aştığı özel
  olarak belirtilmiyor. Bilinen, düzeltilmesi bu görevde istenmeyen UX
  konusu; durumu değişmedi.
- "Editor yetkisiz isteklerinde 500 yerine kontrollü 403" konusu: bu
  turda incelenen AJAX uçlarının **hiçbirinde gerçek bir HTTP 500 (fatal
  error) senaryosu doğrulanamadı** — capability reddi olan uçların büyük
  çoğunluğu zaten `wp_send_json_error(..., 403)` kullanıyor. Ancak
  `DYNAMIC-SECURITY-REPORT.md`'de belgelenen, hâlâ açık bir tutarsızlık
  var: bazı uçlar (örn. `qrm_reward_ajax_admin_lookup`'ın bazı erken red
  yolları, `rewards.php` içindeki `wp_send_json_error()` çağrılarının bir
  kısmı) HTTP durum kodu belirtmeden (varsayılan 200, `success:false`)
  dönüyor. Bu **doğrulanamayan/teorik** bir "500" iddiasından çok,
  **doğrulanmış bir "tutarsız HTTP durum kodu" (200 vs 403)** bulgusudur —
  aşağıda BULGU-AUDIT-04 olarak ayrıca kaydedildi, kapsamı görev
  kısıtlarınca değiştirilmedi.
- Test suite: **5368 doğrulama, 0 hata** (bu oturumda çalıştırıldı).
- Çalışma ağacı: yalnızca bu rapor dosyası eklendi, production/test kodunda
  değişiklik yok.

---

## 3. Kritik bulgular

Yok. Bu turda Kritik seviyede yeni bir bulgu tespit edilmedi.

---

## 4. Yüksek riskli bulgular

Yok. Önceki tek Yüksek/Orta-Yüksek teknik risk (BULGU-001) düzeltilmiş ve
doğrulanmış durumda (bkz. §8).

---

## 5. Orta riskli bulgular

### BULGU-AUDIT-01 — CSV toplu ürün içe aktarımında deduplication yok (yinelenen dosya yüklemesi ürün çoğaltır)

- **Risk seviyesi:** Orta (veri bütünlüğü / operasyonel, güvenlik açığı
  değil)
- **Dosya ve satır:** `modules/restoran-menu/includes/trait-import-export.php:39-113`
  (`handle_csv_import()`)
- **Etkilenen fonksiyon/akış:** "Toplu Ürün Aktarımı (CSV)" ekranı —
  her satır koşulsuz `wp_insert_post()` ile **yeni** `rma_menu_item`
  oluşturur; JSON yedekleme akışının aksine (`import_title_map()` ile
  başlık eşleştirmesi yapan `handle_menu_import()`), burada mevcut bir
  ürünle başlık/ID eşleşmesi aranmıyor.
- **Sorunun açıklaması:** Kullanıcı aynı CSV dosyasını (örn. bir hata
  sonrası veya güncelleme amacıyla) ikinci kez yüklerse, aynı başlıklı
  ürünler tekrar oluşturulur; menüde yinelenen kayıtlar birikir.
- **Olası etki:** Menüde görsel kirlilik, yönetim ekranında yanlışlıkla
  aynı ürünün iki farklı fiyat/durumla görünmesi, kategori/analitik
  sayımlarının bozulması. Veri kaybı veya yetki/güvenlik etkisi yok.
- **Tekrarlanma koşulu:** Aynı CSV dosyasının (aynı başlıklarla) `manage_options`
  (veya eşdeğer) yetkili bir kullanıcı tarafından ikinci kez yüklenmesi.
  %100 tekrarlanabilir.
- **Önerilen düzeltme (bu görevde uygulanmadı):** `handle_csv_import()`
  içine, `handle_menu_import()`'daki `import_title_map()` ile aynı desende
  bir başlık→ID ön-sorgusu eklenip mevcut başlıkta `wp_update_post()`
  ile üzerine yazma seçeneği sunulması (veya en azından "bu başlıklar
  zaten var, üzerine yazılsın mı?" onay adımı).
- **Güven seviyesi:** Doğrulanmış (kod okuması ile kesin — koşulsuz
  `wp_insert_post()` çağrısı, hiçbir eşleşme sorgusu yok).

### BULGU-AUDIT-02 — Fiyat üst sınırı aşıldığında hata mesajı yanıltıcı

- **Risk seviyesi:** Orta (UX/güven, güvenlik açığı değil)
- **Dosya ve satır:** `modules/restoran-menu/includes/trait-post-types.php:508`
- **Etkilenen fonksiyon/akış:** Ürün düzenleme formunda fiyat kaydetme
  (`save_post` hook'u içindeki fiyat doğrulama bloğu, `sanitize_price_value()`
  çağrısının `null` dönmesi durumu).
- **Sorunun açıklaması:** `sanitize_price_value()` (`trait-helpers.php:638-655`)
  hem negatif/metin/biçim hatası hem de **999999.99 üst sınırını aşan**
  değerler için aynı şekilde `null` döner. Ancak kullanıcıya gösterilen
  mesaj sabit: *"Girilen fiyat geçersiz (negatif veya sayısal olmayan bir
  değer)."* — üst sınırı aşan bir değer girildiğinde (örn. `5000000`)
  kullanıcı mesajdan bunun neden reddedildiğini anlayamaz (değer ne
  negatif ne de metin).
- **Olası etki:** Kullanıcı kafa karışıklığı, destek talebi; güvenlik
  etkisi yok (değer zaten güvenli şekilde reddediliyor).
- **Tekrarlanma koşulu:** Ürün formunda fiyat alanına 999999.99'dan büyük
  bir sayı girip kaydetmek.
- **Önerilen düzeltme (bu görevde uygulanmadı):** `sanitize_price_value()`'nun
  iki farklı `null` nedenini (biçim hatası / üst sınır aşımı) ayırt eden bir
  ek dönüş biçimi (örn. hata kodu) veya çağıran tarafta ayrı bir üst sınır
  kontrolü ile "en fazla 999.999,99 ₺ girebilirsiniz" mesajı gösterilmesi.
- **Güven seviyesi:** Doğrulanmış.

### BULGU-AUDIT-03 — Ana CSV içe aktarımında fiyat üst sınırı aşımı için de kullanıcıya geri bildirim yok

- **Risk seviyesi:** Orta (UX, güvenlik açığı değil)
- **Dosya ve satır:** `modules/restoran-menu/includes/trait-import-export.php:53-58`
- **Etkilenen fonksiyon/akış:** `handle_csv_import()` — fiyat sütunu
  geçersizse (üst sınır dahil) sessizce boş fiyatla içe aktarılıyor;
  JSON import (`handle_menu_import()`, satır 449/641-647) ve malzeme CSV
  (`urunum-yok/trait-admin.php:460`) akışlarının aksine, ana CSV
  akışında kaç satırın fiyatının geçersiz sayıldığına dair bir sayaç/uyarı
  admin ekranına yansıtılmıyor (`render_csv_import_page()` yalnızca
  `imported`/`csv_error` gösteriyor, `fiyat_gecersiz` yok).
- **Olası etki:** Kullanıcı, CSV'sindeki hatalı/aşırı büyük fiyatların
  sessizce boş bırakıldığını fark etmeyebilir; menüde "fiyat yok"
  görünen ürünlerin nedenini anlamak için ek araştırma gerekir.
- **Tekrarlanma koşulu:** Fiyat sütununda geçersiz/üst sınırı aşan bir
  değer içeren CSV dosyasının ana toplu içe aktarım ekranından yüklenmesi.
- **Önerilen düzeltme (bu görevde uygulanmadı):** `handle_csv_import()`'a
  da diğer iki akıştaki gibi bir `$fiyat_gecersiz` sayacı ve admin
  uyarısı eklenmesi (tutarlılık için).
- **Güven seviyesi:** Doğrulanmış.

---

## 6. Düşük riskli bulgular

### BULGU-AUDIT-04 — AJAX yetkisizlik reddi HTTP durum kodları tutarsız (200 vs 403)

- **Risk seviyesi:** Düşük (işlevsel güvenlik etkisi yok, semantik/izlenebilirlik sorunu)
- **Dosya ve satır:** Örnek: `modules/yorum-feedback/includes/ajax/rewards.php`
  içindeki bazı erken-dönüş `wp_send_json_error()` çağrıları (parametre
  doğrulama başarısızlıkları) durum kodu belirtmiyor → varsayılan HTTP 200,
  gövdede `success:false`. Capability reddi olan asıl uçlar (`admin_lookup`,
  `cashier_mark_used`, satır ~158/220) zaten `403` döndürüyor
  (bu turda kontrol edildi, doğru).
- **Etkilenen fonksiyon/akış:** Genel olarak "parametre eksik/geçersiz"
  türü erken dönüşler; bu, önceki `DYNAMIC-SECURITY-REPORT.md`
  İyileştirme Önerisi #2'de zaten belgelenmişti, davranış değişmedi.
- **Sorunun açıklaması:** Bazı hata yolları HTTP 200 ile `success:false`
  döndürüyor, bazıları (özellikle capability reddi) `403` döndürüyor —
  istemci/monitoring tarafında tutarsız yorumlanabilir (ör. bir hata
  izleme aracı 200'ü "başarılı" sanabilir).
  Görev metninde belirtilen "Editor'ın 500 alması" senaryosu bu turda
  incelenen uçlarda **doğrulanamadı** — capability reddi veren uçların
  tamamı ya `wp_send_json_error(...,403)` ya da `wp_send_json_error()`
  (200, success:false) döndürüyor; gerçek bir PHP fatal error/500 yolu
  bulunamadı. Bu nedenle "500" iddiası **teorik/doğrulanamayan**, "200 vs
  403 tutarsızlığı" ise **doğrulanmış** olarak sınıflandırılmıştır.
- **Olası etki:** Yalnızca gözlemlenebilirlik/izlenebilirlik; yetkisiz
  işlem hiçbir durumda gerçekleşmiyor (fonksiyonel güvenlik korunuyor).
- **Tekrarlanma koşulu:** Yetkisiz bir kullanıcının ilgili AJAX action'a
  eksik/hatalı parametreyle istek göndermesi.
- **Önerilen düzeltme (bu görevde uygulanmadı, önceki turda da önerilmişti):**
  Tüm `current_user_can()` reddi ve "yetkisiz erişim" yollarında tutarlı
  olarak `wp_send_json_error($data, 403)` kullanılması.
- **Güven seviyesi:** Doğrulanmış (200 vs 403 tutarsızlığı) / teorik
  (500 senaryosu — bu turda kod okumasıyla doğrulanamadı, canlı/Docker
  ortamında Editor rolüyle uçtan uca yeniden test edilmesi önerilir).

### BULGU-AUDIT-05 — Kasiyer rolü otomatik tespiti isim/slug sezgisine dayanıyor (bilinen kalan risk)

- **Risk seviyesi:** Düşük (bilinen, belgelenen tasarım sınırı)
- **Dosya ve satır:** `modules/yorum-feedback/includes/rewards/capabilities.php`
  (`qrm_reward_cap_maybe_upgrade()`)
- **Açıklama:** Değişmedi, `DYNAMIC-SECURITY-REPORT.md` §BULGU-001'de zaten
  belgelenmiş "kalan risk" — farklı isimlendirilmiş özel roller otomatik
  yakalanmıyor, yalnızca admin'e bildirim gösteriliyor.
- **Güven seviyesi:** Doğrulanmış (bu turda kod yeniden okundu, davranış aynı).

---

## 7. Bilgi amaçlı gözlemler

- **SQL Injection:** Bu turda taranan tüm `$wpdb` sorguları (`trait-import-export.php`
  içindeki yeni `import_title_map()` dahil) `prepare()` + placeholder
  kullanıyor; sabit tablo adları hariç kullanıcı girdisi doğrudan SQL'e
  enjekte edilmiyor. Önceki dinamik SQLi testleriyle tutarlı.
- **Dosya yükleme:** `qrmgm_upload_image` hâlâ `wp_check_filetype_and_ext()`
  (gerçek MIME/uzantı eşleşmesi) kullanıyor; dosya adı kullanıcıdan alınıp
  ham path olarak yazılmıyor (WordPress medya API'si devrede) — path
  traversal / arbitrary file write yolu bulunamadı.
- **Hardcoded secret taraması:** `AIza...`, `api_key=...`, `secret=...`
  gibi desenlerle yapılan grep taramasında (production kodu, testler hariç)
  hiçbir sabit anahtar/sır bulunamadı — lisans/Firebase/Gemini anahtarları
  yalnızca admin panelinden girilecek şekilde tasarlanmış (`release-baseline.md`
  §5 ile tutarlı).
- **Uninstall:** `uninstall.php` varsayılan olarak hiçbir tabloyu/opsiyonu
  silmiyor; yalnızca açık opt-in (`qrms_uninstall_veri_sil` option'ı veya
  `QRMS_UNINSTALL_VERI_SIL` sabiti) ile siliyor. Tablo adları sabit listeden
  geliyor, kullanıcı girdisi değil — SQL injection riski yok.
- **Rate limiting:** Tüm hız sınırlama mekanizmaları sunucu tarafında
  (`set_transient`/`get_transient`), istemci JS'ine güvenmiyor (değişmedi).
- **IP/PII pseudonimizasyon:** Rate-limit anahtarları ve analitik `ip_hash`
  alanı hash'lenmiş IP kullanıyor; ham IP saklanmıyor (değişmedi).
- **XSS:** Yorum/geri bildirim çıktı katmanında `esc_html()` tutarlı
  kullanılıyor; stored/reflected XSS önceki dinamik testte bulunamamıştı,
  bu turda ilgili kod yolları (çıktı fonksiyonları) değişmediği doğrulandı.
- **Tek-kiracı mimari:** Restoranlar arası IDOR senaryoları (BULGU-01
  dışında) mimari olarak uygulanamaz — tek site = tek restoran tasarımı
  (değişmedi).

---

## 8. Önceki bulguların güncel durumu

| ID | Konu | Durum | Bu turdaki doğrulama |
|---|---|---|---|
| BULGU-001 | Ödül AJAX uçlarında `edit_posts` ile aşırı geniş yetki (PII ifşası + durum değişikliği) | **Düzeltildi** (commit `85a1979`, `aa17562`) | `rewards.php:158,220` hâlâ `QRM_REWARD_CAP` (`qrm_manage_rewards`) OR `manage_options` kontrolü yapıyor; `capabilities.php` migration'ı ve admin bildirimi kodda mevcut; regresyon testleri (`qrm_manage_rewards yoksa...`) test suite çıktısında görüldü ve geçti. **Gerileme yok.** |
| BULGU-002 | Chatbot prompt injection ikinci katman teorik atlatma senaryosu | **Doğrulanmamış/teorik, açık** (kod değişikliği yok) | Kod (`qmo_chat_dogrulanmamis_etiketleri_temizle()`, `qmo_siparis_kalem_coz()`) bu turda yeniden okundu; mantık değişmemiş — sipariş kalemleri hâlâ sunucuda gerçek `rma_menu_item` kaydına yeniden çözülüyor. Gemini API anahtarı olmadan uçtan uca doğrulama bu turda da yapılamadı (görev kısıtı). Durum **değişmedi**. |
| BULGU-003 | REST `/analytics` JWT sahteciliği canlı test edilemedi | **Doğrulanmamış/teorik, açık** (kod değişikliği yok) | `rest-analytics.php` bu turda tekrar okundu; `openssl_verify()` ile imza doğrulaması hâlâ mevcut, `alg` header'ına güvenilmiyor. Firestore kimlik bilgisi olmadan dinamik test bu turda da yapılamadı. Durum **değişmedi**. |
| CSV import deduplication | Ana CSV toplu içe aktarımda aynı ürünün tekrar yüklenmesi | **Açık, düzeltilmedi** (bu görevde düzeltilmesi istenmiyor) | Bkz. BULGU-AUDIT-01 — kod hâlâ koşulsuz `wp_insert_post()` kullanıyor, JSON import'taki gibi bir eşleştirme yok. |
| Editor 500 vs 403 | Yetkisiz Editor isteklerinde kontrolsüz 500 yerine 403 beklentisi | **Kısmen doğrulanabildi** (bu görevde düzeltilmesi istenmiyor) | İncelenen AJAX uçlarında gerçek bir 500/fatal-error yolu bu turda **bulunamadı**; bunun yerine önceden belgelenen 200-vs-403 tutarsızlığı (BULGU-AUDIT-04) doğrulandı. "500" senaryosunun canlı/Docker ortamında Editor rolüyle uçtan uca yeniden test edilmesi önerilir (bkz. §12). |
| Fiyat üst sınırı hata mesajı UX | Üst sınır aşımında yanıltıcı/genel hata mesajı | **Açık, düzeltilmedi** (bu görevde düzeltilmesi istenmiyor) | Bkz. BULGU-AUDIT-02 — `trait-post-types.php:508` mesajı hâlâ sabit ve genel. |

---

## 9. Düzeltilmiş açıkların regresyon kontrolü

| Düzeltme | Dosya | Regresyon durumu |
|---|---|---|
| Ödül endpoint'i yetkilendirmesi (BULGU-001) | `modules/yorum-feedback/includes/ajax/rewards.php`, `includes/rewards/capabilities.php` | **Gerileme yok.** `QRM_REWARD_CAP` kontrolü kodda duruyor; ilgili regresyon testleri (`qrm_manage_rewards yoksa...`, `manage_options geriye dönük uyumluluk...` vb.) test suite çıktısında ✓ olarak görüldü. |
| Restoran menüsü fiyat doğrulaması (ana form) | `modules/restoran-menu/includes/trait-post-types.php:338,508` | **Gerileme yok.** `sanitize_price_value()` hâlâ çağrılıyor, geçersiz değerde eski fiyat korunuyor ve hata mesajı gösteriliyor. Testler (`tests/test-restoran-menu.php:1147-1183`) geçti. |
| JSON import fiyat bypass'ı | `modules/restoran-menu/includes/trait-import-export.php:526-550` | **Gerileme yok.** `rma_price` meta'sı diğer meta alanlarından ayrı işleniyor, `sanitize_price_value()`'dan geçiyor, geçersizse `$fiyat_gecersiz` sayaçlı uyarı gösteriliyor. |
| Malzeme CSV fiyat bypass'ı | `modules/restoran-menu/includes/urunum-yok/trait-admin.php:740-750` | **Gerileme yok.** `sanitize_price_value( $row['price'] )` çağrısı ve `$fiyat_gecersiz` sayacı kodda mevcut. |
| Bu bypass'lar için eklenen regresyon testleri | `tests/test-restoran-menu.php` (satır ~1147-1520 civarı) | **Hepsi mevcut ve geçiyor** — üst sınır testleri (999999.99, 1000000, 9999999999999999999), malzeme CSV onay testleri, "kaynak kod güvencesi" testi (`sanitize_price_value` çağrısının kaynak kodda literal olarak var olduğunu doğrulayan test) dahil. Test suite toplamda **5368 doğrulama, 0 hata** ile geçti. |

---

## 10. Production'a çıkış için engelleyici konular

**Yok.** Bu turda tespit edilen hiçbir bulgu (BULGU-AUDIT-01 ila 05) production
çıkışını engelleyecek nitelikte değildir:

- Hepsi Orta veya Düşük risk seviyesinde, güvenlik açığı değil UX/gözlemlenebilirlik
  konusu (CSV dedup, fiyat üst sınırı mesajı, HTTP durum kodu tutarlılığı).
- Önceden bilinen teorik/doğrulanmamış riskler (BULGU-002, BULGU-003) mimari
  olarak sınırlı etkili ve gerçek dünyada Gemini/Firestore kimlik bilgisi
  gerektiriyor; iş kuralı (sunucu tarafı yeniden çözümleme) bu riskleri
  sınırlıyor.
- `release-baseline.md`'de belirtilen tek operasyonel konu (production zip'ine
  `tests/`/`security-audit/`/`scripts/` klasörlerinin dahil olma riski) hâlâ
  geçerli ama bu bir kod açığı değil, paketleme sürecine ait bir eksiklik.

---

## 11. Önerilen düzeltme sırası

1. **BULGU-AUDIT-04** (200 vs 403 tutarlılığı) — düşük efor, tüm
   `current_user_can()` red yollarında `wp_send_json_error($data, 403)`
   deseninin standartlaştırılması. (Önceki turda da önerilmişti.)
2. **BULGU-AUDIT-01** (CSV dedup) — orta efor, `handle_csv_import()`'a
   `import_title_map()` benzeri bir eşleştirme eklenmesi.
3. **BULGU-AUDIT-02 / BULGU-AUDIT-03** (fiyat üst sınırı UX mesajları) —
   düşük efor, `sanitize_price_value()`'nun red nedenini ayırt etmesi
   veya çağıran taraflarda ayrı üst sınır kontrolü.
4. **BULGU-AUDIT-05** (kasiyer rolü tespiti) — düşük öncelik, filtre
   (`apply_filters`) desteği veya manuel rol atama arayüzü.
5. **Release baseline'daki paketleme boşluğu** (`.distignore`/build script) —
   kod değişikliği gerektirmeyen, düşük riskli ayrı bir görev olarak
   zaten önerilmişti; hâlâ geçerli.
6. **BULGU-002 / BULGU-003** — mevcut iş kuralı sınırlamaları (sunucu
   tarafı yeniden çözümleme, JWT imza doğrulaması) yeterli görülüyor;
   gerçek Gemini/Firestore kimlik bilgisiyle bir sonraki dinamik test
   turunda uçtan uca doğrulanması önerilir, kod değişikliği şart değil.

---

## 12. İncelenemeyen veya doğrulanamayan alanlar

- **Gerçek Firestore/Identity Toolkit ve Gemini API çağrıları** — bu turda
  da gerçek kimlik bilgisi kullanılmadı (görev kısıtı); BULGU-002/003
  durumu bu nedenle "teorik" kalmaya devam ediyor.
- **"Editor 500" senaryosu** — bu turda statik kod okumasıyla gerçek bir
  fatal-error/500 yolu bulunamadı; **manuel doğrulama önerilir**: izole
  Docker ortamında Editor rolüyle tüm `manage_options`/özel-capability
  gerektiren AJAX uçlarına (özellikle `qr-servis-paneli`, `qr-analiz`,
  `qr-menu-muhendisligi` altındaki uçlar) sistematik istek gönderilip PHP
  hata loglarının (`debug.log`) izlenmesi, gerçek bir 500 tetiklenip
  tetiklenmediğinin doğrulanması gerekir.
- **Lisans sunucusu, gerçek webhook alıcıları** — bu eklentide dışarıdan
  gelen bir webhook alıcı endpoint tespit edilmedi (yalnızca giden istekler:
  lisans doğrulama, Firestore, Gemini); bu nedenle "webhook güvenliği"
  başlığı bu kod tabanında uygulanabilir değil — bilgi amaçlı not.
  Lisans sunucusu entegrasyonu bu turda da gerçek ağ isteğiyle test
  edilmedi (görev kısıtı, `class-license-client.php` yalnızca statik
  okundu; gerçek lisans anahtarı kullanılmadı — bkz. `release-baseline.md` §5).
- **Docker/dinamik penetrasyon testi** — bu görev kapsamında Docker ortamı
  başlatılmadı/çalıştırılmadı (görev kısıtı); yalnızca statik kod incelemesi
  ve mevcut `tests/test-suite.php` (WordPress stub'larıyla, PHPUnit değil)
  çalıştırıldı.
- **JavaScript/CSS dosyaları** — `assets/js`, `assets/css` ve modül bazlı
  JS dosyaları bu turda dosya adı/işlev seviyesinde tarandı (önceki dinamik
  testte DOM-tabanlı XSS için ayrıntılı incelenmişti); bu turda satır satır
  yeniden okunmadı, önceki bulgu ("innerHTML'e escape'siz yazan nokta
  bulunamadı") kabul edilerek referans verildi — **tam yeniden doğrulama
  yapılmadı, düşük öncelikli manuel doğrulama gerektirir.**
- **Mobil/responsive admin UX** — bu turda kod/CSS seviyesinde ayrıntılı
  incelenmedi; önceki commit geçmişinde (`8f0f13c`, `9e69a6e`, `21aaecd`
  vb.) responsive düzeltmelerin yapıldığı görüldü, ancak bu audit'te
  görsel/tarayıcı testi yapılmadı (görev kısıtı: production koduna
  dokunmama ve kapsamlı UI testi bu turun odağı değil).

---

## Kontrol sonuçları

| Kontrol | Sonuç |
|---|---|
| `git status` | Temiz (bu rapor dosyası dışında değişiklik yok) |
| `git diff --check` | Temiz (whitespace hatası yok) |
| `php tests/test-suite.php` | **Geçti — 5368 doğrulama, 0 hata, exit 0** |
| Docker dinamik test ortamı | Bu turda çalıştırılmadı (görev kısıtı) |
| Production koduna değişiklik | **Yok** |
| Test dosyalarına değişiklik | **Yok** |
| Yeni test | **Eklenmedi** |
| Refactor | **Yapılmadı** |

---

**Not:** Bu görevde production kodunda, test dosyalarında veya başka bir
dosyada hiçbir değişiklik yapılmamıştır. Yalnızca bu rapor dosyası
(`security-audit/full-release-audit.md`) oluşturulmuştur. Commit/push bu
oturumda yapılmamıştır; kullanıcı onayı beklenmektedir.

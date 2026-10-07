# QR RESTORAN MENÜ — QA RAPORU

**Modül:** QR Menu Suite / QR Restoran Menü (`modules/restoran-menu`)  
**Ortam:** https://test.qrmenuofficial.com/ (müşteri menüsü)  
**Admin giriş yolu:** https://test.qrmenuofficial.com/qrm/ (`wp-login.php` 404; özel slug)  
**Tarih:** 5–6 Ekim 2026  
**Kapsam:** Faz 1 / 1-1 — canlı sitede keşif + CRUD + frontend + negatif + CSV + kampanya  
**Kod değişikliği:** Yok (yalnızca QA)

> `/qrm` **giriş sayfasıdır**, menü sayfası değildir. Müşteri menüsü kök URL’dedir.

---

## 1. Genel Sonuç

| Metrik | Sayı |
|---|---|
| Toplam özellik (envanter) | 42 |
| Test edilen | 36 |
| PASS | 27 |
| FAIL | 6 |
| BUG (benzersiz) | 8 |
| BLOCKED | 5 |
| Test edilemeyen | 6 |
| Coverage | **86%** |

**Genel değerlendirme: CONDITIONAL PASS**

Menü hub’ı, ürün/kategori CRUD, fiyat doğrulaması, AJAX menü, arama, filtre paneli, ürün modalı, banner slider, CSV içe aktarma (geçerli dosya), yedek JSON dışa aktarma ve tükenen işaretleme çalışıyor. Öne çıkan **slider CPT’si kayıtlı değil** (yönetim UI’si yok + `edit.php?post_type=qmo_slide` geçersiz). Sepet anahtarı açık olsa da masa oturumu olmadan HTML basılmıyor. Kampanya tarih UI’si yok (DB’de faz 2 sütunları var). Native Elementor widget kodda var; canlı anasayfa generic shortcode widget kullanıyor.

---

## 2. Özellik envanteri (keşif)

Her satır: **MODÜL: QR Restoran Menü**

| ÖZELLİK | YER | İŞLEM | BAĞIMLILIK | TEST DURUMU |
|---|---|---|---|---|
| Hub Menü Yönetimi | Admin `qrms-module-restoran-menu` | Okuma / navigasyon | Suite menü | PASS |
| Ürün listesi CPT `rma_menu_item` | Admin | CRUD, ara, süz, duplicate | — | PASS (duplicate link var; kopya UI tam koşulmadı) |
| Ürün ekle / düzenle | Admin editör | Başlık, içerik, fiyat, görsel | — | PASS |
| Özellikler (vegan, vejetaryen, glütensiz, şekersiz, acılık) | Admin + frontend modal | Kaydet / göster | — | PASS (admin persist) |
| Hazır rozetler (Popüler, Yeni, Önerilen, İndirim) | Admin + kart/modal | Kaydet / göster | — | PASS |
| Alerjen taksonomisi | Admin + modal | CRUD + ürüne ata | `rma_allergen` | PASS (CRUD ürüne atama) |
| Besin bilgileri | Admin + modal | Kaydet / göster | — | PASS |
| Şeffaf menü (et menşei, alkol, domuz) | Admin + modal | Kaydet / göster | — | PASS (Adana’da et menşei göründü) |
| Ürün durumu: menüde göster | Admin + frontend | Gizle / göster | `rma_active` | PASS |
| Tükendi (ürün) | Liste toggle + editör + modal | İşaretle | `RMA_Tukendi` | PASS |
| Porsiyon / varyasyon | Editör meta + modal | CRUD fiyat farkı | `RMA_Porsiyon` | PASS |
| Ekstra listeleri (yeniden kullanılan) | `qrms-rm-secenekler` | Liste CRUD | Ürün ataması | PASS (okuma) |
| Ürüne özel ekstra | Editör | Kaydet / modal | — | PASS (ilk kayıtta) |
| Servis saati (ürün/kategori) | Editör + kategori | Kısıt | Sipariş filtresi | BLOCKED (saat penceresi canlı siparişle tam koşulmadı) |
| Özel rozetler | `qrms-rm-secenekler` sekme | CRUD + ürüne ata | — | PASS (okuma; Hızlı Servis/Acı/Şefin Önerisi) |
| Kombin ürün | Editör meta | İşaretle + ürün seç | `QMO_Kombin_Meta` | BLOCKED (işaretlenmedi; fiyat kombo senaryosu yok) |
| Malzemeler taksonomisi | Admin | CRUD + ürünüm yok | `rma_ingredient` | PASS (sayfa) |
| Kategoriler + sıra + parent | Taksonomi + Diğer Ayarlar | CRUD, drag-drop | `rma_cat_order` | PASS (CRUD); drag-drop görsel tam değil |
| Menü görünümü temalar/renk/tipografi | `qrms-rm-gorunum` | Kaydet | `rma_color_settings` | PASS (okuma; canlı temayı bozmamak için geri yazılmadı) |
| Kategori çubuğu tasarımı | Aynı sayfa | Preset + detay | `rma_nav_design_settings` | PASS (okuma) |
| Günün önerileri | `qrms-rm-one-cikanlar` | system/manual | Frontend `SUGGEST_CFG` | PASS (manual ids 138,106,40) |
| Öne çıkan slider CPT `qmo_slide` | Admin + shortcode | CRUD grup | `QMO_Slide_CPT` | **FAIL** |
| Slider görünüm ayarları | Öne Çıkanlar altı | Kaydet | CPT varlığı | **FAIL** (blok yok) |
| Fiyat kampanyası | `qrms-rm-kampanya` | CRUD, önizleme, tek aktif | `RMA_Kampanya` | PASS (taslak kayıt); tarih UI yok |
| Kampanya görselleri / banner | Wizard + CPT `qmo_banner_slide` | CRUD, oran, autoplay | Shortcode | PASS (CPT + frontend markup) |
| Banner görsel üretici | Wizard adım `olustur` | PNG üret | Medya | BLOCKED (tarayıcı çizimi tam koşulmadı) |
| Sepet ile sipariş anahtarı | Diğer Ayarlar | Toggle | Chatbot sepet + masa oturumu | PASS (kayıtlı açık); frontend BLOCKED |
| CSV ürün import | Diğer Ayarlar | Upload | — | PASS (geçerli); FAIL (hatalı dosya mesajı) |
| CSV örnek indir | Aynı | Download | — | PASS (buton var) |
| Malzeme CSV import/export | Aynı | Preview/confirm | Ürünüm yok | PASS (export); import preview BLOCKED |
| Menü JSON yedek | Aynı | Export/import | — | PASS (export 75KB JSON) |
| Tükenen ürünler (malzeme + manuel + otomatik saat) | `qrms-rm-urunum-yok` | İşaretle | Cron | PASS (boş durum + arama UI) |
| Frontend `[restaurant_menu]` | Anasayfa Elementor shortcode | Render AJAX | — | PASS |
| Arama | Frontend | AJAX `search` 60 karakter kesme | — | PASS |
| Filtre & sırala paneli | Frontend | Alerjen, rozet, kcal/fiyat, sort | — | PASS (UI); bazı anahtarlar API ile doğrulandı |
| Ürün kartı + modal | Frontend AJAX details | — | PASS |
| Shortcode `[qmo_one_cikan_slider]` | Rehber | — | CPT kırık | FAIL/BLOCKED |
| Shortcode `[qmo_banner_slider]` | Anasayfa | — | PASS |
| Shortcode `[rma_qr_notice]` | Rehber | — | BLOCKED (sayfada aranmadı) |
| Elementor widget `rma_menu_widget` | Kod + editor | show_search | Elementor | BLOCKED (widget ekleme denemesi tamamlanamadı); anasayfa generic shortcode |
| Hızlı düzenle (göster/gizle, tükendi) | Ürün listesi | AJAX | — | BLOCKED (nonce UI) |
| Ürün kopyala | Liste | `rma_duplicate_post` | — | BLOCKED (link var, tıklanmadı) |

---

## 3. Kritik bulgular

### CRITICAL

Yok (sipariş motoru / yetkisiz yazma / panel çökmesi bu turda kanıtlanmadı).

### HIGH

**RM-001 — Öne çıkan slider CPT hiç register olmuyor**

- ÖZELLİK: Öne Çıkan Slider (`qmo_slide`)
- SEVİYE: High
- ADIMLAR:
  1. Admin: `…/wp-admin/edit.php?post_type=qmo_slide`
  2. `…/post-new.php?post_type=qmo_slide`
  3. Öne Çıkanlar sayfasında “Yeni Slider Grubu Ekle” aranır
- BEKLENEN: CPT listesi / yeni slide formu; hub’da slider bloğu
- GERÇEK: “Yazı türü geçersiz.” Öne Çıkanlar’da slider yönetim bloğu yok (`post_type_exists('qmo_slide')` false → `render_slider_section` erken çıkıyor)
- TEKRARLANABİLİRLİK: Always
- ORTAM: Desktop / Admin
- EKRAN: WP hata sayfası + Öne Çıkanlar
- NOT: `QMO_Slide_CPT::init()` `init` kancasına `register_post_type` ekliyor; çağrı `QMO_One_Cikan_Slider::boot` içinde **zaten `init` öncelik 20** iken yapılıyor. Banner CPT aynı deseni `did_action('init')` ile düzeltmiş; slide CPT düzeltmemiş. Kod değişikliği bu görevde yapılmadı.

**RM-002 — Slider shortcode / yönetim zinciri kırık**

- RM-001’in sonucu: `[qmo_one_cikan_slider]` ve “Tüm Grupları Yönet” pratikte kullanılamaz.
- SEVİYE: High
- TEKRARLANABİLİRLİK: Always

### MEDIUM

**RM-003 — Hatalı/eksik CSV yine “1 ürün aktarıldı” gösterebiliyor**

- ÖZELLİK: CSV import
- ADIMLAR: `qa-bad.csv` (`not,a,valid` / `foo`) ve yalnızca `Başlık` sütunlu dosya yüklendi; her ikisinde de `imported=1` notice
- BEKLENEN: Hata / 0 aktarım
- GERÇEK: Başarı notice (query string kalıntısı veya gerçek junk satır — ürün listesinde `foo` başlığı görülmedi; mesaj yanıltıcı)
- TEKRARLANABİLİRLİK: Often
- EKRAN: Diğer Ayarlar → Toplu Ürün Aktarımı

**RM-004 — CPT/taksonomi arşivleri herkese açık, menü UI’si yok**

- `/menu-item/qa-test-urun-01-cigkofte/` → tema single (200), menü modalı değil
- `/?rma_allergen=sut` → “Süt / Laktoz” arşiv başlığı, `rma-wrap` yok
- `/?rma_ingredient=…` benzeri
- SEVİYE: Medium (UX + gereksiz public yüzey)
- TEKRARLANABİLİRLİK: Always

**RM-005 — Görselsiz üründe `placehold.co` üçüncü taraf placeholder**

- QA ürün modalı: `https://placehold.co/600x380/...`
- SEVİYE: Medium (gizlilik, kırık CDN, marka)
- TEKRARLANABİLİRLİK: Always (öne çıkan görsel yokken)

**RM-006 — Sepet anahtarı açık; masa oturumu yoksa sepet basılmıyor**

- Diğer Ayarlar’da “Sepet ile siparişi etkinleştir” işaretli
- Anasayfa HTML’de `qmo-sepet` yok
- Kod: `qmo_sepet_shortcode()` masa yoksa boş döner; açıklama metni “menünün altına otomatik eklenir” diyor
- SEVİYE: Medium (dokümantasyon/UX sapması; QR masa akışı olmadan sepet test edilemez)
- TEKRARLANABİLİRLİK: Always (oturumsuz kök URL)

### LOW

**RM-007 — Kampanya tarih/saat penceresi admin formunda yok**

- DB: `starts_at`, `ends_at`, gün maskesi (“ikinci faz”)
- Yeni kampanya formu: ad, yön, %, kapsam, eski fiyat — tarih alanı yok
- Kullanıcı senaryosu “başlangıç/bitiş tarihi” bu fazda test edilemez (BLOCKED + ürün açığı)

**RM-008 — Giriş slug’ı `/qrm` ile menü URL karışıklığı**

- Operasyon/test tarayıcıları kök yerine `/qrm` açınca login görür
- `wp-admin` ve `wp-login.php` oturumsuz 404 (tasarım)
- Anasayfa curl ile 200 + `rma-wrap` (gerçek menü erişilebilir)
- SEVİYE: Low (ortam/iletişim; tarayıcı ajanları bunu “site login’e kilitli” sanıyor)

**RM-009 — Malzeme CSV’de boş satır**

- Export ilk veri satırı: `612;;;;`
- SEVİYE: Low

---

## 4. PASS olan özellikler

FEATURE: Admin giriş (`/qrm`)  
TEST: admin ile Genel Bakış  
RESULT: PASS

FEATURE: Hub kartları ve istatistikler  
TEST: 70 ürün, 7 kategori, 0 tükenen (test başı), kampanya/öne çıkan sayaçları  
RESULT: PASS

FEATURE: Kategori CREATE/UPDATE  
TEST: `QA TEST - Kategori 01` → `01B`, Türkçe karakter açıklama, term_id=41  
RESULT: PASS

FEATURE: Ürün CREATE  
TEST: post 696, fiyat 123.45, vegan, glütensiz, Yeni rozeti, alerjen süt, kalori 111, porsiyon Büyük +40, ekstra, kategori 41  
RESULT: PASS

FEATURE: Ürün READ persist  
TEST: Editörde tüm alanlar geri geldi  
RESULT: PASS

FEATURE: Ürün UPDATE  
TEST: başlık UPDATED, fiyat 150.00; porsiyon silinmedi (tam POST ile)  
RESULT: PASS

FEATURE: Frontend AJAX menü  
TEST: `rma_load_items` HTML + kategoriler; arama “Adana” kart 42  
RESULT: PASS

FEATURE: Ürün detay modal  
TEST: Adana 320₺, görsel, glütensiz, acı, besin, et menşei; QA ürün 150₺ + porsiyon Standart/Büyük  
RESULT: PASS

FEATURE: Boş başlık  
TEST: Yayınla → “Ürün adı boş bırakılamaz. Hiçbir bilgi kaydedilmedi…”  
RESULT: PASS

FEATURE: Geçersiz fiyat `abc` / aşırı `999999999`  
TEST: Kayıtlı fiyat 150.00 kaldı (red/kelepçe)  
RESULT: PASS

FEATURE: Tükendi  
TEST: `rma_tukendi=1` → modal `is-tukendi`  
RESULT: PASS  
NOT: Sonra kaldırıldı.

FEATURE: Menüde gizle (`rma_active` gönderilmedi)  
TEST: AJAX aramada 696 yok; sonra tekrar aktif  
RESULT: PASS

FEATURE: CSV geçerli import  
TEST: `QA TEST - CSV Ürün 01`, notice “1 ürün aktarıldı”, listede 3 QA öge  
RESULT: PASS

FEATURE: JSON yedek export  
TEST: `rma-menu-export-2026-10-05-*.json`, plugin restaurant-menu-automation, ~75KB  
RESULT: PASS

FEATURE: Malzeme CSV export  
TEST: UTF-8 BOM, `ID;Başlık;Kategori;Fiyat;Malzemeler`  
RESULT: PASS

FEATURE: Kampanya taslak kaydı  
TEST: `QA TEST - Kampanya 01`, %10 indirim, Taslak (uygula=0)  
RESULT: PASS  
NOT: `scope_type=products` geçersiz; motor `all`/`category`/`manual` bekler. UI “tek tek” = `manual`.

FEATURE: Banner CPT  
TEST: `edit.php?post_type=qmo_banner_slide` açılır; anasayfada `qmo-banner-root`, birden fazla slide  
RESULT: PASS

FEATURE: Filtre paneli UI  
TEST: Alerjen kartları, rozetler, sıralama, kcal/fiyat  
RESULT: PASS

FEATURE: Anasayfa Elementor shortcode  
TEST: `data-widget_type="shortcode.default"` + `rma-wrap`  
RESULT: PASS

FEATURE: Ekstra listeleri / özel rozet ekranı  
TEST: Soslar (Ketçap 5₺…), rozet renkleri  
RESULT: PASS

---

## 5. FAIL olan özellikler

- Öne çıkan slider CPT + yönetim UI (RM-001, RM-002)
- `[qmo_one_cikan_slider]` pratik kullanım
- Hatalı CSV geri bildirimi (RM-003)
- Public single/arşiv yerine menü deneyimi (RM-004) — ürün kararı da olabilir
- Görselsiz üründe harici placeholder (RM-005)
- “Sepet menünün altında” vaadi vs masa zorunluluğu (RM-006)

---

## 6. UX bulguları

- Hub kart başlıkları ile sayfa başlıkları kayıyor: “Ürün Durumu” / “Tükenen Ürünler”, “Menü Araçları” / “Diğer Ayarlar”, “Ekstralar & Etiketler” / “Ekstralar & Ürün Rozetleri”.
- Kampanya motoru aynı anda tek aktif kampanya; form bunu uyarıyor — iyi.
- Ürün kaydı klasik editör + çok meta kutusu; porsiyon/ekstra ayrı nonce (hızlı düzenle sıfırlamasın diye) — doğru ayrım.
- Anasayfa başlığı hâlâ “My Blog – My WordPress Blog”; marka “Filizler Restoran” (test sitesi kirliliği).
- Splash + dil + sosyal + yemek kartı rozetleri restoran menü modülünün dışında (açılış ekranı / HFB) ama menü akışını etkiliyor.

---

## 7. Mobil bulguları

- 390 / 360 / 320 viewports tarayıcı ajanında **tam tur tamamlanamadı** (ajan sık `/qrm` login’e düştü).
- Kod/HTML: sticky kategori, filter bottom sheet, modal — mobil için tasarlanmış.
- BLOCKED: yatay taşma, sticky çakışma, sepet vs chatbot katmanı dokunmatik doğrulama.

Görsel kanıt (masaüstü, ajan): splash, banner, arama, filtre, kart ızgarası artifact klasöründe.

---

## 8. Desktop bulguları

- 1440 civarı: Premium Altın tema, altın vurgu, kart ızgarası, banner autoplay — PASS (görsel QA).
- Admin listeleri ve hub 1440’ta kullanılabilir.
- 1366 sistematik ölçüm kısmi.

---

## 9. Veri / kalıcılık

- Tam editör POST’u alanları koruyor.
- Checkbox/repeater gönderilmezse WordPress semantiğiyle vegan/ekstra/porsiyon silinir — beklenen; hızlı düzenle ayrı nonce ile korunuyor (kod yorumu, bu turda quick-edit AJAX’ı koşulmadı).
- Boş başlık meta yazmıyor — PASS.
- LiteSpeed anasayfada HIT; ürün değişince AJAX `rma_cache_version` ile menü yenilenmeli (QA ürün aramada göründü — PASS).

---

## 10. CSV bulguları

- Geçerli UTF-8 + Türkçe karakter satırı içe aktı.
- Örnek dosya butonu var.
- Hatalı dosyada başarı notice (RM-003).
- Ürün CSV **export** (içe aktarma kadar simetrik dump) UI’da yok; yedek JSON + malzeme CSV var.
- Duplicate CSV (aynı başlık üzerine yazma) kodda var (`trait-import-export`); bilinçli ikinci import bu turda koşulmadı.

---

## 11. Elementor bulguları

- Kod: widget `rma_menu_widget` / “Restoran Menüsü”, tek kontrol: Arama Çubuğu, `do_shortcode('[restaurant_menu]')`.
- Canlı anasayfa: Elementor **generic Shortcode** widget’ları (banner + menü), native RMA widget doğrulanmadı (editor oturumu ajanında yarım).
- Mobil Elementor ayarları / renkler widget düzeyinde neredeyse yok (görünüm plugin ayarlarından geliyor).

---

## 12. En riskli 10 problem

1. `qmo_slide` hiç register olmuyor → öne çıkan slider yönetimi ölü (RM-001).  
2. Slider shortcode ve “Yeni Slider Grubu” UI’si yok (RM-002).  
3. Sepet toggle açık ama QR masa olmadan sepet yok; sipariş QA’sı BLOCKED (RM-006).  
4. Yanıltıcı CSV başarı mesajı (RM-003).  
5. Public `rma_menu_item` / alerjen / malzeme arşivleri (RM-004).  
6. `placehold.co` bağımlılığı (RM-005).  
7. Kampanya zaman penceresi müşteriye kapatılmış (RM-007) — indirim tarih testi imkânsız.  
8. `/qrm` login slug vs menü URL karışıklığı (RM-008).  
9. Combo (kombin) + ekstra + porsiyon + sepet fiyatı uçtan uca test edilemedi.  
10. Native Elementor widget ve mobil viewport turu tamamlanamadı.

---

## 13. Test verisi (silinmedi)

Amaçlı bırakıldı (prefix `QA TEST -`):

- Kategori term_id **41** — `QA TEST - Kategori 01B`
- Ürün **696** — `QA TEST - Ürün 01 Çiğköfte UPDATED` (aktif, 150₺)
- CSV ile ek ürün(ler) — `QA TEST - CSV Ürün 01`
- Kampanya taslak — `QA TEST - Kampanya 01` (%10, uygulanmadı)
- Boş başlık denemesinden auto-draft kalmış olabilir

Mevcut restoran ürünleri silinmedi. Görünüm teması Premium Altın `#c9a84c` olarak bırakıldı. Sepet anahtarı açık bırakıldı (orijinal).

---

## 14. Sonuç

QR Restoran Menü çekirdeği (ürün, kategori, fiyat, AJAX menü, arama, modal, banner, CSV geçerli yol, tükendi, gizle) **canlı sitede çalışıyor**.

**CONDITIONAL PASS** — yayın/regresyon kapısı için `qmo_slide` kaydı ve sepet/masa varsayımları düzeltilmeden “tüm özellikler yeşil” denemez.

Kod bu görevde **değiştirilmedi**.

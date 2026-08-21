# QR Menu Suite

Restoranlar için modüler QR menü sistemi (WordPress plugin'i). Bu depo,
plugin'in **çekirdek iskeletini** içerir: lisans istemcisi, kurulum sihirbazı,
modül yükleyici ve admin menü çatısı. Modüllerin gerçek işlevleri (menü
yönetimi, yorumlar vb.) sonraki aşamalarda tek tek eklenecektir.

## Kurulum

Depoyu `wp-content/plugins/qr-menu-suite/` altına kopyalayın ve plugin'i
etkinleştirin. Etkinleştirmeden sonra bir sonraki admin sayfasında kurulum
sihirbazına yönlendirilirsiniz.

Sihirbazda API anahtarınızı girip **Doğrula ve Kur** deyin. Sunucu adresi
alanı varsayılan olarak `https://full.qrmenuofficial.com` gelir; farklı bir
(staging/yansıma) sunucu kullanmıyorsanız dokunmanıza gerek yok.

## Lisans sözleşmesi

`POST https://<sunucu>/wp-json/qmls/v1/validate` — gövde:
`{ "api_key": "...", "domain": "..." }`

| Cevap | Anlamı |
| --- | --- |
| `404 {"status":"invalid"}` | Anahtar yok |
| `200 {"status":"inactive"}` | Anahtar pasif |
| `200 {"status":"domain_mismatch"}` | Anahtar başka alan adına kayıtlı |
| `200 {"status":"active","modules":[...]}` | Aktif, modül listesi döner |

### Fail-safe davranışı

`qrms_active_modules` **yalnızca** sunucudan `active` + yeni `modules` dizisi
geldiğinde değişir. `invalid`, `inactive`, `domain_mismatch` veya
`unreachable` durumlarında modül listesine dokunulmaz; sadece
`qrms_last_status` güncellenir. Sunucu erişilemez durumda kaldığında site
çalışmaya devam eder — sert kilitleme yoktur.

Günde bir kez `qrms_daily_license_sync` cron'u kayıtlı anahtarla sessizce
yeniden doğrulama yapar. Durum sorunluysa ve son sağlıklı senkronizasyondan
bu yana 3 günden fazla geçtiyse, **sadece plugin ekranlarında** kapatılabilir
bir bilgilendirme notice'ı gösterilir.

### Kullanılan option'lar

| Option | İçerik |
| --- | --- |
| `qrms_api_key` | API anahtarı |
| `qrms_server_url` | Lisans sunucusu kök adresi |
| `qrms_active_modules` | Aktif modül slug'ları |
| `qrms_last_sync` | Sunucudan en son cevap alınan an |
| `qrms_last_status` | `active` / `inactive` / `domain_mismatch` / `invalid` / `unreachable` |
| `qrms_last_active_sync` | En son `active` cevabının alındığı an (notice zamanlaması) |
| `qrms_license_notice` | Arka plan senkronizasyonunun bıraktığı durum bayrağı |
| `qrms_setup_completed` | Sihirbaz tamamlandı mı? |
| `splash_screen_options` | Açılış Ekranı modülünün tüm ayarları (bağımsız eklentiden taşınırken ad korundu) |
| `qrms_calisma_saatleri_renkler` | Çalışma saatleri listesinin renkleri (boş değer = temadan devral) |

## Modüller

| Slug | İsim |
| --- | --- |
| `restoran-menu` | Restoran Menü |
| `yorum-feedback` | Yorum & Feedback |
| `qr-masa` | QR Masa |
| `qr-analiz` | QR Analiz |
| `qr-galeri` | QR Galeri |
| `qr-ceviri` | QR Çeviri |
| `qr-chatbot` | QR Chatbot |
| `qr-calisma-saatleri` | QR Çalışma Saatleri |
| `qr-masa-oturum-guvenligi` | QR Masa Oturum Güvenliği |
| `qr-acilis-ekrani` | Açılış Ekranı |

Bir modül eklemek için `modules/<slug>/module.php` dosyası oluşturmak ve
içinde `qrms_module_<slug_alt_çizgili>_init()` fonksiyonunu tanımlamak
yeterlidir (ör. `restoran-menu` → `qrms_module_restoran_menu_init()`).
Loader, modül lisansta aktifse dosyayı `require` eder ve bu fonksiyonu
çağırır; dosya yoksa sessizce atlar.

### Sol menü tek seviyelidir

`QR Menü` menüsünde **yalnızca** şunlar durur: `Genel Bakış`, lisansta aktif
olan modüllerin adları ve `Genel Ayarlar`. Modüllerin alt ekranları menüye
hiç yazılmaz; onlara modülün **hub ekranındaki kartlardan** gidilir.

```
QR Menü
├ Genel Bakış
├ Restoran Menü                 → hub (8 kart)
├ Yorum & Feedback              → hub (7 kart + özet sayaçlar)
├ QR Masa                       → doğrudan Masalar ekranı
├ QR Analiz                     → hub (2 kart)
├ QR Galeri                     → hub (3 kart)
├ QR Çeviri                     → doğrudan Çeviri ekranı
├ QR Chatbot                    → doğrudan Chatbot ayarları
├ QR Çalışma Saatleri           → doğrudan Saat tablosu
├ QR Masa Oturum Güvenliği      → doğrudan Oturum limitleri
├ Kısa Kodlar                   → modüllerin kısa kod rehberi
└ Genel Ayarlar
```

Hub, modülün **ikiden fazla ekranı olduğunda** vardır. Tek ekranlı modüllerde
araya bir sayfa koymak fazladan tık demek olurdu; modül satırı doğrudan o
ekranı açar.

#### Alt sayfalar nasıl gizleniyor?

Alt sayfalar `add_submenu_page( QRMS_Admin::MENU_SLUG, … )` ile **gerçek
sayfalar olarak kaydolmaya devam eder** — adresleri, hook adları, yetkileri ve
sayfa başlıkları değişmez. Menüden düşürülmeleri `admin_head` kancasında,
beyaz liste dışında kalan her satır için `remove_submenu_page()` çağrılarak
yapılır (`QRMS_Admin::hide_module_subpages()`).

Zamanlama bilinçlidir; WordPress bir admin isteğinde sırasıyla:

| # | Aşama | Satır ne durumda olmalı |
| --- | --- | --- |
| 1 | `wp-admin/menu.php` — route çözümü | **durmalı.** Hook adı hesaplanırken üst menü `$submenu`'de aranır; satır yoksa ad `qr-menu_page_X` yerine `admin_page_X` çıkar, `$_registered_pages` ile eşleşmez ve WordPress 403 verir |
| 2 | `current_screen` | durmalı |
| 3 | `get_admin_page_title()` | **durmalı.** Başlık da `$submenu` üzerinden bulunur; yoksa tarayıcı sekmesi boş kalır |
| 4 | **`admin_head`** | **burada düşürülür** |
| 5 | `menu-header.php` — sol menü basılır | yok |

Beyaz liste (`QRMS_Admin::get_menu_row_slugs()`) modül başına gizleme kodu
gerektirmez: çekirdeğin CPT'den ürettiği satır (`edit.php?post_type=…`) ve
ileride eklenecek satırlar da otomatik kapsanır. Siteye özgü bir istisna
gerekirse `qrms_menu_row_slugs` filtresi vardır.

Açık alt sayfada sol menünün doğru yeri vurgulanır: `parent_file` filtresi
üst menüyü `QR Menü` üzerinde tutar, `submenu_file` filtresi de sayfanın
**sahibi modülün** satırını seçili gösterir (ör. "Görünüm" ekranındayken
"Restoran Menü" vurgulu kalır).

### Modülün kendi yönetim sayfası

Modül, init'i içinde `QRMS_Admin::register_module_page( $slug, $callback )`
çağırırsa modül satırı o callback'i basar; çağırmazsa "Bu modül yakında
burada olacak" placeholder'ı görünmeye devam eder. Kayıt `plugins_loaded`
(öncelik 20) sırasında yapılır, `admin_menu` bundan sonra çalıştığı için
zamanlama doğrudur.

Alt ekranları olan modüller her ekranı şöyle kaydeder:

```php
add_submenu_page(
    QRMS_Admin::MENU_SLUG, $baslik, $baslik, QRMS_Admin::CAPABILITY, $slug,
    QRMS_Admin::register_module_subpage( 'restoran-menu', $slug, $callback )
);
```

`register_module_subpage()` iki iş yapar: sayfayı modülüne bağlayan kayıt
defterine yazar (menü vurgusu ve beyaz liste bunu kullanır) ve callback'i
sayfanın en üstüne **`← Modül Adı` geri bağlantısı** basacak şekilde
sarmalar — sol menüde alt satır kalmadığı için modüle dönüşün yolu budur.

### Ortak hub bileşeni

`QRMS_Admin::render_hub()` tüm modüllerde aynı ekranı üretir; modül yalnızca
içeriği verir:

```php
QRMS_Admin::render_hub( [
    'title'  => 'Restoran Menü',
    'intro'  => 'Ne yapmak istiyorsanız kartına dokunun.',
    'accent' => '#c9a84c',                 // opsiyonel, modülün marka rengi
    'stats'  => [ /* opsiyonel özet kutuları */ ],
    'cards'  => [ [ 'url' => …, 'title' => …, 'desc' => …, 'icon' => 'dashicons-art' ] ],
] );
```

Kart ızgarası masaüstünde **üç sütun**, 960px altında iki, 600px altında tek
sütundur; `pointer: coarse` cihazlarda kart yüksekliği ve ikonlar büyür.
İkonlar **dashicons** setinden gelir — emoji kullanılmaz, çünkü emoji admin'in
yazı tipi yığınına ve işletim sistemine göre kutu karakterine düşebiliyor.
Stiller `assets/css/admin.css` içindeki `.qrms-hub-*` kurallarındadır.

### Varlık sürümleri — önbellek kırma

Eklentinin CSS/JS dosyaları `wp_enqueue_style()`/`wp_enqueue_script()`'e
**sabit** `QRMS_VERSION` ile veriliyordu. Bu, dosyanın adresini
(`admin.css?ver=1.0.0`) eklenti sürümü yükseltilene kadar hiç değiştirmez:
içerik değişse bile tarayıcı, sunucudaki sayfa önbelleği ve CDN eski kopyayı
sunmaya devam eder. Hub kart ızgarası tam olarak böyle kayboldu — `.qrms-hub-*`
kuralları `assets/css/admin.css`'e eklendi ama adres değişmediği için o
kuralları içermeyen eski kopya sunuluyordu ve kartlar tek satıra çökmüş
bağlantı metni olarak görünüyordu.

Sürüm artık dosya başına hesaplanıyor:

```php
wp_enqueue_style(
    'qrms-admin',
    QRMS_PLUGIN_URL . 'assets/css/admin.css',
    array(),
    QRMS_Helpers::asset_version( 'assets/css/admin.css' )   // "1.1.0.1755766421"
);
```

`QRMS_Helpers::asset_version()` eklenti sürümüne dosyanın `filemtime()`
değerini ekler; dosya her değiştiğinde adres kendiliğinden değişir ve hiçbir
sürüm numarasını elle yükseltmek gerekmez. Sonuç istek boyunca saklanır, aynı
dosya birden çok yerde kuyruğa alınsa da disk bir kez okunur. Dosya yoksa
eklenti sürümüne düşülür.

Suite'in sahip olduğu **bütün** varlıklar (çekirdek + dokuz modül, 22 çağrı)
bu yolu kullanır. İki test bunu korur: biri hub ekranında `admin.css`'in
önbellek kıran bir sürümle kuyruğa alındığını doğrular, diğeri kaynak ağacını
tarayıp sabit `QRMS_VERSION` ile kuyruğa alınan bir varlık kalmadığını
kontrol eder.

Hub kartlarının gövdesi ayrıca blok elemanlardan (`div`/`h3`/`p`) kurulur.
HTML5'te `<a>` akış içeriği taşıyabilir; fark yalnızca stil dosyası ulaşmadığı
durumda ortaya çıkar — `span`'lerle kart tek satıra çöküp okunmaz hâle
gelirken blok elemanlarla alt alta dizilmiş okunabilir bir liste kalır.

### Kısa kod rehberi

Suite genelinde dokuz dosyada dağınık `add_shortcode()` çağrısı var ve hiçbiri
kullanıcıya ne işe yaradığını söylemiyordu. `QRMS_Shortcodes` o bilginin tek
kaydıdır; her modül kendi init'inde bildirir:

```php
QRMS_Shortcodes::register( 'restoran-menu', array(
    array(
        'tag'   => 'restaurant_menu',
        'title' => 'Restoran Menüsü',
        'desc'  => 'Ürünlerinizi kategorilere ayrılmış, aranabilir menü olarak gösterir.',
        'usage' => '[restaurant_menu]',              // verilmezse "[tag]" olur
        'note'  => 'Geçerli masa oturumu gerektirir.', // opsiyonel koşul
        'attrs' => array(
            array( 'name' => 'show_search', 'default' => 'yes', 'desc' => 'Gizlemek için "no".' ),
        ),
    ),
) );
```

Sayfa (`qrms-shortcodes`) modül bazlı bölümler hâlinde kart ızgarası basar:
kod bloğu + **Kopyala** butonu, başlık, tek cümlelik açıklama, varsa koşul
notu ve parametre listesi. Liste **dinamiktir** — lisansta aktif olmayan bir
modülün init'i hiç çalışmaz, kısa kodları da rehberde görünmez. Hiç kısa kod
yoksa menü satırı da kaydedilmez (menü kaydı ile beyaz liste aynı koşulu,
`QRMS_Shortcodes::has_any()`, kullanır).

Kopyalama `navigator.clipboard` ile yapılır; güvenli olmayan bağlamda gizli
alan + `execCommand` yedeğine, o da başarısız olursa kodu seçili hâle
getirmeye düşer. Kartlar masaüstünde iki sütun, 782px altında tek sütundur.

Beş kısa kod (`gemini_chatbot`, üç çağrı butonu, `qr_aktif_masa`) yalnızca
geçerli bir masa oturumu varken render edilir; kartlarında bu koşul ayrıca
yazar — yoksa "sayfaya koydum ama görünmüyor" kaçınılmaz olurdu.

Kayıt defterinin gerçekle uyumu testle korunuyor: test, kaynak ağacındaki
`add_shortcode()` çağrılarını tarayıp bildirilen listeyle karşılaştırır, yeni
bir kısa kod rehbere eklenmezse düşer.

### Paketlenmiş modüller

| Slug | İçerik | Yönetim sayfası |
| --- | --- | --- |
| `restoran-menu` | `rma_menu_item` CPT, `[restaurant_menu]`, `[qmo_one_cikan_slider]`, Elementor widget'ı | ✔ Hub + sekiz ekran |
| `yorum-feedback` | Çoklu kriter yorumlar, Google yönlendirme + ödül kodları, dinamik form oluşturucu, `[qr_menu_reviews]`, `[qr_menu_contact]`, `[qr_menu_form]` | ✔ Hub + yedi ayrı sayfa |
| `qr-masa` | Masa kayıtları (CRUD + toplu oluşturma), masa QR adresleri, `[qr_aktif_masa]` | ✔ Masalar ekranı |
| `qr-masa-oturum-guvenligi` | Sahte QR reddi, kilit ekranı, sayfa kilidi | ✔ Oturum limitleri |
| `qr-galeri` | Galeri CPT, bölümler, görseller | ✔ Hub + Bölümler / Görseller / Ayarlar |
| `qr-ceviri` | Çok dilli metin tarama, sözlük, CSV içe/dışa aktarma | ✔ Çeviri ekranı |
| `qr-analiz` | Menü analitiği (masa bazlı görüntüleme/tıklama takibi, panel); `POST /wp-json/qrservis/v1/analytics` — şube analitiği özeti; `POST /wp-json/qrservis/v1/create-user` — garson/müdür hesabı açma (yalnızca ana sitede) | ✔ Hub + Menü Analitiği / Firebase & Şube Ayarları |
| `qr-chatbot` | `[gemini_chatbot]`, garson/hesap buton kısa kodları, Gemini AJAX ucu, sipariş ucu (`POST /wp-json/qrservis/v1/order`) | ✔ Chatbot ayarları + Firebase ayarları |
| `qr-acilis-ekrani` | Ana sayfada tam ekran açılış: logo şeridi, arkaplan görseli, CTA + rozetler, wifi penceresi, sosyal hesaplar, ödeme yöntemleri. Kısa kodu yoktur | ✔ Hub + dört ayrı sayfa |

Kodları kaynak eklentilerinden **aynen** taşındı (`restoran-menu` 12-menu
deposundaki QR MENÜ eklentisinden, `yorum-feedback` `yorumfeedback`
deposundaki v4.2.1 eklentisinden, diğerleri birleşik `qr-menu-official`
eklentisinden); yalnızca yeni klasör konumuna göre yol string'leri düzeltildi.

`yorum-feedback` bu kuralın istisnasıdır: kaynağın kendi **QR Yorumlar** üst
menüsü kaldırılıp yedi ekranı suite menüsüne taşındığı için yönetim
dosyalarına dokunuldu (bkz. *Modül menüleri*). İş mantığı — ön yüz, AJAX,
ödül/form modülleri, veritabanı katmanı — kaynağıyla aynı kaldı.

`qr-analiz` ve `qr-chatbot` artık placeholder değildir: eski eklentinin
`admin/settings-page.php` ekranı ikiye ayrıldı. Chatbot'a özgü kısım
(`gemini_*` alanları, renk şablonları, canlı önizleme) `qr-chatbot`
altındadır; her iki modülün de ihtiyaç duyduğu Firebase/şube alanları
`_qmo-ortak/firebase-ayarlari.php` içindeki ortak bölümdedir ve iki ekranda
da görünür. Option adları değişmedi — canlı sitelerdeki kayıtlı değerler
(`qmo_firebase_sa` / `qmo_branch_id`, `gemini_api_key` vb.) korunur.

Chatbot ayar sayfası (`includes/admin/admin-sayfa.php`) Gemini, görünüm ve
yapay zeka sekmelerini içerir; menü JSON'u Restoran Menü ürünlerinden
güncellenebilir. Firebase/şube formu aynı ekranda, chatbot formunun dışında
basılır.

### Menü analitiği (masa bazlı takip)

`qr-analiz` modülü REST uçlarının yanında **menü analitiğini** de barındırır
(`class-qrms-analitik.php` + `analitik-sayfasi.php`). Eski bağımsız
"RMA Analytics" eklentisinin yerini alır; tablo adı bilinçli olarak
korunmuştur (`{prefix}rma_analytics`), böylece birikmiş kayıtlar taşınırken
kaybolmaz.

**Takip noktası.** Modül kendi izleme ucunu açmaz: `restoran-menu`'nün zaten
var olan iki AJAX ucuna **öncelik 5** ile bağlanır — `rma_load_items`
(menü görüntüleme) ve `rma_get_product_details` (ürün tıklama). Öncelik 5
zorunludur: asıl işleyiciler (öncelik 10) `wp_send_json_*` ile isteği
sonlandırır, daha geç bir öncelik hiç çalışmazdı. Ön yükleme istekleri
(`prefetch=1`) sayılmaz — menü JS'i komşu kartları arka planda ısıtır, bunlar
gerçek tıklama değildir.

**Masa bilgisi.** Menü adresi `?masa=masa-31` biçimindedir. Menü JS'i bu
değeri (adres çubuğunda yoksa imzalı `qr_masa_token` çerezinden okuyarak)
iki isteğe de ekler; sunucu tarafında `masa_belirle()` sırayla istekteki
değeri (kayıtlı masa mı diye `qmo_masa_gecerli_mi()` ile doğrulanır), masa
oturumu çerezini ve son çare olarak referer adresindeki `?masa=`yı dener.
Böylece JS önbellekten eski sürümüyle gelse bile kayıt masasız kalmaz.

**Şema geçişi.** `masa_no varchar(64)` sütunu ve masa indeksleri mevcut
tabloya `dbDelta` ile eklenir. Sorgu `CREATE TABLE IF NOT EXISTS` **değildir**:
`dbDelta` tablo adını `CREATE TABLE ([^ ]*)` kalıbıyla okuduğu için
"IF NOT EXISTS" yazıldığında tablo adını `IF` sanır ve mevcut tabloyu hiç
karşılaştırmaz — eski kurulumlarda sütun eklenmezdi. Şema kontrolü `init`
kancasındadır (frontend istekleri de kapsar) ve sürüm option'ı eşleştiğinde
tek bir option okumasına iner.

Eski bağımsız eklenti hâlâ etkinse modül izlemeyi kapatır (çift sayım olmaz)
ve veriyi aynı tablodan okumaya devam eder.

**Teşhis kutusu.** "Analitikte veri yok" şikayetinin sebebi çoğu zaman
görünmüyordu. `QRMS_Analitik::teshis()` panelin üstünde, yalnızca gerçek bir
engel varken bir kutu basar ve her bulguyu bir **eyleme** bağlar:

| Bulgu | Eylem |
| --- | --- |
| Eski `RMA_Analytics` eklentisi etkin — modül `wp_ajax_rma_load_items` / `rma_get_product_details` kancalarını hiç kaydetmez | Eklentinin gerçek adı + **tek tıkla devre dışı bırakma** bağlantısı |
| Analitik tablosu veritabanında yok | Genel Ayarlar'a yönlendirme |
| Tabloda hiç kayıt yok | "Menüyü bir kez ön yüzden açın" |
| Kayıt var ama hiçbirinde masa yok | QR Masa'ya yönlendirme |

Son madde sessiz bir veri kaybını yakalar: `masa_belirle()` → `masa_gecerli()`
adresteki `?masa=…` slug'ını `qrm_tables`'ta arar ve **kayıtlı değilse güvenlik
gereği yok sayar**; olay masasız yazılır ve "Masalara Göre" sekmesinde yalnızca
"Masasız (doğrudan erişim)" görünür.

Devre dışı bırakma bağlantısı WordPress'in Eklentiler ekranındakinin aynısıdır
(aynı action, aynı nonce). Sınıfın dosyası `ReflectionClass` ile bulunur,
`plugin_basename()` ile eklenti klasörüne indirgenir ve `active_plugins` ile
eşleştirilir; `activate_plugins` yetkisi olmayan kullanıcıya hiç gösterilmez.

### QR Masa — toplu oluşturma ve grup filtresi

Masalar ekranı iki kutuyla açılır: **Tek Masa** ve **Toplu Oluştur**. Toplu
kutusuna bir slug öneki ve numara aralığı girilir (`ic-masa`, 1–10) ve
`ic-masa-1` … `ic-masa-10` tek seferde açılır; gönderilmeden önce hangi
slug'ların oluşacağı canlı önizlemede yazar.

Zaten var olan slug'lar **atlanır** (hata değildir), böylece aralık genişletilip
yeniden gönderildiğinde yalnızca eksikler eklenir. Sonuç tek cümlede
raporlanır: *"8 masa oluşturuldu. 2 tanesi zaten vardı: ic-masa-3, ic-masa-7."*
Girdi doğrulaması (boş önek, ters aralık, tek seferde 200 masa sınırı)
`QMO_Masalar::toplu_aralik_dogrula()` içinde, döngü başlamadan ve veritabanına
hiç gidilmeden biter.

Kritik değişmez: `ekle()` slug'ı **addan** üretir. `toplu_ad()` okunabilir bir
ad verir ("Ic Masa 7") ama `sanitize_title()` sonucu beklenen slug'ı vermezse
adı doğrudan slug'a düşürür — masa yanlış adreste açılıp QR kodları tutmasın
diye. Bu değişmez testte tüm önek/numara kombinasyonları için doğrulanır.

Tablonun üstündeki çipler masaları slug önekine göre filtreler (`Tümü` ·
`ic-masa 10` · `vip 4`). Grup, slug'ın sondaki `-<sayı>` eki atılarak
türetilir (`ic-masa-12` → `ic-masa`); tamamı sayı olan slug kendi grubudur.
Filtreleme tamamen sayfa içidir — satırlar gösterilip gizlenir, sunucuya
istek gitmez. Tek grup varsa çip listesi hiç basılmaz.

Ekran mobil için uyarlanmıştır (`modules/qr-masa/assets/css/admin-masalar.css`):
782px altında kutular tek sütuna iner ve tablo `data-label` başlıklarıyla kart
görünümüne döner; `pointer: coarse` altında çipler, alanlar ve butonlar en az
44px olur, alanların yazı boyutu 16px'e çıkar (iOS Safari'nin yakınlaştırmasını
engeller).

**Panel.** "QR Menü → QR Analiz" satırı iki kartlık bir hub açar: **Menü
Analitiği** (`qrms-analiz-panel`) ve **Firebase & Şube Ayarları**
(`qrms-analiz-ayarlar`). Ayar ekranı v1.0'da modül satırının kendisiydi; hub
oraya gelince kendi slug'ına taşındı — eski adres kırılmaz, hub'ı açar.
Dönem sekmeleri
saatlik / günlük / haftalık / aylık ve **Masalara Göre**'dir; bunların yanında
tüm ekranı tek bir masaya daraltan masa filtresi vardır (o masanın kartları,
grafiği ve en çok tıklanan ürünleri). "Verileri Sil" filtre açıkken yalnızca
o masanın kayıtlarını siler. CSV indirme ekranda ne görünüyorsa onu verir:
masalar sekmesinde masa özeti, diğerlerinde ürün listesi.

Panel mobil önceliklidir (restoran sahibi telefondan bakar): kartlar dar
ekranda alt alta dizilir, tablolar 660px altında kart görünümüne döner
(başlıklar `data-label` ile hücrelerin soluna geçer), sekme çubuğu ve grafik
yatay kayar, dokunmatik cihazlarda (`pointer: coarse`) tıklama hedefleri
44px'e çıkar — `restoran-menu` yönetim ekranlarıyla aynı yaklaşım.

`restoran-menu`'nün ürün, kategori ve ayar ekranlarının tamamı suite menüsünün
altındadır — eklenti artık ayrı bir top-level "Menü" menüsü açmaz. Sol menüde
tek bir "Restoran Menü" satırı vardır; sekiz işin hepsine onun açtığı hub
ekranındaki kartlardan gidilir:

| Hub kartı | Adres |
| --- | --- |
| Ürünlerim | `edit.php?post_type=rma_menu_item` |
| Ürün Ekle | `post-new.php?post_type=rma_menu_item` |
| Kategoriler | `edit-tags.php?taxonomy=rma_category` |
| Alerjenler | `edit-tags.php?taxonomy=rma_allergen` |
| Görünüm | `qrms-rm-gorunum` |
| Öne Çıkanlar | `qrms-rm-one-cikanlar` |
| Ürün Vitrini | `qrms-rm-vitrin` |
| Diğer Ayarlar | `qrms-rm-diger` |

Dördü çekirdeğin kendi ekranı, dördü modülün `add_submenu_page()` ile
kaydettiği **gerçek, ayrı sayfalardır**; JS ile gizlenip gösterilen sekme
yoktur. Hiçbirinin sol menüde satırı yoktur (bkz. *Alt sayfalar nasıl
gizleniyor?*), ama adresleri değişmedi — eski yer imleri çalışmaya devam
eder.

Sayfaların hangi eski sekmeden geldiği:

| Sayfa | Önceki yeri |
| --- | --- |
| Görünüm | "Genel Ayarlar" sekmesi (Renkler + Tipografi + Hazır Paletler iç sekmeleri) ve "Kayar Başlık" sekmesi |
| Öne Çıkanlar | "Öneriler" sekmesi + menüden erişilemeyen `qmo_slide` (Öne Çıkan Slider) ekranı |
| Diğer Ayarlar | "Kategori Sıralaması", "İçe/Dışa Aktar" ve "Yedekleme" sekmeleri, üç bölüm hâlinde |

Sayfa tanımı tek yerdedir (`RMA_Admin_Pages_Trait::get_subpages()`); sayfa
kayıtları ve hub kartları aynı listeden beslenir. Suite yokken (eski tekil
eklenti kurulumu) kayıt `add_admin_menus()` içinde yapılır. Alanların hiçbiri
düşmedi: az kullanılanlar `<details>` bölümlerine alındı.

CPT `show_in_menu => QRMS_Admin::MENU_SLUG` ile kaydolur — böylece CPT
ekranlarında `$parent_file` suite menüsüne çözülür. Çekirdeğin bu yüzden
eklediği ürün listesi satırını beyaz liste düşürür; "Ürün Ekle" ve taksonomi
satırları zaten hiç oluşmaz (onlar yalnızca top-level menü alan CPT'ler için
üretilir). CPT, taksonomi ve Öne Çıkan Slider ekranlarında menü vurgusu
`modules/restoran-menu/module.php` içindeki `parent_file` / `submenu_file`
filtreleriyle "Restoran Menü" satırına sabitlenir — ekran kodlarına
dokunulmaz.

Eski adresler çalışmaya devam eder: `page=rma_settings` ve altı eski sekme
slug'ı (`rma_color_settings`, `rma_nav_design`, `rma_category_order`,
`rma_suggestions_settings`, `rma_csv_import`, `rma_menu_backup`) gizli sayfa
olarak kayıtlı kalır ve içeriğin taşındığı yeni sayfanın ilgili bölümüne
yönlendirir — eski `&tab=` parametresi de dikkate alınır. İçe aktarma
yönlendirmeleri artık doğrudan yeni sayfalara düşer (`admin_page_url()`, suite
var/yok durumuna göre doğru adresi üretir).

Yönetim ekranları mobil tarayıcı için uyarlanmıştır: `pointer: coarse`
medya sorgusunda dokunma alanları en az 44px'e çıkar, dar ekranda `form-table`
satırları alt alta bloklara açılır, geniş tablolar kendi içinde yatay kayar
(`.rma-table-scroll`) ve kart ızgaraları tek sütuna iner. WordPress admin'in
kendi mobil davranışına müdahale edilmez.

> **Dağıtım notu:** `restoran-menu` modülü aktifken eski tekil **QR MENÜ**
> eklentisi devre dışı bırakılmalıdır — modül onun yerini alır. Yan yana
> bırakılırsa eski eklenti daha erken yüklendiği için `RMA_PLUGIN_URL` /
> `QMO_PLUGIN_URL` sabitlerini o tanımlar ve varlık adresleri eski klasörü
> gösterir. Sabitlerdeki `defined()` guard'ı ve `__DIR__` tabanlı require'lar
> yalnızca notice ile çift yüklemeyi önler.

`yorum-feedback` de aynı deseni izler. Kaynakta modülün ekranları ayrı bir
**QR Yorumlar** üst menüsünde duruyor, suite menüsündeki "Yorum & Feedback"
satırı ise bunlardan yalnızca birini (Tüm Yorumlar) ikinci kez basıyordu:
aynı ekran iki menüden açılıyor, diğer altı ekran suite menüsünde hiç
görünmüyordu. Üst menü kaldırıldı; artık tek giriş noktası var:

| Ekran | Slug | İçerik |
| --- | --- | --- |
| **Yorum & Feedback** (sol menüdeki tek satır) | `qrms-module-yorum-feedback` | Hub: özet sayaçlar + yedi kart |
| Tüm Yorumlar | `qrms-yf-yorumlar` | Yorum listesi, durum filtreleri, onay/sil |
| Detaylı İçgörüler | `qrms-yf-icgoruler` | Genel ortalama, kriter bazlı performans |
| Müşteri Bilgileri Formu | `qrms-yf-form-alanlari` | Yorum formunun alanları, sıralama |
| Ayarlar & Puanlama | `qrms-yf-ayarlar` | Kriterler, form görünümü, otomatik onay, spam |
| İletişim | `qrms-yf-iletisim` | İletişim formu başlığı + kısa kod |
| Google & Ödül Sistemi | `qrms-yf-odul` | Google yönlendirme, popup, indirim kodları |
| Formlar | `qrms-yf-formlar` | Özel form oluşturucu + gönderiler |

Sayfa tanımı tek yerde durur (`qrm_pro_admin_pages()`,
`includes/admin/menu.php`); sayfa kayıtları, hub kartları ve varlık kuyruğunun
"bu benim sayfam mı" kontrolü aynı listeden beslenir. Bağlantılar
`qrm_pro_admin_url()` ile üretilir — hiçbir slug JS'e ya da HTML'e gömülmez.

v4.1.x ve v4.2.x adreslerinden (`qrm-pro-main`, `qrm-pro-settings&sub=alanlar`,
`qrm-pro-insights`, `qrm-forms&view=edit` …) gelenler
`qrm_pro_legacy_page_target()` ile yeni sayfalarına yönlendirilir; sekme/görünüm
parametreleri hedefi belirlemekte kullanılır. Rozetler (okunmamış form
gönderimi, eksik ödül kurulumu) sol menüde modülün **tek** satırında toplanır
(`qrms_module_menu_label` filtresi); hangi ekranın ilgilendiği hub
kartlarındaki rozetlerden okunur.

Kaynaktaki üç JS sekmesi (Tüm Yorumlar > İçgörüler, Ayarlar > Müşteri Bilgileri
Formu) gerçek sayfaya dönüştürüldü. Bu sekmelerin hem görünürlüğü hem aktif
göstergesi tamamen jQuery'ye bağlıydı: sayfadaki herhangi bir JS hatasında iki
sekme birden ölüyor ve hiçbiri aktif görünmüyordu. Ayrıca `history.replaceState`
ile adres çubuğunu **başka bir sayfanın** adresine çeviriyorlardı; suite
menüsünden açıldığında yenileme sonrası yanlış ekrana düşülüyordu.

Ekranlar sadeleştirildi: Ayarlar sayfasındaki Google/ödül bilgi kartı
kaldırıldı (aynı checklist zaten "Google & Ödül Sistemi" sayfasının başında
duruyordu), arayüzdeki sürüm notları temizlendi, tekrarlayan açıklama
cümleleri teke indirildi. "Tüm Yorumlar" listesi artık boş kaldığında sebebini
söyler: veri yoksa kısa kod yönlendirmesi, tablo eksikse veritabanı uyarısı
basar (`qrm_pro_reviews_table_exists()`). Satır aksiyonları (onayla / yayından
kaldır / sil) nonce'suz GET bağlantılarıydı; artık `wp_nonce_url` ile üretilip
doğrulanır.

Yönetim ekranları mobil tarayıcı için uyarlanmıştır
(`modules/yorum-feedback/assets/css/admin.css`): `pointer: coarse` medya
sorgusunda dokunma alanları en az 44px'e çıkar ve girdiler 16px'e büyür (iOS
Safari'nin yakınlaştırmasını engeller), dar ekranda `form-table` satırları alt
alta bloklara açılır, geniş tablolar (yorumlar, ödül kodları, form gönderileri)
`data-label` başlıklarıyla kart görünümüne döner. Sürükle-bırak sıralama hem
yorum formu alanlarında (jQuery UI) hem form oluşturucuda (HTML5 DnD)
dokunmatikte çalışmadığı için her iki ekrana da yukarı/aşağı butonları eklendi;
sıralama artık telefondan ve klavyeyle de yapılabilir.

Modül CPT ya da taksonomi kullanmaz; verisini altı kendi tablosunda tutar
(`qrm_reviews`, `qrm_form_fields`, `qrm_reward_codes`, `qrm_custom_forms`,
`qrm_custom_form_fields`, `qrm_custom_form_submissions`). Tablo adları
`qrm_` ön ekini `qr-masa`'nın `qrm_tables` tablosuyla paylaşır ama hiçbiri
çakışmaz. Kurulum, kaynağın `qrm_pro_maybe_upgrade()` fonksiyonu `module.php`
içinden doğrudan çağrılarak yapılır: kaynağın iki kendi yolu da modül
bağlamında ölüdür — `register_activation_hook()` bir eklenti dosyası olmadığı
için hiç tetiklenmez, `plugins_loaded` (öncelik 10) kaydı ise modül zaten
öncelik 20 içinde yüklendiğinden çoktan geçmiş bir önceliğe eklenir.

> **Dağıtım notu:** `yorum-feedback` modülü aktifken eski tekil **QR Menü
> Gelişmiş Müşteri Yorumları** eklentisi devre dışı bırakılmalıdır — modül
> onun yerini alır. Kaynağın 190 fonksiyonunun hiçbirinde `function_exists()`
> guard'ı yoktur, yani iki kopya yan yana yüklenirse çift tanım ölümcül
> hatası verir. `module.php`'deki `QRM_PRO_VERSION` kontrolü bunu fatal
> yerine sessiz devre dışı kalmaya çevirir (sabiti eski eklenti tanımlar ve
> çalışmaya devam eder), ama iki kopyayı birlikte çalışır kılmaz.

### QR Çalışma Saatleri — renkler ve canlı önizleme

Modülün görünümü stylesheet'teki sabit renklerden geliyordu (bugünün vurgusu
`#c9a84c`, satır ayracı `rgba(0,0,0,.08)`) ve yönetim ekranında saatlerin
müşteri tarafında nasıl görüneceğini gösteren hiçbir şey yoktu.

**Renkler ayrı bir option'da durur** (`qrms_calisma_saatleri_renkler`).
Saatler `qrms_calisma_saatleri` içindedir ve `qrms_cs_sanitize()` o diziyi gün
anahtarlarına indirger — gün anahtarı olmayan her şeyi düşürür. Renkleri aynı
option'a koymak ya sessizce silinmelerine ya da çalışan saat şemasını yeniden
yazmaya mal olurdu. Form yine tek: restoran sahibi için tek bir **Kaydet**
vardır, iki option birlikte yazılır.

**Boş renk = temadan devral.** Seçilmemiş renk CSS değişkeni olarak hiç
basılmaz; stylesheet'teki `var(--qrms-cs-today, #c9a84c)` geri düşüşü devrede
kalır. Bu yüzden modül güncellendiğinde hiçbir sitenin görünümü değişmez —
testi de var ("hiç renk seçilmemişken çıktı eskisiyle birebir aynıdır").

Altı alan: bugünün vurgusu, bugünün satır zemini, gün adı, saat metni, kapalı
gün, satır ayracı. Alan işaretlemesi restoran-menu'nün renk seçicisiyle aynı
(`data-default-color` taşıyan metin kutusu + `wpColorPicker`).

**Canlı önizleme kısa kodun taklidi değil, kendisidir:** `qrms_cs_shortcode()`
yönetim ekranında da çağrılır ve ön yüzün gerçek `frontend.css`'i orada da
kuyruğa alınır. Ayrı bir şablon tutulsaydı ikisi zamanla ayrışır ve önizleme
yalan söylemeye başlardı. JS yalnızca hazır DOM'u günceller: saat alanı, kapalı
kutusu ve renk seçicisi değiştikçe liste kaydetmeden yenilenir.

Saat metni iki yerde üretiliyor — sayfa açılışında PHP (`qrms_cs_format_day()`),
değişiklikte JS. Metinlerin kendisi `wp_localize_script` ile PHP'den geçer
(çeviri tek yerde kalır); dallanma (kapalı / açılış-kapanış eşitse 24 saat /
aralık) iki tarafta da aynıdır ve **ikisi birden testle** doğrulanır.

### Açılış Ekranı (`qr-acilis-ekrani`)

Ana sayfaya gelen ziyaretçiyi karşılayan tam ekran açılış. Bağımsız **Açılış
Ekranı (Splash Screen)** eklentisinden (v3.7) taşındı; ön yüz davranışı birebir
korundu, değişen tek şey yönetim tarafı oldu.

Ekranda ne var: tam genişlik logo şeridi (yükseklik/renk/opaklık ayarlanır),
arkaplan görseli (geniş ekran için ayrı varyant + LCP preload) ve okunabilirlik
gradyanı, sağ üstte yüklenme göstergesi (spinner / geri sayım halkası / nokta /
nabız), birincil buton, telefon-takvim-yıldız-wifi rozetleri, ayraç, sosyal
rozetler ve ödeme yöntemi satırı (ikonlu, yalnızca yazı ya da kayan şerit).

**Çıktı çerezden bağımsızdır.** Ana sayfanın HTML'i her ziyaretçide birebir
aynıdır; overlay her zaman `style="display:none"` ile basılır ve "gösterilsin
mi" kararını `document.cookie` okuyan istemci tarafı verir. Tam sayfa cache
(LiteSpeed, WP Rocket, CDN) altında doğru çalışmasının sebebi budur —
sunucu tarafında çerez dallanması olsaydı ilk ziyaretçinin cevabı herkese
servis edilirdi.

**FOUC koruması.** `wp_head` önceliği 2'de küçük bir kritik CSS bloğu ve inline
betik basılır: çerez yoksa `<html>` `splash-loading` sınıfını alır, gövde
gizlenir ve overlay görünür olur. Betik hiç çalışmazsa sayfa sonsuza kadar
gizli kalmasın diye 5 saniyelik bir zaman aşımı sigortası ve `<noscript>`
kurtarması vardır.

**Çerez yönlendirmeden ÖNCE yazılır.** Kapatma (`dismissSplash`) çerezi
yazdıktan sonra navigasyona izin verir; aksi hâlde hedef sayfa açılırken
splash yeniden görünürdü. Süre `dismiss_duration` (dakika) ile ayarlanır,
0 = her ziyarette gösterilir.

**Giriş animasyonu tek seferliktir.** `.splash-stack.is-animating` sınıfı
`animationend` olayında kaldırılır; geride sürekli compositing yapan bir katman
kalmaz. Üç tip: zarif yükseliş, dinamik yay, sinematik derinlik.

#### Suite'e taşınırken değişenler

| Bağımsız eklenti | Suite modülü |
| --- | --- |
| Kendi `Açılış Ekranı` üst menüsü | Suite menüsündeki tek satır + hub ekranı |
| Tek sayfa, dört JS sekmesi | Dört **gerçek sayfa** (`qrms-ae-gorunum`, `-butonlar`, `-odeme`, `-davranis`) |
| Tek form, tek kayıt | Sayfa başına form, nonce ve kayıt |
| `SPLASH_SCREEN_VERSION` ile sabit varlık sürümü | `QRMS_Helpers::asset_version()` (dosya bazlı) |
| Önizleme yok, "ana sayfayı aç" bağlantısı var | Her ayar sayfasında **canlı önizleme** |

Sekmeler ayrı sayfalara bölününce doğan asıl risk kayıt tarafındadır: tek
formda işaretsiz bir onay kutusu güvenle "kapalı" demekti, dört ayrı formda ise
POST'ta bulunmayan kutu başka bir sayfada yapılmış seçimi siler. Bu yüzden
onay kutuları yalnızca **sahibi sayfa** gönderildiğinde yazılır — Ödeme
sayfasında yöntem seçip Davranış sayfasını kaydetmek seçimleri bozmaz. Testte
karşılığı var.

Ayarlar bağımsız eklentiyle **aynı option'da** (`splash_screen_options`)
durur: mevcut bir kurulumda eklenti kapatılıp modül açıldığında ayarlar
olduğu yerden okunmaya devam eder, göç adımı yoktur. Eski yer imleri de
(`admin.php?page=splash-screen&tab=…`) karşılığı olan yeni sayfaya
yönlendirilir.

#### Canlı önizleme ve `isPreview` guard'ı

Önizleme frontend'in **aynı markup'ı ve aynı stylesheet'i**dir; ayrı bir taklit
yoktur. Tek fark overlay'in `data-preview="1"` bayrağı taşımasıdır ve bu bayrak
iki tarafı birden korur:

- `splash.js` bayrağı görürse hiç çalışmaz — çerez okunmaz/yazılmaz,
  yönlendirme zamanlayıcısı kurulmaz. Yönetici önizlemeye bakarken kendi
  tarayıcısında splash'ı "kapatmış" duruma düşmez.
- Yönetim betiği de yalnızca bu bayrağı taşıyan overlay'e dokunur.

Renk, opaklık ve ölçü ayarları CSS özel değişkenine indiği için kaydetmeden
anında yansır; metin alanları da öyle. Yapıyı değiştiren ayarlar (görsel
seçimi, ödeme yöntemi, sosyal hesap, gösterge tipi) yeni markup gerektirir —
o durumda önizlemenin üstünde "kaydedilince güncellenecek" rozeti belirir.
PHP ile JS'in aynı hesabı iki kez yapmaması için değişkenlerin tek kaynağı
`build_css_vars()`'tır.

#### Bilinmesi gerekenler

- Ekran yalnızca **ana sayfada** basılır; Elementor editöründe ve Customizer
  önizlemesinde basılmaz.
- `button_padding` ve `button_font_size` option'ları v3.6'dan beri ön yüzü
  etkilemiyor (CTA'nın ölçüsü stylesheet'te sabit). Anahtarlar ve admin
  alanları veri kaybı olmasın diye korundu.
- Modül, lisans sunucusundan gelen aktif modül listesinde `qr-acilis-ekrani`
  slug'ı yoksa yüklenmez — dosyalar yerinde olsa bile yönetim ekranı
  görünmez. Slug sunucudaki modül sözleşmesine de eklenmelidir.

### `modules/_qmo-ortak/`

Modül değildir (loader yalnızca `QRMS_Helpers::MODULE_SLUGS` içindeki
slug'ları yükler), modüllerin ortak çalışma zeminidir: masa oturumu sınıfı
(`QMO_Oturum`), Firestore istemcisi (`QMO_Firestore`), yardımcılar, varlık
kaydı ve chatbot renk varsayılanları. İhtiyacı olan her `module.php` bunu
`require_once` ile yükler. (`restoran-menu` yüklemez: kendi `RMA_`/`QMO_`
ad alanıyla tamamen kendi kendine yeterlidir.)

Masa oturumu sınıfı bilinçli olarak burada durur: `helpers.php`'deki
`qmo_oturum()` / `qmo_oturum_zorla()` doğrudan `QMO_Oturum` üzerine
kuruludur, oysa modüller birbirinden bağımsız lisanslanır. Sınıf yalnızca
`qr-masa-oturum-guvenligi` altında dursaydı, o modül lisanslı değilken
chatbot her render'da ölümcül hata verirdi. Oturumun *zorlanması* (kilit
ekranı, sayfa kilidi) o modülde kalır.

`QMO_Firestore` aynı gerekçeyle burada durur: sınıfı iki modül birden
kullanır — `qr-analiz` (`rest-analytics`, `rest-create-user`: Firebase ID
token doğrulama ve rol okuma) ve `qr-chatbot` (`rest-order`, `ajax-order`,
`ajax-waiter-bill`: çağrı ve sipariş yazma). Service account JSON'ı koda
gömülmez; sırasıyla `QMO_FIREBASE_SA_JSON` sabiti (wp-config),
`qmo_firebase_sa` option'ı ve eski `qrservis_service_account_json()`
snippet'i aranır.

Sınıfın okuduğu üç option'ın (`qmo_branch_id`, `qmo_firebase_sa`,
`qmo_ana_site`) kaydı ve ortak form bölümü `firebase-ayarlari.php`
içindedir. Kendi ayar grubunda (`qmo_firebase_grup`) ve kendi `<form>`'unda
durur, çünkü `options.php` gönderilen grubun *tüm* option'larını yeniden
yazar: bölüm hem `qr-chatbot` hem `qr-analiz` ekranında göründüğü için ortak
bir grup paylaşsalardı birinden kaydetmek diğerinin alanlarını silerdi. Bu
dosya `ortak.php` ile değil, ihtiyacı olan modülün `module.php`'sindeki
`is_admin()` dalıyla yüklenir.

Taşınan kodun tamamı `class_exists()` / `function_exists()` / `defined()`
guard'lıdır: eski `qr-menu-official` eklentisi aynı sitede hâlâ aktifse
çift tanım hatası olmaz, ilk yükleyen kazanır.

## Admin menüsüyle ilgili iki tasarım notu

**Sihirbaz gizli ama erişilebilir.** `admin.php?page=qrms-wizard` gerçek bir
alt menü olarak kaydedilir; menüden gizleme `current_screen` hook'unda yapılır.
(Beyaz liste de sihirbazı `admin_head`'de düşürür; sihirbazın kendi gizlemesi
lisans doğrulanmamışken bile — yani modül satırları hiç kurulmamışken —
çalıştığı için korunur.)
Gizlemeyi `admin_menu` içinde yapmak sayfayı erişilemez kılar: WordPress
route'u `admin_menu`den sonra çözer (`wp-admin/admin.php` önce `menu.php`, sonra
`get_plugin_page_hook()`) ve hook adını hesaplarken sayfanın parent'ını
`$submenu` içinde arar. Alt menü o an silinmişse hook adı
`admin_page_qrms-wizard` olarak hesaplanır, `$_registered_pages` ile eşleşmez ve
sayfa 403 verir. `current_screen` route çözüldükten sonra, hem menü HTML'i hem
de komut paleti verisi üretilmeden önce çalıştığı için doğru yerdir. Sayfa
`$submenu`den çıktığında WordPress başlığı da bulamadığından `$title` sihirbaz
ekranında elle set edilir.

**Menü konumu ondalıklıdır.** WordPress menü satırlarını `$menu` dizisinde
konumu anahtar yaparak tutar (`$menu['30']`). Aynı tam sayı konumunu kullanan
başka bir plugin o slotu ezerse menü hiç görünmez. Bu yüzden konum `57.3` gibi
bize özgü ondalıklı bir değerdir; ayrıca `admin_menu` zincirinin sonunda
(öncelik 999) menü satırı hâlâ yerinde mi diye bakılır ve ezilmişse geri eklenir.
Menüyü bilerek kaldıran siteler `qrms_ensure_menu_registered` filtresini `false`
döndürerek bu emniyet kemerini kapatabilir.

## Dosya yapısı

```
qr-menu-suite.php            Ana dosya: header, sabitler, aktivasyon hook'ları
includes/
  class-helpers.php          Modül slug/isim listesi, durum etiketleri
  class-license-client.php   Doğrulama, option'lar, günlük cron, notice
  class-wizard.php           Tek ekranlı kurulum sihirbazı + lisans formu
  class-module-loader.php    modules/<slug>/module.php yükleyici
  class-admin.php            Menü çatısı, tek seviyeli menü, ortak hub bileşeni, Genel Bakış, Genel Ayarlar
  class-shortcodes.php       Kısa kod kayıt defteri ve "Kısa Kodlar" rehber ekranı
modules/
  _qmo-ortak/                Ortak zemin (oturum sınıfı, Firestore istemcisi, helpers, varlıklar)
  restoran-menu/             Menü CPT'si, kısa kodlar, slider + hub ve sekiz yönetim ekranı
  yorum-feedback/            Yorumlar, ödül kodları, form oluşturucu + hub ve yedi yönetim sayfası
  qr-masa/                   Masa kayıtları + Masalar yönetim ekranı
  qr-masa-oturum-guvenligi/  Masa doğrulama, kilit ekranı + oturum ayarları
  qr-analiz/                 Menü analitiği (masa bazlı takip + panel), analitik/kullanıcı REST uçları, Firebase ayar ekranı
  qr-chatbot/                Gemini chatbot, garson/hesap butonları, sohbet/çağrı/sipariş uçları + ayar ekranı
  qr-ceviri/                 Çok dilli sözlük + çeviri yönetim ekranı
  qr-galeri/                 Galeri CPT + yönetim ekranları
  qr-acilis-ekrani/          Ana sayfa açılış ekranı + hub ve dört ayar sayfası
assets/css/admin.css         Mobil öncelikli admin stilleri (dokunma ≥44px)
assets/js/admin.js           Form gönderiminde buton kilidi (opsiyonel iyileştirme)
tests/                       WordPress'siz çalışan stub tabanlı testler
```

## Testler

WordPress kurulumu gerekmez; çekirdek fonksiyonlar `tests/stubs-wordpress.php`
içinde taklit edilir.

```
php tests/test-suite.php
```

Lisans durum dalları (`active` / `invalid` / `inactive` / `domain_mismatch` /
`unreachable`), fail-safe modül davranışı, sihirbaz yönlendirme kuralları,
modül yükleyici ve admin menüsünün modül görünürlüğü test edilir.

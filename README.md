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

Görünen adlar modülün NE YAPTIĞINI söyler; slug'lar (lisans sözleşmesinin ve
kayıtlı option'ların anahtarı) hiç değişmez.

| Slug | İsim |
| --- | --- |
| `restoran-menu` | Restoran Menü |
| `yorum-feedback` | Yorum & Feedback |
| `qr-masa` | QR Kod Oluştur |
| `qr-analiz` | İstatistikler |
| `qr-galeri` | Fotoğraf Galerisi |
| `qr-ceviri` | Dil / Çeviri Ayarları |
| `qr-chatbot` | Chatbot Asistan |
| `qr-calisma-saatleri` | Çalışma Saatleri |
| `qr-masa-oturum-guvenligi` | Güvenlik Ayarı |
| `qr-acilis-ekrani` | Açılış Ekranı |
| `header-footer-builder` | Header Footer Builder |
| `qr-servis-paneli` | Servis Paneli |
| `qr-menu-muhendisligi` | Menü Mühendisliği |

Bir modül eklemek için `modules/<slug>/module.php` dosyası oluşturmak ve
içinde `qrms_module_<slug_alt_çizgili>_init()` fonksiyonunu tanımlamak
yeterlidir (ör. `restoran-menu` → `qrms_module_restoran_menu_init()`).
Loader, modül lisansta aktifse dosyayı `require` eder ve bu fonksiyonu
çağırır; dosya yoksa sessizce atlar.

### Sol menü tek seviyeli, satırlar beş kategoride

`QR Menü` menüsünde **yalnızca** şunlar durur: `Genel Bakış`, lisansta aktif
olan modüllerin adları, (varsa) `Kısa Kodlar` ve `Genel Ayarlar`. Modüllerin
alt ekranları menüye hiç yazılmaz; onlara modülün **hub ekranındaki
kartlardan** gidilir. Satırlar alt menü açmaz — yalnızca katlanabilir
**kategori başlıkları** altında gruplanır.

```
QR Menü
├ GENEL
│ ├ Genel Bakış
│ └ Genel Ayarlar
├ MENÜ YÖNETİMİ
│ ├ Restoran Menü               → hub (12 kart)
│ └ Yorum & Feedback            → hub (7 kart + özet sayaçlar)
├ ARAÇLAR
│ ├ Servis Paneli               → doğrudan canlı sipariş/çağrı panosu
│ ├ QR Kod Oluştur              → doğrudan Masalar ekranı
│ ├ İstatistikler               → doğrudan Menü Analitiği
│ ├ Menü Mühendisliği           → hub (4 kart + özet sayaçlar)
│ ├ Fotoğraf Galerisi           → hub (3 kart)
│ ├ Dil / Çeviri Ayarları       → doğrudan Çeviri ekranı
│ ├ Chatbot Asistan             → doğrudan Chatbot ayarları
│ └ Çalışma Saatleri            → doğrudan Saat tablosu
├ GÖRÜNÜM & ERİŞİM
│ ├ Açılış Ekranı               → hub (5 kart + özet sayaçlar)
│ └ Header Footer Builder       → doğrudan HFB ekranı
└ GELİŞMİŞ
  ├ Güvenlik Ayarı              → hub (2 kart)
  └ Kısa Kodlar                 → modüllerin kısa kod rehberi
```

Hub, modülün **ikiden fazla ekranı olduğunda** vardır. Tek ekranlı modüllerde
araya bir sayfa koymak fazladan tık demek olurdu; modül satırı doğrudan o
ekranı açar.

#### Gruplama, renkler ve katlama

| Kategori | Renk | İçindekiler |
| --- | --- | --- |
| Genel | `#9ba7b4` (nötr gri) | Genel Bakış, Genel Ayarlar |
| Menü Yönetimi | `#5cb0f0` (gök mavisi) | Restoran Menü, Yorum & Feedback |
| Araçlar | `#35d1b4` (turkuaz) | Servis Paneli, QR Kod Oluştur, İstatistikler, Menü Mühendisliği, Fotoğraf Galerisi, Dil / Çeviri Ayarları, Chatbot Asistan, Çalışma Saatleri |
| Görünüm & Erişim | `#f27cb8` (pembe) | Açılış Ekranı, Header Footer Builder |
| Gelişmiş | `#f59547` (turuncu) | Güvenlik Ayarı, Kısa Kodlar |

Gruplama, sıra ve renkler **tek yerde**, `QRMS_Admin::get_menu_groups()`
içindedir (siteye özgü değişiklik için `qrms_menu_groups` filtresi vardır).
Renk yalnızca **ikonda** ve satırın **sol kenar şeridinde** görünür; satır
arka planı yönetim renk şemasının kendi rengi olarak kalır. Palet üst
menüdeki mavi–mor gradyanla yarışmasın diye mavi daha açık bir gök mavisine,
mor ise pembeye çekilmiştir.

İş bölümü şöyledir:

| Katman | Sorumluluk |
| --- | --- |
| `QRMS_Admin::build_menu_rows()` (`admin_head`, öncelik 11) | Satırları grup sırasına sokar, etiketin başına dashicon koyar, satıra `qrms-menu-item qrms-mg-<grup>` sınıflarını yazar (WordPress alt menü dizisinin 4. dizinini `<li>` ve `<a>`'nın class'ına geçirir) |
| `assets/css/admin-menu.css` | Kategori rengini `--qrms-menu-accent` üzerinden ikona ve sol kenar şeridine uygular; renklerin değerleri `get_menu_groups()`'tan satır içi stil olarak gelir |
| `assets/js/admin-menu.js` | Grup başlığı satırlarını (aç/kapa düğmesi) DOM'a ekler, durumu `localStorage`'da saklar; açık sayfanın grubu her zaman açılır |

JavaScript çalışmasa bile menü **doğru sırada ve renk şeridiyle** görünür;
yalnızca başlıklar ve katlama olmaz. Hiçbir sayfanın erişimi bu betiğe bağlı
değildir.

Menü katlandığında (`folded`) ya da ekran darken (`auto-fold`) katlama devre
dışıdır: uçan menüde (flyout) hiçbir satır gizli kalmaz. `admin-menu.css`
sol menüye kural yazan **tek** dosyadır ve her seçicisinde `.qrms-` ile
başlayan bir sınıf bulunur — `#adminmenu` ve `.wp-submenu` yalnızca kapsam
daraltmak için kullanılır, konum/genişlik/`display` çekirdeğe bırakılır.
Testler ikisini de kontrol eder.

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

Kartlar başlıklı bölümler hâlinde de verilebilir: `cards` yerine
`card_groups` (`[ [ 'title' => 'Ürünler', 'cards' => [ … ] ], … ]`) geçilirse
her grup kendi `<h2 class="qrms-hub-group-title">` başlığı + ayrı bir
`.qrms-hub-grid` ile art arda basılır (Restoran Menü hub'ı böyle kullanır —
bkz. `RMA_Admin_Pages_Trait::get_hub_cards()`). `card_groups` verilmezse
davranış eskisi gibi tek, başlıksız bir ızgaradır.

Kart ızgarası (her grup içinde ayrı ayrı) masaüstünde **üç sütun**, 960px
altında iki, 600px altında tek sütundur; `pointer: coarse` cihazlarda kart
yüksekliği ve ikonlar büyür. İkonlar **dashicons** setinden gelir — emoji
kullanılmaz, çünkü emoji admin'in yazı tipi yığınına ve işletim sistemine
göre kutu karakterine düşebiliyor. Stiller `assets/css/admin.css` içindeki
`.qrms-hub-*` kurallarındadır; grup başlığının serif/dark-gold stili
modüle özgü olduğu için `modules/restoran-menu/assets/css/hub.css`
içindedir.

### Genel Bakış — kategorili kart ızgarası

`Genel Bakış` (`admin.php?page=qrms-overview`) modülleri düz bir liste yerine
**dört kategoride** kart ızgarası olarak gösterir:

| Kategori | İçindekiler |
| --- | --- |
| Menü & Ürünler | Restoran Menü, QR Galeri, Açılış Ekranı |
| Müşteri Etkileşimi | Yorum & Feedback, QR Chatbot, QR Çeviri |
| Masa & Servis | QR Masa, Güvenlik Ayarı, QR Çalışma Saatleri |
| Analiz & Ayarlar | QR Analiz, Kısa Kodlar, Genel Ayarlar |

Gruplama `QRMS_Admin::get_overview_groups()` içinde **tek yerde** durur;
kartların ikon ve açıklamaları modül isimleriyle aynı dosyadadır
(`QRMS_Helpers::get_module_meta()`). Bir modül yeni eklenir de gruplamaya
yazılmayı unutulursa ekrandan düşmez: `build_overview_groups()` onu sondaki
"Diğer Modüller" kategorisine alır. Bir test her modülün kategorilerde tam
olarak bir kez geçtiğini korur.

Kart görseli hub bileşeniyle **ortaktır** (`.qrms-hub-card`) — aynı dizilim,
aynı 3/2/1 sütun kırılımları, aynı dokunmatik büyütmesi. Genel Bakış'a özgü
tek fark lisans durumudur:

- **Aktif** modül → tıklanabilir kart, sağında yeşil tik.
- **Pasif** modül → soluk, kesik çerçeveli, `Pasif` rozetli **bağlantısız**
  kutu. Pasif modülün sayfası kayıtlı olmadığı için adres de basılmaz; kart
  bağlantı olsaydı WordPress'in "izin verilmiyor" ekranına götürürdü.
- **Kısa Kodlar / Genel Ayarlar** lisansa bağlı değildir, rozet almaz ve
  kategori sayacına (`2/3 aktif`) girmez.

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

Altı kısa kod (`gemini_chatbot`, üç çağrı butonu, `qmo_sepet`, `qr_aktif_masa`) yalnızca
geçerli bir masa oturumu varken render edilir; kartlarında bu koşul ayrıca
yazar — yoksa "sayfaya koydum ama görünmüyor" kaçınılmaz olurdu. `qmo_sepet`
oturum yoksa bilgi kutusu basmaz, boş döner; yöneticiler test için görür.

Kayıt defterinin gerçekle uyumu testle korunuyor: test, kaynak ağacındaki
`add_shortcode()` çağrılarını tarayıp bildirilen listeyle karşılaştırır, yeni
bir kısa kod rehbere eklenmezse düşer.

### Paketlenmiş modüller

| Slug | İçerik | Yönetim sayfası |
| --- | --- | --- |
| `restoran-menu` | `rma_menu_item` CPT, `[restaurant_menu]`, `[qmo_one_cikan_slider]`, `[qmo_banner_slider]` (`qmo_banner_slide` CPT + Kampanya Banner sihirbazı), toplu fiyat kampanyası, Elementor widget'ı | ✔ Hub + on ekran |
| `yorum-feedback` | Çoklu kriter yorumlar, Google yönlendirme + ödül kodları, dinamik form oluşturucu, `[qr_menu_reviews]`, `[qr_menu_contact]`, `[qr_menu_form]` | ✔ Hub + altı ayrı sayfa |
| `qr-masa` | Masa kayıtları (CRUD + toplu oluşturma), masa QR adresleri, `[qr_aktif_masa]` | ✔ Masalar ekranı |
| `qr-masa-oturum-guvenligi` | Sahte QR reddi, kilit ekranı, sayfa kilidi; uygulamanın REST uçlarının Firebase/şube yapılandırması | ✔ Hub + Oturum Limitleri / Firebase & Şube Ayarları |
| `qr-galeri` | Galeri CPT, bölümler, görseller | ✔ Hub + Bölümler / Görseller / Ayarlar |
| `qr-ceviri` | Çok dilli metin tarama, sözlük, CSV içe/dışa aktarma | ✔ Çeviri ekranı |
| `qr-analiz` | Menü analitiği (masa bazlı görüntüleme/tıklama takibi, panel); `POST /wp-json/qrservis/v1/analytics` — şube analitiği özeti; `POST /wp-json/qrservis/v1/create-user` — garson/müdür hesabı açma (yalnızca ana sitede) | ✔ Menü Analitiği (tek ekran) |
| `qr-chatbot` | `[gemini_chatbot]`, garson/hesap buton kısa kodları, `[qmo_sepet]`, Gemini AJAX ucu, sipariş ucu (`POST /wp-json/qrservis/v1/order`) | ✔ Chatbot ayarları + Firebase ayarları |
| `qr-menu-muhendisligi` | Kasavana–Smith menü kârlılık matrisi, ürün maliyetleri, malzeme fiyatları, reçete tabanlı maliyet | ✔ Hub + dört alt ekran |
| `qr-servis-paneli` | Firestore `calls` koleksiyonundan canlı sipariş/garson/hesap paneli, kanban, sesli uyarı | ✔ Panel + ayarlar alt sayfası |
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
(Chatbot ayarları + Güvenlik Ayarı → Firebase & Şube Ayarları) da görünür. Option adları değişmedi — canlı sitelerdeki kayıtlı değerler
(`qmo_firebase_sa` / `qmo_branch_id`, `gemini_api_key` vb.) korunur.

Chatbot ayar sayfası (`includes/admin/admin-sayfa.php`) Gemini, görünüm ve
yapay zeka sekmelerini içerir; menü JSON'u Restoran Menü ürünlerinden
güncellenebilir. Firebase/şube formu aynı ekranda, chatbot formunun dışında
basılır.

### Menü analitiği (masa bazlı takip)

`qr-analiz` modülü REST uçlarının yanında **menü analitiğini** de barındırır
(`class-qrms-analitik.php` ve kategori alt sayfaları). Eski bağımsız
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
gereği yok sayar**; olay masasız yazılır ve Masalar kategorisinde yalnızca
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

**Panel.** "QR Menü → İstatistikler" satırı **doğrudan** Menü Analitiği ekranını
açar; modülün tek ekranı odur, hub yoktur. (v1.0'da satır iki kartlık bir hub
açardı; ikinci kart olan **Firebase & Şube Ayarları** güvenlik modülüne
taşındı — yapılandırdığı şey raporlama değil kimlik doğrulamadır. Ekranın
adresi `qrms-analiz-ayarlar` olarak korundu.) Panelin eski adresi
`qrms-analiz-panel` gizli sayfa olarak kayıtlı kalır ve modül satırına
yönlendirir — yer imleri kırılmaz.

**Kategoriler.** Veriler tek bir chip şeridiyle altı kategoriye bölünür:
saatlik / günlük / haftalık / aylık, **Masalara Göre** ve **En Çok
Tıklananlar**. Her seferinde yalnızca seçili kategorinin bölümü görünür, yani
ekran bir tablo yığınına dönüşmez. Zaman kategorileri verinin penceresini,
masa ve ürün kesitleri aynı pencerenin farklı görünümünü verir; ürün kesitinin
kendi dönem seçicisi vardır ve zaman kategorisiyle eşleşir. Sunucu her yanıtta
grafiği, masaları ve ürünleri birlikte döndürdüğü için yanıtlar dönem+masa
anahtarıyla önbelleğe alınır: aynı pencerede kategori değiştirmek yeni istek
doğurmaz. "Yenile" ve kayıt silme önbelleği düşürür.

Şeridin altında tüm ekranı tek bir masaya daraltan masa filtresi vardır (o
masanın kartları, grafiği ve en çok tıklanan ürünleri). "Verileri Sil" filtre
açıkken yalnızca o masanın kayıtlarını siler. CSV indirme ekranda ne
görünüyorsa onu verir: masalar kategorisinde masa özeti, diğerlerinde ürün
listesi.

Panel mobil önceliklidir (restoran sahibi telefondan bakar): kartlar dar
ekranda alt alta dizilir, tablolar 660px altında kart görünümüne döner
(başlıklar `data-label` ile hücrelerin soluna geçer), kategori şeridi ve
grafik yatay kayar, dokunmatik cihazlarda (`pointer: coarse`) tıklama
hedefleri 44px'e çıkar — `restoran-menu` yönetim ekranlarıyla aynı yaklaşım.

`restoran-menu`'nün ürün, kategori ve ayar ekranlarının tamamı suite menüsünün
altındadır — eklenti artık ayrı bir top-level "Menü" menüsü açmaz. Sol menüde
tek bir "Restoran Menü" satırı vardır; on işin hepsine onun açtığı hub
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
| Malzemeler | `edit-tags.php?taxonomy=rma_ingredient` |
| Ürünüm Yok | `qrms-rm-urunum-yok` |
| Seçenek & Rozet | `qrms-rm-secenekler` |
| Fiyat Kampanyaları | `qrms-rm-kampanya` |
| Kampanya Banner | `qrms-rm-kampanya-banner` |
| Diğer Ayarlar | `qrms-rm-diger` |

Dördü çekirdeğin kendi ekranı, altısı modülün `add_submenu_page()` ile
kaydettiği **gerçek, ayrı sayfalardır**; JS ile gizlenip gösterilen sekme
yoktur. Hiçbirinin sol menüde satırı yoktur (bkz. *Alt sayfalar nasıl
gizleniyor?*), ama adresleri değişmedi — eski yer imleri çalışmaya devam
eder.

Sayfaların hangi eski sekmeden geldiği:

| Sayfa | Önceki yeri |
| --- | --- |
| Görünüm | "Genel Ayarlar" sekmesi (Renkler + Tipografi + Hazır Paletler iç sekmeleri) ve "Kayar Başlık" sekmesi |
| Öne Çıkanlar | "Öneriler" sekmesi + menüden erişilemeyen `qmo_slide` (Öne Çıkan Slider) ekranı |
| Fiyat Kampanyaları | Toplu fiyat kampanyası ekranı — **yalnızca fiyat zam/indirimi**; banner görselleriyle ilgisi yoktur |
| Kampanya Banner | Menüden erişilemeyen `qmo_banner_slide` ekranı + eski "Banner Görünümü" sayfası (`qrms-rm-banner-ayar`), üç adımlı tek bir sihirbazda |
| Diğer Ayarlar | "Kategori Sıralaması", "İçe/Dışa Aktar" ve "Yedekleme" sekmeleri, üç bölüm hâlinde |
| Seçenek & Rozet | Yeni sayfa: yeniden kullanılan ekstra (yan ürün) listeleri ve özel rozet tanımları |

### Kampanya Banner: kendi sayfasındaki üç adımlı sihirbaz

İsimlendirme netleştirildi: **"Kampanya" = banner görselleri**
(`qmo_banner_slide`), **"Fiyat Kampanyası" = toplu zam/indirim**
(`RMA_Kampanya_DB`). İki kavram ayrı ekranlardadır ve ortak kodu yoktur.

Banner'la ilgili her şey — görsel CRUD'u ve görünüm ayarları — **kendi
bağımsız sayfasında** (`qrms-rm-kampanya-banner`, hub'da kendi kartı var),
`banner_adim` query arg'ıyla sürülen üç adımda toplandı
(`RMA_Kampanya_Banner_Admin_Trait`, `includes/trait-kampanya-banner-admin.php`).
Başka bir ekranın alt bölümü değildir: "Menü Görünümü" sayfası yalnızca
renk / yazı tipi / kategori çubuğu içerir, banner'a dair hiçbir şey barındırmaz.

| Adım | Adres | İçerik |
| --- | --- | --- |
| 1 — Kampanya Banner | `banner_adim=ozet` | Yayındaki kampanya sayısı, oran/otomatik geçiş özeti, önizleme, `[qmo_banner_slider]` kısa kodu ve diğer iki adıma giden kartlar |
| 2 — Kampanyalar | `banner_adim=kampanyalar` | Aktif kampanya listesi (görsel seçili mi, sıra, bağlantı), "Yeni Kampanya Ekle" / "Tüm Kampanyaları Yönet" + görünüm ayarları formu (Biçim / Gezinme / Başlık alt sekmeleri) |
| 3 — Görsel Oluştur | `banner_adim=olustur` | Hazır şablonla kampanya görseli üretme aracı |

Adımlar JS ile gizlenen kartlar değil gerçek sayfa yüklemeleridir (2. adımda
`admin-post`'a giden bir form, 3. adımda AJAX ile çalışan bir araç var), bu
yüzden her adım ayrı ayrı yer imlenebilir.

Eski **`qrms-rm-banner-ayar`** sayfası kaldırıldı ama slug'ı silinmedi:
`get_legacy_page_map()` içinde kayıtlıdır ve o adrese gelen istekler
`redirect_legacy_page()` ile
`qrms-rm-kampanya-banner&banner_adim=kampanyalar` adresine
`wp_safe_redirect` edilir. Eski yer imleri ve dış linkler 404 vermez; aynı
işlev iki slug'ta tutulmaz.

**3. adım — görsel üretme neden tarayıcı tarafında?** Kod tabanında hiçbir
yerde GD/Imagick ile çizim yok (tek görüntü işleme `qr-galeri`'deki
`wp_get_image_editor` webp dönüşümü). Sunucuda metin basmak `imagettftext` +
paketlenmiş bir TTF + FreeType desteği isterdi; paylaşımlı hostinglerde bu
garanti değil. Görsel bu yüzden `<canvas>` üzerinde çizilir
(`assets/js/banner-olustur.js`), `toDataURL('image/png')` ile
`wp_ajax_qmo_banner_gorsel_olustur` ucuna gönderilir; sunucu PNG'yi önek /
base64 / dosya imzası / `getimagesize` olmak üzere dört kademede doğrulayıp
medya kütüphanesine yazar ve yayında yeni bir `qmo_banner_slide` kaydı
oluşturup `_qmo_banner_gorsel_id` meta'sını bağlar.

Veri katmanı taşımadan etkilenmedi: CPT slug'ı (`qmo_banner_slide`), meta
anahtarları (`_qmo_banner_gorsel_id`, `_qmo_banner_link`), option
(`qmo_banner_slider_settings`) ve `admin_post_qmo_banner_ayar_kaydet` ucu
aynen korundu — `class-banner-slider-settings.php`, `admin-cpt-banner.php` ve
`shortcode-banner-slider.php` dosyalarına hiç dokunulmadı.

Sayfa tanımı tek yerdedir (`RMA_Admin_Pages_Trait::get_subpages()`); sayfa
kayıtları ve hub kartları aynı listeden beslenir. Suite yokken (eski tekil
eklenti kurulumu) kayıt `add_admin_menus()` içinde yapılır. Alanların hiçbiri
düşmedi: az kullanılanlar `<details>` bölümlerine alındı.

CPT `show_in_menu => QRMS_Admin::MENU_SLUG` ile kaydolur — böylece CPT
ekranlarında `$parent_file` suite menüsüne çözülür. Çekirdeğin bu yüzden
eklediği ürün listesi satırını beyaz liste düşürür; "Ürün Ekle" ve taksonomi
satırları zaten hiç oluşmaz (onlar yalnızca top-level menü alan CPT'ler için
üretilir). CPT, taksonomi, Öne Çıkan Slider ve Kampanya Banner ekranlarında
menü vurgusu `modules/restoran-menu/module.php` içindeki `parent_file` / `submenu_file`
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

### Porsiyon, Ekstra, Servis Saati ve Özel Rozetler

Dört özellik ürün ekranındaki tek bir meta kutusunda ("Porsiyon, Ekstra ve
Servis Saati") toplanır; ortak kaynakları (ekstra listeleri, rozet tanımları)
**Seçenek & Rozet** ekranındadır (`qrms-rm-secenekler`). Hiçbiri varsayılan
olarak açık değildir: tanımlamadığınız sürece müşteri hiçbir ek arayüz görmez.

| Özellik | Sınıf | Veri |
| --- | --- | --- |
| Porsiyon / varyasyon | `RMA_Porsiyon` | `_rma_porsiyonlar` (post meta) |
| Yan ürün / ekstra | `RMA_Ekstra` | `rma_ekstra_listeleri` (option) + `_rma_ekstra_manuel`, `_rma_ekstra_listeler` (post meta) |
| Servis saati | `RMA_Servis_Saati` | `rma_servis_*` (term meta) + `_rma_servis_*` (post meta) |
| Özel rozet | `RMA_Ozel_Rozet` | `rma_ozel_rozetler` (option) + `_rma_ozel_rozetler` (post meta) |

**Porsiyon fiyatı FARK olarak tutulur.** Ürünün tek bir taban fiyatı
(`rma_price`) vardır; "Büyük" için `40`, "Küçük" için `-20` yazılır. İki
sebebi var: (1) fiyat kampanyası taban fiyat üzerinden hesaplanır — porsiyon
mutlak fiyat olsaydı kampanya porsiyonlu ürünleri atlardı; (2) toplu zam tek
alanı güncellemekle biter. Farkı sıfır olan bir seçenek tanımlanmamışsa
listenin başına otomatik "Standart" eklenir, müşteri her zaman taban fiyatı
seçebilir.

**Ekstralar iki kaynaktan gelir.** Yeniden kullanılan gruplar ("Soslar")
option'da bir kez tanımlanır, ürün ekranından kutucukla işaretlenir; fiyatı
tek yerden güncellenir. Yalnızca o ürüne ait satırlar ise ürün ekranındaki
manuel tabloya yazılır. Modalda en altta `<details>` ile açılır kapanır bir
blok olarak çıkar — JavaScript olmadan da çalışır, kapalıyken yer kaplamaz.

**Servis saati kuralı kategoride tanımlanır**, ürün ekranından ezilebilir
(`devral` / `kapalı` / `bu ürüne özel`). Gün numaraları ISO-8601'dir
(1 = Pazartesi); başlangıç bitişten büyükse pencere gece yarısını aşar
(22:00–02:00). Saat dışındaki ürün **menüden kaldırılmaz** — "Tükendi" ile
aynı mantık: yerinde kalır, "Servis dışı" etiketi alır, sepete eklenemez ve
sipariş ucunda sunucu tarafında kesilir (`qmo_siparis_onay_oncesi`, öncelik
12).

#### Sepetle bağlantısı

Fiyat DOM metninden ayrıştırılmaz: modal gövdesi kampanya uygulanmış taban
fiyatı `data-fiyat`, porsiyon farkını `data-fark`, ekstra fiyatını
`data-fiyat` niteliğinde **sayı** olarak taşır. `sepet.js` toplamı bunlardan
hesaplar. Aynı ürünün farklı porsiyon/ekstra kombinasyonu ayrı sepet
satırıdır (`imzaUret`). Sipariş ucu yalnızca `urunAdi / adet / not / itemId`
tanıdığı için porsiyon ürün adına ("Lahmacun (Büyük)"), ekstralar ise notun
başına ("Ekstra: Sos, Ayran") yazılır — mutfak fişi ikisini de görür.

Porsiyon eki ada eklendiği için **tükendi filtresi artık önce `item_id`'ye
bakar**; ID yoksa parantezli ek kırpılarak ada göre eşleşme denenir.

#### Önbellek

Servis penceresi menü önbelleği anahtarına girer (`RMA_Servis_Saati::imza`,
beş dakikalık kova): kahvaltı saati bitince önbellekteki "servis içi" menü en
geç beş dakikada kendiliğinden düşer. Sitede hiç kural tanımlı değilse imza
sabit `'0'` döner — kısıt kullanmayan işletmelerde önbellek ömrü değişmez.

### Toplu Fiyat Kampanyası (zam / indirim)

Menüdeki fiyatlar toplu olarak, geçici şekilde değiştirilebilir: yüzde bazlı
(%10 zam) ya da sabit tutar bazlı (−5 ₺); kapsam tüm menü, seçilen kategoriler
ya da tek tek işaretlenen ürünler olabilir. Ekran: **Fiyat Kampanyaları**
(`qrms-rm-kampanya`).

**Fiyat verisine dokunulmaz.** Kampanya bir KURAL kaydıdır; ürünün `rma_price`
(kombin ürünlerde `_qmo_kombin_fiyat`) alanına hiçbir zaman yazılmaz. Menüde
görünen fiyat her render'da *orijinal fiyat + aktif kural* birleştirilerek
üretilir (`RMA_Kampanya::fiyat_html()`). Bunun üç sonucu var:

- **Geri alma güvenilirdir.** "Kampanyayı Geri Al" bir hesaplama değil, tek bir
  durum değişikliğidir; ürünler aynı saniyede orijinal fiyatlarına döner.
- **Kampanyalar birbirini bozamaz.** Ortada birikmiş bir "zamlanmış fiyat"
  yoktur, dolayısıyla yarım kalan bir işlem fiyatları bozuk bırakamaz.
- **Kapsam canlıdır.** "Tüm menü" ya da bir kategori seçiliyken sonradan
  eklenen ürün de kampanyaya kendiliğinden dahil olur.

**Aynı anda yalnızca bir kampanya aktiftir.** Yeni bir kampanya başlatıldığında
çalışan kampanya kendiliğinden kapanır (`ended_at` damgalanır). Böylece "hangi
kural geçerli?" sorusunun cevabı her zaman tektir ve yüzdeler üst üste binmez.

**Önizleme zorunludur.** "Kampanyayı Başlat" butonu, formun o anki hâli için
önizleme üretilene kadar kapalıdır; ayarların herhangi biri değişince önizleme
bayatlar ve buton yeniden kilitlenir. Önizleme tablosu ürün ürün eski/yeni
fiyatı ve farkı gösterir; fiyatı 0 ₺'ye düşen ve fiyatı hiç girilmemiş ürünler
işaretlenir. Önizleme ile ön yüz aynı saf hesaplayıcıyı çağırır
(`RMA_Kampanya_DB::yeni_fiyat()`), dolayısıyla görülen fiyat ile menüde çıkan
fiyat tanım gereği aynıdır.

| Kayıt yeri | İçerik |
| --- | --- |
| `{prefix}rma_price_campaigns` | Kampanya tanımı: kural, kapsam, durum, uygulama/bitiş damgası, etkilenen ürün sayısı |
| `{prefix}rma_price_campaign_snapshot` | Aktivasyon anındaki fiyat fotoğrafı (denetim kaydı; kapsamın tanımı değildir) |
| `_qrms_orijinal_fiyat` post meta | Orijinal fiyatın **write-once** yedeği — ikinci güvence katmanı, asla üzerine yazılmaz |

Ön yüzde eski fiyat üstü çizili, yeni fiyat yanında görünür
(`.rma-price-old` / `.rma-price-new`); indirim kampanyalarında ürün kartına
otomatik rozet eklenir. Gösterim dört yüzeyde de aynı kaynaktan gelir: menü
kartı, ürün detay modalı, ürün vitrini ve öne çıkan slider. Kombin ürünlerde
kampanya paket fiyatına uygulanır ve üstü çizili fiyat kampanya öncesi paket
fiyatı olur — bir kartta iki ayrı üstü çizili fiyat çıkmaz.

Menü önbelleği kampanyayı tanır: aktif kampanyanın imzası önbellek anahtarına
girer (`RMA_Kampanya::imza()`), kampanya açılıp kapandığında önbelleğe alınmış
menü HTML'i kendiliğinden geçersizleşir. Chatbot'un yapay zekâya verdiği menü
JSON'u da kampanyalı fiyatı kullanır (`rma_get_effective_price()` köprüsü).

**İkinci faz için hazır.** Şemadaki `starts_at` / `ends_at` / `daily_start` /
`daily_end` / `days_mask` sütunları ve bunları değerlendiren
`RMA_Kampanya_DB::aktif_mi()` ilk günden yazıldı; v1 bu alanları doldurmaz ve
boş alan "sınır yok" demektir. Zamanlanmış kampanya, Happy Hour ve gün bazlı
kurallar için yalnızca form alanları eklemek yeterli — render ve önbellek
tarafına dokunulmayacak.

> **Bilinen sınır:** menüdeki "fiyata göre sırala" seçeneği sıralamayı
> veritabanında `rma_price` üzerinden yapar, yani orijinal fiyata göre sıralar.
> Tek kampanya + yüzde + tüm menü kapsamında sıra birebir aynı kalır;
> kategori/sabit tutar kapsamında ufak sapma olabilir.

> **Dağıtım notu:** `restoran-menu` modülü aktifken eski tekil **QR MENÜ**
> eklentisi devre dışı bırakılmalıdır — modül onun yerini alır. Yan yana
> bırakılırsa eski eklenti daha erken yüklendiği için `RMA_PLUGIN_URL` /
> `QMO_PLUGIN_URL` sabitlerini o tanımlar ve varlık adresleri eski klasörü
> gösterir. Sabitlerdeki `defined()` guard'ı ve `__DIR__` tabanlı require'lar
> yalnızca notice ile çift yüklemeyi önler.

`yorum-feedback` de aynı deseni izler. Kaynakta modülün ekranları ayrı bir
**QR Yorumlar** üst menüsünde duruyor, suite menüsündeki "Yorum & Feedback"
satırı ise bunlardan yalnızca birini (Tüm Yorumlar) ikinci kez basıyordu:
aynı ekran iki menüden açılıyor, diğerleri suite menüsünde hiç görünmüyordu.
Üst menü kaldırıldı; artık tek giriş noktası var:

| Ekran | Grup | Slug | İçerik |
| --- | --- | --- | --- |
| **Yorum & Feedback** (sol menüdeki tek satır) | — | `qrms-module-yorum-feedback` | Hub: dört tıklanabilir sayaç + üç başlık altında dört kart |
| Tüm Yorumlar | Yorumlar | `qrms-yf-yorumlar` | Üç sekmeli yorum listesi, durum filtreleri, onay/sil |
| Formlar | Formlar | `qrms-yf-formlar` | Ana yorum / iletişim (sistem) + özel form oluşturucu ve gönderiler |
| Ayarlar & Puanlama | Ayarlar | `qrms-yf-ayarlar` | Kriterler, form görünümü, otomatik onay, spam |
| Google & Ödül Sistemi | Ayarlar | `qrms-yf-odul` | Google yönlendirme, popup, indirim kodları |

Sayfa tanımı tek yerde durur (`qrm_pro_admin_pages()`,
`includes/admin/menu.php`); sayfa kayıtları, hub kartları ve varlık kuyruğunun
"bu benim sayfam mı" kontrolü aynı listeden beslenir. Kartın hangi başlığın
altına gireceği aynı defterdeki `group` anahtarından, başlıkların kendisi
`qrm_pro_admin_page_groups()`'tan gelir; boş kalan grup hiç basılmaz.
Bağlantılar `qrm_pro_admin_url()` ile üretilir — hiçbir slug JS'e ya da HTML'e
gömülmez.

**Detaylı İçgörüler ekranı kaldırıldı.** Kriter ortalamalarını ayrı bir ekranda
göstermek, yorum listesindeki puan kırılımının üstüne ikinci bir okuma yeri
ekliyordu; ekranın altındaki Gemini özeti ise ücretli bir dış çağrıydı. Ekranla
birlikte sayfa kaydı, `qrm_pro_admin_insights()`, `includes/ai-insights.php`
(Gemini istemcisi + `wp_ajax_qrm_ai_summary` ucu), `assets/js/ai-insights.js`,
yalnızca o ekranın kullandığı `qrm_db_serbest_birak()`/`qrm_db_geri_baglan()`
çifti ve `.qrm-ai-*` / `.qrm-meter` stilleri silindi. Eski iki adres
(`qrm-pro-insights`, `qrm-pro-main&tab=insights`) yorum listesine yönlendirilir.

Yorum listesi tek sayfa + üç sekmedir: **Tüm Yorumlar**, **Olumlu Yorumlar**
(ortalama puan ≥ eşik) ve **Olumsuz Yorumlar** (< eşik). Nötr kategori yoktur,
her yorum ikisinden birine düşer. Eşik tek bir yerde tanımlıdır
(`QRM_PRO_SENTIMENT_THRESHOLD`, varsayılan 3.0) ve
`qrm_pro_sentiment_threshold` filtresiyle değiştirilir; sekme sayaçları da,
listenin `WHERE`'i de aynı değerden beslenir. Aktif sekme `sekme` sorgu
parametresinde taşınır (yenilemede, sayfalamada ve satır aksiyonundan sonra
korunur), sekme başlıklarında kayıt sayısı görünür. Filtreleme SQL tarafında
yapılır — sekme koşulu durum filtresiyle aynı `WHERE`'e girer, tablo PHP'ye
çekilip orada elenmez. Sayaçların hepsi `qrm_pro_review_stats()`'in **tek**
sorgusundan gelir (`positive_total` / `positive_approved`; olumsuz kırılım
çıkarmayla türetilir), yani sekmeye tıklamak ek bir `COUNT` açmaz.

Hub'ın üstündeki dört kutunun dördü de tıklanabilir ve saydığı kayıtların
filtrelenmiş listesine gider (`durum=bekleyen`, tüm liste, `durum=onayli`,
`qrms-yf-formlar&tab=submissions`). "Onay Bekleyen" sıfırdan büyükken kutu
`qrms-hub-stat-alert` sınıfıyla vurgulanır ve aynı sayı sol menüdeki rozete
girer. "Genel Ortalama" hiç yayında yorum yokken "—" yerine **"Henüz puan yok"**
yazar. Hiç yorum gelmemişse kartların üstünde tek satırlık bir yönlendirme
durur: QR kodun paylaşılması gerektiğini söyleyen kısa metin +
`[qr_menu_reviews]` kısa kodu ve suite'in ortak kopyalama betiğine
(`assets/js/admin.js`, `data-qrms-copy`) bağlı bir "Kopyala" butonu.

v4.1.x ve v4.2.x adreslerinden (`qrm-pro-main`, `qrm-pro-settings&sub=alanlar`,
`qrm-pro-insights`, `qrm-forms&view=edit` …) gelenler
`qrm_pro_legacy_page_target()` ile yeni sayfalarına yönlendirilir; sekme/görünüm
parametreleri hedefi belirlemekte kullanılır. Rozetler (onay bekleyen yorum,
okunmamış form gönderimi, eksik ödül kurulumu) sol menüde modülün **tek**
satırında toplanır (`qrms_module_menu_label` filtresi); hangi ekranın
ilgilendiği hub kartlarındaki rozetlerden okunur.

Ana yorum formu ve iletişim formu artık ayrı hub kartı değil; **Formlar**
ekranının listesinde silinemez sistem satırları olarak durur. Özel formlar
aynı listede onların altında oluşturulur.

Kaynaktaki JS sekmeleri (Tüm Yorumlar > İçgörüler, Ayarlar > Müşteri Bilgileri
Formu) gerçek sayfaya dönüştürüldü. Bu sekmelerin hem görünürlüğü hem aktif
göstergesi tamamen jQuery'ye bağlıydı: sayfadaki herhangi bir JS hatasında iki
sekme birden ölüyor ve hiçbiri aktif görünmüyordu. Ayrıca `history.replaceState`
ile adres çubuğunu **başka bir sayfanın** adresine çeviriyorlardı; suite
menüsünden açıldığında yenileme sonrası yanlış ekrana düşülüyordu. Yorum
listesinin yeni sekmeleri aynı derdi tekrarlamaz: gerçek bağlantıdırlar ve
aktif sekme adreste taşınır.

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

### QR Çeviri — yönetim ekranının mobil davranışı

Modülün yönetim tarafında hiç stil dosyası yoktu: ölçüler doğrudan markup'a
satır içi yazılmıştı. Satır içi stilin medya sorgusu olamaz, bu yüzden ekran
dar ekranda sıkışıyordu — üç sorunun ortak kökü buydu.

| Sorun | Çözüm |
| --- | --- |
| Durum tablosu 1 + N sütun (N = aktif hedef dil, **30'a kadar**) | 782px altında **kart görünümü**: her içerik satırı kendi kartı, her dil `data-label`'ından okunan "🇬🇧 English — 148" satırı. Sütun sayısı bu kadar değişkenken yatay kaydırma okunur kalmazdı |
| Dil ızgarası satır içi `repeat(3,1fr)` — kırılımı yok | `auto-fill / minmax(230px)`: geniş ekranda sığdığı kadar sütun, 782px altında tek sütun |
| Onay kutuları çekirdek 16px, satır tıklanamaz | Satırın tamamı tıklanabilir kutu, **min 48px**, checkbox 20px, `:focus-within` halkası |

Aynı düzeltme kaynak/taksonomi seçim kutularına da uygulandı (aynı satır içi
ızgaranın ikinci kopyasıydı); kaydırmalı listeler (bulunan metinler, Elementor
sayfaları) dar ekranda tam genişliğe iner ve satırları 44px'e çıkar.

Sütun başlığı dar kalsın diye tabloda dil **kodu** yazar; kart görünümündeki
etiket ise bayrak + dil adıdır — orada yer vardır ve "en" yerine "English"
okunur.

Testler kuralı üç yerden koruyor: satır içi ölçülerin geri gelmediği,
hücrelerin `data-label` taşıdığı ve dokunma yüksekliğinin 44px'in altına
düşmediği doğrulanır.

### QR Çalışma Saatleri — görünüm ayarları ve canlı önizleme

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

Dokuz renk alanı: **arka plan**, **kenar rengi**, **yazı rengi**, bugünün
vurgusu, bugünün satır zemini, gün adı, saat metni, kapalı gün, satır ayracı.
Alan işaretlemesi restoran-menu'nün renk seçicisiyle aynı
(`data-default-color` taşıyan metin kutusu + `wpColorPicker`). Gün adı ve saat
için ayrı renk seçilmemişse iç içe `var()` zinciriyle önce genel yazı rengine,
o da yoksa temaya düşerler.

**Kutu ölçüleri renklerle birlikte basılır.** Çerçeve kalınlığı (`1px`), iç
boşluk ve köşe yuvarlaması da CSS değişkenidir ve PHP onları yalnızca zemin ya
da kenar rengi seçildiğinde yazar. Sebebi "boş renk = devral" kuralının aynısı:
`1px solid transparent` bile satırları kaydırır, yani seçilmemiş bir ayar
görünümü oynatmış olurdu. JS'teki önizleme de aynı kuralı uygular
(`syncBox()` ↔ `qrms_cs_box_declarations()`).

**Yazı tipi** aynı option'ın `font` anahtarındadır ve `--qrms-cs-font`
değişkenine iner. Liste, Restoran Menü'nün Görünüm sayfasındaki seçicinin
listesiyle birebir aynıdır — kopya bilinçlidir (liste orada private bir
metottadır ve modüller bağımsız lisanslanır), ayrışmasını bir test yakalar.
Değer doğrudan CSS'e indiği için serbest metin kabul edilmez: beyaz listede
olmayan girdi "devral"a düşer. Adlandırılmış yazı tipleri ön yüzde Google
Fonts'tan yüklenir (`qrms_cs_enqueue_font()`); Georgia/serif/sans-serif sistem
fontudur ve tek bir dış istek bile yapılmaz.

**Canlı önizleme kısa kodun taklidi değil, kendisidir:** `qrms_cs_shortcode()`
yönetim ekranında da çağrılır ve ön yüzün gerçek `frontend.css`'i orada da
kuyruğa alınır. Ayrı bir şablon tutulsaydı ikisi zamanla ayrışır ve önizleme
yalan söylemeye başlardı. JS yalnızca hazır DOM'u günceller: saat alanı, kapalı
kutusu, renk seçicileri ve font seçici değiştikçe liste kaydetmeden yenilenir.
Font seçimi değişince seçilen ailenin Google Fonts stylesheet'i önizleme için
sayfaya eklenir — restoran sahibi kaydetmeden gerçek yazı tipini görür.

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

#### TR / EN dil düğmesi

Kaynak eklentide yoktu; suite'te eklendi. **Butonlar** sayfasında her metnin
altında bir *English* alanı vardır ve düğme oradaki anahtarla açılır.

Düğme yalnızca **en az bir İngilizce metin girilmişse** basılır — boş bir
çeviriyle düğme ziyaretçiye aynı metni iki kez gösteren bir kontrolden ibaret
kalırdı. Çevirisi girilmemiş tek tek alanlar da İngilizcede Türkçesine düşer.

**Dil sunucuda seçilmez.** Ana sayfanın HTML'i her ziyaretçide birebir aynı
kalmalı (tam sayfa cache güvenliği) — çerezden dallanan bir çıktı, ilk
ziyaretçinin dilini herkese servis ederdi. Sunucu Türkçeyi basar, İngilizcesini
her metnin yanında `data-sp-en` olarak taşır; hangisinin görüneceğine çerezi
okuyan istemci karar verir. Splash'ın "gösterilsin mi" kararındaki desenin
aynısı, testi de aynı biçimde: iki farklı çerez durumunda sunucu çıktısı
karakter karakter karşılaştırılır.

Rozetlerin görünür yazısı yoktur; onlarda dil değişimi `data-sp-attr` ile
`aria-label` ve `title` niteliklerine yazılır. Wifi penceresinin başlığı gibi
eklentinin kendi metinlerinin İngilizcesi sabittir (kullanıcı girdisi değil);
şifrenin kendisi çevrilmez.

Düğme kapalıyken markup'a tek bir dil niteliği bile girmez. Ziyaretçinin
seçimi bir yıl saklanır (`qrms_splash_lang`), dil seçmek splash'ı kapatmaz ve
önizlemede çerez yazılmaz.

#### Bilinmesi gerekenler

- Ekran yalnızca **ana sayfada** basılır; Elementor editöründe ve Customizer
  önizlemesinde basılmaz.
- `button_padding` ve `button_font_size` option'ları v3.6'dan beri ön yüzü
  etkilemiyor (CTA'nın ölçüsü stylesheet'te sabit). Anahtarlar ve admin
  alanları veri kaybı olmasın diye korundu.
- Modül, lisans sunucusundan gelen aktif modül listesinde `qr-acilis-ekrani`
  slug'ı yoksa yüklenmez — dosyalar yerinde olsa bile yönetim ekranı
  görünmez. Slug sunucudaki modül sözleşmesine de eklenmelidir.
- `get_options()` varsayılanları iki kademede uygular: önce `array_merge`,
  sonra dizi biçimindeki her ayar için birlik operatörü. Sığ birleştirme tek
  başına yetmiyordu — kayıtta `button_texts` varsa dizinin tamamı kayıttan
  gelir ve içinde eksik olan alt anahtar varsayılandan gelmezdi (eski
  sürümden yükseltilen kurulumda "undefined index"). Birlik operatörü yalnızca
  eksik anahtarı doldurur, kayıtlı değeri ezmez ve sayısal listelere öğe
  eklemez.

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
yazar: bölüm hem `qr-chatbot` hem `qr-masa-oturum-guvenligi` ekranında göründüğü için ortak
bir grup paylaşsalardı birinden kaydetmek diğerinin alanlarını silerdi. Bu
dosya `ortak.php` ile değil, ihtiyacı olan modülün `module.php`'sindeki
`is_admin()` dalıyla yüklenir.

Taşınan kodun tamamı `class_exists()` / `function_exists()` / `defined()`
guard'lıdır: eski `qr-menu-official` eklentisi aynı sitede hâlâ aktifse
çift tanım hatası olmaz, ilk yükleyen kazanır.

### Menü Mühendisliği (`qr-menu-muhendisligi`)

İşletmeye "menünün hangi ürünü para kazandırıyor, hangisi kaybettiriyor ve ne
yapmalısın" sorusunu somut cevaplayan modül. Veriyi kendisi toplamaz; iki
mevcut modülün üstüne kurulur:

| Girdi | Kaynak |
| --- | --- |
| Ürün, fiyat (`rma_price`), kategori, malzeme taksonomisi | `restoran-menu` |
| Sipariş / sepete ekleme / görüntülenme | `qr-analiz` → `{prefix}rma_analytics` |
| Maliyet | Bu modül (manuel ya da reçeteden) |

#### Analitik tablosuna eklenen `qty` sütunu

Sipariş kalemleri tabloya kalem başına TEK satır olarak yazılıyordu ve **adet
kaydedilmiyordu**. Popülerlik adetsiz hesaplanırsa "3 adet lahmacun" ile "1
adet çorba" aynı ağırlığı alır, matris yanlış çıkar. Şemaya
`qty smallint unsigned NOT NULL DEFAULT 1` eklendi (dbDelta kolonu mevcut
tabloya kendisi ekler), `QRMS_Analitik::kaydet()` alanı 1–999 aralığına
sıkıştırıyor ve `qmo_analitik_siparis_yaz()` kalemin adedini geçiriyor. Eski
satırlar `qty = 1` kalır; geriye dönük göç yazılmadı.

#### Matris (Kasavana–Smith)

Hesap `QRMS_MM_Hesap::hesapla()` içinde, **saf** bir fonksiyondadır: veritabanı
ve option okumaz, yalnızca verilen satırları çevirir. Sorgu ve önbellek işi
`QRMS_MM_Rapor`'dadır.

1. **Veri kaynağı.** Aralıktaki toplam sipariş adedi 20'nin altındaysa gerçek
   satış yerine vekil skora düşülür: `görüntülenme + (sepete ekleme × 3)`.
   Sepete ekleme satın alma niyetine çok daha yakın olduğu için ağırlıklıdır.
   Rapor bu durumu ekranın tepesinde sarı bir şeritle açıkça yazar.
2. **Katkı payı** `fiyat − maliyet`. Fiyatı ya da maliyeti olmayan ürün
   matrise **girmez**, "rapora giremeyen ürünler" listesine düşer ve tek tıkla
   maliyet ekranına bağlanır.
3. **Popülerlik eşiği** `(1 / ürün sayısı) × katsayı` (varsayılan 0,70).
4. **Kârlılık eşiği** adetle **ağırlıklı** ortalama katkı payıdır. Düz ortalama
   alınsaydı hiç satmayan pahalı bir ürün eşiği yukarı çeker, çok satan
   ürünler haksız yere "Köpek" olurdu.
5. Sınırda eşitlik **yüksek** tarafa yazılır; eşiği tam tutturan ürünü
   cezalandırmak için sebep yok.

| | Yüksek kâr | Düşük kâr |
| --- | --- | --- |
| **Çok satan** | Yıldız — koru, fiyatı ve porsiyonu değiştirme | İş Atı — maliyeti düşür ya da %5–10 zam dene |
| **Az satan** | Bulmaca — vitrine al, önerilere ekle | Köpek — menüden çıkarmayı değerlendir |

Ayrıca **kayıp fırsat** hesaplanır: ortalamanın altında katkı üreten her satış,
aradaki fark kadar kaybettirir (`(ortalama − katkı) × adet`). Ekranda tek bir
rakam olarak durur: "bu ürünler dönemde tahminen X ₺ katkı kaybettirdi."

#### Maliyet ve reçete

Maliyet iki yoldan girilir. **Manuel** modda işletmeci ürün başına tek rakam
yazar (satır içinde, AJAX ile kaydedilir). **Reçete** modunda malzeme + miktar
satırları girilir ve maliyet malzeme birim fiyatlarından hesaplanır.

Reçete maliyeti raporun içinde değil, **kaydederken** hesaplanıp meta'ya
yazılır: rapor yüzlerce ürünü tek sorguda çekiyor, her satırda reçete çözmek
onu N+1 sorguya çevirirdi. Malzeme fiyatı değiştiğinde reçeteli bütün ürünler
yeniden hesaplanır; 500 ürünü aşan kurulumlarda iş
`wp_schedule_single_event` ile arka plana atılır.

Birim çevrimi: kg fiyatı → reçetede **gram**, litre fiyatı → **ml**, adet
fiyatı → **adet**. Fire yüzdesi hesabın sonuna eklenir. Kullanıcının yazdığı
sayı `QRMS_MM_Maliyet::sayi()` ile okunur — Türkçe klavyede ondalık ayracı
virgüldür, "12,50" yazan kullanıcının maliyeti 12 TL'ye yuvarlanmamalı.

#### Ekranlar

Hub (4 kart) + Rapor + Ürün Maliyetleri + Malzeme Fiyatları + Ayarlar.
Rapor filtreleri adres satırına yazılır: bağlantı paylaşılabilir, geri tuşu
çalışır. Matris **saf CSS ızgarasıdır**, grafik kütüphanesi yüklenmez; her
kutu `<details>` olduğu için dar ekranda JavaScript olmadan katlanır. Geniş
tablolar 782px altında **kart listesine** dönüşür — dokuz sütunlu bir tabloyu
telefonda yana kaydırarak okumak işletmecinin yapacağı son şeydir.

Rapor sonucu beş dakikalık transient'te saklanır; maliyet, malzeme fiyatı ya
da ayar değiştiğinde önbellek boşaltılır. Transient adları bir option'da
tutulur — `DELETE ... LIKE '_transient_%'` taraması büyük sitelerde pahalıdır
ve nesne önbelleği kullanan kurulumlarda hiç çalışmaz.

### Servis Paneli (`qr-servis-paneli`)

Müşteri siparişleri ile garson/hesap çağrıları Firestore'daki `calls`
koleksiyonuna yazılıyordu (`qr-chatbot` modülü) ama işletmenin bunları
WordPress içinde göreceği bir ekran yoktu. Bu modül o ekranı ekler.

#### Firestore tarafı

`QMO_Firestore`'a üç metot eklendi: `call_listele()`, `call_guncelle()` ve
belge çözücü `belge_coz()` / `deger_coz()`.

Sorgu biçimi bilinçli olarak dardır: Firestore'da eşitlik (`branchId`) ile
başka bir alana göre sıralama (`createdAt`) **birlikte** kullanıldığında
bileşik indeks gerekir ve indeksi olmayan projede sorgu 400 döner. Restoran
sahibinin Firebase konsolundan indeks oluşturması beklenemez; bu yüzden sorgu
tek alanda kalır (`createdAt` üzerinde aralık + sıralama, otomatik tek alan
indeksiyle çalışır) ve şube süzmesi PHP tarafında yapılır.

`call_guncelle()` her zaman `updateMask` gönderir: maskesiz bir PATCH belgenin
tamamını değiştirir ve sipariş kalemleri silinirdi.

Service account anahtarı **tarayıcıya çıkmaz**: panel kendi sunucusundaki AJAX
ucunu çağırır, Firestore'a yalnızca PHP tarafı gider.

#### Durum akışı

```
bekliyor → hazirlaniyor → serviste → tamamlandi
   └────────────┴────────────┴──────→ iptal
```

Geçerli olmayan sıçramalar (`bekliyor → tamamlandi`) reddedilir; iki panelden
gelen eş zamanlı tıklamalar siparişi hazırlanmadan tamamlanmış gösteremesin.
Durum değişince belgeye `durum`, `onaylayanUid`, `onaylayanAd` ve
`guncellendi` yazılır.

#### Yoklama ve uyarılar

- Sekme önplandayken ayarlardaki aralık (varsayılan 5 sn), arka planda 30 sn;
  öne gelince anında bir istek atılır (Page Visibility API).
- Sunucu tarafında **3 saniyelik** transient: aynı anda beş ekran açıksa
  Firestore kotası boşuna tükenmesin.
- Arka arkaya ikinci hatadan itibaren kırmızı bağlantı şeridi gösterilir ve
  aralık ikiye katlanarak 60 saniyeye kadar açılır; bağlantı dönünce iner.
- Yeni kayıt geldiğinde WebAudio ile **kod içinde üretilen** bip çalar (harici
  ses dosyası yok), sayfa başlığı yanıp söner ve izin verilmişse masaüstü
  bildirimi gönderilir. Bildirim izni **yalnızca kullanıcı düğmeye basınca**
  istenir, sayfa açılışında değil.
- Her kartta canlı bekleme sayacı vardır; iki eşiğe göre kart kenarı yeşil →
  sarı → kırmızı olur ve kırmızıya geçen kart listenin başına çıkar.

#### Ekran düzeni

Kart şablonunun **tek kaynağı** `assets/js/panel.js`'tir; PHP yalnızca iskeleti
basar. Canlı bir panelde sunucuda bir kez üretilen kart ikinci saniyede zaten
eskimiş olurdu, iki yerde şablon tutmak da drift demekti.

Tasarım **mobil önceliklidir** — bu ekran işletmede çoğunlukla telefondan
kullanılır. Varsayılan görünüm sekmeli tek sütundur (sekme başlığında bekleyen
sayısı rozetle); dört sütunlu kanban 961px'ten sonra devreye girer. Durum
düğmeleri dokunmatik cihazlarda 48px yüksekliğindedir.

#### Personel erişimi

Yeni yetenek `qrms_servis_panel` ve yeni rol `qrms_servis` ("Servis
Personeli"). Rol her istekte değil, `qrms_sp_rol_surum` option'ı ile
**sürümlenerek** bir kez kurulur. Bu rolle giren kullanıcı yönetim panelinde
yalnızca Servis Panelini görür: diğer menü satırları düşürülür, araç çubuğu
sadeleşir ve başka bir ekrana gitmeye çalışırsa panele geri yönlendirilir
(profil ekranı serbesttir — personel kendi şifresini değiştirebilmeli).

### Kullanılan option'lar (yeni modüller)

| Option | İçerik |
| --- | --- |
| `qrms_mm_ayarlar` | Popülerlik eşiği, fire yüzdesi, varsayılan aralık |
| `qrms_mm_malzeme_fiyat` | Malzeme birim fiyatları (`term_id => [birim, fiyat]`) |
| `qrms_mm_onbellek` | Rapor transient adlarının defteri |
| `qrms_sp_ayarlar` | Panel eşikleri, yenileme aralığı, gösterilecek tipler |
| `qrms_sp_rol_surum` | Servis Personeli rolünün kurulu sürümü |

## Giriş adresi ve giriş ekranı (`QRMS_Login`)

Çekirdeğin parçasıdır, modül değildir: lisans listesine bakılmaz, her kurulumda
vardır. İki bağımsız işi bir sınıfta toplar ve ikisi ayrı ayrı açılıp
kapatılabilir.

### Yol — `site.com/qrm`

`plugins_loaded` (öncelik 1) isteği sınıflandırır, `wp_loaded` sonucu uygular:

| İstek | Sonuç |
| --- | --- |
| `/qrm` (veya `/?qrm`, kalıcı bağlantı kapalıysa) | `$pagenow = 'wp-login.php'`, `wp-login.php` require edilir |
| `wp-login.php`, oturumsuz | 404 (tema 404 şablonu, yoksa `wp_die`) |
| `wp-login.php?action=postpass` | **Geçer** — şifre korumalı yazıların formu buraya POST eder |
| `wp-login.php`, oturum açık | Geçer — çıkış ve ara giriş penceresi bozulmasın |
| `wp-admin/*`, oturumsuz | 404 (`wp_admin_koru` ayarı kapatılabilir) |
| `admin-ajax.php`, `admin-post.php`, `/wp-json/*`, cron, WP-CLI | Hiç dokunulmaz |

Rewrite kuralı **kullanılmaz** (`flush_rewrite_rules()` çağrılmaz); istek doğrudan
yakalanır. WordPress'in ürettiği bütün `wp-login.php` adresleri
`site_url`, `network_site_url`, `wp_redirect`, `login_url`, `logout_url`,
`lostpassword_url`, `register_url` filtrelerinde yeni yola çevrilir — **sorgu
parametreleri korunarak**. Şifre sıfırlama e-postasındaki `action=rp&key=…&login=…`
bağlantısı bu yüzden çalışmaya devam eder.

Slug `sanitize_title()` ile temizlenir; `wp-admin`, `wp-login`, `feed`, `wp-json`
gibi rezerve değerler, iki karakterden kısa ve 64 karakterden uzun değerler ve
**sitede aynı adrese sahip bir sayfa varsa** o değer reddedilir. Reddedilen
değerde eski slug korunur — adres asla boşa düşmez.

**Kilitlenmeye karşı üç koruma:**

1. `wp-config.php` içine `define( 'QRMS_LOGIN_DISABLE', true );` yazmak özelliği
   (görünüm dâhil) tamamen kapatır ve `wp-login.php`'yi geri getirir.
2. Adres her değiştiğinde site yöneticisinin e-postasına yeni adres ve bu
   kurtarma satırı gönderilir.
3. Kaydettikten sonra yönetim ekranında adres kopyalanabilir bir kutuda durur.

Varsayılan **kapalıdır**: eklenti güncellemesi hiç kimsenin giriş adresini
habersiz değiştirmemeli. Çok siteli (multisite) kurulumda devreye girmez —
ağ genelindeki giriş adresi tek bir siteye ait bir option'la yönetilemez.

### Görünüm

Giriş formu WordPress'in kendi formudur; **yeniden yazılmaz**. Nonce'lar,
kimlik doğrulama akışı ve iki aşamalı doğrulama gibi eklentiler dokunulmadan
kalsın diye yalnızca CSS ve küçük bir betikle giydirilir:

- `login_enqueue_scripts` → `assets/css/login.css` + ayarlardan üretilen CSS
  değişkenleri (`QRMS_Login::css_variables()`).
- `login_body_class` → düzen, tema, arka plan tipi ve gizleme sınıfları
  (`QRMS_Login::skin_classes()`).
- `login_message` → marka bloğu, WordPress'in kendi mesajının **önüne** eklenir.
- `login_headerurl` / `login_headertext` → logo bağlantısı WordPress.org yerine
  sitenin kendisi.
- `assets/js/login.js` → Caps Lock uyarısı, gönderim durumu, masaüstünde odak.
  Üçü de opsiyonel iyileştirmedir; betik yüklenmezse ekran eksiksiz çalışır.

Ayarlanabilenler: düzen (bölünmüş / ortalanmış), tema (koyu / açık / cihaza
göre), vurgu ve ikinci vurgu rengi, arka plan (düz renk / gradyan / görsel +
karartma + bulanıklık), logo ve yüksekliği, başlık, alt metin, alt bilgi, kart
köşe yarıçapı, gölge, cam efekti ve dört bileşenin görünürlüğü.

### Önizleme neden ayrı bir stylesheet kullanmaz

`assets/css/login.css` içindeki her yapısal kural **iki seçiciyle** yazılır:

```css
.qrms-login #login,
.qrms-login .qrms-lp-box { … }
```

Ortak `.qrms-login` sınıfı hem gerçek giriş gövdesinde (`login_body_class`) hem
yönetimdeki önizleme kökünde bulunur. Önizleme için ayrı bir taklit stylesheet
yazılsaydı iki dosya zamanla birbirinden ayrı düşer, ekranda gördüğünüzle
kaydettiğiniz farklı olurdu. Bir test bu çift seçici düzeninin bozulmadığını
korur.

Yönetimdeki "Mobil" düğmesi önizleme köküne `qrms-lp-mobil` sınıfını ekler:
tarayıcı penceresi geniş olduğu için `min-width: 1024px` medya sorgusu yine
eşleşir; bölünmüş düzenin mobil önizlemede kapanmasının tek yolu bu sınıfın
`:not()` ile dışlanmasıdır.

### Ayar ekranı

Sol menüye yeni satır **eklenmez** — menü tek seviyeli kalsın diye modüllerin
alt ekranları da menüye yazılmıyor. Ayarlar `Genel Ayarlar` sayfasında bir
sekmedir (`admin.php?page=qrms-settings&tab=giris`); sekmeler
`QRMS_Admin::get_settings_tabs()` içinde tek yerde durur. Form `admin-post.php`
üzerinden kaydedilir ve geri yönlendirilir (yenilemede tekrar gönderim olmaz);
tüm alanlar `QRMS_Login::sanitize_settings()` içinde alan alan temizlenir,
bilinmeyen anahtar düşer, aralık dışı sayı sıkıştırılır.

### Kullanılan option'lar

| Option | İçerik |
| --- | --- |
| `qrms_login_ayarlar` | Yol ve görünüm ayarlarının tamamı (tek dizi) |

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
  class-admin.php            Menü çatısı, tek seviyeli menü, ortak hub bileşeni, Genel Bakış, Genel Ayarlar (sekmeli)
  class-shortcodes.php       Kısa kod kayıt defteri ve "Kısa Kodlar" rehber ekranı
  class-qrms-login.php       Özel giriş adresi (/qrm) + giriş ekranı görünümü
  login-ayar-sayfasi.php     Genel Ayarlar → Giriş Ekranı sekmesi (canlı önizlemeli)
modules/
  _qmo-ortak/                Ortak zemin (oturum sınıfı, Firestore istemcisi, helpers, varlıklar)
  restoran-menu/             Menü CPT'si, kısa kodlar, slider, fiyat kampanyası, porsiyon/ekstra/servis saati + hub ve on yönetim ekranı
  yorum-feedback/            Yorumlar, ödül kodları, form oluşturucu + hub ve altı yönetim sayfası
  qr-masa/                   Masa kayıtları + Masalar yönetim ekranı
  qr-masa-oturum-guvenligi/  Masa doğrulama, kilit ekranı + hub (oturum limitleri, Firebase & şube ayarları)
  qr-analiz/                 Menü analitiği (masa bazlı takip + panel), analitik/kullanıcı REST uçları
  qr-chatbot/                Gemini chatbot, garson/hesap butonları, sohbet/çağrı/sipariş uçları + ayar ekranı
  qr-ceviri/                 Çok dilli sözlük + çeviri yönetim ekranı
  qr-galeri/                 Galeri CPT + yönetim ekranları
  qr-acilis-ekrani/          Ana sayfa açılış ekranı + hub ve dört ayar sayfası
  qr-servis-paneli/          Canlı sipariş/çağrı panosu, durum akışı, servis personeli rolü
  qr-menu-muhendisligi/      Maliyet + reçete, Kasavana–Smith matrisi, CSV çıktısı
assets/css/admin.css         Mobil öncelikli admin stilleri (dokunma ≥44px), ayar sekmeleri
assets/css/admin-menu.css    Sol menü: kategori renkleri ve grup başlıkları
assets/css/login.css         Giriş ekranı görünümü — gerçek ekran ve önizleme ortak
assets/css/login-admin.css   Giriş Ekranı sekmesinin yerleşimi ve önizleme çerçevesi
assets/js/admin.js           Form gönderiminde buton kilidi (opsiyonel iyileştirme)
assets/js/admin-menu.js      Sol menüde katlanabilir kategori başlıkları
assets/js/login.js           Caps Lock uyarısı, gönderim durumu (opsiyonel iyileştirme)
assets/js/login-admin.js     Giriş Ekranı sekmesinin canlı önizlemesi
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

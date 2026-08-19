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

Bir modül eklemek için `modules/<slug>/module.php` dosyası oluşturmak ve
içinde `qrms_module_<slug_alt_çizgili>_init()` fonksiyonunu tanımlamak
yeterlidir (ör. `restoran-menu` → `qrms_module_restoran_menu_init()`).
Loader, modül lisansta aktifse dosyayı `require` eder ve bu fonksiyonu
çağırır; dosya yoksa sessizce atlar.

Admin menüsünde `Genel Bakış` ve `Genel Ayarlar` her zaman görünür; modül
sayfaları yalnızca lisansta aktif olan modüller için eklenir.

### Modülün kendi yönetim sayfası

Modül, init'i içinde `QRMS_Admin::register_module_page( $slug, $callback )`
çağırırsa alt menü sayfası o callback'i basar; çağırmazsa "Bu modül yakında
burada olacak" placeholder'ı görünmeye devam eder. Kayıt `plugins_loaded`
(öncelik 20) sırasında yapılır, `admin_menu` bundan sonra çalıştığı için
zamanlama doğrudur.

### Paketlenmiş modüller

| Slug | İçerik | Yönetim sayfası |
| --- | --- | --- |
| `restoran-menu` | `rma_menu_item` CPT, `[restaurant_menu]`, `[qmo_one_cikan_slider]`, Elementor widget'ı | ✔ Sekmeli ayar ekranı |
| `yorum-feedback` | Çoklu kriter yorumlar, Google yönlendirme + ödül kodları, dinamik form oluşturucu, `[qr_menu_reviews]`, `[qr_menu_contact]`, `[qr_menu_form]` | ✔ Tüm Yorumlar ekranı |
| `qr-masa` | Masa kayıtları (CRUD), masa QR adresleri, `[qr_aktif_masa]` | ✔ Masalar ekranı |
| `qr-masa-oturum-guvenligi` | Sahte QR reddi, kilit ekranı, sayfa kilidi | ✔ Oturum limitleri |
| `qr-galeri` | Galeri CPT, bölümler, görseller | ✔ Galeri yönetim ekranları |
| `qr-ceviri` | Çok dilli metin tarama, sözlük, CSV içe/dışa aktarma | ✔ Çeviri ekranı |
| `qr-analiz` | Menü analitiği (masa bazlı görüntüleme/tıklama takibi, panel); `POST /wp-json/qrservis/v1/analytics` — şube analitiği özeti; `POST /wp-json/qrservis/v1/create-user` — garson/müdür hesabı açma (yalnızca ana sitede) | ✔ Menü Analitiği paneli + uç durumu/Firebase ayarları |
| `qr-chatbot` | `[gemini_chatbot]`, garson/hesap buton kısa kodları, Gemini AJAX ucu, sipariş ucu (`POST /wp-json/qrservis/v1/order`) | ✔ Chatbot ayarları + Firebase ayarları |

Kodları kaynak eklentilerinden **aynen** taşındı (`restoran-menu` 12-menu
deposundaki QR MENÜ eklentisinden, `yorum-feedback` `yorumfeedback`
deposundaki v4.2.1 eklentisinden, diğerleri birleşik `qr-menu-official`
eklentisinden); yalnızca yeni klasör konumuna göre yol string'leri düzeltildi.
`yorum-feedback`'in 29 kaynak dosyasının hepsi kaynağıyla **birebir aynıdır**;
modüle özgü her şey `module.php` içindedir.

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

Eski bağımsız eklenti hâlâ etkinse modül izlemeyi kapatır (çift sayım olmaz),
panelde bunu söyleyen bir uyarı gösterir ve veriyi aynı tablodan okumaya
devam eder.

**Panel.** "QR Menü → — Menü Analitiği" satırı (`qrms-analiz-panel`) ayrı bir
sayfadır; REST/Firebase ayarları "QR Analiz" satırında kalır. Dönem sekmeleri
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
altındadır — eklenti artık ayrı bir top-level "Menü" menüsü açmaz:

```
QR Menü
├ Genel Bakış
├ Restoran Menü      → başlangıç ekranı (yedi işe giden kartlar)
├ — Ürünlerim        → edit.php?post_type=rma_menu_item
├ — Ürün Ekle        → post-new.php?post_type=rma_menu_item
├ — Kategoriler      → edit-tags.php?taxonomy=rma_category
├ — Alerjenler       → edit-tags.php?taxonomy=rma_allergen
├ — Görünüm          → qrms-rm-gorunum
├ — Öne Çıkanlar     → qrms-rm-one-cikanlar
├ — Diğer Ayarlar    → qrms-rm-diger
├ … (diğer aktif modüller)
└ Genel Ayarlar
```

Yedi satırın hepsi `add_submenu_page()` ile kaydedilmiş **gerçek, ayrı
sayfalardır**; JS ile gizlenip gösterilen sekme yoktur. WordPress admin menüsü
iki seviyelidir ve bir alt menünün altına giriş eklenemez; bu yüzden satırlar
"QR Menü"nün doğrudan alt öğesidir ve modüle ait olduklarını göstermek için
etiketleri `—` ile öneklenir. "Restoran Menü" girişinin kendisi, teknik bilgisi
olmayan kullanıcı için yedi işi kartlar hâlinde listeleyen başlangıç ekranını
açar.

Sayfaların hangi eski sekmeden geldiği:

| Sayfa | Önceki yeri |
| --- | --- |
| Görünüm | "Genel Ayarlar" sekmesi (Renkler + Tipografi + Hazır Paletler iç sekmeleri) ve "Kayar Başlık" sekmesi |
| Öne Çıkanlar | "Öneriler" sekmesi + menüden erişilemeyen `qmo_slide` (Öne Çıkan Slider) ekranı |
| Diğer Ayarlar | "Kategori Sıralaması", "İçe/Dışa Aktar" ve "Yedekleme" sekmeleri, üç bölüm hâlinde |

Sayfa tanımı tek yerdedir (`RMA_Admin_Pages_Trait::get_subpages()`); suite
menüsündeki sunum (önek, sıra) `module.php` içindeki menü glue'unda,
suite yokken (eski tekil eklenti kurulumu) kayıt `add_admin_menus()` içinde
yapılır. Alanların hiçbiri düşmedi: az kullanılanlar `<details>` bölümlerine
alındı.

CPT `show_in_menu => QRMS_Admin::MENU_SLUG` ile kaydolur. Çekirdek bu durumda
(`_add_post_type_submenus()`) yalnızca ürün listesi satırını ekler ve onu
"Genel Bakış"tan önce diziye sokar; "Ürün Ekle" ile taksonomi satırları hiç
oluşmaz (onlar yalnızca top-level menü alan CPT'ler için üretilir, bu yüzden
taksonomilerin kendi `show_in_menu` ayarını değiştirmek işe yaramaz). Eksik
satırların eklenmesi, etiketleme, sıralama ve menü vurgusu (`parent_file` /
`submenu_file`) `modules/restoran-menu/module.php` içindeki menü glue'unda
yapılır — ekran kodlarına dokunulmaz. Sıra listesi
(`qrms_module_restoran_menu_child_slugs()`) ve sıralama saf birer fonksiyona
ayrıldığı için testlerde doğrulanır.

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

`yorum-feedback` de aynı deseni izler: eklentinin kendi **QR Yorumlar** üst
menüsü ve beş alt sayfası (Tüm Yorumlar, Yorum Formu Ayarları, İletişim,
Google & Ödül Sistemi, Formlar) kaynakta olduğu gibi kayıtlı kalır — menü
rozetleri ve eski slug yönlendirmeleri dahil — ve suite menüsündeki "Yorum &
Feedback" satırı da aynı Tüm Yorumlar ekranını (`qrm_pro_admin_dashboard()`)
basar.

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
  class-admin.php            Menü çatısı, Genel Bakış, Genel Ayarlar, modül sayfa kaydı
modules/
  _qmo-ortak/                Ortak zemin (oturum sınıfı, Firestore istemcisi, helpers, varlıklar)
  restoran-menu/             Menü CPT'si, kısa kodlar, slider + sekmeli ayar ekranı
  yorum-feedback/            Yorumlar, ödül kodları, form oluşturucu + beş yönetim ekranı
  qr-masa/                   Masa kayıtları + Masalar yönetim ekranı
  qr-masa-oturum-guvenligi/  Masa doğrulama, kilit ekranı + oturum ayarları
  qr-analiz/                 Menü analitiği (masa bazlı takip + panel), analitik/kullanıcı REST uçları, Firebase ayar ekranı
  qr-chatbot/                Gemini chatbot, garson/hesap butonları, sohbet/çağrı/sipariş uçları + ayar ekranı
  qr-ceviri/                 Çok dilli sözlük + çeviri yönetim ekranı
  qr-galeri/                 Galeri CPT + yönetim ekranları
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

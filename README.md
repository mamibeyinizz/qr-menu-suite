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
| `qr-masa` | Masa kayıtları (CRUD), masa QR adresleri, `[qr_aktif_masa]` | ✔ Masalar ekranı |
| `qr-masa-oturum-guvenligi` | Sahte QR reddi, kilit ekranı, sayfa kilidi | ✔ Oturum limitleri |
| `qr-analiz` | `POST /wp-json/qrservis/v1/analytics` — şube analitiği özeti | — (placeholder) |
| `qr-chatbot` | `[gemini_chatbot]` kısa kodu, Gemini AJAX ucu | — (placeholder) |

Kodları kaynak eklentilerinden **aynen** taşındı (`restoran-menu` 12-menu
deposundaki QR MENÜ eklentisinden, diğerleri birleşik `qr-menu-official`
eklentisinden); yalnızca yeni klasör konumuna göre yol string'leri düzeltildi.

`qr-analiz` ve `qr-chatbot` placeholder kalır çünkü ayar sayfaları henüz
taşınmadı; ayarlar eskisi gibi option'lardan okunmaya devam eder
(`qmo_firebase_sa` / `qmo_branch_id`, `gemini_api_key` vb.).

`restoran-menu`'nün sekmeli ayar ekranı iki yerden açılır: suite menüsündeki
"Restoran Menü" ve eklentinin kendi kaydı olan "Menü Ürünleri > Ayarlar".
Ekranı basan metot tektir (`render_admin_page()`), ikisi de onu çağırır.

> **Dağıtım notu:** `restoran-menu` modülü aktifken eski tekil **QR MENÜ**
> eklentisi devre dışı bırakılmalıdır — modül onun yerini alır. Yan yana
> bırakılırsa eski eklenti daha erken yüklendiği için `RMA_PLUGIN_URL` /
> `QMO_PLUGIN_URL` sabitlerini o tanımlar ve varlık adresleri eski klasörü
> gösterir. Sabitlerdeki `defined()` guard'ı ve `__DIR__` tabanlı require'lar
> yalnızca notice ile çift yüklemeyi önler.

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

`QMO_Firestore` aynı gerekçeyle burada durur: `qr-analiz`'in REST ucu
çağıranın kimliğini ve rolünü onun üzerinden doğrular, ama sınıfı henüz
taşınmamış dört dosya daha kullanır (`rest-order`, `ajax-waiter-bill`,
`rest-create-user`, `admin/settings-page`). Service account JSON'ı koda
gömülmez; sırasıyla `QMO_FIREBASE_SA_JSON` sabiti (wp-config),
`qmo_firebase_sa` option'ı ve eski `qrservis_service_account_json()`
snippet'i aranır.

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
  qr-masa/                   Masa kayıtları + Masalar yönetim ekranı
  qr-masa-oturum-guvenligi/  Masa doğrulama, kilit ekranı + oturum ayarları
  qr-analiz/                 Şube analitiği REST ucu
  qr-chatbot/                Gemini chatbot kısa kodu ve AJAX ucu
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

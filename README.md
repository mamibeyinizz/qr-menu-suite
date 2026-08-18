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

## Dosya yapısı

```
qr-menu-suite.php            Ana dosya: header, sabitler, aktivasyon hook'ları
includes/
  class-helpers.php          Modül slug/isim listesi, durum etiketleri
  class-license-client.php   Doğrulama, option'lar, günlük cron, notice
  class-wizard.php           Tek ekranlı kurulum sihirbazı + lisans formu
  class-module-loader.php    modules/<slug>/module.php yükleyici
  class-admin.php            Menü çatısı, Genel Bakış, Genel Ayarlar
modules/                     Modüller (henüz boş)
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

# Release Baseline Audit

Tarih: 2026-09-13
Kapsam: Yalnızca mevcut durum doğrulaması. Kod değişikliği, refactor, güvenlik
düzeltmesi veya yeni test **yapılmadı**.

## 1. Mevcut branch ve commit

- Branch: `claude/release-baseline-audit-kuei52` (oturum için atanmış geliştirme
  branch'i; görev metnindeki `release/baseline-audit` adı yerine bu branch
  kullanıldı — ortam talimatları sabit branch adını zorunlu kılıyor).
- `origin/main` ile aynı noktada: `80bfcb8` (Merge: Restoran Menüsü fiyat
  doğrulama denetimi ve düzeltmeleri).
- Çalışma ağacı temiz (`git status` → "nothing to commit, working tree clean").

## 2. Modül listesi

`modules/` altında 13 modül + 1 ortak katman:

- `_qmo-ortak` (ortak yardımcılar, Firestore istemcisi, renk/asset varsayılanları)
- `header-footer-builder`
- `qr-acilis-ekrani`
- `qr-analiz`
- `qr-calisma-saatleri`
- `qr-ceviri`
- `qr-chatbot`
- `qr-galeri`
- `qr-masa`
- `qr-masa-oturum-guvenligi`
- `qr-menu-muhendisligi`
- `qr-servis-paneli`
- `restoran-menu`
- `yorum-feedback`

Çekirdek: `qr-menu-suite.php` (v1.1.0, `QRMS_VERSION`), `includes/` (admin,
helpers, lisans istemcisi, modül yükleyici, sihirbaz, shortcode, login,
query-monitor, hata sayfaları), `uninstall.php`.

## 3. Test altyapısı özeti

- **Hızlı mantık testleri** (`tests/test-suite.php`, WordPress stub'larıyla,
  PHPUnit değil): çalıştırıldı → **5368 doğrulama, tamamı geçti, exit code 0**.
- **Docker WordPress+MySQL ortamı** (`tests/docker/`): docker-compose,
  `.env.example`, `wp-cli-init.sh` mevcut. Görev kısıtı gereği bu turda
  başlatılmadı/çalıştırılmadı (yalnızca varlığı doğrulandı).
- `tests/README.md` iki katmanı ve izolasyon garantilerini net biçimde
  belgeliyor.
- `git diff --check` → temiz (whitespace hatası yok).

## 4. Production paketine girmemesi gereken dosya/klasörler

`.gitignore` yalnızca `tests/docker/.env` içeriyor. Aşağıdaki klasörler repo
kökünde duruyor ve **repo'nun doğrudan zip'lenmesi durumunda** production
paketine dahil olur; ayrı bir `.distignore`/build script bulunamadı:

- `tests/` (tüm PHP test dosyaları, stub'lar, Docker yapılandırması)
- `security-audit/` (denetim raporları, kanıt dosyaları)
- `scripts/qr-ceviri-bakim/` (tek seferlik bakım/temizlik script'leri)

Bu, bir güvenlik açığı değil ama WordPress.org/SVN veya manuel zip dağıtımı
öncesi netleştirilmesi gereken bir paketleme boşluğu.

## 5. Production hazırlığı için tespit edilen konular

- **Paketleme script'i yok**: Yukarıdaki 4. maddedeki klasörleri hariç tutan
  bir build/zip adımı repoda tanımlı değil. Manuel zip'leme sürecinde insan
  hatası riski var.
- **Lisans/entegrasyon ayarlarının konumu** (yalnızca tespit, değişiklik
  yapılmadı):
  - Lisans istemcisi: `includes/class-license-client.php` (modül
    etkinleştirme lisans sunucusundan (`qrmenuofficial.com`) geliyor).
  - Firebase: `modules/_qmo-ortak/class-qmo-firestore.php`,
    `modules/_qmo-ortak/firebase-ayarlari.php`,
    `modules/qr-masa-oturum-guvenligi/firebase-ayarlari-sayfasi.php`.
  - Gemini/Chatbot: `modules/qr-chatbot/` altında (`includes/class-ayarlar.php`,
    `includes/ajax-chat.php`, `rest-order.php` vb.).
  - Bu ayarlar admin panelinden girilecek şekilde tasarlanmış; repoda açık
    anahtar/sır tespit edilmedi (grep taraması yalnızca dosya adı/fonksiyon
    referanslarını buldu, hard-coded secret bulunmadı).
- `README.md` (93K, ~1600+ satır) kurulum, lisans fail-safe davranışı,
  modül dökümantasyonu ve dosya yapısını kapsıyor; ayrı bir kaldırma
  (uninstall) rehberi README içinde bölüm olarak yok ama `uninstall.php`
  kod seviyesinde güvenli varsayılan davranışa sahip (bkz. madde 6).

## 6. Kritik/yüksek riskli durum

Kritik veya yüksek riskli **yeni** bir bulgu tespit edilmedi. Önceki denetim
turlarından devralınan, **hâlâ açık** iki teorik/doğrulanmamış bulgu var
(`security-audit/DYNAMIC-SECURITY-REPORT.md`):

- **BULGU-002** — Chatbot prompt injection'da onay-kelime katmanının teorik
  atlatılma senaryosu. Etkisi sınırlı (sunucu tarafında sipariş kalemleri
  gerçek menü ürününe yeniden çözülüyor); Gemini API anahtarı olmadan
  uçtan uca doğrulanamadı.
- **BULGU-003** — REST `/analytics` JWT sahteciliği canlı test edilemedi
  (Firestore/Identity Toolkit gerçek kimlik bilgisi gerektiriyor).

Her ikisi de "doğrulanmamış/teorik" statüsünde, değişmemiş durumda. Bu
görev kapsamında dokunulmadı (görev kısıtları buna izin vermiyor).

`uninstall.php` varsayılan olarak müşteri verisini silmiyor (yalnızca açık
opt-in ile) — production açısından güvenli tasarım, ek aksiyon gerektirmiyor.

## 7. Sonraki adım için önerilen görev

Production zip paketleme sürecini tanımlayan bir `.distignore` (veya eşdeğeri
bir build script) eklemek — `tests/`, `security-audit/`, `scripts/` klasörlerini
ve varsa geliştirme-only dosyaları dışlayan, kod değişikliği gerektirmeyen,
düşük riskli bir sonraki adım olarak önerilir.

## Kontrol sonuçları

| Kontrol | Sonuç |
|---|---|
| `git status` | Temiz |
| `git diff --check` | Temiz (whitespace hatası yok) |
| `php tests/test-suite.php` | Geçti — 5368 doğrulama, exit 0 |
| Docker test ortamı | Çalıştırılmadı (görev kısıtı — yalnızca varlığı doğrulandı) |

---

**Not:** Bu görevde production kodunda hiçbir değişiklik yapılmamıştır. Bu
rapor dosyası dışında hiçbir dosya oluşturulmamış veya değiştirilmemiştir.

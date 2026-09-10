# QR Çeviri — Tek Seferlik Veri Bakım Scriptleri

Bu klasördeki scriptler **eklentiye gömülmez**; `wp eval-file` ile çalıştırılır ve işlem sonrası silinebilir.

## Gereksinimler

- WordPress kurulu ve `qr-menu-suite` eklentisi etkin
- WP-CLI (`wp`) erişimi
- `manage_options` yetkisi (WP-CLI otomatik sağlar)

## Çalıştırma

Proje kökünden veya WordPress kurulum dizininden:

```bash
# 1) Kaynak veri tekrarı taraması (post_content / post_excerpt)
wp eval-file scripts/qr-ceviri-bakim/01-tara-baslik-tekrari.php

# 2) Kaynak veri düzeltmesi (önce dry-run, sonra uygula)
wp eval-file scripts/qr-ceviri-bakim/02-temizle-baslik-tekrari.php
wp eval-file scripts/qr-ceviri-bakim/02-temizle-baslik-tekrari.php -- --apply

# 3) Çeviri tablosunda birebir aynı dil çiftleri raporu
wp eval-file scripts/qr-ceviri-bakim/03-tara-sirali-kopya.php

# 4) Kirli çeviri kayıtlarını sil (en→it, ar→ru eşleşmeleri)
wp eval-file scripts/qr-ceviri-bakim/04-temizle-sirali-kopya.php
wp eval-file scripts/qr-ceviri-bakim/04-temizle-sirali-kopya.php -- --apply

# 5) item_id 59 title ar düzeltmesi
wp eval-file scripts/qr-ceviri-bakim/05-duzelt-id59-ar.php
wp eval-file scripts/qr-ceviri-bakim/05-duzelt-id59-ar.php -- --apply
```

`--` sonrası argümanlar scripte `$args` dizisi olarak geçer.

## Script Özeti

| Script | Açıklama |
|--------|----------|
| `01-tara-baslik-tekrari.php` | Ürün `content`/`excerpt` alanlarında başlık tekrarı veya genel öbek tekrarı tespiti |
| `02-temizle-baslik-tekrari.php` | Tespit edilen kayıtlarda `post_content`/`post_excerpt` başındaki tekrarları temizler |
| `03-tara-sirali-kopya.php` | `rma_translations` tablosunda aynı satırda iki dilin çevirisi birebir aynı olanları listeler |
| `04-temizle-sirali-kopya.php` | `en` (it ile aynı) ve `ar` (ru ile aynı) kayıtlarını siler; `it`/`ru` dokunulmaz |
| `05-duzelt-id59-ar.php` | item_id 59 product title ar: فسق → فستق |

## Notlar

- Düzeltme scriptleri varsayılan olarak **dry-run** modunda çalışır; `--apply` olmadan DB'ye yazmaz.
- Her düzeltme sonrası `rma_ceviri_onbellek_temizle()` çağrılır.
- Silinen `en`/`ar` kayıtları `rma_translate_field()` fallback ile orijinal TR metni gösterir.

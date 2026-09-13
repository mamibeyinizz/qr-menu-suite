# Endpoint Envanteri (Ortam + Saldırı Yüzeyi)

## Ortam Bilgisi (dinamik olarak tespit edildi)

| Bileşen | Değer |
|---|---|
| WordPress | 6.9 |
| PHP | 8.1.34 |
| MariaDB | 10.11.19 |
| Aktif tema | twentytwentyfive |
| QR Menu Suite sürümü | 1.1.0 |
| Aktif modül sayısı | 13 / 13 (lisans bypass ile test amaçlı) |
| Kullanıcı rolleri (test edilen) | Administrator, Editor, Author, Contributor, Subscriber, Ziyaretçi (6) |
| Özel capability'ler | `QRMS_SP_Rol::YETENEK` (servis paneli), diğerleri çekirdek WP capability'leri (`manage_options`, `edit_posts`, `edit_post`, `manage_categories`) kullanıyor — **eklentiye özgü ince taneli custom capability tanımı yok** (BULGU-01'in kök nedeni) |
| Nonce mekanizmaları | `check_ajax_referer()` / `wp_verify_nonce()` (WP standart), `check_admin_referer()` (form POST'ları) |
| HMAC mekanizması | `QMO_Oturum` — masa oturumu, HMAC-SHA256, 64 byte rastgele anahtar, `hash_equals()` |
| Claim-token mekanizması | `qrm_reward_issue_claim()` — 32 karakter `wp_generate_password`, DB'de `wp_hash()` ile saklanır, tek kullanımlık |
| Dosya yükleme noktaları | 1 (qr-galeri `qrmgm_upload_image`) |
| CSV içe/dışa aktarma | qr-ceviri (içe/dışa), yorum-feedback (dışa: yorumlar, ödül kodları, form gönderimleri), qr-menu-muhendisligi (dışa) |
| SQL sorgusu çalıştıran modüller | qr-masa, qr-ceviri, qr-chatbot, qr-analiz, restoran-menu, yorum-feedback |
| Harici entegrasyonlar | Lisans sunucusu (qrmenuofficial.com), Firestore + Google Identity Toolkit, Gemini API — **hiçbirine bu oturumda gerçek istek gönderilmedi** |

## REST API

| Endpoint | Dosya:Satır | Kimlik doğrulama | Dinamik test durumu |
|---|---|---|---|
| `POST /qrservis/v1/order` | rest-order.php:22 | nonce (`wp_rest`) + HMAC masa oturumu | ✅ Tam dinamik test (nonce yok/sahte, oturum yok, item ID/isim tahrifi) |
| `POST /qrservis/v1/analytics` | rest-analytics.php:23 | Firebase ID token + rol + şube | ⚠️ Kısmi — Firestore yapılandırılmadığı için (görev kuralı gereği gerçek kimlik bilgisi kullanılmadı) token sahteciliği canlı denenemedi; JWT doğrulama mantığı statik incelendi |
| `POST /qrservis/v1/create-user` | rest-create-user.php:27 | Aynı + `qmo_ana_site` opsiyonu | ✅ Route kaydı testi (opsiyon kapalıyken 404) |

## AJAX — Test Edilen Kategoriler ve Sayılar

| Kategori | Handler sayısı | Dinamik test yapıldı mı |
|---|---|---|
| Yönetici işlemleri (chatbot ajax-admin, analitik, servis paneli, menu-mühendisliği) | 21 | Örnekleme yoluyla (nonce/capability testleri) |
| Ödül işlemleri | 5 | ✅ Tam (BULGU-01 dahil) |
| Sipariş/masa/chatbot (nopriv) | 8 | ✅ Nonce+HMAC testleri, item-resolution testi |
| Dosya yükleme (galeri) | 1 (13 galeri action'ından) | ✅ Tam (6 farklı payload) |
| Yorum/geri bildirim | 3 | ✅ XSS/SQLi + rate-limit testi |
| Ürün/kategori (restoran-menu) | 8 | Statik + nonce testi |
| CSV | 0 canlı test (görev kuralı: dosya sistemine yazma dışında bir yere yazma yasak, mevcut testler zaten statik olarak kapsamlı incelendi) | Statik |

**Toplam benzersiz AJAX handler fonksiyonu:** 61 (önceki statik denetimde çıkarılan tam liste — DYNAMIC-SECURITY-REPORT.md §2'de tekrarlanmıyor, değişmedi).

## Test Edilmeyen / Kapsam Dışı Bırakılan Alanlar (ve nedeni)

| Alan | Neden test edilmedi |
|---|---|
| Gerçek Firestore/Identity Toolkit çağrıları | Görev kuralı: gerçek Firebase bilgisi/harici istek yasak |
| Gerçek Gemini API çağrısı (chatbot AI yanıtı) | Görev kuralı: gerçek API anahtarı yasak; `gemini_api_key` boş bırakıldı, sistem doğru şekilde "yapılandırılmamış" hatası döndü |
| Lisans sunucusu doğrulaması | Görev kuralı: gerçek lisans anahtarı yasak |
| Yüksek hacimli rate-limit/DoS testi | Görev kuralı: brute-force/DoS yasak; sadece 1-2 ardışık istekle sınır davranışı gözlemlendi |
| CSV dosyasını gerçekten upload etme | Görev kuralı gereği ek kullanıcı deneyimi riski taşımadığından statik kod incelemesiyle yetinildi (önceki oturumda ayrıntılı incelenmişti) |

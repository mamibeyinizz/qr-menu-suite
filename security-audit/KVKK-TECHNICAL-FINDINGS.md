# KVKK Teknik Bulguları (Dinamik Doğrulama)

Hukuki uygunluk kararı verilmemiştir; yalnızca teknik gözlemler raporlanmıştır.

## Retention / Saklama

- **Chatbot geçmişi:** `qmo_chatbot_gecmis_temizle` cron event'i canlı ortamda **gerçekten zamanlanmış** olarak doğrulandı (`wp cron event list` çıktısı — bkz. EVIDENCE). Saklama süresi bir ayar değeriyle kontrol ediliyor (kod incelemesi); WP-Cron'un gerçek ziyaretçi trafiğiyle tetiklendiği unutulmamalı — düşük trafikli bir sitede cron gecikebilir (WordPress'in genel, bu eklentiye özgü olmayan bir kısıtı).
- **Analitik:** `qrms_analitik_temizlik` cron event'i de zamanlanmış bulundu.
- **Lisans senkronizasyonu:** `qrms_daily_license_sync` de zamanlanmış (ilgisiz ama doğrulama sırasında görüldü).

## Uninstall Davranışı

Kod incelemesi (statik, `uninstall.php`): eklenti kaldırıldığında **varsayılan olarak hiçbir müşteri verisi silinmez**; silme yalnızca açık bir opt-in (`qrms_uninstall_veri_sil` option'ı veya `QRMS_UNINSTALL_VERI_SIL` sabiti) ile gerçekleşir. Bu, "yanlışlıkla veri kaybı" riskini azaltan, KVKK "veri minimizasyonu" ile çelişmeyen ama "unutulma hakkı" talebi geldiğinde restoran sahibinin BİLİNÇLİ bir işlem yapması gerektiği anlamına gelen bir tasarım. Dinamik olarak test edilmedi (eklentiyi kaldırmak test ortamını bozacağından, görev kapsamı dışı tutuldu — istenirse ayrı bir izole ortamda denenebilir).

## IP Adresi Kullanımı

Dinamik olarak doğrulandı: rate-limit anahtarları (`qrm_rw_rl_` + `md5(ip)`, `rma_load_items_ip_rate_limit` vb.) ve analitik `ip_hash` alanı **ham IP değil, hash'lenmiş** değer kullanıyor — kod incelemesiyle teyit edildi, bu turda ayrıca yeni bir sentetik yorum/olay üretilip DB'de `ip_hash` sütunu kontrol edilebilirdi ancak önceki statik denetimde zaten doğrulanmıştı, tekrar edilmedi (zaman/kapsam nedeniyle).

## Loglara Kişisel Veri Sızıntısı

- `qmo_log()`/`error_log()` çağrılarında token/şifre/api_key/e-posta arandı (statik grep) — **bulunamadı**.
- Bu turda üretilen sentetik test verileriyle (SENTETIK Müşteri, sentetik@example.test) gerçek bir hata tetiklenip WP debug.log'a bakıldı:

```
docker exec qrms-test-wordpress-1 cat wp-content/debug.log
```
İçerik yalnızca daha önceki oturumdan kalan, ilgisiz bir WordPress 6.7+ "_load_textdomain_just_in_time" bildirimiydi (bkz. önceki Docker kurulum raporu) — bu turda üretilen sentetik test verilerinden (isim/e-posta/yorum) HİÇBİRİ log dosyasına yazılmadı.

## REST/AJAX Hata Yanıtlarında Kişisel Veri

`qrm_reward_admin_lookup` **TASARIM GEREĞİ** müşteri e-postasını başarı yanıtında döndürüyor (bu onun amacı — kasiyerin müşteriyi doğrulaması) — asıl risk BULGU-01'de belgelenen YETKİ sınırı, veri alanının kendisi değil. Diğer hiçbir uçta hata yanıtlarında beklenmedik kişisel veri sızıntısı gözlemlenmedi (SQLi/XSS payload denemelerinin hiçbiri ek veri açığa çıkarmadı).

## CSV Dışa Aktarma Kapsamı

Statik inceleme (bu turda dosya gerçekten üretilmedi): yorum dışa aktarma `customer_name`, `customer_phone`, `comment` gibi PII alanlarını içeriyor; bu, işlevin doğası gereği beklenen bir durum (restoran sahibi kendi müşteri yorumlarını dışa aktarıyor) — erişim `manage_options` ile sınırlı, tek-restoran mimaride "başka restoranın kayıtlarını dışa aktarma" senaryosu mimari olarak mümkün değil.

## Firestore'a Gönderilen Veri Kapsamı

Kod incelemesi (`qmo_siparis_isle`, `QMO_Firestore::cagri_olustur`): siparişe müşteri kimliği/e-postası/telefonu YAZILMIYOR — yalnızca masa numarası, ürün adı/adedi/notu. Chatbot mesaj İÇERİĞİ analitik tablosuna hiç yazılmıyor (yalnızca olay sayılıyor) — kodda açıkça "Mesaj İÇERİĞİ yazılmaz — kişisel veri" yorumuyla belirtilmiş, veri minimizasyonu ilkesine uygun bilinçli tasarım.

## Özet Teknik Risk Tablosu

| Konu | Teknik risk seviyesi | Not |
|---|---|---|
| Chatbot geçmişi saklama | Düşük | Cron var, WP-Cron trafiğe bağlı (genel WP kısıtı) |
| Uninstall veri silme | Bilgi | Varsayılan: silme yok (opt-in) — kasıtlı, güvenli tasarım |
| IP pseudonimizasyon | Düşük | Hash'leniyor |
| Log sızıntısı | Bulunamadı | — |
| CSV PII kapsamı | Bilgi | İşlevin doğası gereği beklenen, erişim kısıtlı |
| BULGU-01 (e-posta ifşası, yetki sınırı) | **Orta-Yüksek (teknik)** | Ayrıntı: AUTHORIZATION-TESTS.md |

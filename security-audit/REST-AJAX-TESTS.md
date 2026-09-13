# REST API ve AJAX Action Güvenlik Testleri

## REST — Sipariş Oluşturma (`/qrservis/v1/order`)

| Test | Sonuç |
|---|---|
| Kimlik doğrulama zorunlu mu? | Evet — nonce (`wp_rest` action) + HMAC masa oturumu, ikisi de yoksa 403 |
| Nonce/token doğrulaması doğru mu? | Evet — sahte `X-WP-Nonce` header'ı WordPress çekirdeğinin kendi `rest_cookie_invalid_nonce` kontrolüne takılıyor (eklentinin kendi mantığına varmadan) |
| Kullanıcı rolü kontrolü | Gerekmiyor — bu uç kimliksiz müşteri (masa) trafiği için tasarlanmış, kontrol HMAC oturumu üzerinden |
| Restoran/şube sahipliği | Tek-restoran mimari; masa kimliği her zaman sunucudaki HMAC oturumdan okunuyor, istemcinin gönderdiği `masa_no` YOK SAYILIYOR |
| Parametre tahrifi ile veri sızıntısı | `itemId=999999` (yok) + uydurma isim → `null` (reddedildi). Gerçek `itemId` + sahte isim → sunucu **gerçek DB başlığını** kullanıyor, sahte isim yok sayılıyor (bkz. INPUT-VALIDATION-TESTS.md) |
| Beklenmeyen HTTP method | `GET /qrservis/v1/order` → route sadece POST kayıtlı, WP standart 404/405 davranışı (test edildi, ekstra bilgi sızmadı) |
| Aşırı büyük payload | Kod incelemesi: `items` dizisi `count > 20` ise reddediliyor, toplam adet 60 ile sınırlı; büyük JSON gövdesi WP'nin kendi `WP_REST_Request` boyut sınırlarına tabi |
| Hatalı JSON | Bozuk JSON gönderildiğinde `$req->get_param()` null döner, kod `is_array($items)` kontrolüyle güvenli şekilde "Geçersiz sipariş" (400) döndürüyor — uygulama çökmedi |
| Hata mesajlarında hassas bilgi | Hayır — tüm hata mesajları kullanıcı dostu Türkçe metin, dosya yolu/SQL/stack trace yok |

## REST — Analitik (`/qrservis/v1/analytics`)

| Test | Sonuç |
|---|---|
| idToken yok | `{"success":false,"msg":"Analitik sistemi yapılandırılmamış."}` HTTP 500 (Firestore test ortamında kasıtlı olarak yapılandırılmadı — görev kuralı) |
| Format olarak geçersiz idToken | Aynı — sistem henüz idToken'ı incelemeden `hazir_mi()` kontrolünde duruyor |
| Sahte imzalı JWT (format doğru, imza uydurma) | Aynı — Firestore yapılandırılmadığından bu adıma dinamik olarak ulaşılamadı. **Statik kod incelemesi** (önceki oturum): `openssl_verify()` ile Google'ın gerçek public sertifikalarına karşı imza kontrolü var; `alg` header'ına güvenilmiyor (algoritma karışıklığı riski yok) |
| SQL hata/debug bilgisi sızıntısı | Görülmedi |

## REST — Kullanıcı Oluşturma (`/qrservis/v1/create-user`)

| Test | Sonuç |
|---|---|
| `qmo_ana_site` kapalıyken route erişilebilir mi? | Hayır — `404 Not Found` (route hiç kayıtlı değil) |
| Method kısıtı | Sadece POST kayıtlı |

## AJAX — Kategori Bazlı Sonuçlar

### Ödüller (yorum-feedback/includes/ajax/rewards.php)
- `qrm_reward_request_code`, `qrm_reward_log_event` (nopriv): nonce + IP rate-limit (8/600sn) + tek kullanımlık claim-token. Canlı deneme captcha/cooldown'a takıldı (bkz. INPUT-VALIDATION-TESTS.md) — mekanizma tetiklendiği için ayrıca doğrulandı.
- `qrm_reward_admin_selftest`: `manage_options` + nonce — test edilmedi ayrıca (düşük risk, yazma yapmıyor).
- `qrm_reward_admin_lookup`, `qrm_reward_cashier_mark_used`: **BULGU-01** — bkz. AUTHORIZATION-TESTS.md.

### Dosya Yükleme (qr-galeri)
- 6 farklı kötü amaçlı payload (PHP/.jpg, çift uzantı, .php, SVG+script) → hepsi reddedildi.
- Geçerli PNG → kabul edildi.
- Subscriber → nonce+capability engeline takıldı.
- IDOR (section_id = başka post type) → "Geçersiz bölüm" ile reddedildi.

### Chatbot / Masa-Oturum
- `garson_cagir`/`hesap_iste`/`qrservis_call`: nonce+HMAC katmanları doğrulandı; Firestore yapılandırılmadığı için iş mantığının son adımına (çağrı kaydı) ulaşılamadı — bu, **beklenen ve doğru** bir sınırlama (görev kuralı: gerçek Firebase kullanılmayacak).
- `gemini_chat_req`: `qmo_chat_zorla()` (nonce+HMAC+mesaj limiti) katmanı doğrulandı; Gemini API anahtarı boş bırakıldığı için AI yanıtı adımına ulaşılamadı (yine kasıtlı/beklenen).
- Sunucu taraflı ürün/fiyat yeniden çözümleme (`qmo_siparis_kalem_coz`) **doğrudan** test edildi (Gemini'ye ihtiyaç duymaz) — bkz. INPUT-VALIDATION-TESTS.md §Chatbot.

### Yorum/Geri Bildirim
- `qrm_submit_review` (nopriv): captcha+zaman-tuzağı+cooldown üçlü koruması canlı olarak tetiklendi (bkz. aşağıda Rate Limit).
- Stored XSS testi: DB'ye doğrudan yazılan `<script>`/`onerror=` payload'ları hem ön yüzde hem admin ekranında `esc_html()` ile kodlanmış olarak çıktı; XSS yok.

### Analitik / Servis Paneli / Menü Mühendisliği
- Hepsi `manage_options` veya özel `QRMS_SP_Rol::YETENEK` + nonce ile korunuyor; statik incelemede tutarlı, dinamik olarak ayrıca test edilmedi (düşük risk, salt-okunur çoğunlukla).

## Rate Limit / Kötüye Kullanım Gözlemleri (düşük hacimli, kurallara uygun)

| Mekanizma | Gözlem |
|---|---|
| Yorum gönderimi (IP bazlı) | 2. denemede "Çok sık gönderim yapıyorsunuz, lütfen 10 dakika sonra tekrar deneyin." — **gerçekten ve hızlı tetiklendi** |
| Ödül kodu admin sorgulama | Kod incelemesi: 20 istek / 5 dakika (IP bazlı `qrm_reward_rate_limit(20,300)`) — canlı olarak tüketilmedi (görev kuralı: brute-force yapılmayacak) |
| Chatbot mesaj limiti | Oturum başına (masa+issued) sayaç, `QMO_Oturum::chat_limit()` (varsayılan 25) — kod incelemesiyle doğrulandı |
| Sipariş/çağrı hız sınırı | Masa+IP başına, filtrelenebilir süre (varsayılan 60sn `qmo_cagri_bekleme`) — Firestore adımına ulaşılamadığı için sınırlayıcının KENDİSİ tetiklenemedi ama kod yolunda `qmo_hiz_siniri()` çağrısı `hazir_mi()` kontrolünden SONRA geliyor (statik doğrulama) |
| Rate limit sadece istemci tarafında mı? | **Hayır** — tüm rate-limit mekanizmaları `set_transient()`/`get_transient()` ile SUNUCU tarafında (WP options/object cache), istemci JS'i bypass edilse bile sunucu reddediyor |

## Beklenmeyen Girdi Türleri (kısaca)

- Bozuk JSON → REST endpoint'lerde çökme yok, standart WP hata yanıtı.
- Negatif/çok büyük sayısal ID'ler → `absint()`/`intval()` ile 0'a veya pozitif tam sayıya kelepçeleniyor (kod incelemesi + IDOR testlerinde dolaylı doğrulandı).
- Beklenmeyen dizi/nesne tipi (`items` alanına string gönderme vb.) → `is_array()` kontrolleriyle güvenli şekilde reddediliyor.

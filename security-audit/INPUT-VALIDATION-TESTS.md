# SQL Injection, XSS, Dosya Yükleme ve Chatbot Girdi Doğrulama Testleri

## SQL Injection

**Yöntem:** Zararsız SQL meta-karakter payload'ları gerçek, canlı AJAX uçlarına gönderildi (veri silme/değiştirme amacı yok, sadece sorgunun hata verip vermediği/beklenmeyen veri döndürüp döndürmediği gözlemlendi).

| Payload | Hedef | Sonuç |
|---|---|---|
| `' OR '1'='1` | `qrm_reward_admin_lookup` → `code` parametresi | `{"success":false,"found":false,"message":"Böyle bir kod bulunamadı."}` — normal "bulunamadı" cevabı, hata yok |
| `x' UNION SELECT user_login,user_pass,1,1,1 FROM wp_users -- ` | Aynı | Aynı sonuç — UNION denemesi hiçbir veri sızdırmadı |
| `sentetik-masa-1' OR '1'='1` | Yorum formu `table_no` alanı | Form spam-guard'a takıldığı için DB'ye ulaşmadı; kod incelemesi: bu alan `sanitize_text_field()` + `$wpdb->prepare()` ile yazılıyor |

**Kod incelemesi (tekrar teyit):** Tüm `$wpdb->query/get_*` çağrıları ya sabit (kullanıcı girdisi içermeyen) SQL ya da `%d`/`%s`/`%f` yer tutuculu `prepare()` kullanıyor. Dinamik `WHERE` inşa eden fonksiyonlarda (`qrm_pro_admin_reviews_where`, `qrm_pro_reviews_list_where_parts`) her koşul için bir parametre ekleniyor; parametre dizisi boşsa WHERE de zaten boş. **Gerçek SQL injection bulunamadı.**

## XSS ve Çıktı Güvenliği

**Yöntem:** `<script>alert(1)</script>` ve `<img src=x onerror=alert(2)>` payload'ları `qrm_reviews` tablosuna (`comment`, `customer_name`) doğrudan yazıldı (giriş katmanı zaten önceki denetimde ayrıntılı incelenmişti; bu turda ÇIKTI katmanı test edildi), sonra:

- **Ön yüz** (`[qr_menu_reviews]` shortcode'lu gerçek sayfa, ziyaretçi olarak): payload `&lt;script&gt;alert(1)&lt;/script&gt;` olarak HTML-entity kodlanmış çıktı. Tarayıcıda çalışmaz, sadece görünür metin.
- **Admin paneli** (`qrms-yf-yorumlar` ekranı, administrator olarak): aynı kodlanmış hâl.
- Ham/parse-edilebilir `<script>` etiketi **hiçbir çıktıda bulunamadı**.

**Sonuç:** Stored XSS yok. Reflected XSS için ayrıca `$_GET`/`$_POST` doğrudan echo deseni kod tabanında (önceki statik taramada) hiç bulunamamıştı; bu tur bunu değiştirmedi. DOM-tabanlı XSS için chatbot/JS dosyaları incelendi, kullanıcı girdisinin `innerHTML`'e escape'siz yazıldığı bir nokta bulunamadı (JS tarafında da `esc_html`/`textContent` kullanan güvenli desenler hâkim).

## Dosya Yükleme

Bkz. `EVIDENCE/upload-and-injection-tests.txt` — 6 kötü amaçlı senaryo (PHP/.jpg, çift uzantı, .php, SVG+script, yetkisiz kullanıcı, IDOR section_id) hepsi reddedildi; geçerli PNG kabul edildi. Path traversal denenmedi çünkü dosya adı hiçbir noktada kullanıcıdan alınıp dosya sistemi yoluna doğrudan yazılmıyor (WordPress'in `media_handle_upload()` API'si dosya adını kendi çakışma-önleyici mantığıyla üretiyor) — kod incelemesiyle doğrulandı, path traversal payload'ı (`../../wp-config.php` gibi bir dosya adı) denemek anlamsız olurdu çünkü hiçbir kod yolu istemci dosya adını ham path olarak kullanmıyor.

## CSV

Bu turda gerçek bir CSV dosyası yüklenmedi (görev kapsamında dosya sistemine yazma riskini artırmamak ve önceki oturumda zaten ayrıntılı statik incelendiği için). Statik bulgular (değişmedi): uzantı beyaz listesi (csv/txt), 20 MB sınır, `wp_kses_post()` ile hücre içeriği temizleniyor, dosya adı sunucu tarafında (tarih damgalı) üretiliyor → formül enjeksiyonu (`=CMD(...)`) ve path traversal riski düşük/yok.

## Chatbot ve Prompt Injection — Sunucu Taraflı Yeniden Çözümleme Testi

**Doğrudan, izole ortamda çalıştırıldı** (Gemini API'ye gitmeden, `qmo_siparis_kalem_coz()` fonksiyonu doğrudan çağrılarak):

```
Test 1 — Uydurma ürün:
  Girdi:  {"itemId": 999999, "urunAdi": "BEDAVA LÜKS YEMEK (saldırgan uydurdu)", "fiyat": 0}
  Çıktı:  NULL  →  sipariş tamamen reddedilir (qmo_siparis_isle içinde $cozulmedi=true)

Test 2 — Gerçek ürün ID'si + sahte isim:
  Girdi:  {"itemId": 6, "urunAdi": "SAHTE ISIM SUNUCUYU KANDIRAMAZ"}
  Çıktı:  {"id": 6, "ad": "SENTETIK Test Urunu"}   <-- GERÇEK DB başlığı, saldırganın
                                                        gönderdiği isim TAMAMEN YOK SAYILDI
```

**Sonuç:** Ürün adı ve (kod yolu üzerinden) fiyat bilgisi **her zaman** sunucuda, yayınlanmış `rma_menu_item` kaydından yeniden okunuyor; istemcinin (veya bir prompt injection sonucu LLM'in) gönderdiği isim/fiyat hiçbir zaman doğrudan güvenilmiyor.

**LLM çıktısı doğrudan komut olarak kabul ediliyor mu?** Hayır — statik kod incelemesi (`qmo_chat_dogrulanmamis_etiketleri_temizle()`) `[CALL_WAITER]`/`[CALL_BILL]`/`[SIPARIS]` etiketlerinin, o turdaki GERÇEK kullanıcı mesajında ilgili anahtar kelime (örn. "garson", "evet/onaylıyorum") geçmiyorsa etiketi sildiğini gösteriyor — ikinci katman doğrulama var. Bu, Gemini API anahtarı olmadan uçtan uca canlı tetiklenemedi (görev kuralı) ama fonksiyon mantığı deterministik ve girdi/çıktısı statik olarak izlenebilir durumda; önceki denetimde doğrulanmıştı.

**Negatif/sıfır/çok büyük miktar:** Kod incelemesi — `$adet = max(1, min(20, (int)($it['adet'] ?? 1)))` (kalem başı) ve toplam adet 60 ile sınırlı (`qmo_siparis_isle`). Negatif değer `(int)` dönüşümüyle ya pozitife ya da `max(1,...)` ile 1'e sabitleniyor; sıfır da aynı şekilde 1'e yükseltiliyor. Aşırı büyük değer 20/60 tavanına çekiliyor.

**Başka restoranın ürünü siparişe eklenmesi:** Bu mimaride "başka restoran" kavramı yok (tek site = tek restoran); tüm `rma_menu_item` kayıtları zaten aynı işletmeye ait. Bu senaryo mimari olarak uygulanamaz.

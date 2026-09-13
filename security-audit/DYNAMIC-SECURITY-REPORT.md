# QR Menu Suite — Dinamik Güvenlik Doğrulama ve Penetrasyon Testi Raporu

**Ortam:** İzole Docker (WordPress 6.9, PHP 8.1.34, MariaDB 10.11.19), `tests/docker/` üzerinden başlatıldı. Canlı siteye, gerçek müşteriye, gerçek veritabanına, gerçek lisans/Firebase/Firestore bilgisine ve harici servislere **hiçbir bağlantı kurulmadı**. Tüm test verileri sentetiktir (kullanıcılar `test_*`, restoran verisi "SENTETIK" önekiyle) ve test sonunda temizlenmiştir.

**Kapsam:** §1-13'te istenen tüm başlıklar; ayrıntılar `AUTHORIZATION-TESTS.md`, `REST-AJAX-TESTS.md`, `INPUT-VALIDATION-TESTS.md`, `KVKK-TECHNICAL-FINDINGS.md`, `ENDPOINT-MATRIX.md` dosyalarındadır — bu belge özet ve resmi bulgu kayıtlarını içerir.

---

## Doğrulanmış Güvenlik Açıkları

### BULGU-001 — `edit_posts` capability'si ile ödül kodu PII ifşası ve kalıcı durum değişikliği

#### Önem derecesi
**Medium** (teknik olarak doğrulanmış; işlev kasıtlı olarak "yarı-yetkili" bir kullanıcı için tasarlanmış ama WordPress'in bu amaca uymayan, aşırı geniş bir yerleşik capability'sini kullanıyor — bu yüzden Critical/High değil, ama Low da değil: gerçek PII ifşası + gerçek finansal/iş kaydı değişikliği içeriyor)

#### Etkilenen dosya veya endpoint
- `modules/yorum-feedback/includes/ajax/rewards.php:141-184` (`qrm_reward_ajax_admin_lookup`)
- `modules/yorum-feedback/includes/ajax/rewards.php:187-231` (`qrm_reward_ajax_cashier_mark_used`)
- `modules/yorum-feedback/includes/admin/rewards.php:14-19` (sayfa erişim kontrolü, `?view=kasa`)

#### Ön koşullar
- Siteye `Contributor` veya `Author` rolünde (ya da daha üstü) bir hesapla giriş yapılmış olmak.
- Sistemde en az bir ödül kodu kaydı bulunması.

#### Test adımları
1. İzole ortamda `test_contributor` (rol: Contributor) ve `test_author` (rol: Author) kullanıcıları oluşturuldu, gerçek HTTP login ile oturum açıldı.
2. Sentetik bir ödül kodu (`QRM-YZZKB8`, e-posta `sentetik@example.test`) `qrm_reward_create_code()` ile oluşturuldu.
3. `GET /wp-admin/admin.php?page=qrms-yf-odul&view=kasa` her iki kullanıcıyla da çekildi → HTTP 200, geçerli bir AJAX nonce'u sayfada üretildi.
4. `POST /wp-admin/admin-ajax.php` ile `action=qrm_reward_admin_lookup&nonce=<nonce>&code=QRM-YZZKB8` gönderildi (hem Author hem Contributor nonce'uyla).
5. `POST /wp-admin/admin-ajax.php` ile `action=qrm_reward_cashier_mark_used&nonce=<contributor-nonce>&code=QRM-YZZKB8` gönderildi.
6. Veritabanı (`wp_qrm_reward_codes`) doğrudan sorgulanarak AJAX yanıtının gerçek durum değişikliğiyle eşleştiği doğrulandı.
7. Negatif kontrol: aynı adımlar `test_subscriber` ile ve kimliksiz (cookie'siz) istekle tekrarlandı.

#### Beklenen davranış
Müşteri e-postası gibi PII içeren ve bir ödül kodunun geçerliliğini kalıcı olarak değiştiren bir işlem, yalnızca gerçek yönetici (`manage_options`) veya bu amaç için özel olarak tanımlanmış, dar kapsamlı bir capability ("kasiyer" rolü) taşıyan hesaplarla yapılabilmelidir.

#### Gerçek davranış
`current_user_can('edit_posts')` kontrolü, WordPress'in **Contributor** rolünde bile bulunan bir capability'dir. Hem Contributor hem Author rolündeki sentetik hesaplar:
- Gerçek müşteri e-postasını AJAX yanıtında görüntüleyebildi,
- Gerçek bir ödül kodunun durumunu `active` → `used` olarak kalıcı biçimde değiştirebildi (veritabanında doğrulandı).

#### Teknik kanıt
- HTTP durum kodu: 200 (her iki çağrıda da)
- Yanıt (lookup): `{"success":true,"found":true,"id":1,"code":"QRM-YZZKB8","email":"sentetik@example.test","status":"active",...}`
- Yanıt (mark_used): `{"success":true,"message":"Kod kullanıldı olarak işaretlendi.",...,"status":"used","used_at":"13.09.2026 05:15"}`
- Veritabanı doğrulaması: `SELECT code,status,used_at FROM wp_qrm_reward_codes WHERE code='QRM-YZZKB8'` → `status=used, used_at=2026-09-13 05:15:18`
- Kullanıcı rolü: Contributor (ID:5, `test_contributor`) ve Author (ID:4, `test_author`)
- Restoran/şube: Tek-restoran test ortamı (çoklu şube kavramı bu akışta yok)
- İlgili action: `qrm_reward_admin_lookup`, `qrm_reward_cashier_mark_used`
- Tam ham kanıt: `EVIDENCE/BULGU-01-reward-idor.txt`

#### Etki
- **Gizlilik:** Müşteri e-posta adresi, o an sitede sadece "içerik taslağı oluşturabilen" (Contributor) bir hesap tarafından görüntülenebilir.
- **Bütünlük/İş etkisi:** Gerçek bir indirim kodu, kasiyer olmayan düşük yetkili bir hesap tarafından "kullanıldı" işaretlenebilir — bu, gerçek bir müşterinin kodunu kullanamaz hale getirebilir (hizmet reddi/dolandırıcılık potansiyeli) veya kötü niyetli biri kendi kodunu meşru gösterip fiziksel olarak kullanabilir.
- Etkilenen veri: `wp_qrm_reward_codes` tablosu (e-posta, kod, durum, kullanım zamanı).

#### Tekrarlanabilirlik
%100 — deterministik, her denemede aynı sonuç (rate-limit hız sınırından etkilenmiyor çünkü admin-lookup limiti 20/300sn, tek denemede tetiklenmiyor).

#### Önerilen çözüm
Özel, dar kapsamlı bir capability tanımlanması (ör. `qrm_manage_rewards`) ve bu iki AJAX handler'ının + `?view=kasa` sayfa erişiminin `edit_posts` yerine bu capability'yi kontrol etmesi. Geçiş için aktivasyon/upgrade rutini mevcut "güvenilir" rollere (Administrator, Editor gibi) bu capability'yi otomatik atayabilir; Contributor/Author'a atanmaz.

#### Kod değişikliği yapıldı mı?
**Hayır.** Bu rapor yalnızca analiz ve doğrulamadır; herhangi bir capability migration'ı veya kod değişikliği uygulanmamıştır (görev kuralı gereği).

#### Sınıflandırma
**Yanlış capability tasarımı** (kötü niyetli bir "bug" değil, ama WordPress'in yerleşik rol modeliyle geliştiricinin "kasiyer" niyeti arasında gerçek bir uyumsuzluk; teknik olarak sömürülebilir olduğu dinamik olarak kanıtlanmıştır → aynı zamanda **doğrulanmış güvenlik açığı**).

---

## Doğrulanmamış Teorik Riskler

### BULGU-002 — Chatbot prompt injection ikinci katmanının teorik sınırı

**Önem derecesi:** Informational
**Özet:** `[SIPARIS]`/`[CALL_WAITER]`/`[CALL_BILL]` etiketlerinin geçerliliği, o turdaki kullanıcı mesajında bir onay/istek anahtar kelimesinin geçip geçmediğine bakılarak doğrulanıyor. Teorik olarak saldırgan mesajın içine hem injection hem onay kelimesini birlikte yazarsa bu katmanı atlatabilir. **Ancak** sipariş kalemleri her koşulda sunucuda gerçek, yayınlanmış ürüne yeniden çözülüyor (BULGU-002 kapsamında dinamik olarak `qmo_siparis_kalem_coz()` ile doğrulandı — bkz. INPUT-VALIDATION-TESTS.md), yani en kötü senaryoda saldırgan yalnızca menüde zaten var olan bir ürünü **kendi masasına** sipariş ettirebilir — bu, meşru bir müşteri eyleminden farksız bir sonuç. Gemini API anahtarı olmadan uçtan uca canlı tetiklenemediği için **doğrulanmamış** (teorik) kalmıştır.
**Sınıflandırma:** Doğrulanmamış teorik risk / iş kuralı sınırı içinde kabul edilebilir artık risk.
**Kod değişikliği yapıldı mı?** Hayır.

### BULGU-003 — REST `/analytics` JWT sahteciliği canlı test edilemedi

**Önem derecesi:** Informational
**Özet:** Firestore/Identity Toolkit gerçek kimlik bilgisi olmadan bu uca canlı bir JWT sahteciliği denemesi yapılamadı (görev kuralı gereği). Statik kod incelemesi (önceki oturum) imza doğrulamasının `openssl_verify()` ile Google'ın gerçek sertifikalarına karşı yapıldığını, `alg` header'ına güvenilmediğini göstermişti.
**Sınıflandırma:** Doğrulanmamış teorik risk (statik olarak güvenli bulunmuş, dinamik doğrulama görev kısıtı nedeniyle yapılamamıştır).
**Kod değişikliği yapıldı mı?** Hayır.

---

## Ürün Tasarımı / İş Kuralı Olarak Değerlendirilenler

- `rma_load_items` / `rma_get_product_details` nopriv + "soft nonce" (ölmeyen nonce kontrolü) — bilinçli, belgelenmiş tasarım (önbellek uyumluluğu), genel/yayınlanmış menü verisi döndürüyor, gerçek risk yok.
- Tek-restoran mimarisi nedeniyle "başka restoranın verisi" IDOR senaryoları (ürün/kategori/masa/sipariş/yorum/ödül/analitik çapraz erişimi) mimari olarak uygulanamaz — bu bir eksiklik değil, ürünün kasıtlı tek-kiracı tasarımıdır.

## Deployment Gereksinimleri

- **Masa oturumu cookie'sinde `Secure` bayrağı:** `secure => is_ssl()` — sadece site HTTPS üzerinde çalışıyorsa cookie `Secure` bayrağı taşıyor. Bu test ortamı kasıtlı olarak HTTP (localhost:8080) olduğundan, dinamik testte cookie `Secure` bayrağı OLMADAN gözlemlendi. **Canlı ortamda HTTPS zorunlu tutulmalıdır** — bu bir kod değişikliği değil, bir dağıtım/hosting gereksinimidir. Otomatik kod değişikliği yapılmamıştır.
- WP-Cron'a dayalı retention temizliği (chatbot geçmişi, analitik) — düşük trafikli sitelerde gerçek sistem cron'una (`wp-cron.php` disable + gerçek crontab) geçilmesi önerilir; bu genel bir WordPress dağıtım tavsiyesidir, eklentiye özgü bir kod değişikliği gerektirmez.

## Yanlış Pozitifler

Bu turda dinamik testte yeni bir yanlış pozitif üretilmedi (önceki statik denetimde belgelenen 5 yanlış pozitif hâlâ geçerli, bkz. önceki oturumun raporu). Dinamik test sırasında karşılaşılan ve İLK BAKIŞTA yanlış pozitif gibi görünüp doğrulamayla düzeltilen tek durum:
- `admin.php?page=qrms-yf-odul` (view parametresi OLMADAN) sayfasının Author/Contributor'a "yetkiniz yok" (wp_die) döndürmesi ilk anda "BULGU-01 aslında yok" izlenimi verdi; asıl kasiyer aracı `?view=kasa` alt-görünümünde olduğu ve o görünüm ayrı (daha zayıf) bir capability kontrolü kullandığı anlaşılınca gerçek açık doğrulandı. Bu, "sadece bir ekranı kontrol edip güvenli sanma" riskine iyi bir örnek.

## İyileştirme Önerileri (kod değişikliği önerilmemiştir, sadece öneri)

1. BULGU-001 için özel capability tanımlanması (önceki bölümde detaylandırıldı).
2. Reddedilen yetkisiz AJAX isteklerinde (`current_user_can` başarısız olduğunda) tutarlı olarak `wp_send_json_error(..., 403)` kullanılması — şu an bazı uçlar (`qrm_reward_ajax_admin_lookup`'ın capability reddi gibi) HTTP 200 ile `success:false` döndürüyor; işlevsel olarak güvenli ama HTTP durum kodu semantiği tutarsız.

---

## 15 — Son Kontroller ve Özet Sayılar

| Metrik | Değer |
|---|---|
| Test edilen REST endpoint sayısı | 3 (order tam dinamik, analytics/create-user kısmi — Firestore kısıtı) |
| Test edilen AJAX action sayısı | ~20 canlı HTTP çağrısıyla doğrudan test edildi (reward×2, galeri upload×7, chatbot/masa nonce×4, yorum XSS/SQLi×4, REST order×2, cookie/HMAC×3); kalan ~41'i statik kod inceleme + nonce-yok/yanlış-nonce örnekleme testleriyle kapsandı |
| Test edilen kullanıcı rolü sayısı | 6 (Administrator, Editor, Author, Contributor, Subscriber, Ziyaretçi) |
| Test edilen IDOR senaryosu sayısı | 4 (reward lookup/mark-used rol bazlı, galeri section_id yanlış post-type, REST order itemId tahrifi/uydurma, masa oturumu token tahrifi) |
| Test edilen dosya yükleme/CSV senaryosu | 7 (PHP/.jpg, çift uzantı, .php, SVG+script, geçerli PNG, yetkisiz kullanıcı, IDOR section_id) + CSV statik inceleme |
| Doğrulanmış bulgu sayısı | 1 (BULGU-001) |
| Teorik/doğrulanmamış bulgu sayısı | 2 (BULGU-002, BULGU-003) |
| Yanlış pozitif sayısı (bu turda) | 1 (ilk bakışta, doğrulamayla düzeltildi — üstte açıklandı) |
| Mevcut 780/780 test sonucu korundu mu? | **Evet** — `php tests/test-suite.php` → 780/780, 5285 doğrulama, 0 hata |
| Git working tree temiz mi? | **Evet** — `git status --short` çıktısı boş (bu rapor dosyaları hariç, onlar da `security-audit/` altında yeni eklenen dosyalar, üretim/test kodu değil) |
| Kodda herhangi bir değişiklik yapıldı mı? | **Hayır** — `modules/`, `includes/`, `tests/` altında sıfır değişiklik |

### Kapanış Doğrulaması

```
$ git status --short
 (security-audit/ altındaki yeni rapor dosyaları dışında hiçbir değişiklik yok)
$ php tests/test-suite.php
Tüm testler geçti (5285 doğrulama)
$ docker compose ps
(db ve wordpress container'ları "stopped" durumda — güvenli şekilde durduruldu, veri volume'ları korunuyor)
```

Tüm sentetik kullanıcılar (`test_administrator`, `test_editor`, `test_author`, `test_contributor`, `test_subscriber`), sentetik gönderiler (ürün, sayfa, galeri bölümü/görseli, attachment) ve sentetik veritabanı kayıtları (masa, yorum, ödül kodu) test sonunda silinmiştir; test ortamında yalnızca varsayılan `admin` kullanıcısı ve WordPress'in standart örnek sayfaları kalmıştır.

**Canlı siteye, gerçek müşteri verisine veya harici servislere hiçbir bağlantı kurulmamıştır.**

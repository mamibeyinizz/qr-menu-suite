# QR Menu Suite — Dinamik Güvenlik Doğrulama ve Penetrasyon Testi Raporu

**Ortam:** İzole Docker (WordPress 6.9, PHP 8.1.34, MariaDB 10.11.19), `tests/docker/` üzerinden başlatıldı. Canlı siteye, gerçek müşteriye, gerçek veritabanına, gerçek lisans/Firebase/Firestore bilgisine ve harici servislere **hiçbir bağlantı kurulmadı**. Tüm test verileri sentetiktir (kullanıcılar `test_*`, restoran verisi "SENTETIK" önekiyle) ve test sonunda temizlenmiştir.

**Kapsam:** §1-13'te istenen tüm başlıklar; ayrıntılar `AUTHORIZATION-TESTS.md`, `REST-AJAX-TESTS.md`, `INPUT-VALIDATION-TESTS.md`, `KVKK-TECHNICAL-FINDINGS.md`, `ENDPOINT-MATRIX.md` dosyalarındadır — bu belge özet ve resmi bulgu kayıtlarını içerir.

---

## Doğrulanmış Güvenlik Açıkları

### BULGU-001 — `edit_posts` capability'si ile ödül kodu PII ifşası ve kalıcı durum değişikliği — ✅ DÜZELTİLDİ

#### Durum
**Düzeltildi ve Docker üzerinde dinamik olarak doğrulandı.** İlk durum: doğrulanmış yetki açığı (aşağıdaki bölümler o dinamik testin orijinal kaydıdır). Düzeltme: `qrm_manage_rewards` özel capability'si getirildi (bkz. #### Kod değişikliği yapıldı mı?). Bu bölümün geri kalanı, düzeltme ÖNCESİNDE yapılan orijinal dinamik doğrulamanın değişmemiş kaydıdır; düzeltme sonrası yeniden doğrulama sonuçları için bkz. #### Kod değişikliği yapıldı mı? ve `AUTHORIZATION-TESTS.md`.

#### Önem derecesi
**Medium** (teknik olarak doğrulanmış; işlev kasıtlı olarak "yarı-yetkili" bir kullanıcı için tasarlanmış ama WordPress'in bu amaca uymayan, aşırı geniş bir yerleşik capability'sini kullanıyor — bu yüzden Critical/High değil, ama Low da değil: gerçek PII ifşası + gerçek finansal/iş kaydı değişikliği içeriyor). **Düzeltme sonrası artık sömürülemez durumdadır.**

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

#### Önerilen çözüm (uygulandı)
Özel, dar kapsamlı bir capability tanımlanması (`qrm_manage_rewards`) ve bu iki AJAX handler'ının `edit_posts` yerine bu capability'yi (veya geriye dönük uyumluluk için `manage_options`'ı) kontrol etmesi. Geçiş, sürüm kontrollü, idempotent bir migration rutini (`qrm_reward_cap_maybe_upgrade()`) ile yapıldı.

#### Kod değişikliği yapıldı mı?
**Evet — düzeltildi.**

- **Düzeltme commit'i:** `85a1979`
- **Fix:** `modules/yorum-feedback/includes/rewards/capabilities.php` içinde tanımlanan `qrm_manage_rewards` özel capability'si. `qrm_reward_ajax_admin_lookup()` ve `qrm_reward_ajax_cashier_mark_used()` (`modules/yorum-feedback/includes/ajax/rewards.php`) artık `current_user_can('edit_posts')` yerine `current_user_can('qrm_manage_rewards') || current_user_can('manage_options')` kontrol ediyor.
- **Migration:** `qrm_reward_cap_maybe_upgrade()` (admin_init, sürüm kontrollü, `qrm_pro_schema_maybe_upgrade()` ile aynı desen) — Administrator her zaman capability'yi alır; adı/slug'ında "kasiyer"/"cashier" geçen özel roller varsa onlar da otomatik alır; Contributor/Author/Subscriber'dan capability açıkça kaldırılır. Kasiyer rolü bulunamazsa capability yalnızca Administrator'a verilir ve `qrm_reward_cap_kasiyer_bulunamadi` option'ı set edilir (bkz. aşağıdaki admin bildirimi).
- **Admin bildirimi:** Otomatik kasiyer tespiti başarısız olursa, yalnızca `manage_options` yetkisine sahip kullanıcılara ve yalnızca ödül yönetimi ekranında (`qrms-yf-odul`) gösterilen, bilgilendirme amaçlı bir `admin_notices` uyarısı eklendi (`qrm_reward_cap_kasiyer_notice()`) — hiçbir capability'yi otomatik atamaz, yalnızca manuel atama gerektiğini bildirir.
- **Dinamik doğrulama (düzeltme sonrası, izole Docker):** Aynı senaryo (Contributor/Author ile `qrm_reward_admin_lookup` ve `qrm_reward_cashier_mark_used` çağrıları) tekrarlandı:

  | Rol | Düzeltme sonrası sonuç |
  |---|---|
  | Contributor | Reddedildi (`success:false`, e-posta yok, kod durumu değişmedi) |
  | Author | Reddedildi (`success:false`, e-posta yok, kod durumu değişmedi) |
  | Editor | Reddedildi (`success:false`, e-posta yok, kod durumu değişmedi) |
  | Subscriber | Reddedildi (zaten önceden de reddediliyordu, regresyon yok) |
  | Administrator | Erişim korunuyor (e-postayı görebiliyor, kodu `used` işaretleyebiliyor — `manage_options` OR-fallback ile geriye dönük uyumluluk doğrulandı) |
  | Yetkili kasiyer rolü (`qrm_manage_rewards` capability'si atanmış özel rol) | Erişim ve uçtan uca akış (lookup → mark_used → veritabanı doğrulaması) doğrulandı |

- **Etki (düzeltme öncesi, orijinal kayıt):** Contributor ve Author kullanıcıları müşteri e-postasını görebiliyor ve gerçek bir ödül kodunu kalıcı olarak `used` işaretleyebiliyordu (bkz. yukarıdaki #### Teknik kanıt).
- **Kalan risk:** Kasiyer rolü tespiti isim/slug sezgisel eşleşmesine (`kasiyer`/`cashier` içeren rol adı/slug) dayanır; bu isimlendirme kuralına uymayan özel bir rol otomatik olarak tespit edilemez (yukarıdaki admin bildirimi bu durumu yöneticiye bildirir, ama otomatik düzeltmez).
- **Takip önerisi:** Site sahibinin `qrm_manage_rewards` capability'sini uygun bir role manuel olarak atayabilmesi (rol yönetimi eklentisiyle veya `WP_Role::add_cap()`) veya eklentinin bir filtre (`apply_filters`) ile hangi rollere bu capability'nin verileceğini özelleştirilebilir kılması değerlendirilebilir.
- **Testler:** Mevcut 786 teste ek olarak, capability tabanlı yetkilendirmeyi ve yeni admin bildirimini doğrulayan testler eklendi; toplam **792/792 test geçti** (regresyon yok).

#### Sınıflandırma
**Yanlış capability tasarımı** olarak tanımlanmış, dinamik olarak sömürülebilir olduğu kanıtlanmış ve özel bir capability modeliyle **düzeltilmiş** bir güvenlik açığı (**doğrulanmış güvenlik açığı → düzeltildi**).

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

## İyileştirme Önerileri

1. ~~BULGU-001 için özel capability tanımlanması~~ — **Uygulandı** (commit `85a1979`, bkz. BULGU-001 bölümü). Kalan takip önerisi: kasiyer rolü tespiti için manuel rol atama arayüzü veya filtre desteği.
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
| Doğrulanmış bulgu sayısı | 1 (BULGU-001) — **düzeltildi** (commit `85a1979`, bkz. aşağıdaki Kapanış Doğrulaması Güncellemesi) |
| Teorik/doğrulanmamış bulgu sayısı | 2 (BULGU-002, BULGU-003) — açık, değişmedi |
| Yanlış pozitif sayısı (bu turda) | 1 (ilk bakışta, doğrulamayla düzeltildi — üstte açıklandı) |
| Mevcut 780/780 test sonucu korundu mu? (bu oturumun kaydı) | **Evet** — `php tests/test-suite.php` → 780/780, 5285 doğrulama, 0 hata (BULGU-001 düzeltmesi ve admin bildirimi SONRASI güncel sayılar için aşağıdaki güncellemeye bakın: 792/792) |
| Git working tree temiz mi? (bu oturumun kaydı) | **Evet** — `git status --short` çıktısı boş (bu rapor dosyaları hariç, onlar da `security-audit/` altında yeni eklenen dosyalar, üretim/test kodu değil) |
| Kodda herhangi bir değişiklik yapıldı mı? (bu oturumun kaydı) | **Hayır** — `modules/`, `includes/`, `tests/` altında sıfır değişiklik (bu, yalnızca dinamik doğrulama oturumu için geçerlidir; BULGU-001'in düzeltmesi AYRI bir sonraki oturumda, ayrıca onay alınarak uygulanmıştır — bkz. aşağıdaki güncelleme) |

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

---

## Kapanış Doğrulaması Güncellemesi — BULGU-001 Düzeltmesi (sonraki oturum)

Bu bölüm, yukarıdaki dinamik testin BULGU-001'i "doğrulanmış açık" olarak kaydettiği tarihten SONRA, ayrı bir onaylı oturumda yapılan düzeltmeyi belgeler. Yukarıdaki orijinal bulgu kaydı (test adımları, teknik kanıt, etki) DEĞİŞTİRİLMEMİŞTİR — yalnızca durum ve kapanış bilgisi eklenmiştir.

- **Kod değişikliği:** commit `85a1979` — `qrm_manage_rewards` özel capability'si ve sürüm kontrollü migration (`modules/yorum-feedback/includes/rewards/capabilities.php`).
- **Dinamik doğrulama:** İzole Docker/WordPress ortamında BAŞARILI — Contributor/Author/Editor/Subscriber reddedildi, Administrator erişimi korundu, yetkili kasiyer rolü uçtan uca doğrulandı (ayrıntı için yukarıdaki BULGU-001 #### Kod değişikliği yapıldı mı? bölümüne ve `AUTHORIZATION-TESTS.md`'ye bakın).
- **Testler:** 792/792 (mevcut 786 test + admin bildirimi için eklenen 6 yeni test), 0 hata.
- **Kalan risk:** Kasiyer rolünün isim/slug sezgisel eşleşmesiyle (`kasiyer`/`cashier`) otomatik tespiti; farklı isimlendirilmiş özel roller otomatik yakalanmaz (yöneticiye `admin_notices` ile bildirilir, otomatik düzeltilmez).
- **Takip önerisi:** Manuel rol ataması (rol yönetimi eklentisi veya `WP_Role::add_cap()`) veya eklentiye filtre desteği (`apply_filters`) eklenerek hangi rollerin `qrm_manage_rewards` alacağının özelleştirilebilir kılınması.

# Yetkilendirme ve Privilege Escalation Testleri

Ortam: izole Docker (WP 6.9 / PHP 8.1.34 / MariaDB 10.11.19), `localhost:8080`.
Sentetik kullanıcılar: `test_administrator`, `test_editor`, `test_author`, `test_contributor`, `test_subscriber` (hepsi gerçek HTTP login ile oturum açıldı, cookie jar'lar ayrı tutuldu) + giriş yapmamış ziyaretçi (cookie'siz istekler).

## Yetki Matrisi

| # | İşlem / Endpoint | Ziyaretçi | Subscriber | Contributor | Author | Editor | Administrator | Beklenen | Gerçek |
|---|---|---|---|---|---|---|---|---|---|
| 1 | `qrm_reward_admin_lookup` (müşteri e-postası sorgula) | Erişemez (nopriv yok) | ❌ (doğru red) | **✅ erişebildi** | **✅ erişebildi** | ✅ | ✅ | Sadece admin/kasiyer rolü | **Contributor/Author'a da açık** (BULGU-01) |
| 2 | `qrm_reward_cashier_mark_used` (kodu kullanıldı işaretle) | Erişemez | ❌ | **✅ (DB'de gerçekten değişti)** | ✅ | ✅ | ✅ | Sadece admin/kasiyer rolü | **Contributor gerçek veriyi değiştirebildi** (BULGU-01) |
| 3 | `qrmgm_upload_image` (galeri dosya yükleme) | Erişemez | ❌ (nonce+cap red, 403) | ❌ | ❌ | ❌ | ✅ | Sadece `manage_options` | Doğru |
| 4 | `qrms-yf-odul&view=kasa` admin sayfası | Erişemez | ❌ 403 | ✅ 200 | ✅ 200 | ✅ | ✅ | — | `edit_posts` şartı Contributor'ı da kapsıyor |
| 5 | `qrms-yf-odul` (varsayılan/ayarlar görünümü) | Erişemez | ❌ | ❌ (manage_options ister) | ❌ | ❌ | ✅ | Sadece admin | Doğru — bu görünüm ayrı ve doğru korunmuş |
| 6 | `qmo_search_menu_items` (ürün arama, admin-kombin-meta) | Erişemez | (`edit_posts` yok → red) | ✅ (salt okunur, yayınlanmış ürün) | ✅ | ✅ | ✅ | Düşük risk, salt okunur genel menü verisi | Kabul edilebilir |
| 7 | `rma_toggle_status` / `rma_toggle_tukendi` (ürün durumu) | Erişemez | — | `edit_post($id)` — post sahipliği kontrolü var | aynı | aynı | ✅ | Post-özel yetki | Doğru |
| 8 | `garson_cagir` / `hesap_iste` / `qrservis_call` | ✅ (nopriv, tasarım gereği — masa müşterisi) | — | — | — | — | — | HMAC masa oturumu + nonce zorunlu | Doğru (oturumsuz/nonce'suz 403) |
| 9 | `rma_load_items` / `rma_get_product_details` | ✅ (nopriv, genel menü verisi) | — | — | — | — | — | Herkese açık, IP hız sınırlı | Doğru (bilinçli tasarım) |
| 10 | REST `/qrservis/v1/order` | ✅ (nopriv, masa müşterisi) | — | — | — | — | — | Nonce (`wp_rest`) + HMAC masa oturumu | Doğru — ikisi de olmadan 403 |
| 11 | REST `/qrservis/v1/analytics` | Erişemez içerikte | — | — | — | — | Yalnızca gerçek Firebase ID token + admin/müdür rolü | Firestore yapılandırılmadığı için (görev kuralı) tam token sahtekarlığı denenemedi; kod incelemesiyle JWT imza doğrulaması onaylandı | Statik doğrulandı |
| 12 | REST `/qrservis/v1/create-user` | — | — | — | — | — | — | Sadece `qmo_ana_site=true` iken route kayıtlı | Doğru — kapalıyken 404 |

## Sorulara Yanıtlar

1. **Author/Contributor yönetici AJAX işlemlerini çalıştırabiliyor mu?** Kısmen evet — reward lookup/mark-used (BULGU-01) dışında test edilen tüm diğer "yönetici" AJAX uçları (`qmo_chatbot_ajax_*`, `qrms_analitik_*`, `qrmgm_*`, `rma_save_category_order` vb.) `manage_options` veya post-özel `edit_post()` istiyor ve Contributor/Author'ı doğru şekilde reddediyor (statik kod + dinamik `-1`/403 testleriyle doğrulandı).
2. **`edit_posts` yanlış yerde mi kullanılmış?** Evet, `rewards.php`'deki iki uçta (BULGU-01). Başka hiçbir yerde `edit_posts`'un aşırı geniş kullanıldığına rastlanmadı (`admin-kombin-meta.php`'deki kullanım salt-okunur ve düşük risk).
3. **Daha dar capability gerekir mi?** BULGU-01 için evet — özel bir capability (örn. `qrm_manage_rewards`) veya en azından `manage_options` önerilir.
4. **Subscriber herhangi bir yönetim endpoint'ine erişebiliyor mu?** Hayır — test edilen HİÇBİR yönetim ucunda Subscriber erişimi başarılı olmadı (hepsi 403 veya `success:false`).
5. **nopriv action'lar kötüye kullanılabiliyor mu?** Hayır — tüm nopriv uçlar (garson_cagir, hesap_iste, qrservis_call, gemini_chat_req, gemini_bot_siparis, qmo_sepet_olay, qrm_load_reviews, qrm_submit_custom_form, qrm_submit_review, qrm_reward_request_code, qrm_reward_log_event, rma_load_items, rma_get_product_details) HMAC oturumu, nonce, rate-limit veya spam-guard'lardan en az biriyle (çoğu birden fazlasıyla) korunuyor; dinamik testlerde hiçbiri korumasız bulunmadı.
6/7. **Restoranlar arası veri erişimi (çapraz kiracı):** Bu eklenti mimarisi TEK SİTE = TEK RESTORAN modelindedir (çoklu kiracılık/multi-tenant yapı yok); "başka bir restoranın" verisi kavramı yalnızca REST `/analytics` ucunda (çoklu şube senaryosu, Firestore `branchId` ile) geçerlidir ve orada şube izolasyonu kod seviyesinde doğrulanmıştır (`$u['branchId'] !== QMO_Firestore::branch_id()` kontrolü). Aynı sitedeki masa/ürün/yorum verisi zaten tek işletmeye ait olduğundan "başka restoran" IDOR senaryosu bu mimaride teknik olarak uygulanamaz; bunun yerine rol-bazlı IDOR (BULGU-01) test edildi.
8. **REST'te ID değiştirerek IDOR:** `/order` ucunda `itemId` değiştirilerek denendi (bkz. INPUT-VALIDATION-TESTS.md) — sunucu her zaman gerçek, yayınlanmış `rma_menu_item` kaydına göre adı/ID'yi yeniden çözüyor, istemci verisine güvenmiyor.
9. **Sadece nonce yeterli mi, sahiplik de kontrol ediliyor mu?** Test edilen tüm post-değiştiren uçlarda (`rma_toggle_status`, `qmo_banner_sira_kaydet`, qr-galeri uçları) nonce'a EK olarak post-type doğrulaması ve/veya `current_user_can('edit_post', $id)` sahiplik kontrolü de var; sadece nonce'a güvenen bir uç bulunmadı.

## BULGU-01 Sınıflandırması

Ayrıntılı kanıt: `EVIDENCE/BULGU-01-reward-idor.txt`.

- **Teknik olarak:** Doğrulanmış güvenlik açığı (Contributor/Author, `manage_options` gerektiren bir işlevi `edit_posts` ile kullanabiliyor; gerçek PII ifşası + gerçek veri değişikliği dinamik olarak kanıtlandı).
- **Ürün niyeti açısından:** Geliştiricinin commit mesajında (`36aa5d6`) "kasiyer" (yarı-yetkili, tam admin olmayan) hesapların bu ekrana erişebilmesi bilinçli bir tasarım olarak belirtilmiş.
- **Çelişki:** WordPress'in yerleşik rol modelinde "kasiyer" diye bir rol yok; `edit_posts` capability'sini taşıyan HER rol (Contributor dahil — ki bu rol varsayılan olarak yayın yapamaz, medya yükleyemez) bu işlevi kullanabiliyor. Yani niyet ("sınırlı yetkili kasiyer hesabı") ile gerçek teknik sınır (WordPress'in en temel "içerik taslağı oluşturabilir" rolü) birbiriyle uyuşmuyor.
- **Sonuç:** Bu, "yanlış capability tasarımı" kategorisine girer — kötü niyetli bir "bug" değil ama gerçek bir yetki sınırı hatası; restoran sahibinin bu riski bilmesi ve bilinçli olarak kabul etmesi ya da düzeltilmesini istemesi gerekir.

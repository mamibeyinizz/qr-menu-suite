<?php
/*
Plugin Name: QR Menü Gelişmiş Müşteri Yorumları & Değerlendirme
Description: QR menü sistemleri için çoklu kriter (yemek, hizmet, temizlik vb.) puanlama sistemli, Google yorum yönlendirmeli (review gating), AJAX destekli, gelişmiş analitik barındıran premium müşteri yorum eklentisi.
Version: 4.2.1
Author: QR MENÜ

== Changelog ==

4.2.1
 - DÜZELTME (kök neden): "Formlar > Yeni Form Oluştur" ekranı, yönetici olarak giriş
   yapılmış olsa bile "bu sayfaya erişmenize izin verilmiyor" (HTTP 403) veriyordu.
   Yetki parametresi ve sayfa kaydı doğruydu; hata, sayfanın add_submenu_page() ile
   kaydedilip hemen ardından remove_submenu_page() ile menüden gizlenmesindendi.
   WordPress admin.php?page=X isteklerinde üst menüyü $submenu dizisini tarayarak
   bulduğu için (get_admin_page_parent), satır kaldırılınca parent boş kalıyor, hook
   adı "qr-yorumlar_page_qrm-form-edit" yerine "admin_page_qrm-form-edit" hesaplanıyor
   ve $_registered_pages'te karşılığı bulunamıyordu. Düzenleyici artık gizli bir sayfa
   değil, kayıtlı "Formlar" sayfasının bir görünümü (?page=qrm-forms&view=edit).
 - KALDIRILDI: Yapay zekâ destekli yorum analizi özelliği (ayar alanları, İçgörüler
   sayfasındaki analiz bloğu, AJAX ucu ve dış API çağrıları) tamamen çıkarıldı.
   Sürüm yükseltmesinde saklanmış API anahtarı ve analiz önbelleği veritabanından silinir.
 - YENİ: Ardışık gönderim kısıtı (cooldown). "Aynı kişi kaç dakikada bir form
   gönderebilir" ayarı panelden yönetilir (varsayılan 10 dk, 0 = kapalı) ve TÜM
   formlara uygulanır: yorum, iletişim ve özel formlar. Kimliklendirme IP + gönderilen
   e-posta/telefon ile yapılır; yetkili kullanıcılar (edit_posts) muaftır; süre
   dolmadan denenirse kalan dakika mesajda belirtilir.
 - SADELEŞTİRME: Admin menüsü 8 alt sayfadan 5'e indi. Müşteri Bilgileri Formu ->
   Yorum Formu Ayarları sekmesi, Detaylı İçgörüler -> Tüm Yorumlar sekmesi,
   Form Gönderileri -> Formlar sekmesi. Hiçbir işlev kaybolmadı; eski adreslerden
   gelenler yeni sekmelere yönlendirilir.
 - GÜVENLİK: Yorum Formu Ayarları sayfasına nonce (check_admin_referer) ve
   manage_options kontrolü eklendi; Tüm Yorumlar sayfasına yetki kontrolü eklendi.

4.2.0
 - YENİ MODÜL: Dinamik Form Oluşturucu. Admin panelinden sınırsız sayıda özel form
   (şikayet, geri bildirim, rezervasyon, anket vb.) oluşturulabilir. Tek genel
   kısa kod: [qr_menu_form key="sikayet-formu"].
     * 10 alan tipi: metin, uzun metin, e-posta, telefon, sayı, açılır liste,
       tek seçim, çoklu seçim, yıldız puanlama, tarih. Yeni tip eklemek tek bir
       kayıt defteri + iki switch/case dokunuşu.
     * Üç panelli düzenleyici: alan paleti, sürükle-bırak sıralanabilen canlı
       önizleme, form ayarları; üstte sticky Kaydet/Önizle. Dış kütüphane yok.
     * Her formun gönderimleri kendi listesinde (yorum verileriyle karışmaz);
       tek sayfada form seçici sekmelerle gezilir, menüde okunmamış rozeti gösterilir.
     * AJAX gönderim: nonce + honeypot + zaman tabanlı spam guard + akış koruması
       + sunucu tarafı alan doğrulaması; isteğe bağlı admin bildirim e-postası.
     Yeni dosyalar: includes/forms/{db,functions,render}.php,
                    includes/admin/{forms-list,custom-form-builder,form-submissions}.php,
                    includes/frontend/shortcode-form.php, includes/ajax/submit-custom-form.php
 - REFACTOR: Giriş alanı/buton/uyarı/captcha/telefon stilleri qrm_pro_input_css()
   ortak fonksiyonuna çıkarıldı; yorum formu ve özel formlar aynı kaynağı kullanıyor
   (v4.1.1'deki input[type="tel"] hatası yeni formlarda tekrar edemez). Yorum
   formunun CSS çıktısı birebir korundu.
 - TEMİZLİK: Debug kalıntısı taraması yapıldı (üretimde çalışan çıktı bulunmadı);
   yalnızca WP_DEBUG + WP_DEBUG_LOG açıkken yazan qrm_pro_debug_log() eklendi.
 - DÜZELTME (kök neden): Ödül popup'ı, tüm ayarlar doğru olmasına rağmen açılmıyordu.
   Popup DOM'u shortcode çıktısına basılıyor ve "sayfa başına bir kez" kuralı istek
   düzeyinde bir static ile korunuyordu; the_content filtresi birden fazla kez çalışan
   sitelerde (SEO/alıntı/sayfa kurucu) ilk, atılan geçiş bayrağı tüketiyor ve popup
   sayfaya hiç ulaşmıyordu. Popup artık wp_footer'da basılıyor (yedek satır içi yol var).
 - DAYANIKLILIK: popup DOM'u bulunamazsa ön yüz satır içi Google CTA'sına düşüyor.
 - YENİ: "Google & Ödül Sistemi" tek yönetim sayfası — Hızlı Kurulum checklist'i,
   tek form/tek kaydet ile üç sekme, Google linki normalleştirme (Place ID / Maps /
   writereview), fabrika şablonu ve kayıt oluşturmayan "Test Et" simülasyonu.

4.1.1
 - DÜZELTME: "Masa No" alanı küçük/orantısız görünüyordu. Alan type="tel" ile
   render edilirken form stilleri yalnızca input[type="text"] seçiyordu; temel,
   :focus ve otomatik renklendirme seçicileri input[type="tel"] kapsayacak
   şekilde genişletildi (includes/frontend/form-render.php).
 - İYİLEŞTİRME: Ödül modülü açık ama Google Yorum Linki boş / yönlendirme kapalı
   olduğunda popup sessizce devre dışı kalıyordu. qrm_reward_is_active() mantığı
   değişmedi; yalnızca admin tarafında görünürlük artırıldı: Ayarlar & Puanlama
   sayfasında çapraz uyarı, admin menüsünde uyarı rozeti ve Ödül Sistemi
   sayfasındaki uyarının kapalı yönlendirmeyi de kapsaması.

4.1.0
 - Tek dosyalık yapı standart eklenti klasör yapısına ayrıldı. Hiçbir fonksiyon,
   hook, shortcode adı veya veritabanı şeması değişmedi; kodlar yalnızca
   aşağıdaki dosyalara taşındı:
     includes/settings.php            -> qrm_pro_default_settings(), qrm_pro_get_settings()
     includes/security.php            -> spam guard, ts token, captcha, telefon normalize
     includes/install.php             -> qrm_pro_install() + dbDelta tabloları
     includes/admin/menu.php          -> admin_menu, admin_enqueue_scripts
     includes/admin/dashboard.php     -> Tüm Yorumlar sayfası
     includes/admin/form-builder.php  -> Form Alanları sayfası
     includes/admin/settings-page.php -> Ayarlar & Puanlama sayfası
     includes/admin/insights.php      -> İçgörüler sayfası
     includes/admin/contact.php       -> İletişim Formu sayfası
     includes/frontend/form-render.php    -> stil bloğu, tema yardımcıları, form render
     includes/frontend/form-script.php    -> ön yüz JS çıktısı
     includes/frontend/shortcode-reviews.php / shortcode-contact.php -> shortcode'lar
     includes/ajax/submit-review.php  -> yorum gönderimi (ortak işleyici + AJAX)
 - YENİ MODÜL: Google Review Ödül / İndirim Kodu Sistemi
     * Eşik üstü puan veren müşteriye, tamamen admin panelden özelleştirilebilen
       minimal bir popup gösterilir (metin, renk, tema, border-radius, süreler).
     * Popup state machine: başlangıç -> (Google'a git + 30 sn bekleme) veya
       (X sn sonra otomatik) -> e-posta adımı -> kod / "zaten alınmış" / hata.
     * 1 e-posta = 1 indirim kodu (DB seviyesinde UNIQUE KEY ile de garanti).
     * İndirim şablonları (ad + yüzde + aktif/varsayılan) admin panelden yönetilir.
     * Kod yönetimi: listeleme, filtreleme, arama, kullanıldı/iptal/sil, manuel kod
       üretimi ve AJAX "kod sorgula" kutusu.
     * Kodlar e-posta ile gönderilir (wp_mail) ve ekranda kopyalanabilir gösterilir.
     * qrm_reward_enabled açıkken eski inline Google CTA bastırılır, kapalıyken
       eski davranış birebir korunur.
     Yeni dosyalar: includes/rewards/{db,functions,popup-render}.php,
                    includes/admin/{rewards,reward-codes}.php,
                    includes/ajax/rewards.php

4.0.0
 - İlk yayınlanan tek dosyalık sürüm.
*/

if (!defined('ABSPATH')) {
    exit;
}

define('QRM_PRO_VERSION', '4.2.1');
define('QRM_PRO_FILE', __FILE__);
define('QRM_PRO_PATH', plugin_dir_path(__FILE__));
define('QRM_PRO_URL', plugin_dir_url(__FILE__));

// Bağımlılık sırası önemli: önce ayarlar/güvenlik, sonra kurulum, sonra geri kalanı.
require_once QRM_PRO_PATH . 'includes/settings.php';
require_once QRM_PRO_PATH . 'includes/security.php';

// Ödül modülü (kurulum fonksiyonu install.php içinden çağrıldığı için önce yüklenir)
require_once QRM_PRO_PATH . 'includes/rewards/db.php';
require_once QRM_PRO_PATH . 'includes/rewards/functions.php';
require_once QRM_PRO_PATH . 'includes/rewards/popup-render.php';

// Özel form builder modülü (v4.2.0) — kurulum fonksiyonu install.php içinden
// çağrıldığı için ondan önce yüklenir. Yorum/İletişim formlarından bağımsızdır.
require_once QRM_PRO_PATH . 'includes/forms/db.php';
require_once QRM_PRO_PATH . 'includes/forms/functions.php';

require_once QRM_PRO_PATH . 'includes/install.php';
require_once QRM_PRO_PATH . 'includes/ai-insights.php';

// Admin
require_once QRM_PRO_PATH . 'includes/admin/menu.php';
require_once QRM_PRO_PATH . 'includes/admin/hub.php';
require_once QRM_PRO_PATH . 'includes/admin/dashboard.php';
require_once QRM_PRO_PATH . 'includes/admin/form-builder.php';
require_once QRM_PRO_PATH . 'includes/admin/settings-page.php';
require_once QRM_PRO_PATH . 'includes/admin/insights.php';
require_once QRM_PRO_PATH . 'includes/admin/contact.php';
require_once QRM_PRO_PATH . 'includes/admin/rewards.php';
require_once QRM_PRO_PATH . 'includes/admin/reward-codes.php';
require_once QRM_PRO_PATH . 'includes/admin/forms-list.php';
require_once QRM_PRO_PATH . 'includes/admin/custom-form-builder.php';
require_once QRM_PRO_PATH . 'includes/admin/form-submissions.php';

// Ön yüz
require_once QRM_PRO_PATH . 'includes/frontend/form-render.php';
require_once QRM_PRO_PATH . 'includes/frontend/form-script.php';
// Yorum listesinin sayfalaması ve kart çıktısı; kısa kod da AJAX ucu da
// buradan geçer, bu yüzden ikisinden de önce yüklenir.
require_once QRM_PRO_PATH . 'includes/frontend/reviews-list.php';
require_once QRM_PRO_PATH . 'includes/frontend/shortcode-reviews.php';
require_once QRM_PRO_PATH . 'includes/frontend/shortcode-contact.php';
require_once QRM_PRO_PATH . 'includes/forms/render.php';
require_once QRM_PRO_PATH . 'includes/frontend/shortcode-form.php';

// AJAX
require_once QRM_PRO_PATH . 'includes/ajax/submit-review.php';
require_once QRM_PRO_PATH . 'includes/ajax/load-reviews.php';
require_once QRM_PRO_PATH . 'includes/ajax/rewards.php';
require_once QRM_PRO_PATH . 'includes/ajax/submit-custom-form.php';

register_activation_hook(__FILE__, 'qrm_pro_install');

// Eklenti dosyaları güncellendiğinde (reaktivasyon olmadan) yeni tabloların da
// oluşmasını sağlar. Sürüm aynıysa hiçbir sorgu çalışmaz.
//
// v4.2.0: form builder tabloları aynı sürüm numarası altında sonradan eklendiği
// için ayrı bir modül sürümü de kontrol edilir; böylece 4.2.0'ı zaten kurmuş
// sitelerde yeni tablolar reaktivasyon gerektirmeden oluşur.
add_action('plugins_loaded', 'qrm_pro_maybe_upgrade');
function qrm_pro_maybe_upgrade() {
    if (get_option('qrm_db_version') === QRM_PRO_VERSION
        && get_option('qrm_cf_db_version') === QRM_PRO_VERSION) {
        return;
    }
    qrm_pro_install();
    qrm_pro_cleanup_removed_settings();

    // Tablolar bu yolda güncel şemayla (indeksler dahil) kurulmuş OLMALI —
    // ama yalnızca gerçekten kurulduysa damgalanır. Aksi hâlde ALTER yetkisi
    // olmayan bir kurulumda şema "güncel" görünür, admin_init'teki denetim
    // devreye girmez ve site kalıcı olarak indekssiz kalırdı.
    if (qrm_pro_schema_indexes_ok()) {
        update_option(QRM_PRO_SCHEMA_OPTION, QRM_PRO_SCHEMA_VERSION, false);
    }
}

/* -------------------------------------------------------------------------
 * ŞEMA (İNDEKS) GÜNCELLEMESİ
 *
 * v4.2.3'te dört tabloya eksik indeksler eklendi. Eklenti sürümü değişmediği
 * için yukarıdaki qrm_pro_maybe_upgrade() bunu tek başına yakalamaz; ayrı bir
 * şema sürümü tutulur.
 *
 * ZAMANLAMA — güncelleme bilinçli olarak SADECE yönetim isteklerinde çalışır.
 * ALTER TABLE, büyük bir yorum tablosunda birkaç saniye sürebilir ve o süre
 * boyunca tabloyu meşgul eder. plugins_loaded'a bağlansaydı bu iş, menüyü açan
 * rastgele bir MÜŞTERİNİN isteğinde yapılırdı; admin_init'e bağlıyken yöneticinin
 * kendi sayfa yüklemesinde olur ve sonucu ona bir uyarı olarak bildirilir.
 * ---------------------------------------------------------------------- */

/** Şema (indeks) sürümü. İndeks tanımları değiştiğinde artırılır. */
define('QRM_PRO_SCHEMA_VERSION', '2');

/** Şema sürümünün saklandığı option. */
define('QRM_PRO_SCHEMA_OPTION', 'qrm_pro_schema_version');

/** Güncelleme sonucunun yöneticiye bir kez gösterilmesi için kullanılan transient. */
define('QRM_PRO_SCHEMA_NOTICE', 'qrm_pro_schema_notice');

/**
 * Şema güncellemesi bekliyor mu?
 *
 * Maliyeti tek bir autoload option okumasıdır; sorgu açmaz.
 *
 * @return bool
 */
function qrm_pro_schema_pending() {
    return get_option(QRM_PRO_SCHEMA_OPTION) !== QRM_PRO_SCHEMA_VERSION;
}

/**
 * Eksik indeksleri oluşturur (dbDelta; mevcut satırlara dokunmaz).
 *
 * @return bool Şema güncel hâle geldiyse true.
 */
function qrm_pro_schema_upgrade() {
    qrm_pro_install();

    // dbDelta hatayı istisna olarak fırlatmaz; indeksin gerçekten oluştuğu
    // doğrulanır ki başarısız bir ALTER "tamam" diye işaretlenmesin.
    if (!qrm_pro_schema_indexes_ok()) {
        return false;
    }

    update_option(QRM_PRO_SCHEMA_OPTION, QRM_PRO_SCHEMA_VERSION, false);

    return true;
}

/**
 * Kritik indeksler tabloda gerçekten var mı?
 *
 * Yalnızca yorum tablosu denetlenir: en büyük tablo odur ve sürüm damgası
 * yalnızca onun indeksi kurulduğunda anlamlıdır.
 *
 * @return bool
 */
function qrm_pro_schema_indexes_ok() {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        // Tablo yoksa yapacak indeks de yok; şema "güncel" sayılır.
        return true;
    }

    $table = $wpdb->prefix . 'qrm_reviews';

    $suppress = $wpdb->suppress_errors(true);
    $rows     = $wpdb->get_col("SHOW INDEX FROM {$table}", 2); // Key_name sütunu
    $wpdb->suppress_errors($suppress);

    return is_array($rows) && in_array('idx_status_created', $rows, true);
}

add_action('admin_init', 'qrm_pro_schema_maybe_upgrade', 5);
/**
 * Yönetim isteğinde şemayı güncelle ve sonucu bir kez bildirilmek üzere sakla.
 *
 * @return void
 */
function qrm_pro_schema_maybe_upgrade() {
    if (!qrm_pro_schema_pending()) {
        return;
    }

    // Yetkisiz bir kullanıcının admin isteği ALTER TABLE tetiklemesin.
    if (!current_user_can('manage_options')) {
        return;
    }

    set_transient(
        QRM_PRO_SCHEMA_NOTICE,
        qrm_pro_schema_upgrade() ? 'ok' : 'hata',
        5 * MINUTE_IN_SECONDS
    );
}

/**
 * v4.2.1: Kaldırılan yapay zekâ analiz modülünün geride bıraktığı verileri siler.
 * Saklanmış API anahtarının veritabanında öylece kalmaması için sürüm yükseltmede
 * bir kez çalışır. (Bilerek eklenti kök dosyasında tutuluyor — includes/ altındaki
 * modül kodunda kaldırılan özelliğe dair hiçbir referans bırakmamak için.)
 */
function qrm_pro_cleanup_removed_settings() {
    $removed_keys = ['gemini_api_key', 'gemini_model'];

    $stored = get_option('qrm_settings');
    if (is_array($stored)) {
        $changed = false;
        foreach ($removed_keys as $key) {
            if (array_key_exists($key, $stored)) {
                unset($stored[$key]);
                $changed = true;
            }
        }
        if ($changed) {
            update_option('qrm_settings', $stored);
        }
    }

    delete_option('qrm_ai_analysis_cache');
}

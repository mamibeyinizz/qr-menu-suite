<?php
if (!defined('ABSPATH')) exit;

/**
 * Yorum/form sabit metnini QR Çeviri tablosundan geçirir (item_type=review).
 *
 * Çeviri yoksa veya modül kapalıysa girdi (Türkçe) döner. AJAX uçlarında
 * dil rma_get_current_lang() — $_REQUEST['lang'] sonra rma_lang cookie.
 * admin-ajax cache dışı; fetch credentials: 'same-origin'.
 *
 * Bu modül _qmo-ortak yüklemez; köprü burada durur.
 *
 * @param string $metin Türkçe kaynak (genelde __( '…', 'qrms' ) çıktısı).
 * @return string
 */
if (!function_exists('qrm_ceviri_review')) {
    function qrm_ceviri_review($metin) {
        $metin = (string) $metin;
        if (function_exists('rma_ceviri_modul')) {
            return rma_ceviri_modul('review', $metin);
        }
        return $metin;
    }
}

/**
 * form-script.php inline JS için localize yükü.
 *
 * Script wp_enqueue ile değil shortcode çıktısına gömülür; footer
 * wp_localize_script yarışırdı. Aynı dizi burada JSON olarak basılır.
 *
 * @return array<string,string>
 */
if (!function_exists('qrm_ceviri_review_js_metinleri')) {
    function qrm_ceviri_review_js_metinleri() {
        return [
            'genericError' => qrm_ceviri_review(__('Bir şeyler ters gitti, lütfen tekrar deneyin.', 'qrms')),
            'rateRequired' => qrm_ceviri_review(__('Devam etmek için lütfen tüm kriterleri puanlayın.', 'qrms')),
            'thanks'       => qrm_ceviri_review(__('Değerlendirmeniz için teşekkürler!', 'qrms')),
            'loading'      => qrm_ceviri_review(__('Yükleniyor…', 'qrms')),
        ];
    }
}

// 0. VARSAYILAN AYARLAR VE GÜVENLİ GETTER
// Not: get_option() doğrudan çağrılmaz. Böylece eklenti güncellendiğinde
// (reaktivasyon olmadan) yeni ayar anahtarları eski kurulumlarda da eksiksiz gelir.
//
// P1 / adım 7-2: Aşağıdaki varsayılanlar yönetici option'ıdır (VERİ, sabit
// kod değil): form_title, kriter adları, Google CTA, ödül popup metinleri.
// CSV'ye option satırı olarak çıkmaları ayrı mekanizma ister — şimdi çevrilmez.
function qrm_pro_default_settings() {
    return [
        'form_title' => 'Deneyiminizi Paylaşın',
        'btn_color' => '#10b981',
        'btn_text_color' => '#ffffff',
        'theme_style' => 'light',
        'auto_approve_rating' => 0,
        'reviews_per_page' => 3,
        'show_overall_stats' => 1,
        'crit_1_name' => 'Yemek Lezzeti', 'crit_1_active' => 1,
        'crit_2_name' => 'Hizmet Hızı', 'crit_2_active' => 1,
        'crit_3_name' => 'Temizlik', 'crit_3_active' => 1,
        'crit_4_name' => 'Atmosfer', 'crit_4_active' => 1,
        'crit_5_name' => 'Fiyat / Performans', 'crit_5_active' => 1,
        // Google Yorum Yönlendirme (Review Gating)
        'google_review_enabled' => 1,
        'google_review_url' => '',
        'google_review_threshold' => 3.5,
        'google_review_headline' => 'Bizi Sevdiğinize Sevindik! 🎉',
        'google_review_subtext' => 'Deneyiminizi 30 saniyenizi ayırıp Google üzerinden paylaşır mısınız? Bize çok yardımcı olur.',
        'google_review_btn_text' => "Google'da Değerlendir",
        'google_review_skip_text' => 'Hayır, teşekkürler',
        // Otomatik Form Alanı Renklendirme (Premium)
        'auto_color_enabled' => 0,
        'auto_color_1' => '#6366f1',
        'auto_color_2' => '#ec4899',
        'auto_color_3' => '#f59e0b',
        // İletişim Formu (ayrı shortcode, yorum listesi/ortalama olmadan)
        'contact_form_title' => 'Bize Ulaşın',
        // Spam koruması: aynı kişi kaç dakikada bir form gönderebilir (0 = kapalı).
        // Tüm formlar için geçerlidir; yetkili kullanıcılar (edit_posts) muaftır.
        'qrm_spam_cooldown_minutes' => 10,

        // --- Google Review Ödül Sistemi (v4.1.0) ---
        // Not: google_review_url / google_review_threshold gibi mevcut ayarlar
        // tekrar üretilmez, ödül modülü doğrudan onları okur.
        'qrm_reward_enabled' => 0,
        'qrm_reward_popup_title' => 'Bizi Sevdiğinize Sevindik!',
        'qrm_reward_popup_text' => 'Bizi Google üzerinden puanlayın ve bir sonraki siparişinizde %10 indirim kazanın!',
        'qrm_reward_popup_button_text' => "Google'da Değerlendir ve Kazan",
        'qrm_reward_popup_claim_text' => 'Kodunu Al',
        'qrm_reward_popup_waiting_text' => 'Değerlendirmeniz kontrol ediliyor...',
        'qrm_reward_popup_skip_text' => 'Şimdi değil',
        'qrm_reward_popup_email_step_title' => 'Son bir adım!',
        'qrm_reward_popup_email_step_text' => 'İndirim kodunuzu gönderebilmemiz için e-posta adresinizi yazın.',
        'qrm_reward_popup_email_placeholder' => 'ornek@eposta.com',
        'qrm_reward_popup_email_button_text' => 'Kodumu Gönder',
        'qrm_reward_popup_success_text' => 'İşte indirim kodunuz:',
        'qrm_reward_popup_already_used_text' => 'Bu e-posta adresi daha önce bir indirim kodu almış. Her e-posta adresi yalnızca bir kez kod alabilir.',
        'qrm_reward_popup_error_text' => 'Kod oluşturulamadı, lütfen birazdan tekrar deneyin.',
        'qrm_reward_popup_copy_text' => 'Kopyala',
        'qrm_reward_popup_copied_text' => 'Kopyalandı ✓',
        // Görsel özelleştirme
        'qrm_reward_popup_theme' => 'light',          // light | dark | custom
        'qrm_reward_popup_bg_color' => '#ffffff',
        'qrm_reward_popup_text_color' => '#1e293b',
        'qrm_reward_popup_btn_color' => '#10b981',
        'qrm_reward_popup_btn_text_color' => '#ffffff',
        'qrm_reward_popup_border_radius' => 18,
        // Süreler
        'qrm_reward_wait_seconds' => 30,              // Google butonuna basınca beklenen süre
        'qrm_reward_auto_trigger_seconds' => 20,      // hiç tıklanmazsa otomatik e-posta adımı (15-30)
        // E-posta
        'qrm_reward_email_subject' => 'İndirim kodunuz hazır!',
        'qrm_reward_email_intro' => 'Değerlendirmeniz için teşekkür ederiz. Aşağıdaki indirim kodunu bir sonraki ziyaretinizde kullanabilirsiniz.',
    ];
}
function qrm_pro_get_settings() {
    return wp_parse_args(get_option('qrm_settings', []), qrm_pro_default_settings());
}

/**
 * Geliştirme günlüğü (v4.2.0). Üretimde tamamen sessizdir: yalnızca WP_DEBUG
 * ve WP_DEBUG_LOG açıkken yazar. Kod tabanında doğrudan error_log() çağrısı
 * yapılmaz, gerekiyorsa bu yardımcı kullanılır.
 */
function qrm_pro_debug_log($message) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) return;
    if (!is_scalar($message)) $message = wp_json_encode($message);
    error_log('[QRM] ' . $message);
}

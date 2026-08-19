<?php
if (!defined('ABSPATH')) exit;

// 7. ORTAK GÖNDERİM İŞLEYİCİSİ (Hem klasik POST hem AJAX tarafından kullanılır)
function qrm_pro_handle_review_submission($settings) {
    global $wpdb;
    $table_reviews = $wpdb->prefix . 'qrm_reviews';

    // Honeypot: botlar bu alanı doldurur, gerçek kullanıcı görmez (CSS ile gizli).
    if (!empty($_POST['qrm_website'])) {
        return ['success' => true, 'status' => 0, 'avg' => 0, 'show_google' => false, 'google_url' => '', 'show_reward' => false, 'review_id' => 0, 'message' => 'Değerlendirmeniz alındı, teşekkürler.'];
    }

    // Spam doğrulaması (zaman tuzağı + matematik captcha + akış koruması)
    $guard = qrm_pro_spam_guard();
    if ($guard !== true) {
        return ['success' => false, 'message' => $guard];
    }

    $total_score = 0;
    $score_count = 0;
    $ratings = [];

    for ($i = 1; $i <= 5; $i++) {
        $val = 0;
        if (!empty($settings['crit_'.$i.'_active']) && isset($_POST['rating_'.$i])) {
            $val = intval($_POST['rating_'.$i]);
            if ($val > 0) {
                $total_score += $val;
                $score_count++;
            }
        }
        $ratings['rating_'.$i] = $val;
    }

    $calc_avg = ($score_count > 0) ? ($total_score / $score_count) : 0;

    if ($calc_avg <= 0) {
        return ['success' => false, 'message' => 'Lütfen en az bir kriteri puanlayın.'];
    }

    // TR telefon doğrulama (girildiyse formatı kontrol et, normalize edilerek saklanır)
    $phone_norm = '';
    if (isset($_POST['customer_phone']) && trim($_POST['customer_phone']) !== '') {
        $phone_norm = qrm_pro_normalize_tr_phone($_POST['customer_phone']);
        if ($phone_norm === false) {
            return ['success' => false, 'message' => 'Geçerli bir Türkiye cep numarası girin. Örn: 0 (5XX) XXX XX XX'];
        }
    }

    // v4.2.1: Ardışık gönderim kısıtı. Telefon doğrulandıktan sonra kontrol edilir ki
    // kimlik anahtarı normalize edilmiş numarayla üretilebilsin. Yetkili kullanıcılar muaf.
    // Bu yol hem AJAX hem de JS kapalı klasik POST akışı tarafından kullanılır.
    $cooldown = qrm_pro_cooldown_guard(['phone' => $phone_norm], $settings);
    if ($cooldown !== true) {
        return ['success' => false, 'message' => $cooldown];
    }

    $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';

    $status = 0;
    if ($settings['auto_approve_rating'] > 0 && $calc_avg >= $settings['auto_approve_rating']) {
        $status = 1;
    }

    $insert_data = [
        'rating' => $calc_avg,
        'rating_1' => $ratings['rating_1'],
        'rating_2' => $ratings['rating_2'],
        'rating_3' => $ratings['rating_3'],
        'rating_4' => $ratings['rating_4'],
        'rating_5' => $ratings['rating_5'],
        'comment' => $comment,
        'customer_name' => isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '',
        'customer_phone' => $phone_norm,
        'table_no' => isset($_POST['table_no']) ? preg_replace('/[^0-9]/', '', $_POST['table_no']) : '',
        'is_anonymous' => isset($_POST['is_anonymous']) ? 1 : 0,
        'status' => $status,
        'form_source' => (isset($_POST['qrm_form_source']) && $_POST['qrm_form_source'] === 'contact') ? 'contact' : 'review',
    ];

    $wpdb->insert($table_reviews, $insert_data);
    $review_id = (int) $wpdb->insert_id;

    // Cooldown penceresi yalnızca gerçekten kayıt oluştuktan sonra başlatılır.
    qrm_pro_cooldown_mark(['phone' => $phone_norm], $settings);

    $threshold = floatval($settings['google_review_threshold']);
    $eligible = !empty($settings['google_review_enabled']) && !empty($settings['google_review_url']) && $calc_avg >= $threshold;

    // v4.1.0: Ödül modülü açıksa eşiği geçen müşteriye satır içi CTA yerine popup gösterilir.
    // Modül kapalıysa show_google eski davranışıyla birebir aynı kalır.
    $reward_on   = qrm_reward_is_active($settings);
    $show_reward = $eligible && $reward_on;
    $show_google = $eligible && !$reward_on;

    return [
        'success' => true,
        'status' => $status,
        'avg' => round($calc_avg, 1),
        'show_google' => $show_google,
        // Eşiği geçen her durumda gönderilir: popup DOM'u sayfaya ulaşmadıysa
        // ön yüz satır içi CTA'ya düşebilsin diye (JS tarafındaki yedek yol).
        'google_url' => $eligible ? esc_url_raw($settings['google_review_url']) : '',
        'show_reward' => $show_reward,
        'review_id' => $show_reward ? $review_id : 0,
        'message' => $status == 1 ? 'Değerlendirmeniz yayınlandı.' : 'Değerlendirmeniz alındı, onay sonrası yayınlanacaktır.',
    ];
}

// AJAX ile sayfa yenilenmeden gönderim (giriş yapmış / yapmamış tüm ziyaretçiler için)
add_action('wp_ajax_qrm_submit_review', 'qrm_pro_ajax_submit_review');
add_action('wp_ajax_nopriv_qrm_submit_review', 'qrm_pro_ajax_submit_review');
function qrm_pro_ajax_submit_review() {
    check_ajax_referer('qrm_submit_review', 'qrm_review_nonce');
    $settings = qrm_pro_get_settings();
    $result = qrm_pro_handle_review_submission($settings);
    wp_send_json($result);
}

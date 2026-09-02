<?php
if (!defined('ABSPATH')) exit;

// ÖDÜL MODÜLÜ: AJAX UÇLARI

/**
 * Ödül modülü için hafifletilmiş akış koruması.
 * Yorum formundaki qrm_pro_spam_guard() captcha/zaman tuzağı bekler; popup
 * adımında bunlar bulunmadığı için burada IP bazlı bir hız sınırı uygulanır.
 * Sorun yoksa true, varsa hata mesajı (string) döner.
 */
function qrm_reward_rate_limit($max = 8, $window = 600) {
    $key = 'qrm_rw_rl_' . md5(qrm_pro_client_ip());
    $cnt = (int) get_transient($key);
    if ($cnt >= $max) {
        return qrm_ceviri_review(__('Çok fazla deneme yapıldı, lütfen birkaç dakika sonra tekrar deneyin.', 'qrms'));
    }
    set_transient($key, $cnt + 1, $window);
    return true;
}

// Popup: e-posta karşılığı indirim kodu talebi
add_action('wp_ajax_qrm_reward_request_code', 'qrm_reward_ajax_request_code');
add_action('wp_ajax_nopriv_qrm_reward_request_code', 'qrm_reward_ajax_request_code');
function qrm_reward_ajax_request_code() {
    check_ajax_referer('qrm_reward_request_code', 'nonce');
    qrm_pro_bootstrap_lang();

    $settings = qrm_pro_get_settings();
    if (!qrm_reward_is_active($settings)) {
        wp_send_json(['success' => false, 'message' => qrm_ceviri_review(__('Ödül sistemi şu anda kapalı.', 'qrms'))]);
    }

    $guard = qrm_reward_rate_limit();
    if ($guard !== true) {
        wp_send_json(['success' => false, 'message' => $guard]);
    }

    // Kod talebi, GERÇEKTEN eşiği geçen bir yorum bırakılmış olmasına bağlıdır.
    // review_id istemciden gelir ve tek başına hiçbir şey ispatlamaz; yanında
    // yorum kaydedilirken sunucuda üretilmiş tek kullanımlık talep anahtarı
    // gelmek zorundadır. (Doğrulama e-posta kontrolünden ÖNCE yapılır:
    // yetkisiz bir istek, hangi e-postaların kod aldığını sızdırmasın.)
    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    $claim     = isset($_POST['claim']) ? sanitize_text_field(wp_unslash($_POST['claim'])) : '';

    $gecerli = qrm_reward_verify_claim($review_id, $claim, $settings);
    if (is_wp_error($gecerli)) {
        wp_send_json(['success' => false, 'message' => $gecerli->get_error_message()]);
    }

    $email = qrm_reward_normalize_email(isset($_POST['email']) ? wp_unslash($_POST['email']) : '');
    if ($email === '') {
        wp_send_json(['success' => false, 'message' => qrm_ceviri_review(__('Lütfen geçerli bir e-posta adresi girin.', 'qrms'))]);
    }

    // 1 e-posta = 1 kod
    if (qrm_reward_find_by_email($email)) {
        wp_send_json([
            'success'      => false,
            'already_used' => true,
            'message'      => qrm_ceviri_option('qrm_settings.qrm_reward_popup_already_used_text', $settings['qrm_reward_popup_already_used_text']),
        ]);
    }

    $result = qrm_reward_create_code([
        'email'            => $email,
        'template_id'      => '',   // otomatik: varsayılan/aktif şablon
        'is_manual'        => 0,
        'source_review_id' => $review_id,
        'ip_address'       => qrm_pro_client_ip(),
    ]);

    if (is_wp_error($result)) {
        if ($result->get_error_code() === 'qrm_reward_exists') {
            wp_send_json([
                'success'      => false,
                'already_used' => true,
                'message'      => qrm_ceviri_option('qrm_settings.qrm_reward_popup_already_used_text', $settings['qrm_reward_popup_already_used_text']),
            ]);
        }
        wp_send_json(['success' => false, 'message' => $result->get_error_message()]);
    }

    // Anahtar tek kullanımlıktır: kod üretildiği anda harcanır, aynı yorumla
    // ikinci bir kod istenemez.
    qrm_reward_consume_claim($review_id);

    qrm_reward_log_event($review_id, 'email_submitted');
    qrm_reward_log_event($review_id, 'code_issued');

    $emailed = qrm_reward_send_code_email($email, $result['code'], $result['discount_label']);

    wp_send_json([
        'success'        => true,
        'code'           => $result['code'],
        'discount_label' => $result['discount_label'],
        'emailed'        => (bool) $emailed,
    ]);
}

// Admin: "Test Et" — 5 yıldızlık bir gönderimi KAYIT OLUŞTURMADAN simüle eder.
add_action('wp_ajax_qrm_reward_admin_selftest', 'qrm_reward_ajax_admin_selftest');
function qrm_reward_ajax_admin_selftest() {
    if (!current_user_can('manage_options')) {
        wp_send_json(['show_reward' => false, 'message' => 'Bu işlem için yetkiniz yok.']);
    }
    check_ajax_referer('qrm_reward_admin', 'nonce');

    $settings = qrm_pro_get_settings();
    $sim      = qrm_reward_simulate_submission($settings, 5);
    $status   = qrm_reward_setup_status($settings);

    if ($sim['show_reward']) {
        $message = sprintf(
            '5 yıldızlık bir yorum gönderilseydi popup açılırdı. Eşik: %s, kullanılacak şablon: %s',
            number_format($sim['threshold'], 1),
            $sim['template'] !== '' ? $sim['template'] : 'tanımlı değil'
        );
    } elseif (!empty($status['missing'])) {
        $first = $status['missing'][0];
        $message = 'Popup açılmaz. Eksik adım: ' . $first['label'] . ' — ' . $first['hint'];
    } elseif (!$sim['eligible']) {
        $message = sprintf('Popup açılmaz: 5 yıldız, %s olan eşiğin altında kalıyor.', number_format($sim['threshold'], 1));
    } else {
        $message = 'Popup açılmaz: ödül modülü etkin değil.';
    }

    wp_send_json([
        'show_reward' => (bool) $sim['show_reward'],
        'show_google' => (bool) $sim['show_google'],
        'eligible'    => (bool) $sim['eligible'],
        'reward_on'   => (bool) $sim['reward_on'],
        'threshold'   => $sim['threshold'],
        'template'    => $sim['template'],
        'message'     => $message,
    ]);
}

// Admin / kasiyer: "Kod Sorgula" kutusu
add_action('wp_ajax_qrm_reward_admin_lookup', 'qrm_reward_ajax_admin_lookup');
function qrm_reward_ajax_admin_lookup() {
    if (!current_user_can('edit_posts')) {
        wp_send_json(['success' => false, 'message' => 'Bu işlem için yetkiniz yok.']);
    }
    check_ajax_referer('qrm_reward_cashier', 'nonce');

    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    if (trim($code) === '') {
        wp_send_json(['success' => false, 'message' => 'Lütfen bir kod girin.']);
    }

    $row = qrm_reward_find_by_code($code);
    if (!$row) {
        wp_send_json(['success' => false, 'found' => false, 'message' => 'Böyle bir kod bulunamadı.']);
    }

    $valid = qrm_reward_code_is_redeemable($row);

    wp_send_json([
        'success'        => true,
        'found'          => true,
        'id'             => (int) $row->id,
        'code'           => $row->code,
        'email'          => $row->email ? $row->email : '—',
        'status'         => $row->status,
        'status_label'   => qrm_reward_status_label($row->status),
        'valid'          => $valid,
        'can_mark_used'  => $valid,
        'discount_label' => $row->discount_label,
        'created_at'     => date_i18n('d.m.Y H:i', strtotime($row->created_at)),
        'expires_at'     => !empty($row->expires_at) ? date_i18n('d.m.Y H:i', strtotime($row->expires_at)) : '',
        'used_at'        => $row->used_at ? date_i18n('d.m.Y H:i', strtotime($row->used_at)) : '',
    ]);
}

// Kasiyer: geçerli kodu tek tıkla kullanıldı işaretle
add_action('wp_ajax_qrm_reward_cashier_mark_used', 'qrm_reward_ajax_cashier_mark_used');
function qrm_reward_ajax_cashier_mark_used() {
    if (!current_user_can('edit_posts')) {
        wp_send_json(['success' => false, 'message' => 'Bu işlem için yetkiniz yok.']);
    }
    check_ajax_referer('qrm_reward_cashier', 'nonce');

    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    if (trim($code) === '') {
        wp_send_json(['success' => false, 'message' => 'Lütfen bir kod girin.']);
    }

    $row = qrm_reward_find_by_code($code);
    if (!$row) {
        wp_send_json(['success' => false, 'message' => 'Böyle bir kod bulunamadı.']);
    }

    if (!qrm_reward_code_is_redeemable($row)) {
        wp_send_json([
            'success'      => false,
            'message'      => 'Bu kod kullanılamaz (' . qrm_reward_status_label($row->status) . ').',
            'status'       => $row->status,
            'status_label' => qrm_reward_status_label($row->status),
        ]);
    }

    if (!qrm_reward_set_status((int) $row->id, 'used')) {
        wp_send_json(['success' => false, 'message' => 'Kod güncellenemedi, tekrar deneyin.']);
    }

    wp_send_json([
        'success'        => true,
        'message'        => 'Kod kullanıldı olarak işaretlendi.',
        'code'           => $row->code,
        'status'         => 'used',
        'status_label'   => qrm_reward_status_label('used'),
        'discount_label' => $row->discount_label,
        'used_at'        => date_i18n('d.m.Y H:i', current_time('timestamp')),
    ]);
}

// Popup: dönüşüm hunisi olay kaydı (fire-and-forget)
add_action('wp_ajax_qrm_reward_log_event', 'qrm_reward_ajax_log_event');
add_action('wp_ajax_nopriv_qrm_reward_log_event', 'qrm_reward_ajax_log_event');
function qrm_reward_ajax_log_event() {
    check_ajax_referer('qrm_reward_request_code', 'nonce');
    qrm_pro_bootstrap_lang();

    $settings = qrm_pro_get_settings();
    if (!qrm_reward_is_active($settings)) {
        wp_send_json(['success' => false]);
    }

    $review_id = isset($_POST['review_id']) ? intval($_POST['review_id']) : 0;
    $claim     = isset($_POST['claim']) ? sanitize_text_field(wp_unslash($_POST['claim'])) : '';
    $event     = isset($_POST['event']) ? sanitize_key($_POST['event']) : '';

    // İstemci olayları yalnızca geçerli talep anahtarıyla kabul edilir.
    $client_events = ['popup_shown', 'google_clicked', 'returned', 'skipped'];
    if (!in_array($event, $client_events, true)) {
        wp_send_json(['success' => false]);
    }

    $gecerli = qrm_reward_verify_claim($review_id, $claim, $settings);
    if (is_wp_error($gecerli)) {
        wp_send_json(['success' => false]);
    }

    wp_send_json(['success' => (bool) qrm_reward_log_event($review_id, $event)]);
}

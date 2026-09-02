<?php
if (!defined('ABSPATH')) exit;

// ÖZEL FORM BUILDER: AJAX GÖNDERİM (v4.2.0)

add_action('wp_ajax_qrm_submit_custom_form', 'qrm_cf_ajax_submit');
add_action('wp_ajax_nopriv_qrm_submit_custom_form', 'qrm_cf_ajax_submit');

function qrm_cf_ajax_submit() {
    check_ajax_referer('qrm_submit_custom_form', 'qrm_cf_nonce');
    qrm_pro_bootstrap_lang();

    $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
    $form    = $form_id > 0 ? qrm_cf_get_form($form_id) : null;

    if (!$form || $form->status !== 'active') {
        wp_send_json(['success' => false, 'message' => qrm_ceviri_review(__('Bu form şu anda gönderime kapalı.', 'qrms'))]);
    }

    $settings = qrm_cf_get_form_settings($form);

    // Honeypot: bot doldurur, gerçek kullanıcı görmez. Bota başarı görünümü döndürülür
    // (yeniden denemesin diye) ama hiçbir kayıt oluşturulmaz.
    if (qrm_pro_honeypot_tripped()) {
        wp_send_json([
            'success' => true,
            'message' => qrm_ceviri_cf_form($form->id, 'success_message', $settings['success_message']),
        ]);
    }

    // Spam koruması: zaman tuzağı + akış koruması (captcha bu formlarda kullanılmaz).
    $guard = qrm_pro_spam_guard_basic();
    if ($guard !== true) {
        wp_send_json(['success' => false, 'message' => $guard]);
    }

    $fields = qrm_cf_get_fields($form->id);
    if (!$fields) {
        wp_send_json(['success' => false, 'message' => qrm_ceviri_review(__('Bu formda tanımlı alan yok.', 'qrms'))]);
    }

    // Sunucu tarafı doğrulama: tarayıcıdaki required/type özniteliklerine güvenilmez.
    $validated = qrm_cf_validate_submission($fields, wp_unslash($_POST));
    if (!$validated['ok']) {
        wp_send_json(['success' => false, 'message' => implode(' ', $validated['errors'])]);
    }

    // v4.2.1: Ardışık gönderim kısıtı — yorum/iletişim formuyla aynı kuralı paylaşır.
    // Kimlik anahtarları için formun e-posta ve telefon alanlarındaki değerler kullanılır.
    $identifiers = qrm_cf_cooldown_identifiers($fields, $validated['data']);
    $cooldown = qrm_pro_cooldown_guard($identifiers);
    if ($cooldown !== true) {
        wp_send_json(['success' => false, 'message' => $cooldown]);
    }

    $masa_ctx = qrm_pro_resolve_masa_for_submission('');
    if ($masa_ctx['masa_slug'] !== '' || $masa_ctx['table_id'] !== null) {
        if ($masa_ctx['masa_slug'] !== '') {
            $validated['data']['_qrm_masa_slug'] = $masa_ctx['masa_slug'];
        }
        if ($masa_ctx['table_id'] !== null && (int) $masa_ctx['table_id'] > 0) {
            $validated['data']['_qrm_table_id'] = (int) $masa_ctx['table_id'];
        }
    }

    $submission_id = qrm_cf_insert_submission(
        $form->id,
        $validated['data'],
        qrm_pro_client_ip(),
        qrm_pro_consent_from_request()
    );
    if (!$submission_id) {
        wp_send_json(['success' => false, 'message' => qrm_ceviri_review(__('Gönderiminiz kaydedilemedi, lütfen tekrar deneyin.', 'qrms'))]);
    }

    qrm_pro_cooldown_mark($identifiers);
    qrm_cf_notify_admin($form, $fields, $validated['data']);

    // Analitik: form yanıtları yazılmaz; item_name form adıdır.
    if (function_exists('qmo_analitik_yaz')) {
        qmo_analitik_yaz([
            'event_type' => 'form_submit',
            'item_name'  => isset($form->title) ? (string) $form->title : '',
        ]);
    }

    wp_send_json([
        'success' => true,
        'message' => qrm_ceviri_cf_form($form->id, 'success_message', $settings['success_message']),
    ]);
}

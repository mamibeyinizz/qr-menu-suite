<?php
/**
 * AJAX: Yorum iş akışı kaydı (durum, sorumlu, iç not).
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_qrm_review_workflow_save', 'qrm_pro_ajax_review_workflow_save');

/**
 * Tek bir yorumun iş akışı alanlarını günceller.
 *
 * @return void
 */
function qrm_pro_ajax_review_workflow_save() {
    check_ajax_referer('qrm_review_workflow_save', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Bu işlem için yetkiniz yok.', 'qrms')], 403);
    }

    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if ($id <= 0) {
        wp_send_json_error(['message' => __('Geçersiz yorum.', 'qrms')]);
    }

    $statuses = qrm_pro_review_workflow_statuses();
    $workflow = isset($_POST['workflow_status']) ? sanitize_key(wp_unslash($_POST['workflow_status'])) : '';
    if (!array_key_exists($workflow, $statuses)) {
        wp_send_json_error(['message' => __('Geçersiz iş akışı durumu.', 'qrms')]);
    }

    $assigned_raw = isset($_POST['assigned_user_id']) ? wp_unslash($_POST['assigned_user_id']) : '0';
    $assigned_id  = ($assigned_raw === '' || $assigned_raw === '0') ? 0 : absint($assigned_raw);

    if ($assigned_id > 0) {
        $user = get_userdata($assigned_id);
        if (!$user || !user_can($user, 'edit_posts')) {
            wp_send_json_error(['message' => __('Seçilen kullanıcı atanamaz.', 'qrms')]);
        }
    }

    $internal_note = isset($_POST['internal_note'])
        ? sanitize_textarea_field(wp_unslash($_POST['internal_note']))
        : '';

    global $wpdb;
    $table = $wpdb->prefix . 'qrm_reviews';

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d", $id));
    if (!$exists) {
        wp_send_json_error(['message' => __('Yorum bulunamadı.', 'qrms')]);
    }

    $data = [
        'workflow_status'  => $workflow,
        'assigned_user_id' => $assigned_id > 0 ? $assigned_id : null,
        'internal_note'    => $internal_note !== '' ? $internal_note : null,
        'resolved_at'      => ($workflow === 'resolved') ? current_time('mysql') : null,
    ];

    $updated = $wpdb->update($table, $data, ['id' => $id]);

    if ($updated === false) {
        wp_send_json_error(['message' => __('Kayıt sırasında bir hata oluştu.', 'qrms')]);
    }

    $resolved_label = '';
    if ($workflow === 'resolved' && !empty($data['resolved_at'])) {
        $resolved_label = date_i18n('d.m.Y H:i', strtotime($data['resolved_at']));
    }

    wp_send_json_success([
        'message'       => __('İş akışı kaydedildi.', 'qrms'),
        'workflow'      => $workflow,
        'resolved_at'   => $data['resolved_at'],
        'resolved_label'=> $resolved_label,
    ]);
}

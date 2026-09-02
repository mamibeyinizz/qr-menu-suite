<?php
if (!defined('ABSPATH')) exit;

// Rapor görünümü — İzinli Kişiler bloğu (v4.2.7)

add_action('admin_post_qrm_revoke_consent', 'qrm_pro_admin_revoke_consent_handler');

/**
 * İzin geri alma (admin_post).
 *
 * @return void
 */
function qrm_pro_admin_revoke_consent_handler() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Bu işlem için yetkiniz yok.', 'qrms'));
    }

    check_admin_referer('qrm_revoke_consent');

    $source = isset($_POST['consent_source']) ? sanitize_key(wp_unslash($_POST['consent_source'])) : '';
    $id     = isset($_POST['consent_id']) ? absint($_POST['consent_id']) : 0;
    $bas    = isset($_POST['rapor_bas']) ? sanitize_text_field(wp_unslash($_POST['rapor_bas'])) : '';
    $bit    = isset($_POST['rapor_bit']) ? sanitize_text_field(wp_unslash($_POST['rapor_bit'])) : '';

    if ($id > 0 && qrm_pro_revoke_consent($source, $id)) {
        $redirect = add_query_arg(
            [
                'page'       => 'qrms-yf-yorumlar',
                'view'       => 'rapor',
                'rapor_bas'  => $bas,
                'rapor_bit'  => $bit,
                'consent_ok' => '1',
            ],
            admin_url('admin.php')
        );
        wp_safe_redirect($redirect);
        exit;
    }

    wp_die(esc_html__('İzin kaydı güncellenemedi.', 'qrms'));
}

/**
 * Rapor sayfasında İzinli Kişiler bloğu.
 *
 * @param array $range qrm_pro_report_date_range() çıktısı.
 * @return void
 */
function qrm_pro_admin_render_consent_block(array $range) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['consent_ok']) && $_GET['consent_ok'] === '1') {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Pazarlama izni kaldırıldı.', 'qrms') . '</p></div>';
    }

    $per_page = 25;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $paged    = isset($_GET['consent_paged']) ? max(1, (int) $_GET['consent_paged']) : 1;
    $total    = qrm_pro_count_consent_entries($range['bas_dt'], $range['bit_excl']);
    $offset   = ($paged - 1) * $per_page;
    $rows     = $total > 0
        ? qrm_pro_fetch_consent_entries($range['bas_dt'], $range['bit_excl'], $per_page, $offset)
        : [];
    $pages    = max(1, (int) ceil($total / $per_page));

    $fields_cache = [];
    $report_args  = [
        'page'      => 'qrms-yf-yorumlar',
        'view'      => 'rapor',
        'rapor_bas' => $range['bas'],
        'rapor_bit' => $range['bit'],
    ];
    ?>
    <div class="qrm-card qrm-consent-block">
        <div class="qrm-consent-block-head">
            <div>
                <h3><?php esc_html_e('İzinli Kişiler', 'qrms'); ?></h3>
                <p class="description" style="margin:4px 0 0;">
                    <?php esc_html_e('Kampanya ve duyuru izni veren kayıtlar. Onay vermeyenler bu listede görünmez.', 'qrms'); ?>
                </p>
            </div>
            <?php if ($total > 0 && function_exists('qrm_export_csv_button')): ?>
                <?php
                echo qrm_export_csv_button('consent_marketing', [
                    'rapor_bas' => $range['bas'],
                    'rapor_bit' => $range['bit'],
                ]);
                ?>
            <?php endif; ?>
        </div>

        <?php if ($total === 0): ?>
            <p class="qrm-empty-inline"><?php esc_html_e('Bu tarih aralığında pazarlama izni veren kayıt yok.', 'qrms'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped qrm-consent-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Ad', 'qrms'); ?></th>
                        <th><?php esc_html_e('Telefon', 'qrms'); ?></th>
                        <th><?php esc_html_e('E-posta', 'qrms'); ?></th>
                        <th><?php esc_html_e('Kaynak', 'qrms'); ?></th>
                        <th><?php esc_html_e('Onay Tarihi', 'qrms'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('İşlem', 'qrms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $contact = qrm_pro_consent_contact_from_row($row, $fields_cache);
                ?>
                    <tr>
                        <td><?php echo $contact['name'] !== '' ? esc_html($contact['name']) : '—'; ?></td>
                        <td><?php echo $contact['phone'] !== '' ? esc_html($contact['phone']) : '—'; ?></td>
                        <td><?php echo $contact['email'] !== '' ? esc_html($contact['email']) : '—'; ?></td>
                        <td><?php echo esc_html((string) $row->source_label); ?></td>
                        <td><?php echo !empty($row->consent_at) ? esc_html(date_i18n('d.m.Y H:i', strtotime($row->consent_at))) : '—'; ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="qrm-consent-revoke-form">
                                <?php wp_nonce_field('qrm_revoke_consent'); ?>
                                <input type="hidden" name="action" value="qrm_revoke_consent">
                                <input type="hidden" name="consent_source" value="<?php echo esc_attr((string) $row->source_type); ?>">
                                <input type="hidden" name="consent_id" value="<?php echo esc_attr((string) (int) $row->row_id); ?>">
                                <input type="hidden" name="rapor_bas" value="<?php echo esc_attr($range['bas']); ?>">
                                <input type="hidden" name="rapor_bit" value="<?php echo esc_attr($range['bit']); ?>">
                                <button type="submit" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Bu kişinin pazarlama iznini kaldırmak istediğinize emin misiniz?', 'qrms')); ?>');">
                                    <?php esc_html_e('İzni Kaldır', 'qrms'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $page_base = add_query_arg(array_merge($report_args, ['consent_paged' => '%#%']), admin_url('admin.php'));
                        echo paginate_links([
                            'base'      => $page_base,
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $pages,
                            'current'   => $paged,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

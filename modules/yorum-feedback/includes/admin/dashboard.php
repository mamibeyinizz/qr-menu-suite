<?php
if (!defined('ABSPATH')) exit;

// 3. ADMİN: TÜM YORUMLAR (Puan kırılımları ile)
//
// v4.2.1: "Detaylı İçgörüler" ayrı bir menü maddesi olmaktan çıkıp bu sayfanın
// ikinci sekmesi oldu. Her iki sekme de salt okunur listeler olduğu için
// (satır aksiyonları GET ile çalışır) sekmeler birbirinin verisini etkilemez.
function qrm_pro_admin_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    global $wpdb;
    $table_reviews = $wpdb->prefix . 'qrm_reviews';
    $settings = qrm_pro_get_settings();
    $g_threshold = floatval($settings['google_review_threshold']);

    if (isset($_GET['action']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        if ($_GET['action'] == 'approve') $wpdb->update($table_reviews, ['status' => 1], ['id' => $id]);
        if ($_GET['action'] == 'unapprove') $wpdb->update($table_reviews, ['status' => 0], ['id' => $id]);
        if ($_GET['action'] == 'delete') $wpdb->delete($table_reviews, ['id' => $id]);
        echo '<div class="notice notice-success is-dismissible"><p>İşlem yapıldı.</p></div>';
    }

    $reviews = $wpdb->get_results("SELECT * FROM $table_reviews ORDER BY created_at DESC");
    $tab = (isset($_GET['tab']) && sanitize_key($_GET['tab']) === 'insights') ? 'insights' : 'reviews';
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1 class="wp-heading-inline">Tüm Yorumlar</h1>
        <hr class="wp-header-end">

        <h2 class="nav-tab-wrapper">
            <a href="#" class="nav-tab qrm-dash-tab" data-tab="reviews">Yorumlar</a>
            <a href="#" class="nav-tab qrm-dash-tab" data-tab="insights">İçgörüler</a>
        </h2>

        <div class="qrm-dash-pane" data-pane="insights" style="margin-top:16px;">
            <?php qrm_pro_admin_insights_pane(); ?>
        </div>

        <div class="qrm-dash-pane" data-pane="reviews" style="margin-top:16px;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 120px;">Tarih</th>
                    <th>Müşteri / Masa</th>
                    <th style="width: 200px;">Puan & Detay</th>
                    <th>Yorum</th>
                    <th style="width: 80px;">Durum</th>
                    <th style="width: 130px;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $r):
                    $name_display = $r->is_anonymous ? '<em>Anonim</em>' : esc_html($r->customer_name);
                    $name_display .= $r->table_no ? ' (Masa: '.esc_html($r->table_no).')' : '';
                    if (!empty($r->form_source) && $r->form_source === 'contact') {
                        $name_display .= ' <span class="qrm-google-pill" style="background:#f3e8ff;color:#7e22ce;">İletişim</span>';
                    }

                    // Kriter Kırılımını Hazırla
                    $breakdown = [];
                    for($i=1; $i<=5; $i++) {
                        $c_act = $settings['crit_'.$i.'_active'];
                        $c_name = $settings['crit_'.$i.'_name'];
                        $c_val = $r->{'rating_'.$i};
                        if($c_act && $c_val > 0) {
                            $breakdown[] = "{$c_name}: {$c_val}";
                        }
                    }
                    $breakdown_str = implode(', ', $breakdown);
                ?>
                <tr>
                    <td><?php echo date('d.m.Y H:i', strtotime($r->created_at)); ?></td>
                    <td><?php echo $name_display; ?></td>
                    <td>
                        <strong>Ort: <?php echo number_format($r->rating, 1); ?>/5</strong>
                        <?php if ($r->rating >= $g_threshold && !empty($settings['google_review_enabled'])): ?>
                            <span class="qrm-google-pill" title="Bu puan Google'a yönlendirme eşiğinin üzerinde">G Adayı</span>
                        <?php endif; ?>
                        <span class="qrm-breakdown"><?php echo esc_html($breakdown_str); ?></span>
                    </td>
                    <td><?php echo esc_html($r->comment); ?></td>
                    <td><?php echo $r->status ? '<span style="color:green;font-weight:bold;">Onaylı</span>' : '<span style="color:orange;font-weight:bold;">Bekliyor</span>'; ?></td>
                    <td>
                        <?php if (!$r->status): ?>
                            <a href="?page=qrm-pro-main&action=approve&id=<?php echo $r->id; ?>" class="button button-small">Onayla</a>
                        <?php else: ?>
                            <a href="?page=qrm-pro-main&action=unapprove&id=<?php echo $r->id; ?>" class="button button-small">Reddet</a>
                        <?php endif; ?>
                        <a href="?page=qrm-pro-main&action=delete&id=<?php echo $r->id; ?>" class="button button-small" style="color:red;border-color:red;" onclick="return confirm('Sil?');">Sil</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($){
        // Sekme geçişi sayfa yenilemeden (Google & Ödül Sistemi sayfasındaki desen).
        function showTab(name) {
            $('.qrm-dash-pane').hide();
            $('.qrm-dash-pane[data-pane="' + name + '"]').show();
            $('.qrm-dash-tab').removeClass('nav-tab-active');
            $('.qrm-dash-tab[data-tab="' + name + '"]').addClass('nav-tab-active');
            if (history.replaceState) {
                history.replaceState(null, '', '?page=qrm-pro-main' + (name === 'insights' ? '&tab=insights' : ''));
            }
        }
        $('.qrm-dash-tab').on('click', function(e){
            e.preventDefault();
            showTab($(this).data('tab'));
        });
        showTab(<?php echo wp_json_encode($tab); ?>);
    });
    </script>
    <?php
}

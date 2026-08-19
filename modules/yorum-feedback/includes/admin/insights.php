<?php
if (!defined('ABSPATH')) exit;

// 6. ADMİN: İÇGÖRÜLER (Kriterlere Özel Ortalamalar)
//
// v4.2.1: Yapay zekâ destekli yorum analizi bloğu tamamen kaldırıldı. Geriye kalan
// istatistikler anlamlı olduğu için sayfa silinmedi; ayrı bir menü maddesi olmak
// yerine "Tüm Yorumlar" sayfasının "İçgörüler" sekmesi hâline geldi.

/** "İçgörüler" sekmesinin içeriği (yalnızca onaylı yorumlar üzerinden). */
function qrm_pro_admin_insights_pane() {
    global $wpdb;
    $table = $wpdb->prefix . 'qrm_reviews';
    $settings = qrm_pro_get_settings();

    $total_all = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    if ($total_all === 0) {
        echo '<div class="qrm-card"><p style="margin:0;">Henüz hiç yorum yok. İlk değerlendirme geldiğinde kriter bazlı ortalamalar burada görünecek.</p></div>';
        return;
    }

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 1");
    if ($total === 0) {
        echo '<div class="qrm-card"><p style="margin:0;">Henüz onaylı yorum yok. İçgörüler yalnızca onayladığınız yorumlar üzerinden hesaplanır — "Yorumlar" sekmesinden onaylayabilirsiniz.</p></div>';
        return;
    }

    $avg_total   = $wpdb->get_var("SELECT AVG(rating) FROM $table WHERE status = 1");
    $g_threshold = floatval($settings['google_review_threshold']);
    $g_eligible  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = 1 AND rating >= %f", $g_threshold));
    ?>
    <p class="description" style="margin:14px 0 18px;">Aşağıdaki değerler yalnızca <strong>onaylı</strong> yorumlar üzerinden hesaplanır.</p>

    <div class="qrm-insight-grid">
        <div class="qrm-stat-box" style="border-left-color:#8b5cf6;">
            <h3>Genel Ortalama</h3>
            <div class="value"><?php echo number_format((float) $avg_total, 1); ?> ★</div>
        </div>
        <div class="qrm-stat-box" style="border-left-color:#10b981;">
            <h3>Toplam Değerlendirme</h3>
            <div class="value"><?php echo intval($total); ?></div>
        </div>
        <div class="qrm-stat-box" style="border-left-color:#1a73e8;">
            <h3>Google'a Yönlendirilen (<?php echo number_format($g_threshold, 1); ?>+)</h3>
            <div class="value"><?php echo intval($g_eligible); ?></div>
        </div>
    </div>

    <div class="qrm-card">
        <h3>Kriter Bazlı Performans Ortalamaları</h3>
        <table class="wp-list-table widefat striped">
            <tr><th>Kriter Adı</th><th>Ortalama Puan</th><th>Performans Çubuğu</th></tr>
            <?php
            for ($i = 1; $i <= 5; $i++) {
                if ($settings['crit_' . $i . '_active']) {
                    $c_name = $settings['crit_' . $i . '_name'];
                    // Sadece o kritere oy verenlerin ortalaması alınır
                    $c_avg = $wpdb->get_var("SELECT AVG(rating_$i) FROM $table WHERE status = 1 AND rating_$i > 0");
                    $c_avg = $c_avg ? number_format((float) $c_avg, 1) : 0;
                    $pct = ($c_avg / 5) * 100;
                    echo "<tr>
                        <td style='width:200px;'><strong>" . esc_html($c_name) . "</strong></td>
                        <td style='width:100px;'>{$c_avg} / 5</td>
                        <td>
                            <div style='width:100%; max-width:300px; background:#e5e7eb; border-radius:10px; height:12px; overflow:hidden;'>
                                <div style='width:{$pct}%; background:#10b981; height:100%;'></div>
                            </div>
                        </td>
                    </tr>";
                }
            }
            ?>
        </table>
    </div>
    <?php
}

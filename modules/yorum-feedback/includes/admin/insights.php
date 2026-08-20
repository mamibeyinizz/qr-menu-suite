<?php
if (!defined('ABSPATH')) exit;

// 6. ADMİN: DETAYLI İÇGÖRÜLER (Kriterlere Özel Ortalamalar)
//
// v4.2.1'de "Tüm Yorumlar" sayfasının JS sekmesine gömülmüştü; suite'e taşınırken
// yeniden kendi sayfası oldu (bkz. dashboard.php'deki gerekçe).

/** Detaylı İçgörüler ekranı. */
function qrm_pro_admin_insights() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $stats = qrm_pro_review_stats();
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>Detaylı İçgörüler</h1>
        <p class="qrm-lead">Tüm değerler yalnızca <strong>yayındaki</strong> yorumlar üzerinden hesaplanır.</p>

        <?php if (!$stats['table_ok']): ?>

            <div class="notice notice-error">
                <p><strong>Yorum tablosu veritabanında bulunamadı.</strong> İstatistikler hesaplanamıyor.</p>
            </div>

        <?php elseif ($stats['total'] === 0): ?>

            <div class="qrm-card qrm-empty">
                <strong>Henüz hiç yorum yok.</strong>
                <p>İlk değerlendirme geldiğinde kriter bazlı ortalamalarınız burada görünecek.</p>
            </div>

        <?php elseif ($stats['approved'] === 0): ?>

            <div class="qrm-card qrm-empty">
                <strong><?php echo intval($stats['total']); ?> yorum onayınızı bekliyor.</strong>
                <p>
                    Yayına aldığınız yorumların ortalamaları burada hesaplanacak.
                    <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-yorumlar', ['durum' => 'bekleyen'])); ?>">Bekleyen yorumları aç</a>
                </p>
            </div>

        <?php else: qrm_pro_admin_insights_body($stats); endif; ?>

    </div>
    <?php
}

/**
 * İçgörülerin gövdesi: özet kutuları + kriter bazlı performans.
 * Yalnızca en az bir yayında yorum varken çağrılır.
 *
 * @param array $stats qrm_pro_review_stats() çıktısı.
 * @return void
 */
function qrm_pro_admin_insights_body($stats) {
    global $wpdb;
    $table = $wpdb->prefix . 'qrm_reviews';
    $settings = qrm_pro_get_settings();

    $g_threshold = floatval($settings['google_review_threshold']);
    $g_eligible  = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE status = 1 AND rating >= %f",
        $g_threshold
    ));
    ?>
    <div class="qrm-insight-grid">
        <div class="qrm-stat-box" style="border-left-color:#8b5cf6;">
            <h3>Genel Ortalama</h3>
            <div class="value"><?php echo esc_html(number_format($stats['avg'], 1)); ?> ★</div>
        </div>
        <div class="qrm-stat-box" style="border-left-color:#10b981;">
            <h3>Yayındaki Değerlendirme</h3>
            <div class="value"><?php echo intval($stats['approved']); ?></div>
        </div>
        <div class="qrm-stat-box" style="border-left-color:#1a73e8;">
            <h3>Google Eşiğini Geçen (<?php echo esc_html(number_format($g_threshold, 1)); ?>+)</h3>
            <div class="value"><?php echo intval($g_eligible); ?></div>
        </div>
    </div>

    <div class="qrm-card">
        <h3>Kriter Bazlı Performans</h3>
        <table class="wp-list-table widefat striped qrm-table-cards">
            <thead>
                <tr>
                    <th style="width:220px;">Kriter</th>
                    <th style="width:110px;">Ortalama</th>
                    <th>Performans</th>
                </tr>
            </thead>
            <tbody>
            <?php for ($i = 1; $i <= 5; $i++):
                if (empty($settings['crit_' . $i . '_active'])) continue;

                // Sadece o kritere oy verenlerin ortalaması alınır.
                $c_avg = (float) $wpdb->get_var("SELECT AVG(rating_$i) FROM $table WHERE status = 1 AND rating_$i > 0");
                $pct   = max(0, min(100, ($c_avg / 5) * 100));
            ?>
                <tr>
                    <td data-label="Kriter"><strong><?php echo esc_html($settings['crit_' . $i . '_name']); ?></strong></td>
                    <td data-label="Ortalama"><?php echo esc_html(number_format($c_avg, 1)); ?> / 5</td>
                    <td data-label="Performans">
                        <div class="qrm-meter"><span style="width:<?php echo esc_attr(round($pct, 1)); ?>%;"></span></div>
                    </td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php
}

<?php
/**
 * Yorum raporları — masa, saat ve vardiya kırılımları.
 *
 * Tüm aggregate sorgular toplu GROUP BY ile çalışır; satır başına ek sorgu yoktur.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Varsayılan vardiya tanımları.
 *
 * @return array<int,array{name:string,start:int,end:int}>
 */
function qrm_pro_default_shifts() {
    return [
        ['name' => 'Sabah', 'start' => 6,  'end' => 12],
        ['name' => 'Öğle',  'start' => 12, 'end' => 17],
        ['name' => 'Akşam', 'start' => 17, 'end' => 23],
        ['name' => 'Gece',  'start' => 23, 'end' => 6],
    ];
}

/**
 * Ayarlardan geçerli vardiya listesini döndürür.
 *
 * @param array|null $settings qrm_pro_get_settings() çıktısı.
 * @return array<int,array{name:string,start:int,end:int}>
 */
function qrm_pro_get_shifts($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

    $raw = isset($settings['qrm_shifts']) && is_array($settings['qrm_shifts'])
        ? $settings['qrm_shifts']
        : [];

    $shifts = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name  = isset($row['name']) ? sanitize_text_field($row['name']) : '';
        $start = isset($row['start']) ? (int) $row['start'] : -1;
        $end   = isset($row['end']) ? (int) $row['end'] : -1;

        if ($name === '' || $start < 0 || $start > 23 || $end < 0 || $end > 23) {
            continue;
        }

        $shifts[] = [
            'name'  => $name,
            'start' => $start,
            'end'   => $end,
        ];
    }

    return !empty($shifts) ? $shifts : qrm_pro_default_shifts();
}

/**
 * POST'tan vardiya dizisini doğrular.
 *
 * @param array $posted $_POST['qrm_shifts'].
 * @return array<int,array{name:string,start:int,end:int}>
 */
function qrm_pro_sanitize_shifts_from_post($posted) {
    if (!is_array($posted)) {
        return qrm_pro_default_shifts();
    }

    $shifts = [];
    foreach ($posted as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name  = isset($row['name']) ? sanitize_text_field(wp_unslash($row['name'])) : '';
        $start = isset($row['start']) ? (int) $row['start'] : -1;
        $end   = isset($row['end']) ? (int) $row['end'] : -1;

        if ($name === '' || $start < 0 || $start > 23 || $end < 0 || $end > 23) {
            continue;
        }

        $shifts[] = [
            'name'  => $name,
            'start' => $start,
            'end'   => $end,
        ];
    }

    return !empty($shifts) ? $shifts : qrm_pro_default_shifts();
}

/**
 * Rapor tarih aralığını doğrular (varsayılan: son 30 gün).
 *
 * @param string $bas Ham başlangıç (Y-m-d).
 * @param string $bit Ham bitiş (Y-m-d).
 * @return array{bas:string,bit:string,bas_dt:string,bit_excl:string}
 */
function qrm_pro_report_date_range($bas = '', $bit = '') {
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

    try {
        $bitis = ($bit !== '') ? new DateTime($bit, $tz) : new DateTime('now', $tz);
    } catch (Exception $e) {
        $bitis = new DateTime('now', $tz);
    }

    try {
        $baslangic = ($bas !== '') ? new DateTime($bas, $tz) : (clone $bitis)->modify('-29 days');
    } catch (Exception $e) {
        $baslangic = (clone $bitis)->modify('-29 days');
    }

    if ($baslangic > $bitis) {
        $tmp       = $baslangic;
        $baslangic = $bitis;
        $bitis     = $tmp;
    }

    $bit_excl = (clone $bitis)->modify('+1 day');

    return [
        'bas'      => $baslangic->format('Y-m-d'),
        'bit'      => $bitis->format('Y-m-d'),
        'bas_dt'   => $baslangic->format('Y-m-d 00:00:00'),
        'bit_excl' => $bit_excl->format('Y-m-d 00:00:00'),
    ];
}

/**
 * Saat bir vardiya aralığında mı? (bitiş saati hariç; gece yarısı geçişi desteklenir.)
 *
 * @param int $hour   0-23.
 * @param int $start  0-23.
 * @param int $end    0-23.
 * @return bool
 */
function qrm_pro_hour_in_shift($hour, $start, $end) {
    $hour  = (int) $hour;
    $start = (int) $start;
    $end   = (int) $end;

    if ($start === $end) {
        return false;
    }

    if ($start < $end) {
        return $hour >= $start && $hour < $end;
    }

    return $hour >= $start || $hour < $end;
}

/**
 * Masa bazlı rapor satırları — tek GROUP BY sorgusu.
 *
 * @param string $bas_dt   Dahil başlangıç datetime.
 * @param string $bit_excl Hariç bitiş datetime.
 * @return array<int,object>
 */
function qrm_pro_fetch_report_table_rows($bas_dt, $bit_excl) {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        return [];
    }

    $reviews = $wpdb->prefix . 'qrm_reviews';
    $join    = '';
    $select_name = 'NULL AS table_name';

    if (class_exists('QMO_Masalar') && method_exists('QMO_Masalar', 'tablo')) {
        $tables = QMO_Masalar::tablo();
        $join   = " LEFT JOIN {$tables} t ON r.table_id = t.id";
        $select_name = 'MAX(t.table_name) AS table_name';
    }

    $sql = "SELECT
            r.table_id,
            MAX(r.table_no) AS table_no,
            {$select_name},
            COUNT(*) AS review_count,
            AVG(r.rating) AS avg_rating,
            MAX(r.created_at) AS last_review_at,
            AVG(CASE WHEN r.rating_1 > 0 THEN r.rating_1 END) AS crit_1,
            AVG(CASE WHEN r.rating_2 > 0 THEN r.rating_2 END) AS crit_2,
            AVG(CASE WHEN r.rating_3 > 0 THEN r.rating_3 END) AS crit_3,
            AVG(CASE WHEN r.rating_4 > 0 THEN r.rating_4 END) AS crit_4,
            AVG(CASE WHEN r.rating_5 > 0 THEN r.rating_5 END) AS crit_5
        FROM {$reviews} r{$join}
        WHERE r.created_at >= %s AND r.created_at < %s
        GROUP BY IFNULL(r.table_id, 0),
                 CASE WHEN r.table_id IS NULL THEN r.table_no ELSE '' END
        ORDER BY review_count DESC, last_review_at DESC";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $bas_dt, $bit_excl));

    return is_array($rows) ? $rows : [];
}

/**
 * Saat dilimi dağılımı — tek GROUP BY sorgusu (0-23).
 *
 * @param string $bas_dt   Dahil başlangıç.
 * @param string $bit_excl Hariç bitiş.
 * @return array<int,array{hour:int,count:int,avg:float}>
 */
function qrm_pro_fetch_report_hour_rows($bas_dt, $bit_excl) {
    global $wpdb;

    $buckets = [];
    for ($h = 0; $h < 24; $h++) {
        $buckets[$h] = ['hour' => $h, 'count' => 0, 'avg' => 0.0];
    }

    if (!qrm_pro_reviews_table_exists()) {
        return $buckets;
    }

    $reviews = $wpdb->prefix . 'qrm_reviews';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT HOUR(created_at) AS hr,
                COUNT(*) AS cnt,
                AVG(rating) AS avg_rating
         FROM {$reviews}
         WHERE created_at >= %s AND created_at < %s
         GROUP BY HOUR(created_at)",
        $bas_dt,
        $bit_excl
    ));

    if (!is_array($rows)) {
        return $buckets;
    }

    foreach ($rows as $row) {
        $h = isset($row->hr) ? (int) $row->hr : -1;
        if ($h < 0 || $h > 23) {
            continue;
        }
        $buckets[$h] = [
            'hour'  => $h,
            'count' => (int) $row->cnt,
            'avg'   => (float) $row->avg_rating,
        ];
    }

    return $buckets;
}

/**
 * Saat kovalarından vardiya özetini üretir (ek SQL yok).
 *
 * @param array<int,array{hour:int,count:int,avg:float}> $hour_rows Saat dağılımı.
 * @param array<int,array{name:string,start:int,end:int}> $shifts    Vardiya tanımları.
 * @return array<int,array{name:string,count:int,avg:float}>
 */
function qrm_pro_aggregate_shift_rows(array $hour_rows, array $shifts) {
    $out = [];

    foreach ($shifts as $shift) {
        $total = 0;
        $weighted = 0.0;

        foreach ($hour_rows as $bucket) {
            if (!qrm_pro_hour_in_shift($bucket['hour'], $shift['start'], $shift['end'])) {
                continue;
            }
            $cnt = (int) $bucket['count'];
            if ($cnt <= 0) {
                continue;
            }
            $total += $cnt;
            $weighted += $bucket['avg'] * $cnt;
        }

        $out[] = [
            'name'  => $shift['name'],
            'count' => $total,
            'avg'   => $total > 0 ? $weighted / $total : 0.0,
        ];
    }

    return $out;
}

/**
 * Rapor satırı için görünen masa adı.
 *
 * @param object $row Sorgu satırı.
 * @return string
 */
function qrm_pro_report_table_label($row) {
    if (!empty($row->table_name)) {
        return (string) $row->table_name;
    }
    if (!empty($row->table_id)) {
        /* translators: %d: masa kimliği. */
        return sprintf(__('Masa #%d', 'qrms'), (int) $row->table_id);
    }
    if (!empty($row->table_no)) {
        /* translators: %s: serbest metin masa numarası. */
        return sprintf(__('Masa %s', 'qrms'), (string) $row->table_no);
    }

    return __('Belirtilmemiş', 'qrms');
}

/**
 * Tüm Yorumlar — ?view=rapor görünümü.
 *
 * @return void
 */
function qrm_pro_admin_reports_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Bu sayfayı görüntüleme yetkiniz yok.', 'qrms'));
    }

    $self_url = qrm_pro_admin_url('qrms-yf-yorumlar');
    $settings = qrm_pro_get_settings();
    $shifts   = qrm_pro_get_shifts($settings);

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- görünüm filtresi.
    $bas_raw = isset($_GET['rapor_bas']) && is_scalar($_GET['rapor_bas']) ? sanitize_text_field(wp_unslash($_GET['rapor_bas'])) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $bit_raw = isset($_GET['rapor_bit']) && is_scalar($_GET['rapor_bit']) ? sanitize_text_field(wp_unslash($_GET['rapor_bit'])) : '';

    $range      = qrm_pro_report_date_range($bas_raw, $bit_raw);
    $table_rows = qrm_pro_fetch_report_table_rows($range['bas_dt'], $range['bit_excl']);
    $hour_rows  = qrm_pro_fetch_report_hour_rows($range['bas_dt'], $range['bit_excl']);
    $shift_rows = qrm_pro_aggregate_shift_rows($hour_rows, $shifts);

    $max_hour_count = 0;
    foreach ($hour_rows as $bucket) {
        $max_hour_count = max($max_hour_count, (int) $bucket['count']);
    }
    if ($max_hour_count < 1) {
        $max_hour_count = 1;
    }

    $drop_state = qrm_pro_trend_drop_state();
    $report_url = add_query_arg(['view' => 'rapor'], $self_url);
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1><?php esc_html_e('Tüm Yorumlar', 'qrms'); ?></h1>

        <?php qrm_pro_admin_dashboard_view_tabs('rapor'); ?>

        <?php qrm_pro_admin_render_trend_drop_notice($drop_state); ?>

        <h2 class="qrm-report-heading"><?php esc_html_e('Masa / Vardiya / Saat Raporu', 'qrms'); ?></h2>
        <p class="qrm-lead">
            <?php esc_html_e('Seçilen tarih aralığındaki yorumları masa, günün saati ve tanımlı vardiyalara göre özetler. Vardiya saatleri Ayarlar & Puanlama ekranından düzenlenir.', 'qrms'); ?>
        </p>

        <?php if (!qrm_pro_reviews_table_exists()): ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e('Yorum tablosu veritabanında bulunamadı.', 'qrms'); ?></strong></p>
            </div>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="qrm-report-filters qrm-card">
            <input type="hidden" name="page" value="qrms-yf-yorumlar">
            <input type="hidden" name="view" value="rapor">
            <div class="qrm-report-filter-row">
                <label>
                    <span class="qrm-report-filter-label"><?php esc_html_e('Başlangıç', 'qrms'); ?></span>
                    <input type="date" name="rapor_bas" value="<?php echo esc_attr($range['bas']); ?>" required>
                </label>
                <label>
                    <span class="qrm-report-filter-label"><?php esc_html_e('Bitiş', 'qrms'); ?></span>
                    <input type="date" name="rapor_bit" value="<?php echo esc_attr($range['bit']); ?>" required>
                </label>
                <button type="submit" class="button button-primary"><?php esc_html_e('Uygula', 'qrms'); ?></button>
                <a class="button" href="<?php echo esc_url($report_url); ?>"><?php esc_html_e('Son 30 gün', 'qrms'); ?></a>
            </div>
        </form>

        <?php qrm_pro_admin_render_trend_block($settings, $range); ?>

        <div class="qrm-card">
            <h3><?php esc_html_e('Masa Bazlı Özet', 'qrms'); ?></h3>
            <?php if (empty($table_rows)): ?>
                <p class="qrm-empty-inline"><?php esc_html_e('Bu aralıkta yorum bulunamadı.', 'qrms'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped qrm-report-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Masa', 'qrms'); ?></th>
                            <th><?php esc_html_e('Yorum', 'qrms'); ?></th>
                            <th><?php esc_html_e('Ort. Puan', 'qrms'); ?></th>
                            <th><?php esc_html_e('Son Yorum', 'qrms'); ?></th>
                            <?php for ($i = 1; $i <= 5; $i++):
                                if (empty($settings['crit_'.$i.'_active'])) continue;
                            ?>
                                <th><?php echo esc_html($settings['crit_'.$i.'_name']); ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($table_rows as $row):
                            $label = qrm_pro_report_table_label($row);
                            $crit_vals = [];
                            for ($i = 1; $i <= 5; $i++) {
                                $key = 'crit_'.$i;
                                $crit_vals[$i] = isset($row->$key) ? (float) $row->$key : 0.0;
                            }
                        ?>
                        <tr>
                            <td><?php echo esc_html($label); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $row->review_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((float) $row->avg_rating, 1)); ?></td>
                            <td>
                                <?php
                                echo !empty($row->last_review_at)
                                    ? esc_html(date_i18n('d.m.Y H:i', strtotime($row->last_review_at)))
                                    : '—';
                                ?>
                            </td>
                            <?php for ($i = 1; $i <= 5; $i++):
                                if (empty($settings['crit_'.$i.'_active'])) continue;
                                $avg = $crit_vals[$i];
                            ?>
                                <td><?php echo $avg > 0 ? esc_html(number_format_i18n($avg, 1)) : '—'; ?></td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="qrm-card-row">
            <div class="qrm-card">
                <h3><?php esc_html_e('Saat Dilimi Dağılımı', 'qrms'); ?></h3>
                <div class="qrm-hour-chart" role="img" aria-label="<?php esc_attr_e('Saat dilimine göre yorum sayısı ve ortalama puan', 'qrms'); ?>">
                    <?php foreach ($hour_rows as $bucket):
                        $cnt = (int) $bucket['count'];
                        $pct = $max_hour_count > 0 ? round(($cnt / $max_hour_count) * 100) : 0;
                        $hour_label = sprintf('%02d:00', (int) $bucket['hour']);
                    ?>
                    <div class="qrm-hour-row">
                        <span class="qrm-hour-label"><?php echo esc_html($hour_label); ?></span>
                        <div class="qrm-hour-bar-wrap">
                            <div class="qrm-hour-bar" style="width: <?php echo esc_attr((string) max(0, min(100, $pct))); ?>%;"></div>
                        </div>
                        <span class="qrm-hour-meta">
                            <?php
                            echo esc_html(number_format_i18n($cnt));
                            if ($cnt > 0) {
                                echo ' · ' . esc_html(number_format_i18n((float) $bucket['avg'], 1));
                            }
                            ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="qrm-card">
                <h3><?php esc_html_e('Vardiya Özeti', 'qrms'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Vardiya', 'qrms'); ?></th>
                            <th><?php esc_html_e('Yorum', 'qrms'); ?></th>
                            <th><?php esc_html_e('Ort. Puan', 'qrms'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shift_rows as $shift): ?>
                        <tr>
                            <td><?php echo esc_html($shift['name']); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $shift['count'])); ?></td>
                            <td><?php echo (int) $shift['count'] > 0 ? esc_html(number_format_i18n((float) $shift['avg'], 1)) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description" style="margin-top:12px;">
                    <a href="<?php echo esc_url(qrm_pro_admin_url('qrms-yf-ayarlar')); ?>"><?php esc_html_e('Vardiya saatlerini düzenle', 'qrms'); ?></a>
                </p>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Liste / Rapor görünüm sekmeleri.
 *
 * @param string $active 'liste' | 'rapor'.
 * @return void
 */
function qrm_pro_admin_dashboard_view_tabs($active = 'liste') {
    $self_url   = qrm_pro_admin_url('qrms-yf-yorumlar');
    $report_url = add_query_arg(['view' => 'rapor'], $self_url);
    ?>
    <h2 class="nav-tab-wrapper qrm-dashboard-views">
        <a class="nav-tab<?php echo $active === 'liste' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url($self_url); ?>"
           <?php echo $active === 'liste' ? 'aria-current="page"' : ''; ?>>
            <?php esc_html_e('Liste', 'qrms'); ?>
        </a>
        <a class="nav-tab<?php echo $active === 'rapor' ? ' nav-tab-active' : ''; ?>"
           href="<?php echo esc_url($report_url); ?>"
           <?php echo $active === 'rapor' ? 'aria-current="page"' : ''; ?>>
            <?php esc_html_e('Rapor', 'qrms'); ?>
        </a>
    </h2>
    <?php
}

<?php
/**
 * Kriter trend grafikleri ve düşüş uyarısı.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Düşüş uyarısı önbelleği (12 saat). */
const QRM_PRO_TREND_DROP_TRANSIENT = 'qrm_pro_trend_drop_alert';

/** Trend hesabı için asgari yorum sayısı. */
const QRM_PRO_TREND_MIN_REVIEWS = 5;

/** Haftalık trendde gösterilecek azami dönem. */
const QRM_PRO_TREND_WEEK_BUCKETS = 12;

/** Aylık trendde gösterilecek azami dönem. */
const QRM_PRO_TREND_MONTH_BUCKETS = 12;

/**
 * Düşüş uyarısı eşiği (puan).
 *
 * @param array|null $settings qrm_pro_get_settings() çıktısı.
 * @return float
 */
function qrm_pro_trend_drop_threshold($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

    $raw = isset($settings['qrm_trend_drop_threshold']) ? (float) $settings['qrm_trend_drop_threshold'] : 0.5;

    return max(0.1, min(5.0, $raw));
}

/**
 * Aktif kriterlerin listesi.
 *
 * @param array $settings Ayarlar.
 * @return array<int,array{id:int,name:string,color:string}>
 */
function qrm_pro_active_criteria_for_trend(array $settings) {
    $palette = [
        isset($settings['auto_color_1']) ? $settings['auto_color_1'] : '#2271b1',
        isset($settings['auto_color_2']) ? $settings['auto_color_2'] : '#10b981',
        isset($settings['auto_color_3']) ? $settings['auto_color_3'] : '#f59e0b',
        '#8b5cf6',
        '#ec4899',
    ];

    $out = [];
    for ($i = 1; $i <= 5; $i++) {
        if (empty($settings['crit_'.$i.'_active'])) {
            continue;
        }
        $out[$i] = [
            'id'    => $i,
            'name'  => (string) $settings['crit_'.$i.'_name'],
            'color' => $palette[count($out) % count($palette)],
        ];
    }

    return $out;
}

/**
 * Trend grafikleri için haftalık aralık (son N hafta, bitiş dahil).
 *
 * @param string $bitis_ymd Y-m-d.
 * @return array{bas_dt:string,bit_excl:string}
 */
function qrm_pro_trend_week_range($bitis_ymd) {
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

    try {
        $bitis = new DateTime($bitis_ymd, $tz);
    } catch (Exception $e) {
        $bitis = new DateTime('now', $tz);
    }

    $bas = (clone $bitis)->modify('-' . (QRM_PRO_TREND_WEEK_BUCKETS - 1) . ' weeks')->modify('monday this week');

    return [
        'bas_dt'   => $bas->format('Y-m-d 00:00:00'),
        'bit_excl' => (clone $bitis)->modify('+1 day')->format('Y-m-d 00:00:00'),
    ];
}

/**
 * Trend grafikleri için aylık aralık (son N ay, bitiş dahil).
 *
 * @param string $bitis_ymd Y-m-d.
 * @return array{bas_dt:string,bit_excl:string}
 */
function qrm_pro_trend_month_range($bitis_ymd) {
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');

    try {
        $bitis = new DateTime($bitis_ymd, $tz);
    } catch (Exception $e) {
        $bitis = new DateTime('now', $tz);
    }

    $bas = (clone $bitis)->modify('first day of this month')->modify('-' . (QRM_PRO_TREND_MONTH_BUCKETS - 1) . ' months');

    return [
        'bas_dt'   => $bas->format('Y-m-d 00:00:00'),
        'bit_excl' => (clone $bitis)->modify('+1 day')->format('Y-m-d 00:00:00'),
    ];
}

/**
 * Haftalık kriter ortalamaları — tek GROUP BY sorgusu.
 *
 * @param string $bas_dt   Dahil başlangıç.
 * @param string $bit_excl Hariç bitiş.
 * @return array<int,object>
 */
function qrm_pro_fetch_trend_weekly_rows($bas_dt, $bit_excl) {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        return [];
    }

    $table = $wpdb->prefix . 'qrm_reviews';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT
            YEARWEEK(created_at, 3) AS bucket_key,
            MIN(DATE(created_at)) AS period_start,
            COUNT(*) AS review_count,
            AVG(CASE WHEN rating_1 > 0 THEN rating_1 END) AS c1_avg,
            SUM(CASE WHEN rating_1 > 0 THEN 1 ELSE 0 END) AS c1_cnt,
            AVG(CASE WHEN rating_2 > 0 THEN rating_2 END) AS c2_avg,
            SUM(CASE WHEN rating_2 > 0 THEN 1 ELSE 0 END) AS c2_cnt,
            AVG(CASE WHEN rating_3 > 0 THEN rating_3 END) AS c3_avg,
            SUM(CASE WHEN rating_3 > 0 THEN 1 ELSE 0 END) AS c3_cnt,
            AVG(CASE WHEN rating_4 > 0 THEN rating_4 END) AS c4_avg,
            SUM(CASE WHEN rating_4 > 0 THEN 1 ELSE 0 END) AS c4_cnt,
            AVG(CASE WHEN rating_5 > 0 THEN rating_5 END) AS c5_avg,
            SUM(CASE WHEN rating_5 > 0 THEN 1 ELSE 0 END) AS c5_cnt
         FROM {$table}
         WHERE created_at >= %s AND created_at < %s
         GROUP BY YEARWEEK(created_at, 3)
         ORDER BY bucket_key ASC",
        $bas_dt,
        $bit_excl
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * Aylık kriter ortalamaları — tek GROUP BY sorgusu.
 *
 * @param string $bas_dt   Dahil başlangıç.
 * @param string $bit_excl Hariç bitiş.
 * @return array<int,object>
 */
function qrm_pro_fetch_trend_monthly_rows($bas_dt, $bit_excl) {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        return [];
    }

    $table = $wpdb->prefix . 'qrm_reviews';

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT
            DATE_FORMAT(created_at, '%%Y-%%m') AS bucket_key,
            MIN(DATE(created_at)) AS period_start,
            COUNT(*) AS review_count,
            AVG(CASE WHEN rating_1 > 0 THEN rating_1 END) AS c1_avg,
            SUM(CASE WHEN rating_1 > 0 THEN 1 ELSE 0 END) AS c1_cnt,
            AVG(CASE WHEN rating_2 > 0 THEN rating_2 END) AS c2_avg,
            SUM(CASE WHEN rating_2 > 0 THEN 1 ELSE 0 END) AS c2_cnt,
            AVG(CASE WHEN rating_3 > 0 THEN rating_3 END) AS c3_avg,
            SUM(CASE WHEN rating_3 > 0 THEN 1 ELSE 0 END) AS c3_cnt,
            AVG(CASE WHEN rating_4 > 0 THEN rating_4 END) AS c4_avg,
            SUM(CASE WHEN rating_4 > 0 THEN 1 ELSE 0 END) AS c4_cnt,
            AVG(CASE WHEN rating_5 > 0 THEN rating_5 END) AS c5_avg,
            SUM(CASE WHEN rating_5 > 0 THEN 1 ELSE 0 END) AS c5_cnt
         FROM {$table}
         WHERE created_at >= %s AND created_at < %s
         GROUP BY DATE_FORMAT(created_at, '%%Y-%%m')
         ORDER BY bucket_key ASC",
        $bas_dt,
        $bit_excl
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * SQL satırından bir kriterin dönem serisini çıkarır.
 *
 * @param array<int,object> $rows       Haftalık/aylık satırlar.
 * @param int               $criterion  1-5.
 * @param string            $mode       'week' | 'month'.
 * @return array<int,array{label:string,value:float|null,count:int}>
 */
function qrm_pro_trend_series_from_rows(array $rows, $criterion, $mode = 'week') {
    $avg_key = 'c' . (int) $criterion . '_avg';
    $cnt_key = 'c' . (int) $criterion . '_cnt';
    $series  = [];

    foreach ($rows as $row) {
        $count = isset($row->$cnt_key) ? (int) $row->$cnt_key : 0;
        $avg   = isset($row->$avg_key) ? (float) $row->$avg_key : 0.0;

        if ($mode === 'month' && !empty($row->bucket_key)) {
            $label = (string) $row->bucket_key;
        } elseif (!empty($row->period_start)) {
            $label = date_i18n('d.m', strtotime($row->period_start));
        } else {
            $label = '';
        }

        $series[] = [
            'label' => $label,
            'value' => ($count >= QRM_PRO_TREND_MIN_REVIEWS && $avg > 0) ? $avg : null,
            'count' => $count,
        ];
    }

    return $series;
}

/**
 * Son iki 7 günlük dönem için kriter ortalamalarını hesaplar (tek sorgu).
 *
 * @return array{
 *   sufficient:bool,
 *   alerts:array<int,array{id:int,name:string,prev:float,recent:float,drop:float}>,
 *   threshold:float
 * }
 */
function qrm_pro_compute_trend_drop_alerts() {
    $settings  = qrm_pro_get_settings();
    $threshold = qrm_pro_trend_drop_threshold($settings);
    $criteria  = qrm_pro_active_criteria_for_trend($settings);
    $empty     = [
        'sufficient' => false,
        'alerts'     => [],
        'threshold'  => $threshold,
    ];

    if (!qrm_pro_reviews_table_exists() || empty($criteria)) {
        return $empty;
    }

    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $now = new DateTime('now', $tz);

    $recent_end   = (clone $now)->modify('+1 day')->format('Y-m-d 00:00:00');
    $recent_start = (clone $now)->modify('-7 days')->format('Y-m-d 00:00:00');
    $prev_end     = $recent_start;
    $prev_start   = (clone $now)->modify('-14 days')->format('Y-m-d 00:00:00');

    global $wpdb;
    $table = $wpdb->prefix . 'qrm_reviews';

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT
            SUM(CASE WHEN created_at >= %s AND created_at < %s THEN 1 ELSE 0 END) AS recent_total,
            SUM(CASE WHEN created_at >= %s AND created_at < %s THEN 1 ELSE 0 END) AS prev_total,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_1 > 0 THEN rating_1 END) AS c1_recent_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_1 > 0 THEN 1 ELSE 0 END) AS c1_recent_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_1 > 0 THEN rating_1 END) AS c1_prev_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_1 > 0 THEN 1 ELSE 0 END) AS c1_prev_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_2 > 0 THEN rating_2 END) AS c2_recent_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_2 > 0 THEN 1 ELSE 0 END) AS c2_recent_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_2 > 0 THEN rating_2 END) AS c2_prev_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_2 > 0 THEN 1 ELSE 0 END) AS c2_prev_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_3 > 0 THEN rating_3 END) AS c3_recent_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_3 > 0 THEN 1 ELSE 0 END) AS c3_recent_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_3 > 0 THEN rating_3 END) AS c3_prev_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_3 > 0 THEN 1 ELSE 0 END) AS c3_prev_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_4 > 0 THEN rating_4 END) AS c4_recent_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_4 > 0 THEN 1 ELSE 0 END) AS c4_recent_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_4 > 0 THEN rating_4 END) AS c4_prev_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_4 > 0 THEN 1 ELSE 0 END) AS c4_prev_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_5 > 0 THEN rating_5 END) AS c5_recent_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_5 > 0 THEN 1 ELSE 0 END) AS c5_recent_cnt,
            AVG(CASE WHEN created_at >= %s AND created_at < %s AND rating_5 > 0 THEN rating_5 END) AS c5_prev_avg,
            SUM(CASE WHEN created_at >= %s AND created_at < %s AND rating_5 > 0 THEN 1 ELSE 0 END) AS c5_prev_cnt
         FROM {$table}
         WHERE created_at >= %s AND created_at < %s",
        $recent_start, $recent_end,
        $prev_start, $prev_end,
        $recent_start, $recent_end,
        $recent_start, $recent_end,
        $prev_start, $prev_end,
        $prev_start, $prev_end,
        $recent_start, $recent_end,
        $recent_start, $recent_end,
        $prev_start, $prev_end,
        $prev_start, $prev_end,
        $recent_start, $recent_end,
        $recent_start, $recent_end,
        $prev_start, $prev_end,
        $prev_start, $prev_end,
        $recent_start, $recent_end,
        $recent_start, $recent_end,
        $prev_start, $prev_end,
        $prev_start, $prev_end,
        $recent_start, $recent_end,
        $recent_start, $recent_end,
        $prev_start, $prev_end,
        $prev_start, $prev_end,
        $prev_start, $recent_end
    ), ARRAY_A);

    if (!is_array($row)) {
        return $empty;
    }

    $recent_total = isset($row['recent_total']) ? (int) $row['recent_total'] : 0;
    $prev_total   = isset($row['prev_total']) ? (int) $row['prev_total'] : 0;

    if ($recent_total < QRM_PRO_TREND_MIN_REVIEWS || $prev_total < QRM_PRO_TREND_MIN_REVIEWS) {
        return [
            'sufficient' => false,
            'alerts'     => [],
            'threshold'  => $threshold,
        ];
    }

    $alerts = [];

    foreach ($criteria as $id => $crit) {
        $recent_cnt = isset($row['c'.$id.'_recent_cnt']) ? (int) $row['c'.$id.'_recent_cnt'] : 0;
        $prev_cnt   = isset($row['c'.$id.'_prev_cnt']) ? (int) $row['c'.$id.'_prev_cnt'] : 0;

        if ($recent_cnt < QRM_PRO_TREND_MIN_REVIEWS || $prev_cnt < QRM_PRO_TREND_MIN_REVIEWS) {
            continue;
        }

        $recent_avg = isset($row['c'.$id.'_recent_avg']) ? (float) $row['c'.$id.'_recent_avg'] : 0.0;
        $prev_avg   = isset($row['c'.$id.'_prev_avg']) ? (float) $row['c'.$id.'_prev_avg'] : 0.0;
        $drop       = $prev_avg - $recent_avg;

        if ($drop >= $threshold) {
            $alerts[] = [
                'id'     => $id,
                'name'   => $crit['name'],
                'prev'   => $prev_avg,
                'recent' => $recent_avg,
                'drop'   => $drop,
            ];
        }
    }

    return [
        'sufficient' => true,
        'alerts'     => $alerts,
        'threshold'  => $threshold,
    ];
}

/**
 * Düşüş uyarısı durumu — 12 saatlik transient ile önbellekli.
 *
 * @param bool $taze true ise önbelleği atlar.
 * @return array{sufficient:bool,alerts:array,threshold:float}
 */
function qrm_pro_trend_drop_state($taze = false) {
    if (!$taze) {
        $cached = get_transient(QRM_PRO_TREND_DROP_TRANSIENT);
        if (is_array($cached) && isset($cached['threshold'], $cached['alerts'])) {
            return $cached;
        }
    }

    $state = qrm_pro_compute_trend_drop_alerts();
    set_transient(QRM_PRO_TREND_DROP_TRANSIENT, $state, 12 * HOUR_IN_SECONDS);

    return $state;
}

/**
 * Düşüş uyarısı önbelleğini temizler.
 *
 * @return void
 */
function qrm_pro_flush_trend_drop_cache() {
    delete_transient(QRM_PRO_TREND_DROP_TRANSIENT);
}

/**
 * Saf inline SVG çizgi grafiği.
 *
 * @param array<int,array{label:string,value:float|null,count:int}> $series Veri noktaları.
 * @param string                                                   $color  Çizgi rengi.
 * @param string                                                   $title  Grafik başlığı (erişilebilirlik).
 * @return string
 */
function qrm_pro_render_trend_svg(array $series, $color, $title = '') {
    if (empty($series)) {
        return '<p class="qrm-empty-inline">' . esc_html__('Yeterli veri yok.', 'qrms') . '</p>';
    }

    $pad_l = 36;
    $pad_r = 16;
    $pad_t = 16;
    $pad_b = 28;
    $point_w = 56;
    $height  = 160;
    $count   = count($series);
    $width   = $pad_l + $pad_r + ($count * $point_w);
    $inner_w = max(1, $width - $pad_l - $pad_r);
    $inner_h = $height - $pad_t - $pad_b;

    $y_min = 1.0;
    $y_max = 5.0;

    $points = [];
    $segments = [];
    $segment = [];

    foreach ($series as $idx => $point) {
        $x = $pad_l + ($count > 1 ? ($idx / ($count - 1)) * $inner_w : $inner_w / 2);
        $has_value = $point['value'] !== null && $point['value'] > 0;
        $y = $has_value
            ? $pad_t + (1 - (($point['value'] - $y_min) / ($y_max - $y_min))) * $inner_h
            : null;

        $points[] = [
            'x'         => $x,
            'y'         => $y,
            'label'     => $point['label'],
            'value'     => $point['value'],
            'count'     => $point['count'],
            'has_value' => $has_value,
        ];

        if ($has_value) {
            $segment[] = round($x, 2) . ',' . round($y, 2);
        } elseif (!empty($segment)) {
            $segments[] = $segment;
            $segment = [];
        }
    }

    if (!empty($segment)) {
        $segments[] = $segment;
    }

    $grid_lines = '';
    for ($g = 1; $g <= 5; $g++) {
        $gy = $pad_t + (1 - (($g - $y_min) / ($y_max - $y_min))) * $inner_h;
        $grid_lines .= '<line x1="' . (int) $pad_l . '" y1="' . round($gy, 1) . '" x2="' . (int) ($width - $pad_r) . '" y2="' . round($gy, 1) . '" class="qrm-trend-grid"/>';
        $grid_lines .= '<text x="' . (int) ($pad_l - 8) . '" y="' . round($gy + 4, 1) . '" class="qrm-trend-axis">' . (int) $g . '</text>';
    }

    $paths = '';
    foreach ($segments as $seg) {
        $paths .= '<polyline points="' . esc_attr(implode(' ', $seg)) . '" class="qrm-trend-line" style="stroke:' . esc_attr($color) . '"/>';
    }

    $dots = '';
    foreach ($points as $p) {
        $dots .= '<text x="' . round($p['x'], 1) . '" y="' . (int) ($height - 6) . '" text-anchor="middle" class="qrm-trend-label">' . esc_html($p['label']) . '</text>';
        if (!$p['has_value']) {
            continue;
        }

        $tip = sprintf(
            /* translators: 1: dönem etiketi, 2: ortalama puan, 3: yorum sayısı. */
            __('%1$s — Ort: %2$s (%3$s yorum)', 'qrms'),
            $p['label'],
            number_format_i18n((float) $p['value'], 1),
            number_format_i18n((int) $p['count'])
        );

        $dots .= '<circle cx="' . round($p['x'], 1) . '" cy="' . round($p['y'], 1) . '" r="4" class="qrm-trend-dot" style="fill:' . esc_attr($color) . '">'
            . '<title>' . esc_html($tip) . '</title></circle>';
    }

    $aria = $title !== '' ? esc_attr($title) : esc_attr__('Kriter trend grafiği', 'qrms');

    return '<svg class="qrm-trend-svg" viewBox="0 0 ' . (int) $width . ' ' . (int) $height . '" width="' . (int) $width . '" height="' . (int) $height . '" role="img" aria-label="' . $aria . '">'
        . $grid_lines . $paths . $dots
        . '</svg>';
}

/**
 * Rapor ekranında kriter trend bloğunu basar.
 *
 * @param array $settings Ayarlar.
 * @param array $range    qrm_pro_report_date_range() çıktısı.
 * @return void
 */
function qrm_pro_admin_render_trend_block(array $settings, array $range) {
    $criteria = qrm_pro_active_criteria_for_trend($settings);
    if (empty($criteria)) {
        return;
    }

    $week_range   = qrm_pro_trend_week_range($range['bit']);
    $month_range  = qrm_pro_trend_month_range($range['bit']);
    $weekly_rows  = qrm_pro_fetch_trend_weekly_rows($week_range['bas_dt'], $week_range['bit_excl']);
    $monthly_rows = qrm_pro_fetch_trend_monthly_rows($month_range['bas_dt'], $month_range['bit_excl']);
    ?>
    <div class="qrm-card qrm-trend-block">
        <h3><?php esc_html_e('Kriter Trendleri', 'qrms'); ?></h3>
        <p class="description">
            <?php
            printf(
                /* translators: %d: asgari yorum sayısı. */
                esc_html__('Her dönemde en az %d puanlı yorum olmadan ortalama çizilmez. Noktaların üzerine gelince ayrıntı görünür.', 'qrms'),
                (int) QRM_PRO_TREND_MIN_REVIEWS
            );
            ?>
        </p>

        <h4 class="qrm-trend-subheading"><?php esc_html_e('Haftalık ortalama', 'qrms'); ?></h4>
        <div class="qrm-trend-grid">
            <?php foreach ($criteria as $id => $crit):
                $series = qrm_pro_trend_series_from_rows($weekly_rows, $id, 'week');
                $has_any = false;
                foreach ($series as $pt) {
                    if ($pt['value'] !== null) {
                        $has_any = true;
                        break;
                    }
                }
            ?>
            <div class="qrm-trend-chart-card">
                <div class="qrm-trend-chart-title"><?php echo esc_html($crit['name']); ?></div>
                <div class="qrm-trend-scroll">
                    <?php
                    if (!$has_any) {
                        echo '<p class="qrm-empty-inline">' . esc_html__('Yeterli veri yok.', 'qrms') . '</p>';
                    } else {
                        echo qrm_pro_render_trend_svg(
                            $series,
                            $crit['color'],
                            sprintf(
                                /* translators: %s: kriter adı. */
                                __('%s — haftalık trend', 'qrms'),
                                $crit['name']
                            )
                        );
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <h4 class="qrm-trend-subheading"><?php esc_html_e('Aylık ortalama', 'qrms'); ?></h4>
        <div class="qrm-trend-grid">
            <?php foreach ($criteria as $id => $crit):
                $series = qrm_pro_trend_series_from_rows($monthly_rows, $id, 'month');
                $has_any = false;
                foreach ($series as $pt) {
                    if ($pt['value'] !== null) {
                        $has_any = true;
                        break;
                    }
                }
            ?>
            <div class="qrm-trend-chart-card">
                <div class="qrm-trend-chart-title"><?php echo esc_html($crit['name']); ?></div>
                <div class="qrm-trend-scroll">
                    <?php
                    if (!$has_any) {
                        echo '<p class="qrm-empty-inline">' . esc_html__('Yeterli veri yok.', 'qrms') . '</p>';
                    } else {
                        echo qrm_pro_render_trend_svg(
                            $series,
                            $crit['color'],
                            sprintf(
                                /* translators: %s: kriter adı. */
                                __('%s — aylık trend', 'qrms'),
                                $crit['name']
                            )
                        );
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Düşüş uyarısı kutusu (rapor üstü).
 *
 * @param array $drop_state qrm_pro_trend_drop_state() çıktısı.
 * @return void
 */
function qrm_pro_admin_render_trend_drop_notice(array $drop_state) {
    if (empty($drop_state['alerts'])) {
        return;
    }

    $threshold = isset($drop_state['threshold']) ? (float) $drop_state['threshold'] : 0.5;
    ?>
    <div class="notice notice-warning qrm-trend-drop-notice">
        <p><strong><?php esc_html_e('Kriter puanında düşüş tespit edildi', 'qrms'); ?></strong></p>
        <ul class="qrm-trend-drop-list">
            <?php foreach ($drop_state['alerts'] as $alert): ?>
            <li>
                <?php
                printf(
                    /* translators: 1: kriter adı, 2: önceki ortalama, 3: son ortalama, 4: düşüş miktarı. */
                    esc_html__('"%1$s" son 7 günde %2$s → %3$s (▼ %4$s puan)', 'qrms'),
                    esc_html($alert['name']),
                    esc_html(number_format_i18n((float) $alert['prev'], 1)),
                    esc_html(number_format_i18n((float) $alert['recent'], 1)),
                    esc_html(number_format_i18n((float) $alert['drop'], 1))
                );
                ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="description">
            <?php
            printf(
                /* translators: %s: eşik değeri. */
                esc_html__('Uyarı eşiği: %s puan. Son iki 7 günlük dönemde her kriter için en az %d yorum gerekir.', 'qrms'),
                esc_html(number_format_i18n($threshold, 1)),
                (int) QRM_PRO_TREND_MIN_REVIEWS
            );
            ?>
        </p>
    </div>
    <?php
}

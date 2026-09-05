<?php
if (!defined('ABSPATH')) exit;

// 9. FRONT-END: KISA KOD EKRANI VE KAYIT İŞLEMİ (Normal yorum formu + yorumlar listesi)
add_shortcode('qr_menu_reviews', 'qrm_pro_shortcode');
function qrm_pro_shortcode() {
    global $wpdb;
    $table_fields = $wpdb->prefix . 'qrm_form_fields';
    $settings = qrm_pro_get_settings();
    $gonderim = qrm_pro_process_classic_form_submission($settings, 'review');
    $message = $gonderim['message'];
    $show_google_cta = $gonderim['show_google_cta'];
    $cta_avg = $gonderim['cta_avg'];
    $auto_open_reward = $gonderim['auto_open_reward'];

    $active_fields = $wpdb->get_results("SELECT * FROM $table_fields WHERE is_active = 1 ORDER BY sort_order ASC");

    // Sorgu GERÇEKTEN sayfalıdır: sonraki sayfaları "Daha Fazla Göster"
    // butonu qrm_load_reviews ucundan ister. Eskiden tüm onaylı yorumlar
    // çekilip basılıyor, ayar yalnızca JS ile gizleme yapıyordu.
    $js_limit         = qrm_pro_reviews_page_size($settings);
    $list_query       = qrm_pro_sanitize_reviews_list_query($_GET);
    $pagination_mode  = qrm_pro_reviews_pagination_mode($settings);
    $initial_offset   = ($pagination_mode === 'pages') ? (max(1, (int) $list_query['page']) - 1) * $js_limit : 0;
    $first_page       = qrm_pro_fetch_approved_reviews($js_limit, $initial_offset, $list_query);
    $list_built       = qrm_pro_build_reviews_list_response($first_page, $list_query, $settings, $js_limit, $pagination_mode);
    $has_more_reviews = $list_built['has_more'] && $pagination_mode === 'loadmore';

    // "N Değerlendirme" sayacı artık çekilen satır sayısından okunamaz.
    //
    // Sayaç ve ortalamalar TEK sorgudan, üstelik önbellekten gelir: eskiden bu
    // kısa kod her ziyaretçi için ayrı ayrı bir COUNT, bir genel AVG ve beş
    // kriter AVG'si (toplam yedi aggregate) çalıştırıyordu. Menü sayfası
    // yoğun saatte saniyede onlarca kez açıldığında bağlantı havuzunu tüketen
    // asıl yük buydu.
    $stats         = qrm_pro_review_stats();
    $total_reviews = $stats['approved'];

    ob_start();
    echo qrm_pro_render_style_block($settings);
    ?>
    <div class="qrm-wrap-full">

        <?php
        $show_stats = isset($settings['show_overall_stats']) ? $settings['show_overall_stats'] : 1;
        if ($show_stats && $total_reviews > 0):
            $global_avg = $stats['avg'];
        ?>
        <div class="qrm-stats-panel">
            <div class="qrm-global-score">
                <div class="big-num"><?php echo number_format((float)$global_avg, 1); ?></div>
                <div class="stars">
                    <?php
                        $int_star = round($global_avg);
                        echo str_repeat('★', $int_star) . str_repeat('☆', 5 - $int_star);
                    ?>
                </div>
                <div class="total-count"><?php echo esc_html(sprintf(qrm_ceviri_review(__('%d Değerlendirme', 'qrms')), (int) $total_reviews)); ?></div>
            </div>
            <?php if ((isset($settings['rating_display_mode']) ? $settings['rating_display_mode'] : 'breakdown') === 'breakdown') : ?>
            <div class="qrm-crit-bars">
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    if ($settings['crit_'.$i.'_active']) {
                        $c_name = qrm_ceviri_option('qrm_settings.crit_'.$i.'_name', $settings['crit_'.$i.'_name']);
                        $c_avg  = isset($stats['crit'][$i]) ? (float) $stats['crit'][$i] : 0.0;
                        if ($c_avg > 0) {
                            $pct = ($c_avg / 5) * 100;
                            echo '<div class="qrm-crit-bar-row">
                                    <div class="qrm-crit-bar-label">'.esc_html($c_name).'</div>
                                    <div class="qrm-crit-bar-track"><div class="qrm-crit-bar-fill" style="width:'.esc_attr($pct).'%;"></div></div>
                                    <div class="qrm-crit-bar-val">'.number_format((float)$c_avg, 1).'</div>
                                  </div>';
                        }
                    }
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php echo qrm_pro_render_review_form($settings, $active_fields, $message, $show_google_cta, $cta_avg, 'review', $auto_open_reward); ?>

        <?php echo qrm_pro_render_reviews_list_controls($settings, $list_query); ?>

        <div class="qrm-reviews-grid" id="qrm-reviews-container">
            <?php echo $list_built['html']; ?>
        </div>

        <?php if ($pagination_mode === 'loadmore' && $has_more_reviews): ?>
        <div class="qrm-load-more-wrap" id="qrm-load-more-wrap">
            <button id="qrm-load-more" class="qrm-load-more-btn"><?php echo esc_html(qrm_ceviri_review(__('Daha Fazla Göster', 'qrms'))); ?></button>
        </div>
        <?php endif; ?>

        <?php if ($pagination_mode === 'pages'): ?>
        <div id="qrm-reviews-pagination-wrap">
            <?php echo $list_built['pagination_html']; ?>
        </div>
        <?php endif; ?>

    </div>

    <?php echo qrm_pro_render_review_media_lightbox(); ?>

    <?php
    echo qrm_pro_render_form_script($settings, $js_limit, true, [
        'list_query'       => $list_query,
        'pagination_mode'=> $pagination_mode,
        'media_enabled'  => qrm_pro_media_is_enabled($settings),
    ]);
    return ob_get_clean();
}

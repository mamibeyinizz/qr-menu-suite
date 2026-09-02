<?php
if (!defined('ABSPATH')) exit;

// 9. FRONT-END: KISA KOD EKRANI VE KAYIT İŞLEMİ (Normal yorum formu + yorumlar listesi)
add_shortcode('qr_menu_reviews', 'qrm_pro_shortcode');
function qrm_pro_shortcode() {
    global $wpdb;
    $table_fields = $wpdb->prefix . 'qrm_form_fields';
    $settings = qrm_pro_get_settings();
    $message = '';
    $show_google_cta = false;
    $cta_avg = 0;
    $auto_open_reward = false;

    // FORM GÖNDERİMİ İŞLEME (JS kapalıysa devreye giren klasik yol)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['qrm_review_submit']) && wp_verify_nonce($_POST['qrm_review_nonce'], 'qrm_submit_review')) {
        $result = qrm_pro_handle_review_submission($settings);
        if ($result['success']) {
            if (!empty($result['show_google'])) {
                $show_google_cta = true;
                $cta_avg = $result['avg'];
            } else {
                $message = '<div class="qrm-alert qrm-success">' . esc_html($result['message']) . '</div>';
                // v4.1.0: Ödül modülü açıksa popup sayfa yüklenir yüklenmez gösterilir.
                // Popup JS gerektirdiği için, JS tamamen kapalı ziyaretçilere eski
                // satır içi Google CTA'sı <noscript> içinde yedek olarak sunulur.
                if (!empty($result['show_reward'])) {
                    // JS kapalı akışta popup'ı sunucu açar; kod talebini
                    // yetkilendiren anahtar da bu yüzden popup'a gömülür.
                    $auto_open_reward = [
                        'review_id' => (int) $result['review_id'],
                        'claim'     => (string) $result['reward_claim'],
                    ];
                    $message .= '<noscript>' . qrm_pro_render_google_cta($settings, $result['avg']) . '</noscript>';
                }
            }
        } else {
            $message = '<div class="qrm-alert qrm-error">' . esc_html($result['message']) . '</div>';
        }
    }

    $active_fields = $wpdb->get_results("SELECT * FROM $table_fields WHERE is_active = 1 ORDER BY sort_order ASC");

    // Sorgu GERÇEKTEN sayfalıdır: sonraki sayfaları "Daha Fazla Göster"
    // butonu qrm_load_reviews ucundan ister. Eskiden tüm onaylı yorumlar
    // çekilip basılıyor, ayar yalnızca JS ile gizleme yapıyordu.
    $js_limit         = qrm_pro_reviews_page_size($settings);
    $first_page       = qrm_pro_fetch_approved_reviews($js_limit, 0);
    $approved_reviews = $first_page['rows'];
    $has_more_reviews = $first_page['has_more'];

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
        </div>
        <?php endif; ?>

        <?php echo qrm_pro_render_review_form($settings, $active_fields, $message, $show_google_cta, $cta_avg, 'review', $auto_open_reward); ?>

        <div class="qrm-reviews-grid" id="qrm-reviews-container">
            <?php if ($approved_reviews): ?>
                <?php echo qrm_pro_render_review_cards($approved_reviews); ?>
            <?php else: ?>
                <div class="qrm-empty-state"><?php echo esc_html(qrm_ceviri_review(__('Henüz yayınlanmış bir değerlendirme yok. İlk yorumu siz bırakın!', 'qrms'))); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($has_more_reviews): ?>
        <div class="qrm-load-more-wrap">
            <button id="qrm-load-more" class="qrm-load-more-btn"><?php echo esc_html(qrm_ceviri_review(__('Daha Fazla Göster', 'qrms'))); ?></button>
        </div>
        <?php endif; ?>

    </div>

    <?php echo qrm_pro_render_review_media_lightbox(); ?>

    <?php
    echo qrm_pro_render_form_script($settings, $js_limit, true);
    return ob_get_clean();
}

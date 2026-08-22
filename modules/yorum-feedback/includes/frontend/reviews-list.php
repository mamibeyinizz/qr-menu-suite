<?php
/**
 * Yorum listesi: sayfalama, veri erişimi ve tek kart çıktısı.
 *
 * v4.2.2 — Eskiden kısa kod TÜM onaylı yorumları tek sorguyla çekiyor ve
 * hepsini HTML'e basıyordu; "Sayfada Gösterilecek Yorum" ayarı yalnızca
 * JavaScript ile gizleme yapıyordu. Yani ayar performans değil görünüm
 * ayarıydı: 5.000 yorumlu bir restoranda her ziyaretçi 5.000 satırı çekip
 * 5.000 kart indiriyordu ve belirli bir noktada PHP bellek limiti aşılıp
 * sayfa tamamen çöküyordu.
 *
 * Artık sorgu gerçekten LIMIT'li; "Daha Fazla Göster" DOM'da gizlenmiş
 * kartları açmak yerine sunucudan bir sonraki sayfayı ister.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) exit;

/**
 * Bir sayfada çekilecek yorum sayısı.
 *
 * Ayar 'all' ise sayfa boyutu üst sınıra çekilir — "tümü" seçeneği artık
 * "sınırsız sorgu" değil, "büyük sayfalarla sayfala" anlamına gelir. Sorgu
 * her koşulda sınırlı kalır.
 *
 * @param array|null $settings Eklenti ayarları.
 * @return int
 */
function qrm_pro_reviews_page_size($settings = null) {
    if ($settings === null) $settings = qrm_pro_get_settings();

    $raw = isset($settings['reviews_per_page']) ? $settings['reviews_per_page'] : '3';

    /**
     * Tek sorguda çekilebilecek azami yorum sayısı.
     *
     * @param int $max
     */
    $max = (int) apply_filters('qrm_reviews_max_page_size', 50);
    $max = max(1, $max);

    if ($raw === 'all') {
        return $max;
    }

    $size = intval($raw);
    if ($size < 1) $size = 3;

    return min($size, $max);
}

/**
 * Onaylı yorumların bir sayfasını çeker.
 *
 * İstenen sayıdan bir fazlası çekilir: fazladan satır yalnızca "daha var mı?"
 * sorusunu cevaplamak içindir, döndürülen listeye girmez. Böylece ayrı bir
 * COUNT sorgusu olmadan sayfa sonu tespit edilir.
 *
 * @param int $limit  Sayfa boyutu.
 * @param int $offset Atlanacak satır sayısı.
 * @return array{rows:array,has_more:bool}
 */
function qrm_pro_fetch_approved_reviews($limit, $offset = 0) {
    global $wpdb;

    $table  = $wpdb->prefix . 'qrm_reviews';
    $limit  = max(1, (int) $limit);
    $offset = max(0, (int) $offset);

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = 1 ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        $limit + 1,
        $offset
    ));

    $rows     = is_array($rows) ? $rows : [];
    $has_more = count($rows) > $limit;

    if ($has_more) {
        array_pop($rows);
    }

    return ['rows' => $rows, 'has_more' => $has_more];
}

/**
 * Onaylı yorum sayısı.
 *
 * Sorgu artık LIMIT'li olduğu için "N Değerlendirme" sayacı çekilen satır
 * sayısından okunamaz; toplam ayrıca sorulur. status sütunu indekslidir.
 *
 * @return int
 */
function qrm_pro_count_approved_reviews() {
    global $wpdb;

    $table = $wpdb->prefix . 'qrm_reviews';

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 1");
}

/**
 * Tek bir yorum kartının HTML'i.
 *
 * Kısa kod ilk sayfayı, AJAX ucu sonraki sayfaları basar; ikisi de buradan
 * geçer ki kart biçimi tek yerde dursun.
 *
 * @param object $review Yorum satırı.
 * @return string
 */
function qrm_pro_render_review_card($review) {
    $display_name = $review->is_anonymous ? 'Anonim Misafir' : esc_html($review->customer_name);
    if (empty($display_name)) $display_name = 'Misafir';

    $int_r = (int) round($review->rating);
    $int_r = max(0, min(5, $int_r));

    ob_start();
    ?>
    <div class="qrm-review-item">
        <div class="qrm-review-header">
            <div class="qrm-review-who">
                <?php echo qrm_pro_avatar_html($review->is_anonymous ? 'A' : $review->customer_name); ?>
                <div>
                    <span class="qrm-reviewer-name"><?php echo $display_name; ?></span>
                    <span class="qrm-review-stars">
                        <?php echo str_repeat('★', $int_r) . str_repeat('☆', 5 - $int_r); ?>
                        <span style="font-size:12px; opacity:0.6;">(<?php echo number_format($review->rating, 1); ?>)</span>
                    </span>
                </div>
            </div>
            <span class="qrm-review-date"><?php echo esc_html(date('d.m.Y', strtotime($review->created_at))); ?></span>
        </div>
        <p class="qrm-review-text"><?php echo nl2br(esc_html($review->comment)); ?></p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Bir yorum listesini karta çevirir.
 *
 * @param array $rows Yorum satırları.
 * @return string
 */
function qrm_pro_render_review_cards($rows) {
    $html = '';

    foreach ((array) $rows as $review) {
        $html .= qrm_pro_render_review_card($review);
    }

    return $html;
}

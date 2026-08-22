<?php
/**
 * AJAX: qrm_load_reviews — yorum listesinin sonraki sayfası.
 *
 * "Daha Fazla Göster" eskiden DOM'da zaten basılmış ve CSS ile gizlenmiş
 * kartları açıyordu; yani bütün yorumlar ilk yüklemede indirilmiş oluyordu.
 * Artık her sayfa sunucudan ayrı istenir ve sorgu her koşulda LIMIT'lidir.
 *
 * Uç herkese açıktır — sunduğu veri (onaylı yorumlar) zaten kısa kodun
 * ön yüzde herkese bastığı veridir. Yine de üç sınır uygulanır:
 *   - nonce (önbelleklenmiş sayfalarda eskiyebileceği için "yumuşak" değil,
 *     gerçek kontrol: bu uç menü gibi kritik bir akışın parçası değil),
 *   - sayfa boyutu ayardan gelir, istemciden değil,
 *   - offset üst sınırla kapatılır.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_qrm_load_reviews', 'qrm_pro_ajax_load_reviews');
add_action('wp_ajax_nopriv_qrm_load_reviews', 'qrm_pro_ajax_load_reviews');

/**
 * Sonraki yorum sayfasını döndürür.
 *
 * @return void
 */
function qrm_pro_ajax_load_reviews() {
    check_ajax_referer('qrm_load_reviews', 'nonce');

    $settings = qrm_pro_get_settings();

    // Sayfa boyutu AYARDAN gelir: istemcinin "bana 100000 tane ver" demesinin
    // önü kapalı.
    $page_size = qrm_pro_reviews_page_size($settings);

    $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

    /**
     * Sayfalamanın ulaşabileceği azami derinlik.
     *
     * Büyük OFFSET değerleri MySQL'de atlanan satırları da taradığı için
     * pahalıdır; sonsuz kaydırma bir tarama vektörüne dönüşmesin diye
     * derinlik kapatılır.
     *
     * @param int $max
     */
    $max_offset = (int) apply_filters('qrm_reviews_max_offset', 2000);

    if ($offset > $max_offset) {
        wp_send_json_success(['html' => '', 'has_more' => false]);
    }

    $page = qrm_pro_fetch_approved_reviews($page_size, $offset);

    wp_send_json_success([
        'html'     => qrm_pro_render_review_cards($page['rows']),
        'has_more' => $page['has_more'],
        'count'    => count($page['rows']),
    ]);
}

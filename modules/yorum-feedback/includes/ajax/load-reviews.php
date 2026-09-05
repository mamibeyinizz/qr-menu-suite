<?php
/**
 * AJAX: qrm_load_reviews — yorum listesi (filtre, sıralama, sayfalama).
 *
 * v4.2.9 — Sıralama ve filtre parametreleri sunucuda beyaz listeyle doğrulanır.
 * load-more (offset) veya sayfa numaraları (page) modları desteklenir.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_qrm_load_reviews', 'qrm_pro_ajax_load_reviews');
add_action('wp_ajax_nopriv_qrm_load_reviews', 'qrm_pro_ajax_load_reviews');

/**
 * Yorum listesi sayfasını döndürür.
 *
 * @return void
 */
function qrm_pro_ajax_load_reviews() {
    check_ajax_referer('qrm_load_reviews', 'nonce');
    qrm_pro_bootstrap_lang();

    $settings  = qrm_pro_get_settings();
    $page_size = qrm_pro_reviews_page_size($settings);
    $mode      = qrm_pro_reviews_pagination_mode($settings);

    $source = array_merge(
        isset($_GET) && is_array($_GET) ? $_GET : [],
        isset($_POST) && is_array($_POST) ? $_POST : []
    );

    $query = qrm_pro_sanitize_reviews_list_query($source);

    if ($mode === 'pages') {
        $page   = max(1, (int) $query['page']);
        $offset = ($page - 1) * $page_size;
    } else {
        $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

        /**
         * Sayfalamanın ulaşabileceği azami derinlik.
         *
         * @param int $max
         */
        $max_offset = (int) apply_filters('qrm_reviews_max_offset', 2000);

        if ($offset > $max_offset) {
            wp_send_json_success([
                'html'            => '',
                'pagination_html' => '',
                'has_more'        => false,
                'count'           => 0,
                'total'           => 0,
                'page'            => 1,
                'total_pages'     => 0,
            ]);
        }

        if ($offset === 0) {
            $query['page'] = 1;
        }
    }

    $page_result = qrm_pro_fetch_approved_reviews($page_size, $offset, $query);
    $total       = qrm_pro_count_filtered_approved_reviews($query);
    $built       = qrm_pro_build_reviews_list_response($page_result, $query, $settings, $page_size, $mode, $total);

    wp_send_json_success([
        'html'            => $built['html'],
        'pagination_html' => $built['pagination_html'],
        'has_more'        => $built['has_more'],
        'count'           => count($page_result['rows']),
        'total'           => $built['total'],
        'page'            => $built['page'],
        'total_pages'     => $built['total_pages'],
        'filtered'        => $built['filtered'],
        'pagination_mode' => $built['pagination_mode'],
    ]);
}

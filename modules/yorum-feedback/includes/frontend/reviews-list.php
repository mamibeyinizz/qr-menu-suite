<?php
/**
 * Yorum listesi: filtre, sıralama, sayfalama, veri erişimi ve kart çıktısı.
 *
 * v4.2.9 — Ön yüz listesine sıralama (en yeni / en eski / puan), yıldız ve
 * fotoğraflı filtreleri ile load-more veya sayfa numaraları eklendi. Tüm
 * parametreler sunucuda beyaz listeyle doğrulanır; ORDER BY sabit eşlemeden gelir.
 *
 * @package QR_Menu_Reviews
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Geçerli sıralama anahtarları.
 *
 * @return array<string,string> anahtar => etiket (çeviri öncesi Türkçe).
 */
function qrm_pro_reviews_sort_options() {
    return [
        'newest'      => __('En yeni', 'qrms'),
        'oldest'      => __('En eski', 'qrms'),
        'rating_high' => __('En yüksek puan', 'qrms'),
        'rating_low'  => __('En düşük puan', 'qrms'),
    ];
}

/**
 * Sıralama anahtarından sabit ORDER BY ifadesi (kullanıcı girdisi birleştirilmez).
 *
 * @param string $sort Beyaz listedeki anahtar.
 * @return string
 */
function qrm_pro_reviews_order_sql($sort) {
    $map = [
        'newest'      => 'r.created_at DESC, r.id DESC',
        'oldest'      => 'r.created_at ASC, r.id ASC',
        'rating_high' => 'r.rating DESC, r.id DESC',
        'rating_low'  => 'r.rating ASC, r.id DESC',
    ];

    return isset($map[$sort]) ? $map[$sort] : $map['newest'];
}

/**
 * İstekten yorum listesi sorgu parametrelerini doğrular.
 *
 * @param array|null $source $_GET / $_POST benzeri dizi; null ise $_GET okunur.
 * @return array{sort:string,star:int,photos_only:bool,page:int}
 */
function qrm_pro_sanitize_reviews_list_query($source = null) {
    if ($source === null) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- ön yüz filtreleri.
        $source = $_GET;
    }

    if (!is_array($source)) {
        $source = [];
    }

    $sort = 'newest';
    if (isset($source['qrm_sort'])) {
        $raw = sanitize_key((string) wp_unslash($source['qrm_sort']));
        if (array_key_exists($raw, qrm_pro_reviews_sort_options())) {
            $sort = $raw;
        }
    }

    $star = 0;
    if (isset($source['qrm_star'])) {
        $candidate = absint($source['qrm_star']);
        if ($candidate >= 1 && $candidate <= 5) {
            $star = $candidate;
        }
    }

    $photos_only = isset($source['qrm_photos']) && (string) $source['qrm_photos'] !== '' && (string) $source['qrm_photos'] !== '0';
    if (!qrm_pro_media_is_enabled()) {
        $photos_only = false;
    }

    $page = 1;
    if (isset($source['qrm_page'])) {
        $page = max(1, absint($source['qrm_page']));
    }

    return [
        'sort'        => $sort,
        'star'        => $star,
        'photos_only' => $photos_only,
        'page'        => $page,
    ];
}

/**
 * Aktif filtre var mı?
 *
 * @param array $query qrm_pro_sanitize_reviews_list_query() çıktısı.
 * @return bool
 */
function qrm_pro_reviews_list_has_filters(array $query) {
    return !empty($query['photos_only']) || (!empty($query['star']) && (int) $query['star'] >= 1);
}

/**
 * WHERE parçalarını ve prepare parametrelerini üretir.
 *
 * @param array $query Doğrulanmış sorgu.
 * @return array{0:string,1:array<int,mixed>}
 */
function qrm_pro_reviews_list_where_parts(array $query) {
    $parts  = ['r.status = 1'];
    $params = [];

    if (!empty($query['star']) && (int) $query['star'] >= 1 && (int) $query['star'] <= 5) {
        $parts[]  = 'ROUND(r.rating) = %d';
        $params[] = (int) $query['star'];
    }

    if (!empty($query['photos_only']) && qrm_pro_media_is_enabled()) {
        $media_table = qrm_review_media_table();
        $parts[]     = "EXISTS (SELECT 1 FROM {$media_table} m WHERE m.review_id = r.id)";
    }

    return [implode(' AND ', $parts), $params];
}

/**
 * Bir sayfada çekilecek yorum sayısı.
 *
 * @param array|null $settings Eklenti ayarları.
 * @return int
 */
function qrm_pro_reviews_page_size($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

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
    if ($size < 1) {
        $size = 3;
    }

    return min($size, $max);
}

/**
 * Sayfalama modu: loadmore | pages.
 *
 * @param array|null $settings Eklenti ayarları.
 * @return string
 */
function qrm_pro_reviews_pagination_mode($settings = null) {
    if ($settings === null) {
        $settings = qrm_pro_get_settings();
    }

    $mode = isset($settings['qrm_reviews_pagination_mode'])
        ? sanitize_key((string) $settings['qrm_reviews_pagination_mode'])
        : 'loadmore';

    return in_array($mode, ['loadmore', 'pages'], true) ? $mode : 'loadmore';
}

/**
 * Filtreli onaylı yorumların bir sayfasını çeker.
 *
 * @param int        $limit  Sayfa boyutu.
 * @param int        $offset Atlanacak satır.
 * @param array|null $query  Doğrulanmış sorgu; null ise varsayılanlar.
 * @return array{rows:array,has_more:bool,total:int}
 */
function qrm_pro_fetch_approved_reviews($limit, $offset = 0, $query = null) {
    global $wpdb;

    if ($query === null) {
        $query = qrm_pro_sanitize_reviews_list_query([]);
    }

    $table  = $wpdb->prefix . 'qrm_reviews';
    $limit  = max(1, (int) $limit);
    $offset = max(0, (int) $offset);

    list($where_sql, $where_params) = qrm_pro_reviews_list_where_parts($query);
    $order_sql                      = qrm_pro_reviews_order_sql($query['sort']);

    $sql = "SELECT r.* FROM {$table} r WHERE {$where_sql} ORDER BY {$order_sql} LIMIT %d OFFSET %d";
    $params = array_merge($where_params, [$limit + 1, $offset]);

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

    $rows     = is_array($rows) ? $rows : [];
    $has_more = count($rows) > $limit;

    if ($has_more) {
        array_pop($rows);
    }

    $total = qrm_pro_count_filtered_approved_reviews($query);

    return [
        'rows'     => $rows,
        'has_more' => $has_more,
        'total'    => $total,
    ];
}

/**
 * Filtreli onaylı yorum sayısı.
 *
 * @param array|null $query Doğrulanmış sorgu.
 * @return int
 */
function qrm_pro_count_filtered_approved_reviews($query = null) {
    global $wpdb;

    if ($query === null) {
        $query = qrm_pro_sanitize_reviews_list_query([]);
    }

    $table = $wpdb->prefix . 'qrm_reviews';
    list($where_sql, $where_params) = qrm_pro_reviews_list_where_parts($query);

    $sql = "SELECT COUNT(*) FROM {$table} r WHERE {$where_sql}";

    if (!empty($where_params)) {
        $count = $wpdb->get_var($wpdb->prepare($sql, $where_params));
    } else {
        $count = $wpdb->get_var($sql);
    }

    return (int) $count;
}

/**
 * Onaylı yorum sayısı (filtresiz).
 *
 * @return int
 */
function qrm_pro_count_approved_reviews() {
    return qrm_pro_count_filtered_approved_reviews(qrm_pro_sanitize_reviews_list_query([]));
}

/**
 * Boş liste mesajı.
 *
 * @param bool $filtered Aktif filtre varken sonuç yok mu?
 * @return string
 */
function qrm_pro_render_reviews_empty_state($filtered = false) {
    if ($filtered) {
        ob_start();
        ?>
        <div class="qrm-empty-state qrm-empty-filtered">
            <p><?php echo esc_html(qrm_ceviri_review(__('Bu filtreye uygun yorum yok.', 'qrms'))); ?></p>
            <p><a href="#" class="qrm-reviews-clear-filters"><?php echo esc_html(qrm_ceviri_review(__('Filtreyi temizle', 'qrms'))); ?></a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    return '<div class="qrm-empty-state">' . esc_html(qrm_ceviri_review(__('Henüz yayınlanmış bir değerlendirme yok. İlk yorumu siz bırakın!', 'qrms'))) . '</div>';
}

/**
 * Ön yüz filtre / sıralama araç çubuğu.
 *
 * @param array $settings Eklenti ayarları.
 * @param array $query      Aktif sorgu.
 * @return string
 */
function qrm_pro_render_reviews_list_controls($settings, array $query) {
    $media_on = qrm_pro_media_is_enabled($settings);

    ob_start();
    ?>
    <div class="qrm-reviews-toolbar" id="qrm-reviews-toolbar">
        <div class="qrm-reviews-toolbar-row">
            <label class="qrm-reviews-field">
                <span class="qrm-reviews-field-label"><?php echo esc_html(qrm_ceviri_review(__('Sırala', 'qrms'))); ?></span>
                <select id="qrm-reviews-sort" class="qrm-reviews-select" aria-label="<?php esc_attr_e('Sıralama', 'qrms'); ?>">
                    <?php foreach (qrm_pro_reviews_sort_options() as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($query['sort'], $key); ?>>
                            <?php echo esc_html(qrm_ceviri_review($label)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="qrm-reviews-field">
                <span class="qrm-reviews-field-label"><?php echo esc_html(qrm_ceviri_review(__('Yıldız', 'qrms'))); ?></span>
                <select id="qrm-reviews-star" class="qrm-reviews-select" aria-label="<?php esc_attr_e('Yıldız filtresi', 'qrms'); ?>">
                    <option value="0" <?php selected((int) $query['star'], 0); ?>><?php echo esc_html(qrm_ceviri_review(__('Tümü', 'qrms'))); ?></option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo esc_attr((string) $i); ?>" <?php selected((int) $query['star'], $i); ?>>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: star count 1-5 */
                                    qrm_ceviri_review(__('%d yıldız', 'qrms')),
                                    $i
                                )
                            );
                            ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </label>

            <?php if ($media_on): ?>
            <label class="qrm-reviews-field qrm-reviews-photos-field">
                <input type="checkbox" id="qrm-reviews-photos" value="1" <?php checked(!empty($query['photos_only'])); ?>>
                <span><?php echo esc_html(qrm_ceviri_review(__('Yalnızca fotoğraflı', 'qrms'))); ?></span>
            </label>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Klasik sayfa numaraları.
 *
 * @param array $query     Aktif sorgu.
 * @param int   $total     Toplam kayıt.
 * @param int   $page_size Sayfa boyutu.
 * @return string
 */
function qrm_pro_render_reviews_pagination(array $query, $total, $page_size) {
    $total      = max(0, (int) $total);
    $page_size  = max(1, (int) $page_size);
    $page       = max(1, (int) $query['page']);
    $total_pages = (int) ceil($total / $page_size);

    if ($total_pages <= 1) {
        return '';
    }

    ob_start();
    ?>
    <nav class="qrm-reviews-pages" id="qrm-reviews-pages" aria-label="<?php esc_attr_e('Yorum sayfaları', 'qrms'); ?>">
        <?php if ($page > 1): ?>
            <button type="button" class="qrm-reviews-page-btn" data-page="<?php echo esc_attr((string) ($page - 1)); ?>" aria-label="<?php esc_attr_e('Önceki sayfa', 'qrms'); ?>">&laquo;</button>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <button type="button"
                    class="qrm-reviews-page-btn<?php echo $p === $page ? ' is-active' : ''; ?>"
                    data-page="<?php echo esc_attr((string) $p); ?>"
                    <?php echo $p === $page ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html((string) $p); ?>
            </button>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <button type="button" class="qrm-reviews-page-btn" data-page="<?php echo esc_attr((string) ($page + 1)); ?>" aria-label="<?php esc_attr_e('Sonraki sayfa', 'qrms'); ?>">&raquo;</button>
        <?php endif; ?>
    </nav>
    <?php
    return ob_get_clean();
}

/**
 * Tek bir yorum kartının HTML'i.
 *
 * @param object $review Yorum satırı.
 * @return string
 */
function qrm_pro_render_review_card($review) {
    $display_name = $review->is_anonymous ? esc_html(qrm_ceviri_review(__('Anonim Misafir', 'qrms'))) : esc_html($review->customer_name);
    if (empty($display_name)) {
        $display_name = esc_html(qrm_ceviri_review(__('Misafir', 'qrms')));
    }

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
        <?php echo qrm_pro_render_review_media_gallery((int) $review->id, (int) $review->status); ?>
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

/**
 * Liste + sayfalama sarmalayıcısı (AJAX ve kısa kod ortak).
 *
 * @param array       $page_result qrm_pro_fetch_approved_reviews() çıktısı.
 * @param array       $query       Aktif sorgu.
 * @param array       $settings    Ayarlar.
 * @param int         $page_size   Sayfa boyutu.
 * @param string|null $mode        loadmore | pages; null ise ayardan okunur.
 * @return array{html:string,pagination_html:string,has_more:bool,total:int,page:int,total_pages:int}
 */
function qrm_pro_build_reviews_list_response(array $page_result, array $query, array $settings, $page_size, $mode = null) {
    if ($mode === null) {
        $mode = qrm_pro_reviews_pagination_mode($settings);
    }

    $rows        = isset($page_result['rows']) ? $page_result['rows'] : [];
    $has_more    = !empty($page_result['has_more']);
    $total       = isset($page_result['total']) ? (int) $page_result['total'] : 0;
    $page        = max(1, (int) $query['page']);
    $page_size   = max(1, (int) $page_size);
    $total_pages = (int) ceil(max(0, $total) / $page_size);
    $filtered    = qrm_pro_reviews_list_has_filters($query);

    if (empty($rows)) {
        $html = qrm_pro_render_reviews_empty_state($filtered);
    } else {
        $html = qrm_pro_render_review_cards($rows);
    }

    $pagination_html = '';
    if ($mode === 'pages') {
        $pagination_html = qrm_pro_render_reviews_pagination($query, $total, $page_size);
    }

    return [
        'html'              => $html,
        'pagination_html'   => $pagination_html,
        'has_more'          => $has_more,
        'total'             => $total,
        'page'              => $page,
        'total_pages'       => $total_pages,
        'filtered'          => $filtered,
        'pagination_mode'   => $mode,
    ];
}

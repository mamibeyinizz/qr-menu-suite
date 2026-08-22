<?php
if (!defined('ABSPATH')) exit;

// 3. ADMİN: TÜM YORUMLAR (Puan kırılımları ile)
//
// Suite'e taşınırken "İçgörüler" JS sekmesi buradan çıkarıldı ve kendi sayfası
// oldu (qrms-yf-icgoruler). Sebep: sekmenin görünürlüğü de, hangi sekmenin aktif
// olduğu da tamamen jQuery'ye bağlıydı; sayfadaki herhangi bir JS hatasında iki
// sekme birden ölüyor ve hiçbiri aktif görünmüyordu. Ayrıca sekme, adres çubuğunu
// history.replaceState ile BAŞKA bir sayfanın adresine çeviriyordu; suite
// menüsünden açıldığında yenileme sonrası kullanıcı yanlış ekrana düşüyordu.
// Artık iki ekran da gerçek WordPress sayfası — JS'siz de tam çalışır.

/**
 * Yönetim listesinde bir sayfada gösterilecek yorum sayısı.
 *
 * @return int
 */
function qrm_pro_admin_reviews_per_page() {
    /**
     * Yönetimdeki yorum listesinin sayfa boyutu.
     *
     * @param int $per_page Varsayılan 25.
     */
    $per_page = (int) apply_filters('qrm_admin_reviews_per_page', 25);

    return max(1, min(200, $per_page));
}

/**
 * Filtreye göre toplam kayıt sayısı — EK SORGU AÇMADAN.
 *
 * Üç sayacın üçü de qrm_pro_review_stats()'in tek sorgusundan gelir; sayfalama
 * bu yüzden listeye ayrı bir COUNT eklemez.
 *
 * @param string $durum '' | 'bekleyen' | 'onayli'.
 * @param array  $stats qrm_pro_review_stats() çıktısı.
 * @return int
 */
function qrm_pro_admin_reviews_total($durum, array $stats) {
    if ($durum === 'bekleyen') return (int) $stats['pending'];
    if ($durum === 'onayli')   return (int) $stats['approved'];

    return (int) $stats['total'];
}

/**
 * İstenen sayfa numarasını geçerli aralığa çeker.
 *
 * Saf fonksiyon (WordPress'e bağımlılığı yok), bu yüzden doğrudan test edilir.
 * Elle girilmiş `&paged=9999` gibi bir değer son sayfaya iner; boş bir OFFSET
 * ile veritabanına gidilmez.
 *
 * @param int $paged    İstenen sayfa (1 tabanlı).
 * @param int $total    Toplam kayıt.
 * @param int $per_page Sayfa boyutu.
 * @return int
 */
function qrm_pro_admin_reviews_clamp_page($paged, $total, $per_page) {
    $per_page = max(1, (int) $per_page);
    $son      = max(1, (int) ceil(max(0, (int) $total) / $per_page));

    return min(max(1, (int) $paged), $son);
}

/**
 * Yorumların BİR SAYFASINI çeker.
 *
 * Eskiden burada üç dalın üçü de LIMIT'siz `SELECT *` çalıştırıyordu: binlerce
 * yorumu olan bir sitede yönetici sayfayı her açtığında tablonun tamamı
 * çekiliyor, tek bir istek veritabanı bağlantısını uzun süre meşgul ediyor ve
 * PHP bellek limitini zorluyordu.
 *
 * @param string $durum    '' | 'bekleyen' | 'onayli'.
 * @param int    $per_page Sayfa boyutu.
 * @param int    $paged    Sayfa numarası (1 tabanlı, sınırlanmış).
 * @return array
 */
function qrm_pro_admin_fetch_reviews($durum, $per_page, $paged) {
    global $wpdb;

    $table    = $wpdb->prefix . 'qrm_reviews';
    $per_page = max(1, (int) $per_page);
    $offset   = (max(1, (int) $paged) - 1) * $per_page;

    if ($durum === 'bekleyen' || $durum === 'onayli') {
        $status = ($durum === 'onayli') ? 1 : 0;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            $status,
            $per_page,
            $offset
        ));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
    }

    return is_array($rows) ? $rows : [];
}

/** Tüm Yorumlar ekranı. */
function qrm_pro_admin_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die('Bu sayfayı görüntüleme yetkiniz yok.');
    }

    $settings = qrm_pro_get_settings();
    $g_threshold = floatval($settings['google_review_threshold']);
    $self_url = qrm_pro_admin_url('qrms-yf-yorumlar');

    $notice = qrm_pro_admin_handle_review_actions();

    // Onay bekleyenlere hızlı geçiş (başlangıç ekranındaki sayaç buraya bağlanır).
    $durum = isset($_GET['durum']) ? sanitize_key($_GET['durum']) : '';
    if (!in_array($durum, ['bekleyen', 'onayli'], true)) $durum = '';

    $stats    = qrm_pro_review_stats();
    $per_page = qrm_pro_admin_reviews_per_page();
    $toplam   = qrm_pro_admin_reviews_total($durum, $stats);

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnızca sayfa numarası.
    $paged   = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;
    $paged   = qrm_pro_admin_reviews_clamp_page($paged, $toplam, $per_page);
    $reviews = [];

    if ($stats['table_ok'] && $toplam > 0) {
        $reviews = qrm_pro_admin_fetch_reviews($durum, $per_page, $paged);
    }
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1>Tüm Yorumlar</h1>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <?php if (!$stats['table_ok']): ?>
            <div class="notice notice-error">
                <p>
                    <strong>Yorum tablosu veritabanında bulunamadı.</strong>
                    Liste bu yüzden boş — gelen yorumlar kaydedilemiyor olabilir. Genel Ayarlar sayfasından
                    lisansı yeniden doğrulayın; sorun sürerse veritabanı kullanıcınızın tablo oluşturma
                    yetkisi olmayabilir.
                </p>
            </div>
        <?php endif; ?>

        <?php if ($stats['total'] > 0): ?>
            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url($self_url); ?>" <?php echo $durum === '' ? 'class="current"' : ''; ?>>
                        Tümü <span class="count">(<?php echo intval($stats['total']); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('durum', 'bekleyen', $self_url)); ?>" <?php echo $durum === 'bekleyen' ? 'class="current"' : ''; ?>>
                        Onay Bekleyen <span class="count">(<?php echo intval($stats['pending']); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('durum', 'onayli', $self_url)); ?>" <?php echo $durum === 'onayli' ? 'class="current"' : ''; ?>>
                        Yayında <span class="count">(<?php echo intval($stats['approved']); ?>)</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped qrm-table-cards">
            <thead>
                <tr>
                    <th style="width: 120px;">Tarih</th>
                    <th>Müşteri / Masa</th>
                    <th style="width: 200px;">Puan &amp; Detay</th>
                    <th>Yorum</th>
                    <th style="width: 90px;">Durum</th>
                    <th style="width: 150px;">İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                <tr class="no-items">
                    <td colspan="6" class="qrm-empty">
                        <?php if (!$stats['table_ok']): ?>
                            <strong>Liste yüklenemedi.</strong>
                            <p>Yukarıdaki veritabanı uyarısını giderdikten sonra yorumlar burada görünecek.</p>
                        <?php elseif ($durum !== ''): ?>
                            <strong>Bu filtreye uyan yorum yok.</strong>
                            <p><a href="<?php echo esc_url($self_url); ?>">Tüm yorumları göster</a></p>
                        <?php else: ?>
                            <strong>Henüz hiç yorum gelmemiş.</strong>
                            <p>
                                Müşterilerinizin yorum bırakabilmesi için <code>[qr_menu_reviews]</code> kısa kodunu
                                menü ya da değerlendirme sayfanıza ekleyin. İlk değerlendirme geldiğinde bu listede
                                görünecek ve onayınızı bekleyecek.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($reviews as $r):
                    // Satır aksiyonu, kullanıcıyı bulunduğu sayfada bıraksın.
                    $row_page = $paged > 1 ? ['paged' => $paged] : [];
                    $name_display = $r->is_anonymous ? '<em>Anonim</em>' : esc_html($r->customer_name);
                    if ($name_display === '') {
                        $name_display = '<em>İsimsiz</em>';
                    }
                    $name_display .= $r->table_no ? ' (Masa: '.esc_html($r->table_no).')' : '';
                    if (!empty($r->form_source) && $r->form_source === 'contact') {
                        $name_display .= ' <span class="qrm-google-pill qrm-source-pill">İletişim</span>';
                    }

                    // Kriter Kırılımını Hazırla
                    $breakdown = [];
                    for($i=1; $i<=5; $i++) {
                        $c_act = $settings['crit_'.$i.'_active'];
                        $c_name = $settings['crit_'.$i.'_name'];
                        $c_val = $r->{'rating_'.$i};
                        if($c_act && $c_val > 0) {
                            $breakdown[] = "{$c_name}: {$c_val}";
                        }
                    }
                    $breakdown_str = implode(', ', $breakdown);
                ?>
                <tr>
                    <td data-label="Tarih"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($r->created_at))); ?></td>
                    <td data-label="Müşteri"><?php echo $name_display; ?></td>
                    <td data-label="Puan">
                        <strong>Ort: <?php echo number_format($r->rating, 1); ?>/5</strong>
                        <?php if ($r->rating >= $g_threshold && !empty($settings['google_review_enabled'])): ?>
                            <span class="qrm-google-pill" title="Bu puan Google'a yönlendirme eşiğinin üzerinde">G Adayı</span>
                        <?php endif; ?>
                        <?php if ($breakdown_str !== ''): ?>
                            <span class="qrm-breakdown"><?php echo esc_html($breakdown_str); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Yorum" class="qrm-cell-block"><?php echo esc_html($r->comment); ?></td>
                    <td data-label="Durum">
                        <?php echo $r->status
                            ? '<span class="qrm-status-approved">Yayında</span>'
                            : '<span class="qrm-status-pending">Bekliyor</span>'; ?>
                    </td>
                    <td data-label="" class="qrm-row-actions">
                        <?php
                        $row_args = ['id' => intval($r->id)] + $row_page;
                        if ($durum !== '') $row_args['durum'] = $durum;
                        ?>
                        <?php if (!$r->status): ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'approve'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>" class="button button-small">Onayla</a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'unapprove'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>" class="button button-small">Yayından Kaldır</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>"
                           class="button button-small" style="color:#b32d2e;border-color:#d5b0b0;"
                           onclick="return confirm('Bu yorum kalıcı olarak silinsin mi?');">Sil</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
        $sayfa_sayisi = (int) ceil($toplam / $per_page);

        if ($sayfa_sayisi > 1):
            $page_args = [];
            if ($durum !== '') $page_args['durum'] = $durum;
        ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php echo esc_html(sprintf('%d kayıt', $toplam)); ?>
                    </span>
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg($page_args, $self_url) . '&paged=%#%',
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $sayfa_sayisi,
                        'current'   => $paged,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Satır aksiyonları (onayla / yayından kaldır / sil).
 *
 * Kaynakta bu aksiyonlar nonce'suz GET bağlantılarıydı: yönetici oturumu açık bir
 * tarayıcıda üçüncü bir sitenin yerleştirdiği <img src="...action=delete&id=5">
 * yorumu silmeye yetiyordu (CSRF). Bağlantılar artık wp_nonce_url ile üretilir ve
 * burada doğrulanır.
 *
 * @return string Kullanıcıya gösterilecek mesaj (yoksa boş string).
 */
function qrm_pro_admin_handle_review_actions() {
    if (!isset($_GET['action'], $_GET['id'])) return '';

    $action = sanitize_key($_GET['action']);
    $id     = intval($_GET['id']);

    if ($id <= 0 || !in_array($action, ['approve', 'unapprove', 'delete'], true)) return '';
    if (!current_user_can('manage_options')) return '';

    check_admin_referer('qrm_review_action_' . $id);

    global $wpdb;
    $table_reviews = $wpdb->prefix . 'qrm_reviews';

    if ($action === 'approve') {
        $wpdb->update($table_reviews, ['status' => 1], ['id' => $id]);
        qrm_pro_flush_review_stats();
        return 'Yorum yayınlandı.';
    }
    if ($action === 'unapprove') {
        $wpdb->update($table_reviews, ['status' => 0], ['id' => $id]);
        qrm_pro_flush_review_stats();
        return 'Yorum yayından kaldırıldı.';
    }

    $wpdb->delete($table_reviews, ['id' => $id]);
    qrm_pro_flush_review_stats();
    return 'Yorum silindi.';
}

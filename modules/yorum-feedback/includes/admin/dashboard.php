<?php
if (!defined('ABSPATH')) exit;

// 3. ADMİN: TÜM YORUMLAR (Puan kırılımları ile)
//
// Tek sayfa, üç sekme: Tüm Yorumlar / Olumlu Yorumlar / Olumsuz Yorumlar.
// Ayrım tek bir eşikten yapılır (qrm_pro_sentiment_threshold): ortalama puanı
// eşiğe eşit ya da üzerinde olan yorum olumlu, altındaki olumsuzdur — nötr
// kova yoktur, her yorum ikisinden birine düşer.
//
// Sekmeler gerçek BAĞLANTIDIR, JS değil: aktif sekme `sekme` sorgu
// parametresinde taşınır, sayfa yenilense de, satır aksiyonu çalışsa da,
// sayfalar arasında gezilse de korunur. (Kaynaktaki "İçgörüler" sekmesi bunun
// tersiydi: görünürlüğü de aktifliği de jQuery'ye bağlıydı, adres çubuğunu
// history.replaceState ile başka bir sayfanın adresine çeviriyordu ve sayfadaki
// herhangi bir JS hatasında iki sekme birden ölüyordu.)
//
// Filtreleme SQL'de yapılır: sekme de, durum filtresi de sorgunun WHERE'ine
// girer. Tablonun tamamı PHP'ye çekilip orada elenmez.

/**
 * Yorum listesinin sekmeleri — anahtar => başlık.
 *
 * Sıra sekme çubuğundaki sırayı belirler. Anahtarlar hem URL'de (`sekme=`) hem
 * de sayaç dizisinde ($stats['sentiment']) kullanılır.
 *
 * @return array<string,string>
 */
function qrm_pro_admin_review_tabs() {
    return [
        ''        => __( 'Tüm Yorumlar', 'qrms' ),
        'olumlu'  => __( 'Olumlu Yorumlar', 'qrms' ),
        'olumsuz' => __( 'Olumsuz Yorumlar', 'qrms' ),
    ];
}

/**
 * İstekteki sekmeyi geçerli bir sekme anahtarına indirger.
 *
 * Saf fonksiyon: bilinmeyen değer "tümü" sekmesine (boş string) düşer, yani
 * elle yazılmış bir `&sekme=` parametresi sorguya sızamaz.
 *
 * @param string $sekme Ham istek değeri.
 * @return string '' | 'olumlu' | 'olumsuz'
 */
function qrm_pro_admin_review_tab($sekme) {
    // `?sekme[]=x` gibi bir istek dizi taşır; metne çevrilmeden elenir.
    if (!is_scalar($sekme)) return '';

    $sekme = sanitize_key((string) $sekme);

    return array_key_exists($sekme, qrm_pro_admin_review_tabs()) ? $sekme : '';
}

/**
 * Bir sekmenin durum kırılımı (toplam / yayında / bekleyen).
 *
 * Üç sekmenin üçü de qrm_pro_review_stats()'in TEK sorgusundan beslenir;
 * sekmeye tıklamak ekstra bir COUNT sorgusu açmaz.
 *
 * @param string $sekme '' | 'olumlu' | 'olumsuz'.
 * @param array  $stats qrm_pro_review_stats() çıktısı.
 * @return array{total:int,approved:int,pending:int}
 */
function qrm_pro_admin_review_tab_counts($sekme, array $stats) {
    if ($sekme === 'olumlu' || $sekme === 'olumsuz') {
        $kirilim = isset($stats['sentiment'][$sekme]) ? $stats['sentiment'][$sekme] : [];

        return [
            'total'    => isset($kirilim['total']) ? (int) $kirilim['total'] : 0,
            'approved' => isset($kirilim['approved']) ? (int) $kirilim['approved'] : 0,
            'pending'  => isset($kirilim['pending']) ? (int) $kirilim['pending'] : 0,
        ];
    }

    return [
        'total'    => isset($stats['total']) ? (int) $stats['total'] : 0,
        'approved' => isset($stats['approved']) ? (int) $stats['approved'] : 0,
        'pending'  => isset($stats['pending']) ? (int) $stats['pending'] : 0,
    ];
}

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
 * Sayaçların tamamı qrm_pro_review_stats()'in tek sorgusundan gelir; sayfalama
 * bu yüzden listeye ayrı bir COUNT eklemez — sekme ile durum filtresi bir arada
 * kullanıldığında da.
 *
 * @param string $durum     '' | 'bekleyen' | 'onayli'.
 * @param array  $stats     qrm_pro_review_stats() çıktısı.
 * @param string $sekme     '' | 'olumlu' | 'olumsuz'.
 * @param string $wf        '' | iş akışı durumu.
 * @param array  $wf_counts qrm_pro_fetch_workflow_counts() çıktısı (isteğe bağlı).
 * @return int
 */
function qrm_pro_admin_reviews_total($durum, array $stats, $sekme = '', $wf = '', array $wf_counts = null) {
    if ($wf !== '' && is_array($wf_counts) && array_key_exists($wf, $wf_counts)) {
        return (int) $wf_counts[$wf];
    }

    if ($wf !== '' && is_array($wf_counts)) {
        return 0;
    }

    $sayaclar = qrm_pro_admin_review_tab_counts($sekme, $stats);

    if ($durum === 'bekleyen') return (int) $sayaclar['pending'];
    if ($durum === 'onayli')   return (int) $sayaclar['approved'];

    return (int) $sayaclar['total'];
}

/**
 * Liste sorgusunun WHERE parçası — sekme ve durum filtresi birlikte.
 *
 * Saf fonksiyon (WordPress'e ve $wpdb'ye bağımlılığı yok): koşul metnini
 * yer tutucularla, değerleri ayrı bir dizide döndürür; prepare çağıranın işi.
 * Hiçbir filtre yoksa boş string döner ve sorgu WHERE'siz kalır.
 *
 * @param string $durum     '' | 'bekleyen' | 'onayli'.
 * @param string $sekme     '' | 'olumlu' | 'olumsuz'.
 * @param float  $threshold Olumlu/olumsuz eşiği.
 * @param string $wf        '' | 'new' | 'read' | 'in_progress' | 'resolved'.
 * @param array  $extra     liste_bas, liste_bit, bas_dt, bit_excl, search, table_id.
 * @return array{0:string,1:array} [WHERE parçası, parametreler]
 */
function qrm_pro_admin_reviews_where($durum, $sekme, $threshold, $wf = '', array $extra = []) {
    $kosullar = [];
    $params   = [];

    if ($durum === 'bekleyen' || $durum === 'onayli') {
        $kosullar[] = 'status = %d';
        $params[]   = ($durum === 'onayli') ? 1 : 0;
    }

    // Nötr kova yok: eşiğe eşit ve üzeri olumlu, altı olumsuz. İki koşul
    // birbirinin tümleyeni olduğu için sekmelerin sayaçları toplamı her zaman
    // toplam kayıt sayısını verir.
    if ($sekme === 'olumlu' || $sekme === 'olumsuz') {
        $kosullar[] = ($sekme === 'olumlu') ? 'rating >= %f' : 'rating < %f';
        $params[]   = (float) $threshold;
    }

    if ($wf !== '' && array_key_exists($wf, qrm_pro_review_workflow_statuses())) {
        $kosullar[] = 'workflow_status = %s';
        $params[]   = $wf;
    }

    if (!empty($extra['bas_dt']) && !empty($extra['bit_excl'])) {
        $kosullar[] = 'created_at >= %s';
        $params[]   = $extra['bas_dt'];
        $kosullar[] = 'created_at < %s';
        $params[]   = $extra['bit_excl'];
    }

    if (!empty($extra['table_id'])) {
        $kosullar[] = 'table_id = %d';
        $params[]   = (int) $extra['table_id'];
    }

    if (!empty($extra['search'])) {
        global $wpdb;
        $like       = '%' . $wpdb->esc_like($extra['search']) . '%';
        $kosullar[] = '(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR comment LIKE %s OR reward_code LIKE %s OR table_no LIKE %s)';
        $params[]   = $like;
        $params[]   = $like;
        $params[]   = $like;
        $params[]   = $like;
        $params[]   = $like;
        $params[]   = $like;
    }

    return [
        $kosullar ? ' WHERE ' . implode(' AND ', $kosullar) : '',
        $params,
    ];
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
 * Sekme filtresi de aynı sorguya girer: olumlu/olumsuz ayrımı için tablonun
 * tamamı çekilip PHP'de elenmez.
 *
 * @param string     $durum     '' | 'bekleyen' | 'onayli'.
 * @param int        $per_page  Sayfa boyutu.
 * @param int        $paged     Sayfa numarası (1 tabanlı, sınırlanmış).
 * @param string     $sekme     '' | 'olumlu' | 'olumsuz'.
 * @param float|null $threshold Olumlu/olumsuz eşiği; null ise filtreden okunur.
 * @param string     $wf        '' | iş akışı durumu.
 * @param array      $extra     Tarih, arama, masa filtreleri.
 * @return array
 */
function qrm_pro_admin_fetch_reviews($durum, $per_page, $paged, $sekme = '', $threshold = null, $wf = '', array $extra = []) {
    global $wpdb;

    $table    = $wpdb->prefix . 'qrm_reviews';
    $per_page = max(1, (int) $per_page);
    $offset   = (max(1, (int) $paged) - 1) * $per_page;

    if ($threshold === null) {
        $threshold = qrm_pro_sentiment_threshold();
    }

    list($where, $params) = qrm_pro_admin_reviews_where($durum, $sekme, $threshold, $wf, $extra);

    $params[] = $per_page;
    $params[] = $offset;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}{$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        $params
    ));

    return is_array($rows) ? $rows : [];
}

/**
 * İş akışı durumları — anahtar => etiket.
 *
 * @return array<string,string>
 */
function qrm_pro_review_workflow_statuses() {
    return [
        'new'         => __( 'Yeni', 'qrms' ),
        'read'        => __( 'Okundu', 'qrms' ),
        'in_progress' => __( 'İşleme alındı', 'qrms' ),
        'resolved'    => __( 'Çözüldü', 'qrms' ),
    ];
}

/**
 * İstekteki iş akışı filtresini geçerli bir anahtara indirger.
 *
 * @param string $wf Ham istek değeri.
 * @return string '' | 'new' | 'read' | 'in_progress' | 'resolved'
 */
function qrm_pro_admin_review_workflow_filter($wf) {
    if (!is_scalar($wf)) {
        return '';
    }

    $wf = sanitize_key((string) $wf);

    return array_key_exists($wf, qrm_pro_review_workflow_statuses()) ? $wf : '';
}

/**
 * İş akışı durum sayaçları — tek GROUP BY sorgusu.
 *
 * Mevcut sekme ve yayın durumu filtresine göre kapsam daraltılır; iş akışı
 * filtresi sayaçlara dahil edilmez (alt filtreler üst filtreye göre sayılır).
 *
 * @param string     $durum     '' | 'bekleyen' | 'onayli'.
 * @param string     $sekme     '' | 'olumlu' | 'olumsuz'.
 * @param float|null $threshold Olumlu/olumsuz eşiği.
 * @param array      $extra     Tarih, arama, masa filtreleri.
 * @return array<string,int> workflow_status => adet
 */
function qrm_pro_fetch_workflow_counts($durum = '', $sekme = '', $threshold = null, array $extra = []) {
    global $wpdb;

    if (!qrm_pro_reviews_table_exists()) {
        return [];
    }

    if ($threshold === null) {
        $threshold = qrm_pro_sentiment_threshold();
    }

    $table = $wpdb->prefix . 'qrm_reviews';
    list($where, $params) = qrm_pro_admin_reviews_where($durum, $sekme, $threshold, '', $extra);

    $sql = "SELECT workflow_status, COUNT(*) AS cnt FROM {$table}{$where} GROUP BY workflow_status";

    if (!empty($params)) {
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    } else {
        $rows = $wpdb->get_results($sql, ARRAY_A);
    }

    $counts = array_fill_keys(array_keys(qrm_pro_review_workflow_statuses()), 0);

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $key = isset($row['workflow_status']) ? sanitize_key($row['workflow_status']) : '';
            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $row['cnt'];
            }
        }
    }

    return $counts;
}

/**
 * İş akışı sayaçlarından toplam kayıt sayısı.
 *
 * @param array<string,int> $wf_counts qrm_pro_fetch_workflow_counts() çıktısı.
 * @return int
 */
function qrm_pro_workflow_counts_total(array $wf_counts) {
    return (int) array_sum($wf_counts);
}

/** Tüm Yorumlar ekranı — üç sekmeli tek sayfa. */
function qrm_pro_admin_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Bu sayfayı görüntüleme yetkiniz yok.', 'qrms'));
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- görünüm seçimi.
    $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
    if ($view === 'rapor') {
        qrm_pro_admin_reports_page();
        return;
    }

    $settings = qrm_pro_get_settings();
    $g_threshold = floatval($settings['google_review_threshold']);
    $self_url = qrm_pro_admin_url('qrms-yf-yorumlar');

    $notice = qrm_pro_admin_handle_review_actions();

    // Aktif sekme adreste taşınır; yenilemede, sayfalamada ve satır aksiyonu
    // sonrasında korunur.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnızca görünüm filtresi.
    $sekme = qrm_pro_admin_review_tab(isset($_GET['sekme']) ? wp_unslash($_GET['sekme']) : '');

    // Onay bekleyenlere hızlı geçiş (başlangıç ekranındaki sayaç buraya bağlanır).
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnızca görünüm filtresi.
    $durum = (isset($_GET['durum']) && is_scalar($_GET['durum'])) ? sanitize_key(wp_unslash($_GET['durum'])) : '';
    if (!in_array($durum, ['bekleyen', 'onayli'], true)) $durum = '';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnızca görünüm filtresi.
    $wf = qrm_pro_admin_review_workflow_filter(isset($_GET['wf']) ? wp_unslash($_GET['wf']) : '');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnızca görünüm filtresi.
    $list_filters = function_exists('qrm_pro_admin_review_list_filters')
        ? qrm_pro_admin_review_list_filters($_GET)
        : ['liste_bas' => '', 'liste_bit' => '', 'search' => '', 'table_id' => 0];

    $stats     = qrm_pro_review_stats();
    $esik      = qrm_pro_sentiment_threshold();
    $per_page  = qrm_pro_admin_reviews_per_page();
    $has_list_filters = function_exists('qrm_pro_admin_review_has_list_filters')
        && qrm_pro_admin_review_has_list_filters($list_filters);
    $wf_counts = qrm_pro_fetch_workflow_counts($durum, $sekme, $esik, $has_list_filters ? $list_filters : []);

    if ($has_list_filters && function_exists('qrm_export_reviews_count')) {
        $toplam = qrm_export_reviews_count($durum, $sekme, $esik, $wf, $list_filters);
    } else {
        $toplam = qrm_pro_admin_reviews_total($durum, $stats, $sekme, $wf, $wf_counts);
    }

    $sekme_url = $sekme === '' ? $self_url : add_query_arg(['sekme' => $sekme], $self_url);
    if ($durum !== '') {
        $sekme_url = add_query_arg(['durum' => $durum], $sekme_url);
    }
    if ($list_filters['liste_bas'] !== '') {
        $sekme_url = add_query_arg(['liste_bas' => $list_filters['liste_bas']], $sekme_url);
    }
    if ($list_filters['liste_bit'] !== '') {
        $sekme_url = add_query_arg(['liste_bit' => $list_filters['liste_bit']], $sekme_url);
    }
    if ($list_filters['search'] !== '') {
        $sekme_url = add_query_arg(['s' => $list_filters['search']], $sekme_url);
    }
    if (!empty($list_filters['table_id'])) {
        $sekme_url = add_query_arg(['table_id' => (int) $list_filters['table_id']], $sekme_url);
    }

    $masa_labels = [];
    if (class_exists('QMO_Masalar') && method_exists('QMO_Masalar', 'hepsi')) {
        foreach ((array) QMO_Masalar::hepsi() as $masa) {
            if (!empty($masa->id)) {
                $masa_labels[(int) $masa->id] = (string) $masa->table_name;
            }
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- yalnızca sayfa numarası.
    $paged   = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;
    $paged   = qrm_pro_admin_reviews_clamp_page($paged, $toplam, $per_page);
    $reviews = [];

    if ($stats['table_ok'] && $toplam > 0) {
        $reviews = qrm_pro_admin_fetch_reviews($durum, $per_page, $paged, $sekme, $esik, $wf, $has_list_filters ? $list_filters : []);
    }

    $workflow_statuses = qrm_pro_review_workflow_statuses();
    $wf_total          = qrm_pro_workflow_counts_total($wf_counts);
    ?>
    <div class="wrap qrm-pro-wrap">
        <h1><?php esc_html_e('Tüm Yorumlar', 'qrms'); ?></h1>

        <?php qrm_pro_admin_dashboard_view_tabs('liste'); ?>

        <?php if ($notice !== ''): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
        <?php endif; ?>

        <?php if (!$stats['table_ok']): ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('Yorum tablosu veritabanında bulunamadı.', 'qrms'); ?></strong>
                    <?php esc_html_e('Liste bu yüzden boş — gelen yorumlar kaydedilemiyor olabilir. Genel Ayarlar sayfasından lisansı yeniden doğrulayın; sorun sürerse veritabanı kullanıcınızın tablo oluşturma yetkisi olmayabilir.', 'qrms'); ?>
                </p>
            </div>
        <?php endif; ?>

        <h2 class="nav-tab-wrapper qrm-review-tabs">
            <?php foreach (qrm_pro_admin_review_tabs() as $anahtar => $baslik):
                $sayac = qrm_pro_admin_review_tab_counts($anahtar, $stats);

                // Sekme değişince durum filtresi ve sayfa numarası sıfırlanır:
                // yeni sekmede aynı sayfa numarası var olmayabilir.
                $url = $anahtar === '' ? $self_url : add_query_arg(['sekme' => $anahtar], $self_url);
            ?>
                <a class="nav-tab<?php echo $sekme === $anahtar ? ' nav-tab-active' : ''; ?>"
                   href="<?php echo esc_url($url); ?>"
                   <?php echo $sekme === $anahtar ? 'aria-current="page"' : ''; ?>>
                    <?php echo esc_html($baslik); ?>
                    <span class="qrm-tab-count"><?php echo esc_html(number_format_i18n($sayac['total'])); ?></span>
                </a>
            <?php endforeach; ?>
        </h2>

        <?php
        $sekme_sayaclari = qrm_pro_admin_review_tab_counts($sekme, $stats);
        if ($sekme_sayaclari['total'] > 0):
        ?>
            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url($sekme_url); ?>" <?php echo $durum === '' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Tümü', 'qrms'); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($sekme_sayaclari['total'])); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg(['durum' => 'bekleyen'], $sekme_url)); ?>" <?php echo $durum === 'bekleyen' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Onay Bekleyen', 'qrms'); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($sekme_sayaclari['pending'])); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg(['durum' => 'onayli'], $sekme_url)); ?>" <?php echo $durum === 'onayli' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Yayında', 'qrms'); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($sekme_sayaclari['approved'])); ?>)</span>
                    </a>
                </li>
            </ul>
        <?php endif; ?>

        <?php if ($stats['table_ok'] && ($wf_total > 0 || $wf !== '')): ?>
            <ul class="subsubsub qrm-wf-filters">
                <li>
                    <a href="<?php echo esc_url($sekme_url); ?>" <?php echo $wf === '' ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Tümü', 'qrms'); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($wf_total)); ?>)</span>
                    </a> |
                </li>
                <?php
                $wf_i = 0;
                $wf_keys = array_keys($workflow_statuses);
                foreach ($workflow_statuses as $wf_key => $wf_label):
                    $wf_i++;
                    $wf_count = isset($wf_counts[$wf_key]) ? (int) $wf_counts[$wf_key] : 0;
                    $wf_url   = add_query_arg(['wf' => $wf_key], $sekme_url);
                ?>
                <li>
                    <a href="<?php echo esc_url($wf_url); ?>" <?php echo $wf === $wf_key ? 'class="current"' : ''; ?>>
                        <?php echo esc_html($wf_label); ?>
                        <span class="count">(<?php echo esc_html(number_format_i18n($wf_count)); ?>)</span>
                    </a><?php echo $wf_i < count($wf_keys) ? ' |' : ''; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="qrm-list-toolbar qrm-card">
            <input type="hidden" name="page" value="qrms-yf-yorumlar">
            <?php if ($sekme !== ''): ?>
                <input type="hidden" name="sekme" value="<?php echo esc_attr($sekme); ?>">
            <?php endif; ?>
            <?php if ($durum !== ''): ?>
                <input type="hidden" name="durum" value="<?php echo esc_attr($durum); ?>">
            <?php endif; ?>
            <?php if ($wf !== ''): ?>
                <input type="hidden" name="wf" value="<?php echo esc_attr($wf); ?>">
            <?php endif; ?>
            <div class="qrm-list-toolbar-row">
                <label>
                    <span class="qrm-list-toolbar-label"><?php esc_html_e('Başlangıç', 'qrms'); ?></span>
                    <input type="date" name="liste_bas" value="<?php echo esc_attr($list_filters['liste_bas']); ?>">
                </label>
                <label>
                    <span class="qrm-list-toolbar-label"><?php esc_html_e('Bitiş', 'qrms'); ?></span>
                    <input type="date" name="liste_bit" value="<?php echo esc_attr($list_filters['liste_bit']); ?>">
                </label>
                <?php if (!empty($masa_labels)): ?>
                <label>
                    <span class="qrm-list-toolbar-label"><?php esc_html_e('Masa', 'qrms'); ?></span>
                    <select name="table_id">
                        <option value="0"><?php esc_html_e('Tümü', 'qrms'); ?></option>
                        <?php foreach ($masa_labels as $tid => $mlabel): ?>
                            <option value="<?php echo esc_attr((string) $tid); ?>" <?php selected((int) $list_filters['table_id'], (int) $tid); ?>><?php echo esc_html($mlabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <label class="qrm-list-toolbar-search">
                    <span class="qrm-list-toolbar-label"><?php esc_html_e('Ara', 'qrms'); ?></span>
                    <input type="search" name="s" value="<?php echo esc_attr($list_filters['search']); ?>" placeholder="<?php esc_attr_e('Ad, e-posta, yorum…', 'qrms'); ?>">
                </label>
                <button type="submit" class="button"><?php esc_html_e('Filtrele', 'qrms'); ?></button>
                <?php
                if (function_exists('qrm_export_csv_button')) {
                    echo qrm_export_csv_button('reviews', [
                        'sekme'     => $sekme,
                        'durum'     => $durum,
                        'wf'        => $wf,
                        'liste_bas' => $list_filters['liste_bas'],
                        'liste_bit' => $list_filters['liste_bit'],
                        's'         => $list_filters['search'],
                        'table_id'  => !empty($list_filters['table_id']) ? (int) $list_filters['table_id'] : '',
                    ]);
                }
                ?>
            </div>
        </form>

        <table class="wp-list-table widefat fixed striped qrm-table-cards qrm-review-workflow-table">
            <thead>
                <tr>
                    <th style="width: 120px;"><?php esc_html_e('Tarih', 'qrms'); ?></th>
                    <th><?php esc_html_e('Müşteri / Masa', 'qrms'); ?></th>
                    <th style="width: 200px;"><?php esc_html_e('Puan & Detay', 'qrms'); ?></th>
                    <th><?php esc_html_e('Yorum', 'qrms'); ?></th>
                    <th style="width: 90px;"><?php esc_html_e('Durum', 'qrms'); ?></th>
                    <th style="width: 200px;"><?php esc_html_e('İş Akışı', 'qrms'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('İşlemler', 'qrms'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                <tr class="no-items">
                    <td colspan="7" class="qrm-empty">
                        <?php if (!$stats['table_ok']): ?>
                            <strong><?php esc_html_e('Liste yüklenemedi.', 'qrms'); ?></strong>
                            <p><?php esc_html_e('Yukarıdaki veritabanı uyarısını giderdikten sonra yorumlar burada görünecek.', 'qrms'); ?></p>
                        <?php elseif ($durum !== '' || $sekme !== '' || $wf !== '' || $has_list_filters): ?>
                            <strong><?php esc_html_e('Bu filtreye uyan yorum yok.', 'qrms'); ?></strong>
                            <p><a href="<?php echo esc_url($self_url); ?>"><?php esc_html_e('Tüm yorumları göster', 'qrms'); ?></a></p>
                        <?php else: ?>
                            <strong><?php esc_html_e('Henüz hiç yorum gelmemiş.', 'qrms'); ?></strong>
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: yorum formunun kısa kodu. */
                                    esc_html__('Müşterilerinizin yorum bırakabilmesi için %s kısa kodunu menü ya da değerlendirme sayfanıza ekleyin. İlk değerlendirme geldiğinde bu listede görünecek ve onayınızı bekleyecek.', 'qrms'),
                                    '<code>[qr_menu_reviews]</code>'
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>

                <?php foreach ($reviews as $r):
                    // Satır aksiyonu, kullanıcıyı bulunduğu sayfada bıraksın.
                    $row_page = $paged > 1 ? ['paged' => $paged] : [];
                    $name_display = $r->is_anonymous ? '<em>' . esc_html__('Anonim', 'qrms') . '</em>' : esc_html($r->customer_name);
                    if ($name_display === '') {
                        $name_display = '<em>' . esc_html__('İsimsiz', 'qrms') . '</em>';
                    }
                    if ($r->table_no) {
                        /* translators: %s: masa numarası. */
                        $name_display .= ' ' . sprintf(esc_html__('(Masa: %s)', 'qrms'), esc_html($r->table_no));
                    }
                    if (!empty($r->form_source) && $r->form_source === 'contact') {
                        $name_display .= ' <span class="qrm-google-pill qrm-source-pill">' . esc_html__('İletişim', 'qrms') . '</span>';
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

                    $wf_status = isset($r->workflow_status) ? sanitize_key($r->workflow_status) : 'new';
                    if (!array_key_exists($wf_status, $workflow_statuses)) {
                        $wf_status = 'new';
                    }
                    $assigned_id = isset($r->assigned_user_id) ? (int) $r->assigned_user_id : 0;
                    $internal_note = isset($r->internal_note) ? (string) $r->internal_note : '';
                    $has_note = $internal_note !== '';
                    $resolved_at = !empty($r->resolved_at) ? $r->resolved_at : '';
                ?>
                <tbody class="qrm-review-row-block">
                <tr class="qrm-review-row" data-review-id="<?php echo esc_attr((string) intval($r->id)); ?>">
                    <td data-label="<?php esc_attr_e('Tarih', 'qrms'); ?>"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($r->created_at))); ?></td>
                    <td data-label="<?php esc_attr_e('Müşteri', 'qrms'); ?>"><?php echo wp_kses_post($name_display); ?></td>
                    <td data-label="<?php esc_attr_e('Puan', 'qrms'); ?>">
                        <strong><?php
                            /* translators: %s: yorumun ortalama puanı. */
                            printf(esc_html__('Ort: %s/5', 'qrms'), esc_html(number_format_i18n((float) $r->rating, 1)));
                        ?></strong>
                        <?php if ($r->rating >= $g_threshold && !empty($settings['google_review_enabled'])): ?>
                            <span class="qrm-google-pill" title="<?php esc_attr_e('Bu puan Google\'a yönlendirme eşiğinin üzerinde', 'qrms'); ?>"><?php esc_html_e('G Adayı', 'qrms'); ?></span>
                        <?php endif; ?>
                        <?php if ($breakdown_str !== ''): ?>
                            <span class="qrm-breakdown"><?php echo esc_html($breakdown_str); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Yorum', 'qrms'); ?>" class="qrm-cell-block"><?php echo esc_html($r->comment); ?></td>
                    <td data-label="<?php esc_attr_e('Durum', 'qrms'); ?>">
                        <?php if ($r->status): ?>
                            <span class="qrm-status-approved"><?php esc_html_e('Yayında', 'qrms'); ?></span>
                        <?php else: ?>
                            <span class="qrm-status-pending"><?php esc_html_e('Bekliyor', 'qrms'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('İş Akışı', 'qrms'); ?>" class="qrm-wf-cell">
                        <div class="qrm-wf-controls">
                            <select class="qrm-wf-status" aria-label="<?php esc_attr_e('İş akışı durumu', 'qrms'); ?>">
                                <?php foreach ($workflow_statuses as $wf_key => $wf_label): ?>
                                    <option value="<?php echo esc_attr($wf_key); ?>" <?php selected($wf_status, $wf_key); ?>>
                                        <?php echo esc_html($wf_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php
                            $dropdown_args = [
                                'name'             => 'assigned_user_id',
                                'id'               => 'qrm-wf-assignee-' . intval($r->id),
                                'selected'         => $assigned_id,
                                'show_option_none' => __('— Atanmamış —', 'qrms'),
                                'option_none_value'=> '0',
                                'class'            => 'qrm-wf-assignee',
                                'capability'       => 'edit_posts',
                            ];
                            wp_dropdown_users($dropdown_args);
                            ?>
                            <button type="button"
                                    class="button button-small qrm-wf-note-toggle<?php echo $has_note ? ' has-note' : ''; ?>"
                                    aria-expanded="false"
                                    title="<?php esc_attr_e('İç not', 'qrms'); ?>">
                                <?php esc_html_e('Not', 'qrms'); ?>
                                <?php if ($has_note): ?><span class="qrm-wf-note-dot" aria-hidden="true"></span><?php endif; ?>
                            </button>
                        </div>
                        <span class="qrm-wf-save-status" aria-live="polite"></span>
                        <?php if ($resolved_at !== ''): ?>
                            <span class="qrm-wf-resolved-at">
                                <?php
                                /* translators: %s: çözülme tarihi. */
                                printf(esc_html__('Çözüldü: %s', 'qrms'), esc_html(date_i18n('d.m.Y H:i', strtotime($resolved_at))));
                                ?>
                            </span>
                        <?php else: ?>
                            <span class="qrm-wf-resolved-at" hidden></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="" class="qrm-row-actions">
                        <?php
                        // Aksiyon sonrası kullanıcı aynı sekmede, aynı filtrede ve
                        // aynı sayfada kalır.
                        $row_args = ['id' => intval($r->id)] + $row_page;
                        if ($durum !== '') $row_args['durum'] = $durum;
                        if ($sekme !== '') $row_args['sekme'] = $sekme;
                        if ($wf !== '') $row_args['wf'] = $wf;
                        if ($list_filters['liste_bas'] !== '') $row_args['liste_bas'] = $list_filters['liste_bas'];
                        if ($list_filters['liste_bit'] !== '') $row_args['liste_bit'] = $list_filters['liste_bit'];
                        if ($list_filters['search'] !== '') $row_args['s'] = $list_filters['search'];
                        if (!empty($list_filters['table_id'])) $row_args['table_id'] = (int) $list_filters['table_id'];
                        ?>
                        <?php if (!$r->status): ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'approve'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>" class="button button-small"><?php esc_html_e('Onayla', 'qrms'); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'unapprove'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>" class="button button-small"><?php esc_html_e('Yayından Kaldır', 'qrms'); ?></a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>"
                           class="button button-small" style="color:#b32d2e;border-color:#d5b0b0;"
                           onclick="return confirm('<?php echo esc_js(__('Bu yorum kalıcı olarak silinsin mi?', 'qrms')); ?>');"><?php esc_html_e('Sil', 'qrms'); ?></a>
                    </td>
                </tr>
                <tr class="qrm-wf-note-row" hidden>
                    <td colspan="7">
                        <label class="screen-reader-text" for="qrm-wf-note-<?php echo esc_attr((string) intval($r->id)); ?>">
                            <?php esc_html_e('İç not', 'qrms'); ?>
                        </label>
                        <textarea id="qrm-wf-note-<?php echo esc_attr((string) intval($r->id)); ?>"
                                  class="qrm-wf-note"
                                  rows="3"
                                  placeholder="<?php esc_attr_e('Yalnızca yöneticiler görür — müşteriye gösterilmez.', 'qrms'); ?>"><?php echo esc_textarea($internal_note); ?></textarea>
                    </td>
                </tr>
                </tbody>
                <?php endforeach; ?>
        </table>

        <?php
        $sayfa_sayisi = (int) ceil($toplam / $per_page);

        if ($sayfa_sayisi > 1):
            $page_args = [];
            if ($durum !== '') $page_args['durum'] = $durum;
            if ($sekme !== '') $page_args['sekme'] = $sekme;
            if ($wf !== '') $page_args['wf'] = $wf;
            if ($list_filters['liste_bas'] !== '') $page_args['liste_bas'] = $list_filters['liste_bas'];
            if ($list_filters['liste_bit'] !== '') $page_args['liste_bit'] = $list_filters['liste_bit'];
            if ($list_filters['search'] !== '') $page_args['s'] = $list_filters['search'];
            if (!empty($list_filters['table_id'])) $page_args['table_id'] = (int) $list_filters['table_id'];
        ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php
                        printf(
                            /* translators: %s: listedeki kayıt sayısı. */
                            esc_html(_n('%s kayıt', '%s kayıt', $toplam, 'qrms')),
                            esc_html(number_format_i18n($toplam))
                        );
                        ?>
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

    $action = sanitize_key(wp_unslash($_GET['action']));
    $id     = intval($_GET['id']);

    if ($id <= 0 || !in_array($action, ['approve', 'unapprove', 'delete'], true)) return '';
    if (!current_user_can('manage_options')) return '';

    check_admin_referer('qrm_review_action_' . $id);

    global $wpdb;
    $table_reviews = $wpdb->prefix . 'qrm_reviews';

    if ($action === 'approve') {
        $wpdb->update($table_reviews, ['status' => 1], ['id' => $id]);
        qrm_pro_flush_review_stats();
        return __('Yorum yayınlandı.', 'qrms');
    }
    if ($action === 'unapprove') {
        $wpdb->update($table_reviews, ['status' => 0], ['id' => $id]);
        qrm_pro_flush_review_stats();
        return __('Yorum yayından kaldırıldı.', 'qrms');
    }

    $wpdb->delete($table_reviews, ['id' => $id]);
    qrm_pro_flush_review_stats();
    return __('Yorum silindi.', 'qrms');
}

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
        $like         = '%' . $wpdb->esc_like($extra['search']) . '%';
        $reward_table = function_exists('qrm_reward_table') ? qrm_reward_table() : ($wpdb->prefix . 'qrm_reward_codes');
        // email ve kod qrm_reviews'ta yok; ödül tablosunda (source_review_id) durur.
        $kosullar[]   = '(customer_name LIKE %s OR customer_phone LIKE %s OR comment LIKE %s OR table_no LIKE %s OR id IN (SELECT source_review_id FROM ' . $reward_table . ' WHERE email LIKE %s OR code LIKE %s))';
        $params[]     = $like;
        $params[]     = $like;
        $params[]     = $like;
        $params[]     = $like;
        $params[]     = $like;
        $params[]     = $like;
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
    if ($notice === '') {
        $notice = qrm_pro_cf_handle_review_delete();
    }

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
        $native_toplam = qrm_export_reviews_count($durum, $sekme, $esik, $wf, $list_filters);
    } else {
        $native_toplam = qrm_pro_admin_reviews_total($durum, $stats, $sekme, $wf, $wf_counts);
    }

    // Puanlama Kriterleri (rating_group) widget'lı özel formların gönderimleri
    // (bkz. includes/admin/reviews-cf-bridge.php) burada listeye katılır. İş
    // akışı filtresi ve "Onay Bekleyen" bu satırlara hiç uygulanmaz — ikisi de
    // özel formda karşılığı olmayan kavramlar, o yüzden o filtreler aktifken
    // özel form satırları listeden tamamen çıkar (yanlış bir eşleşme uydurmak
    // yerine).
    $merge_cf = !empty(qrm_pro_cf_review_forms()) && $wf === '' && $durum !== 'bekleyen';
    $cf_rows  = $merge_cf ? qrm_pro_cf_fetch_review_rows($sekme, $esik, $has_list_filters ? $list_filters : []) : [];
    $cf_toplam = count($cf_rows);
    $toplam    = $native_toplam + $cf_toplam;

    $cf_sent      = $merge_cf ? qrm_pro_cf_review_sentiment_counts($esik, $has_list_filters ? $list_filters : []) : ['total' => 0, 'olumlu' => 0, 'olumsuz' => 0];
    $display_stats = qrm_pro_cf_merge_stats_for_display($stats, $cf_sent);

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
        if ($merge_cf && $cf_toplam > 0) {
            // İki farklı şemayı (sabit sütunlu tablo + JSON blob) TEK SQL'de
            // sayfalamak mümkün değil; native satırların TAMAMI (sayfalamasız)
            // çekilip özel form satırlarıyla PHP'de tarihe göre birleştirilir,
            // sayfalama bu birleşik dizi üzerinde yapılır. qrm_pro_admin_fetch_reviews()
            // burada per_page=$native_toplam ile "tek sayfada hepsi" olarak
            // çağrılır — mevcut, test edilmiş sorgu mantığı değişmeden yeniden
            // kullanılır.
            $native_rows = $native_toplam > 0
                ? qrm_pro_admin_fetch_reviews($durum, $native_toplam, 1, $sekme, $esik, $wf, $has_list_filters ? $list_filters : [])
                : [];
            $merged = array_merge($native_rows, $cf_rows);
            usort($merged, function ($a, $b) {
                return strcmp($b->created_at, $a->created_at);
            });
            $reviews = array_slice($merged, ($paged - 1) * $per_page, $per_page);
        } else {
            $reviews = qrm_pro_admin_fetch_reviews($durum, $per_page, $paged, $sekme, $esik, $wf, $has_list_filters ? $list_filters : []);
        }
    }

    $review_ids = array_filter(array_map(function ($row) {
        return (int) $row->id;
    }, $reviews), function ($id) {
        return $id > 0; // özel form satırları negatif id taşır, medya eşlemesi yalnızca native satırlar içindir.
    });
    $review_media_map = function_exists('qrm_pro_get_review_media_bulk')
        ? qrm_pro_get_review_media_bulk($review_ids)
        : [];

    $workflow_statuses = qrm_pro_review_workflow_statuses();
    $wf_total          = qrm_pro_workflow_counts_total($wf_counts);
    ?>
    <div class="wrap qrm-pro-wrap qrm-reviews-screen">
        <div class="qrm-page-head">
            <h1 class="qrm-page-title"><?php esc_html_e('Tüm Yorumlar', 'qrms'); ?></h1>
            <p class="qrm-page-sub">
                <?php
                printf(
                    /* translators: 1: toplam yorum, 2: onay bekleyen yorum sayısı. */
                    esc_html__('%1$s yorum · %2$s onay bekliyor', 'qrms'),
                    esc_html(number_format_i18n((int) $stats['total'])),
                    esc_html(number_format_i18n((int) $stats['pending']))
                );
                ?>
            </p>
        </div>

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

        <?php
        $sekme_sayaclari = qrm_pro_admin_review_tab_counts($sekme, $display_stats);
        ?>

        <div class="qrm-filterbar" role="group" aria-label="<?php esc_attr_e('Yorum filtreleri', 'qrms'); ?>">
            <div class="qrm-seg" role="group" aria-label="<?php esc_attr_e('Yorum türü', 'qrms'); ?>">
                <?php foreach (qrm_pro_admin_review_tabs() as $anahtar => $baslik):
                    $sayac = qrm_pro_admin_review_tab_counts($anahtar, $display_stats);

                    // Sekme değişince durum filtresi ve sayfa numarası sıfırlanır:
                    // yeni sekmede aynı sayfa numarası var olmayabilir.
                    $url    = $anahtar === '' ? $self_url : add_query_arg(['sekme' => $anahtar], $self_url);
                    $aktif  = $sekme === $anahtar;
                    $s_mod  = $anahtar !== '' ? ' qrm-seg-item--' . $anahtar : '';
                ?>
                    <a class="qrm-seg-item<?php echo esc_attr($s_mod); ?><?php echo $aktif ? ' is-active' : ''; ?>"
                       href="<?php echo esc_url($url); ?>"
                       <?php echo $aktif ? 'aria-current="page"' : ''; ?>>
                        <span class="qrm-seg-label"><?php echo esc_html($baslik); ?></span>
                        <span class="qrm-seg-count"><?php echo esc_html(number_format_i18n($sayac['total'])); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($sekme_sayaclari['total'] > 0): ?>
            <div class="qrm-chiprow">
                <span class="qrm-chiprow-label" id="qrm-chiprow-durum"><?php esc_html_e('Durum', 'qrms'); ?></span>
                <div class="qrm-chips" role="group" aria-labelledby="qrm-chiprow-durum">
                    <?php
                    $durum_filtreleri = [
                        ''         => [__('Tümü', 'qrms'), $sekme_sayaclari['total'], ''],
                        'bekleyen' => [__('Onay bekleyen', 'qrms'), $sekme_sayaclari['pending'], 'warning'],
                        'onayli'   => [__('Yayında', 'qrms'), $sekme_sayaclari['approved'], 'success'],
                    ];
                    foreach ($durum_filtreleri as $d_key => $d_data):
                        // "Tümü" durum filtresini KALDIRIR: $sekme_url aktif
                        // durumu taşır, add_query_arg ile ezmek yerine silinir.
                        $d_url   = $d_key === ''
                            ? remove_query_arg('durum', $sekme_url)
                            : add_query_arg(['durum' => $d_key], $sekme_url);
                        $d_aktif = $durum === $d_key;
                    ?>
                        <a class="qrm-chip<?php echo $d_data[2] !== '' ? ' qrm-chip--' . esc_attr($d_data[2]) : ''; ?><?php echo $d_aktif ? ' is-active' : ''; ?>"
                           href="<?php echo esc_url($d_url); ?>"
                           <?php echo $d_aktif ? 'aria-current="page"' : ''; ?>>
                            <?php echo esc_html($d_data[0]); ?>
                            <span class="qrm-chip-count"><?php echo esc_html(number_format_i18n((int) $d_data[1])); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($stats['table_ok'] && ($wf_total > 0 || $wf !== '')): ?>
            <div class="qrm-chiprow">
                <span class="qrm-chiprow-label" id="qrm-chiprow-wf"><?php esc_html_e('İş akışı', 'qrms'); ?></span>
                <div class="qrm-chips" role="group" aria-labelledby="qrm-chiprow-wf">
                    <a class="qrm-chip<?php echo $wf === '' ? ' is-active' : ''; ?>"
                       href="<?php echo esc_url($sekme_url); ?>"
                       <?php echo $wf === '' ? 'aria-current="page"' : ''; ?>>
                        <?php esc_html_e('Tümü', 'qrms'); ?>
                        <span class="qrm-chip-count"><?php echo esc_html(number_format_i18n($wf_total)); ?></span>
                    </a>
                    <?php foreach ($workflow_statuses as $wf_key => $wf_label):
                        $wf_count = isset($wf_counts[$wf_key]) ? (int) $wf_counts[$wf_key] : 0;
                        $wf_url   = add_query_arg(['wf' => $wf_key], $sekme_url);
                    ?>
                        <a class="qrm-chip qrm-chip--wf-<?php echo esc_attr($wf_key); ?><?php echo $wf === $wf_key ? ' is-active' : ''; ?>"
                           href="<?php echo esc_url($wf_url); ?>"
                           <?php echo $wf === $wf_key ? 'aria-current="page"' : ''; ?>>
                            <?php echo esc_html($wf_label); ?>
                            <span class="qrm-chip-count"><?php echo esc_html(number_format_i18n($wf_count)); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="qrm-list-toolbar">
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
                <label class="qrm-field qrm-field--search">
                    <span class="qrm-field-label"><?php esc_html_e('Ara', 'qrms'); ?></span>
                    <input type="search" name="s" value="<?php echo esc_attr($list_filters['search']); ?>" placeholder="<?php esc_attr_e('Ad, e-posta, yorum…', 'qrms'); ?>">
                </label>
                <label class="qrm-field qrm-field--date">
                    <span class="qrm-field-label"><?php esc_html_e('Başlangıç', 'qrms'); ?></span>
                    <input type="date" name="liste_bas" value="<?php echo esc_attr($list_filters['liste_bas']); ?>">
                </label>
                <label class="qrm-field qrm-field--date">
                    <span class="qrm-field-label"><?php esc_html_e('Bitiş', 'qrms'); ?></span>
                    <input type="date" name="liste_bit" value="<?php echo esc_attr($list_filters['liste_bit']); ?>">
                </label>
                <?php if (!empty($masa_labels)): ?>
                <label class="qrm-field qrm-field--table">
                    <span class="qrm-field-label"><?php esc_html_e('Masa', 'qrms'); ?></span>
                    <select name="table_id">
                        <option value="0"><?php esc_html_e('Tümü', 'qrms'); ?></option>
                        <?php foreach ($masa_labels as $tid => $mlabel): ?>
                            <option value="<?php echo esc_attr((string) $tid); ?>" <?php selected((int) $list_filters['table_id'], (int) $tid); ?>><?php echo esc_html($mlabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
                <div class="qrm-toolbar-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Filtrele', 'qrms'); ?></button>
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
                    <?php if ($has_list_filters || $durum !== '' || $sekme !== '' || $wf !== ''): ?>
                        <a class="qrm-toolbar-reset" href="<?php echo esc_url($self_url); ?>"><?php esc_html_e('Filtreleri temizle', 'qrms'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <table class="wp-list-table widefat striped qrm-review-workflow-table">
            <thead>
                <tr>
                    <th scope="col" class="qrm-col-score"><?php esc_html_e('Puan', 'qrms'); ?></th>
                    <th scope="col" class="qrm-col-main"><?php esc_html_e('Yorum', 'qrms'); ?></th>
                    <th scope="col" class="qrm-col-status"><?php esc_html_e('Durum', 'qrms'); ?></th>
                    <th scope="col" class="qrm-col-wf"><?php esc_html_e('İş Akışı', 'qrms'); ?></th>
                    <th scope="col" class="qrm-col-actions"><?php esc_html_e('İşlemler', 'qrms'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reviews)): ?>
                <tr class="no-items">
                    <td colspan="5" class="qrm-empty">
                        <?php if (!$stats['table_ok']): ?>
                            <strong><?php esc_html_e('Liste yüklenemedi.', 'qrms'); ?></strong>
                            <p><?php esc_html_e('Yukarıdaki veritabanı uyarısını giderdikten sonra yorumlar burada görünecek.', 'qrms'); ?></p>
                        <?php elseif ($durum !== '' || $sekme !== '' || $wf !== '' || $has_list_filters): ?>
                            <strong><?php esc_html_e('Bu filtreye uyan yorum yok.', 'qrms'); ?></strong>
                            <p><?php esc_html_e('Tarih aralığını genişletebilir ya da arama teriminizi sadeleştirebilirsiniz.', 'qrms'); ?></p>
                            <p><a class="button" href="<?php echo esc_url($self_url); ?>"><?php esc_html_e('Tüm yorumları göster', 'qrms'); ?></a></p>
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
                    // Özel form (rating_group) kaynaklı satırlar NEGATİF id taşır
                    // (bkz. reviews-cf-bridge.php) — onay/iş akışı/medya bu
                    // satırlarda hiç uygulanmaz, aşağıdaki bloklar bu bayrağa göre dallanır.
                    $is_cf_row = (int) $r->id < 0;
                    $name_display = $r->is_anonymous ? '<em>' . esc_html__('Anonim', 'qrms') . '</em>' : esc_html($r->customer_name);
                    if ($name_display === '') {
                        $name_display = '<em>' . esc_html__('İsimsiz', 'qrms') . '</em>';
                    }

                    // Kaynak rozetleri ad satırının yanında, masa bilgisi ise
                    // tarihle birlikte ikincil meta satırında durur.
                    $source_pills = '';
                    if (!empty($r->form_source) && $r->form_source === 'contact') {
                        $source_pills .= ' <span class="qrm-pill qrm-pill--source">' . esc_html__('İletişim', 'qrms') . '</span>';
                    }
                    if ($is_cf_row && !empty($r->_cf_form_title)) {
                        $source_pills .= ' <span class="qrm-pill qrm-pill--source">' . esc_html($r->_cf_form_title) . '</span>';
                    }

                    // Kriter Kırılımını Hazırla
                    $breakdown = [];
                    for($i=1; $i<=5; $i++) {
                        $c_act = $settings['crit_'.$i.'_active'];
                        $c_name = $settings['crit_'.$i.'_name'];
                        $c_val = $r->{'rating_'.$i};
                        if($c_act && $c_val > 0) {
                            $breakdown[] = ['name' => $c_name, 'value' => $c_val];
                        }
                    }

                    $wf_status = isset($r->workflow_status) ? sanitize_key($r->workflow_status) : 'new';
                    if (!array_key_exists($wf_status, $workflow_statuses)) {
                        $wf_status = 'new';
                    }
                    $assigned_id = isset($r->assigned_user_id) ? (int) $r->assigned_user_id : 0;
                    $internal_note = isset($r->internal_note) ? (string) $r->internal_note : '';
                    $has_note = $internal_note !== '';
                    $resolved_at = !empty($r->resolved_at) ? $r->resolved_at : '';
                    $rating_val  = (float) $r->rating;
                    $rating_tone = $rating_val >= $esik ? 'pos' : 'neg';
                ?>
                <tbody class="qrm-review-row-block">
                <tr class="qrm-review-row" <?php echo $is_cf_row ? '' : 'data-review-id="' . esc_attr((string) intval($r->id)) . '"'; ?>>
                    <td data-label="<?php esc_attr_e('Puan', 'qrms'); ?>" class="qrm-cell-score">
                        <span class="qrm-score qrm-score--<?php echo esc_attr($rating_tone); ?>">
                            <span class="qrm-score-value"><?php echo esc_html(number_format_i18n($rating_val, 1)); ?></span>
                            <span class="qrm-score-star" aria-hidden="true">&#9733;</span>
                            <span class="screen-reader-text"><?php
                                /* translators: %s: yorumun ortalama puanı. */
                                printf(esc_html__('5 üzerinden %s puan', 'qrms'), esc_html(number_format_i18n($rating_val, 1)));
                            ?></span>
                        </span>
                        <?php if ($r->rating >= $g_threshold && !empty($settings['google_review_enabled'])): ?>
                            <span class="qrm-pill qrm-pill--google" title="<?php esc_attr_e('Bu puan Google\'a yönlendirme eşiğinin üzerinde', 'qrms'); ?>"><?php esc_html_e('G Adayı', 'qrms'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Yorum', 'qrms'); ?>" class="qrm-cell-main">
                        <div class="qrm-rv-customer"><?php echo wp_kses_post($name_display . $source_pills); ?></div>
                        <div class="qrm-rv-meta">
                            <span><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($r->created_at))); ?></span>
                            <?php if ($r->table_no): ?>
                                <span class="qrm-rv-meta-sep" aria-hidden="true">·</span>
                                <span><?php
                                    /* translators: %s: masa numarası. */
                                    printf(esc_html__('Masa %s', 'qrms'), esc_html($r->table_no));
                                ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (trim((string) $r->comment) !== ''): ?>
                            <p class="qrm-rv-text"><?php echo nl2br(esc_html($r->comment)); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($breakdown)): ?>
                            <ul class="qrm-rv-criteria">
                                <?php foreach ($breakdown as $crit): ?>
                                    <li><span class="qrm-rv-criteria-name"><?php echo esc_html($crit['name']); ?></span> <span class="qrm-rv-criteria-value"><?php echo esc_html(number_format_i18n((float) $crit['value'], 0)); ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!$is_cf_row):
                        $row_media = isset($review_media_map[(int) $r->id]) ? $review_media_map[(int) $r->id] : [];
                        echo qrm_pro_render_admin_review_media($row_media);
                        endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Durum', 'qrms'); ?>" class="qrm-cell-status">
                        <?php if ($r->status): ?>
                            <span class="qrm-status qrm-status-approved"><span class="qrm-status-dot" aria-hidden="true"></span><?php esc_html_e('Yayında', 'qrms'); ?></span>
                        <?php else: ?>
                            <span class="qrm-status qrm-status-pending"><span class="qrm-status-dot" aria-hidden="true"></span><?php esc_html_e('Bekliyor', 'qrms'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('İş Akışı', 'qrms'); ?>" class="qrm-wf-cell">
                        <?php if ($is_cf_row): ?>
                            <span class="qrm-cf-row-note">
                                <?php esc_html_e('Özel form kaydı', 'qrms'); ?>
                            </span>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </td>
                    <td data-label="" class="qrm-row-actions">
                        <?php if ($is_cf_row):
                            // Özel form satırlarında onay/yayından kaldır kavramı yok —
                            // yalnızca silme, kendi tablosuna (qrm_custom_form_submissions)
                            // ayrı bir parametre çiftiyle (cf_action/cf_id) yönlendirilir.
                            $cf_submission_id = (int) $r->_cf_submission_id;
                            $cf_row_args = ['cf_action' => 'delete', 'cf_id' => $cf_submission_id] + $row_page;
                            if ($durum !== '') $cf_row_args['durum'] = $durum;
                            if ($sekme !== '') $cf_row_args['sekme'] = $sekme;
                            if ($list_filters['liste_bas'] !== '') $cf_row_args['liste_bas'] = $list_filters['liste_bas'];
                            if ($list_filters['liste_bit'] !== '') $cf_row_args['liste_bit'] = $list_filters['liste_bit'];
                            if ($list_filters['search'] !== '') $cf_row_args['s'] = $list_filters['search'];
                            if (!empty($list_filters['table_id'])) $cf_row_args['table_id'] = (int) $list_filters['table_id'];
                        ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg($cf_row_args, $self_url), 'qrm_cf_review_action_' . $cf_submission_id)); ?>"
                               class="button button-small qrm-btn-danger"
                               onclick="return confirm('<?php echo esc_js(__('Bu yorum kalıcı olarak silinsin mi?', 'qrms')); ?>');"><?php esc_html_e('Sil', 'qrms'); ?></a>
                        <?php else:
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
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'approve'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>" class="button button-small qrm-btn-approve"><?php esc_html_e('Onayla', 'qrms'); ?></a>
                        <?php else: ?>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'unapprove'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>" class="button button-small"><?php esc_html_e('Yayından Kaldır', 'qrms'); ?></a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'delete'] + $row_args, $self_url), 'qrm_review_action_' . intval($r->id))); ?>"
                           class="button button-small qrm-btn-danger"
                           onclick="return confirm('<?php echo esc_js(__('Bu yorum kalıcı olarak silinsin mi?', 'qrms')); ?>');"><?php esc_html_e('Sil', 'qrms'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!$is_cf_row): ?>
                <tr class="qrm-wf-note-row" hidden>
                    <td colspan="5">
                        <label class="screen-reader-text" for="qrm-wf-note-<?php echo esc_attr((string) intval($r->id)); ?>">
                            <?php esc_html_e('İç not', 'qrms'); ?>
                        </label>
                        <textarea id="qrm-wf-note-<?php echo esc_attr((string) intval($r->id)); ?>"
                                  class="qrm-wf-note"
                                  rows="3"
                                  placeholder="<?php esc_attr_e('Yalnızca yöneticiler görür — müşteriye gösterilmez.', 'qrms'); ?>"><?php echo esc_textarea($internal_note); ?></textarea>
                    </td>
                </tr>
                <?php endif; ?>
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
            <div class="tablenav bottom qrm-tablenav">
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
        // Ekler yorumla birlikte yayına açılır (yükleme sırasında private).
        if (function_exists('qrm_pro_media_sync_status')) {
            qrm_pro_media_sync_status($id, true);
        }
        qrm_pro_flush_review_stats();
        return __('Yorum yayınlandı.', 'qrms');
    }
    if ($action === 'unapprove') {
        $wpdb->update($table_reviews, ['status' => 0], ['id' => $id]);
        if (function_exists('qrm_pro_media_sync_status')) {
            qrm_pro_media_sync_status($id, false);
        }
        qrm_pro_flush_review_stats();
        return __('Yorum yayından kaldırıldı.', 'qrms');
    }

    if (function_exists('qrm_pro_delete_review_media')) {
        qrm_pro_delete_review_media($id);
    }

    $wpdb->delete($table_reviews, ['id' => $id]);
    qrm_pro_flush_review_stats();
    return __('Yorum silindi.', 'qrms');
}

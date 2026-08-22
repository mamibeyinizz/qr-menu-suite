<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Import_Export_Trait {

    public function handle_csv_import() {
        if ( ! isset( $_POST['rma_import_csv_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['rma_import_csv_nonce'], 'rma_import_csv_action' ) ) return;
        if ( ! current_user_can( 'edit_posts' ) ) return;

        if ( isset( $_FILES['rma_csv_file'] ) && $_FILES['rma_csv_file']['error'] === UPLOAD_ERR_OK ) {
            $content   = file_get_contents( $_FILES['rma_csv_file']['tmp_name'] );
            $content   = preg_replace( "/^\xEF\xBB\xBF/", '', $content );
            $content   = str_replace( [ "\r\n", "\r" ], "\n", $content );
            $lines     = explode( "\n", $content );
            $delimiter = ( strpos( $lines[0] ?? '', ';' ) !== false ) ? ';' : ',';
            $imported  = 0;

            // PERF: Terim sayacı her wp_set_object_terms çağrısında yeniden
            // hesaplanıyordu (satır × kategori kadar UPDATE). Toplu içe
            // aktarım boyunca ertelenir, sonunda tek seferde güncellenir.
            wp_defer_term_counting( true );

            // Aynı kategori adı yüzlerce satırda tekrar eder; term_exists
            // sonucu burada saklanır, satır başına sorgu yerine tek sorgu.
            $term_cache        = [];
            $allowed_allergens = array_keys( $this->get_allergen_definitions() );
            $meat_options      = $this->get_meat_origin_options();

            foreach ( $lines as $i => $line ) {
                if ( $i === 0 || empty( trim( $line ) ) ) continue;
                $d     = str_getcsv( $line, $delimiter );
                $title = sanitize_text_field( $d[0] ?? '' );
                if ( empty( $title ) ) continue;

                $pid = wp_insert_post( [
                    'post_title'   => $title,
                    'post_content' => wp_kses_post( $d[1] ?? '' ),
                    'post_excerpt' => sanitize_textarea_field( $d[2] ?? '' ),
                    'post_status'  => 'publish',
                    'post_type'    => 'rma_menu_item',
                ] );

                if ( $pid && ! is_wp_error( $pid ) ) {
                    $meta_map = [
                        'rma_price'             => $d[3]  ?? '',
                        'rma_spicy_level'       => $d[5]  ?? '',
                        'rma_calories'          => $d[6]  ?? '',
                        'rma_grams'             => $d[7]  ?? '',
                        'rma_is_vegan'          => $d[8]  ?? '0',
                        'rma_is_vegetarian'     => $d[9]  ?? '0',
                        'rma_is_gluten_free'    => $d[10] ?? '0',
                        'rma_badge_popular'     => $d[11] ?? '0',
                        'rma_badge_new'         => $d[12] ?? '0',
                        'rma_badge_recommended' => $d[13] ?? '0',
                        'rma_badge_discount'    => $d[14] ?? '0',
                        'rma_protein'           => $d[15] ?? '',
                        'rma_carbs'             => $d[16] ?? '',
                        // --- 1 Temmuz 2026 şeffaf menü alanları (eski CSV'lerle geriye dönük uyumlu: sütun yoksa boş/0 kalır) ---
                        'rma_fat'               => $d[17] ?? '',
                        'rma_prep_time'         => $d[18] ?? '',
                        'rma_contains_alcohol'  => $d[20] ?? '0',
                        'rma_contains_pork'     => $d[21] ?? '0',
                    ];
                    foreach ( $meta_map as $key => $val ) update_post_meta( $pid, $key, sanitize_text_field( $val ) );
                    update_post_meta( $pid, 'rma_active', '1' );

                    // Et menşei (sütun 19) — sadece izinli değerler kabul edilir
                    $meat_val = sanitize_text_field( $d[19] ?? '' );
                    if ( array_key_exists( $meat_val, $meat_options ) ) {
                        update_post_meta( $pid, 'rma_meat_origin', $meat_val );
                    }

                    $cats = array_filter( array_map( 'trim', explode( ',', $d[4] ?? '' ) ) );
                    $tids = [];
                    foreach ( $cats as $cat_name ) {
                        if ( ! array_key_exists( $cat_name, $term_cache ) ) {
                            $term = term_exists( $cat_name, 'rma_category' ) ?: wp_insert_term( $cat_name, 'rma_category' );
                            $term_cache[ $cat_name ] = ( ! is_wp_error( $term ) && isset( $term['term_id'] ) )
                                ? (int) $term['term_id']
                                : 0;
                        }
                        if ( $term_cache[ $cat_name ] ) $tids[] = $term_cache[ $cat_name ];
                    }
                    if ( $tids ) wp_set_object_terms( $pid, $tids, 'rma_category' );

                    // Alerjenler (sütun 22) — slug'lar "|" ile ayrılır, örn: gluten|sut|yumurta
                    // Kategori sütunundaki "," ayırıcısıyla çakışmaması için "|" kullanılır.
                    $allergen_raw      = $d[22] ?? '';
                    $allergen_slugs    = array_filter( array_map( 'trim', explode( '|', $allergen_raw ) ) );
                    $allergen_slugs    = array_values( array_intersect( $allergen_slugs, $allowed_allergens ) );
                    if ( $allergen_slugs ) wp_set_object_terms( $pid, $allergen_slugs, 'rma_allergen' );

                    $imported++;
                }
            }

            wp_defer_term_counting( false );
            $this->force_bump_cache_version();

            wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'imported' => $imported ], 'rma-ice-disa-aktar' ) );
            exit;
        }
        wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'csv_error' => 2 ], 'rma-ice-disa-aktar' ) );
        exit;
    }

    /**
     * CSV sütunları — sıra handle_csv_import()'un okuduğu $d[N] indeksleriyle
     * birebir aynıdır. Hem "Format detayları" bölümü hem de örnek CSV dosyası
     * bu tek diziden beslenir.
     */
    public function get_csv_columns() {
        return [
            [ 'Başlık',                 'Ürün adı (zorunlu)',                          'Mercimek Çorbası' ],
            [ 'İçerik',                 'Uzun açıklama',                               'Ev yapımı kırmızı mercimek çorbası' ],
            [ 'Özet',                   'Kartta görünen kısa açıklama',                'Limonla servis edilir' ],
            [ 'Fiyat',                  'Sadece sayı',                                 '95' ],
            [ 'Kategori',               'Birden fazlaysa virgülle ayırın',             'Çorbalar' ],
            [ 'Acı',                    '0-3 arası',                                   '0' ],
            [ 'Kalori',                 'kcal',                                        '180' ],
            [ 'Gramaj',                 'g',                                           '300' ],
            [ 'Vegan',                  '0 veya 1',                                    '1' ],
            [ 'Vejetaryen',             '0 veya 1',                                    '1' ],
            [ 'Glütensiz',              '0 veya 1',                                    '0' ],
            [ 'Popüler',                '0 veya 1',                                    '1' ],
            [ 'Yeni',                   '0 veya 1',                                    '0' ],
            [ 'Önerilen',               '0 veya 1',                                    '0' ],
            [ 'İndirim',                '0 veya 1',                                    '0' ],
            [ 'Protein',                'g',                                           '9' ],
            [ 'Karbonhidrat',           'g',                                           '28' ],
            [ 'Yağ',                    'g',                                           '4' ],
            [ 'Hazırlanış Süresi',      'dakika',                                      '10' ],
            [ 'Et Menşei',              'dana / kuzu / tavuk / hindi / balik / deniz / karisik ya da boş', '' ],
            [ 'Alkol İçerir',           '0 veya 1',                                    '0' ],
            [ 'Domuz Türevi İçerir',    '0 veya 1',                                    '0' ],
            [ 'Alerjenler',             'Slug\'ları | ile ayırın',                     'gluten' ],
        ];
    }

    public function render_csv_import_page() {
        if ( isset( $_GET['imported'] ) ) echo '<div class="updated"><p><strong>' . intval( $_GET['imported'] ) . '</strong> ürün aktarıldı.</p></div>';
        if ( isset( $_GET['csv_error'] ) ) echo '<div class="error"><p>Dosya yükleme hatası.</p></div>';

        $columns = $this->get_csv_columns();
        $sample  = [
            array_map( fn( $col ) => $col[0], $columns ),   // başlık satırı
            array_map( fn( $col ) => $col[2], $columns ),   // örnek satır
        ];
        ?>
        <div class="rma-card" id="rma-ice-disa-aktar">
            <h2 class="rma-card-title">Toplu Ürün Aktarımı (CSV)</h2>
            <p class="rma-card-desc">Excel'de hazırladığınız listeyi CSV olarak kaydedip yükleyin, ürünler menüye tek seferde eklensin. Nasıl bir dosya gerektiğini görmek için önce "Örnek dosya indir" butonuna basabilirsiniz.</p>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'rma_import_csv_action', 'rma_import_csv_nonce' ); ?>
                <table class="form-table rma-form-table">
                    <tr><th><label for="rma_csv_file">CSV Dosyası</label></th>
                        <td><input type="file" name="rma_csv_file" id="rma_csv_file" accept=".csv,text/csv" required></td></tr>
                </table>
                <p class="rma-actions">
                    <?php submit_button( 'Dosyayı Yükle ve Ürünleri Ekle', 'primary', 'rma_submit_csv', false ); ?>
                    <button type="button" class="button" id="rma-csv-sample"
                            data-csv="<?php echo esc_attr( wp_json_encode( $sample ) ); ?>">Örnek dosya indir</button>
                </p>
            </form>

            <details class="rma-details">
                <summary>Dosyada hangi sütunlar olmalı?</summary>
                <p class="rma-desc">Sütunlar aşağıdaki sırada olmalıdır. 18. sütundan sonrası zorunlu değildir — eski dosyalarınız bozulmadan çalışmaya devam eder.</p>
                <div class="rma-table-scroll">
                    <table class="rma-details-table">
                        <thead><tr><th>#</th><th>Sütun</th><th>Açıklama</th></tr></thead>
                        <tbody>
                            <?php foreach ( $columns as $i => $col ) : ?>
                            <tr>
                                <td><?php echo (int) $i; ?></td>
                                <td><?php echo esc_html( $col[0] ); ?></td>
                                <td><?php echo esc_html( $col[1] ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="rma-desc">Alerjen sütununa yazılabilecek değerler: <code><?php echo esc_html( implode( ', ', array_keys( $this->get_allergen_definitions() ) ) ); ?></code></p>
            </details>
        </div>
    <?php }

    /* -----------------------------------------------------------------
       MENÜ YEDEKLEME (JSON EXPORT / IMPORT — ID eşleşmesiyle üzerine yazma)
    ----------------------------------------------------------------- */

    /**
     * Tüm menü ürünlerini JSON olarak dışa aktarır.
     * Her ürün 'export_id' alanında kendi post ID'sini taşır; bu değer
     * içe aktarımda eşleşme anahtarı olarak kullanılır (yeni ürün oluşturmak
     * yerine mevcut ürünün üzerine yazılmasını sağlar).
     *
     * PERF: Eskiden tüm ürünler tek seferde belleğe alınıp $items dizisinde
     * biriktiriliyor, ardından tek json_encode ile basılıyordu — büyük
     * menülerde bellek limitine takılma riski. Artık ürünler 200'lük
     * gruplar hâlinde çekilip doğrudan çıkışa yazılır; bellek kullanımı
     * menü büyüklüğünden bağımsız sabit kalır. Her grupta post/meta/terim
     * ve öne çıkan görsel cache'leri toplu ısıtılır (N+1 kaldırıldı).
     */
    public function handle_export_menu() {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'rma_export_menu_action' ) ) {
            wp_die( 'Güvenlik doğrulaması başarısız.' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Yetkiniz yok.' );

        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="rma-menu-export-' . gmdate( 'Y-m-d-His' ) . '.json"' );

        $json_flags = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

        // Sarmalayıcı alanlar tek seferde; ürünler akış hâlinde eklenir.
        echo '{' . "\n";
        echo '    "plugin": '       . wp_json_encode( 'restaurant-menu-automation', $json_flags ) . ",\n";
        echo '    "version": '      . wp_json_encode( '5.0.0', $json_flags ) . ",\n";
        echo '    "generated_at": ' . wp_json_encode( current_time( 'mysql' ), $json_flags ) . ",\n";
        echo '    "site_url": '     . wp_json_encode( site_url(), $json_flags ) . ",\n";
        echo '    "items": [';

        $batch_size = (int) apply_filters( 'rma_export_batch_size', 200 );
        $paged      = 1;
        $first      = true;

        do {
            $query = new WP_Query( [
                'post_type'              => 'rma_menu_item',
                'post_status'            => 'any',
                'posts_per_page'         => $batch_size,
                'paged'                  => $paged,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => true,
                'orderby'                => 'menu_order title',
                'order'                  => 'ASC',
            ] );

            $posts = $query->posts;
            if ( $posts && function_exists( 'update_post_thumbnail_cache' ) ) {
                update_post_thumbnail_cache( $query );
            }

            foreach ( $posts as $p ) {
                $meta_raw = get_post_meta( $p->ID );
                $meta     = [];
                foreach ( $meta_raw as $key => $vals ) {
                    if ( strpos( $key, 'rma_' ) === 0 ) {
                        $meta[ $key ] = maybe_unserialize( $vals[0] );
                    }
                }

                $cats      = $this->term_slugs( $p->ID, 'rma_category' );
                $allergens = $this->term_slugs( $p->ID, 'rma_allergen' );
                $thumb_id  = get_post_thumbnail_id( $p->ID );

                $item = [
                    'export_id'  => $p->ID,
                    'title'      => $p->post_title,
                    'content'    => $p->post_content,
                    'excerpt'    => $p->post_excerpt,
                    'status'     => $p->post_status,
                    'menu_order' => $p->menu_order,
                    'categories' => $cats,
                    'allergens'  => $allergens,
                    'meta'       => $meta,
                    'image_url'  => $thumb_id ? wp_get_attachment_url( $thumb_id ) : '',
                ];

                echo ( $first ? "\n" : ",\n" ) . wp_json_encode( $item, $json_flags );
                $first = false;
            }

            $count = count( $posts );
            unset( $query, $posts );
            $paged++;

            // Belleği ve çıktı tamponunu şişirmeden akıt.
            if ( ob_get_length() ) @ob_flush();
            flush();
        } while ( $count === $batch_size );

        echo "\n    ]\n}";
        exit;
    }

    /**
     * Başlık eşleştirmesinin anahtarı.
     *
     * MySQL'in varsayılan (…_ci) harmanlaması büyük/küçük harf ayrımı yapmaz ve
     * kenar boşluklarını yok sayar; eski satır-başına sorgu bu davranışla
     * eşleşiyordu. Harita da aynı normalizasyonu kullanmalı, yoksa yalnızca harf
     * büyüklüğü farklı bir başlık yeni ürün olarak açılırdı.
     *
     * Türkçe'de strtolower() "I" harfini bozduğu için mb_strtolower tercih
     * edilir (bkz. RMA_Tukendi::ad_normalize).
     *
     * Saf fonksiyon — doğrudan test edilir.
     *
     * @param string $title Ürün başlığı.
     * @return string
     */
    public function import_title_key( $title ) {
        $title = trim( (string) $title );

        return function_exists( 'mb_strtolower' )
            ? mb_strtolower( $title, 'UTF-8' )
            : strtolower( $title );
    }

    /**
     * İçe aktarılacak başlıklar için "başlık => ürün ID" haritası.
     *
     * Yalnızca dosyada GEÇEN başlıklar sorulur (IN listesi), tablonun tamamı
     * değil; büyük menülerde bu, taramayı da döndürülen satır sayısını da
     * sınırlar. Liste çok uzunsa sorgu parçalara bölünür: tek bir dev IN(...)
     * ifadesi hem max_allowed_packet sınırına takılabilir hem de sorgu
     * planlayıcısını zorlar.
     *
     * Aynı başlıkta birden çok ürün varsa EN KÜÇÜK ID kazanır; eski
     * "… ORDER BY yok, LIMIT 1" sorgusu da pratikte ilk (en eski) kaydı
     * getiriyordu, davranış böylece hem korunur hem deterministik olur.
     *
     * @param array $items İçe aktarılacak kalemler.
     * @return array<string,int> Normalize başlık => ürün ID.
     */
    private function import_title_map( array $items ) {
        global $wpdb;

        $basliklar = [];

        foreach ( $items as $item ) {
            $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';

            if ( '' === $title ) continue;

            $basliklar[ $this->import_title_key( $title ) ] = $title;
        }

        if ( empty( $basliklar ) ) {
            return [];
        }

        $harita = [];

        foreach ( array_chunk( array_values( $basliklar ), 200 ) as $parca ) {
            $yer_tutucu = implode( ', ', array_fill( 0, count( $parca ), '%s' ) );

            $satirlar = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_title FROM {$wpdb->posts}
                     WHERE post_type = 'rma_menu_item'
                       AND post_status != 'trash'
                       AND post_title IN ({$yer_tutucu})
                     ORDER BY ID ASC",
                    $parca
                ),
                ARRAY_A
            );

            foreach ( (array) $satirlar as $satir ) {
                $anahtar = $this->import_title_key( $satir['post_title'] );

                // ORDER BY ID ASC + ilk gelen kazanır = en küçük ID.
                if ( ! isset( $harita[ $anahtar ] ) ) {
                    $harita[ $anahtar ] = (int) $satir['ID'];
                }
            }
        }

        return $harita;
    }

    /**
     * Bir gönderinin terim slug'ları — terim cache'inden okur (ek sorgu yok).
     *
     * @param int    $post_id
     * @param string $taxonomy
     * @return string[]
     */
    private function term_slugs( $post_id, $taxonomy ) {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( empty( $terms ) || is_wp_error( $terms ) ) return [];
        return wp_list_pluck( $terms, 'slug' );
    }

    /**
     * JSON yedeğini içe aktarır. Eşleşme sırası:
     * 1) 'export_id' aynı siteye ait mevcut bir rma_menu_item post'una karşılık geliyorsa -> üzerine yazılır.
     * 2) ID eşleşmezse (örn. farklı site / silinmiş ürün), aynı başlıkta mevcut bir ürün aranır -> üzerine yazılır.
     * 3) Hiçbiri bulunamazsa -> yeni ürün oluşturulur.
     * Bu sayede aynı dosya tekrar tekrar yüklendiğinde ürünler çoğalmaz, sadece güncellenir.
     */
    public function handle_menu_import() {
        if ( ! isset( $_POST['rma_import_menu_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['rma_import_menu_nonce'], 'rma_import_menu_action' ) ) return;
        if ( ! current_user_can( 'edit_posts' ) ) return;

        if ( empty( $_FILES['rma_menu_json_file'] ) || $_FILES['rma_menu_json_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'rma_backup_error' => 1 ], 'rma-yedekleme' ) );
            exit;
        }

        $raw  = file_get_contents( $_FILES['rma_menu_json_file']['tmp_name'] );
        $data = json_decode( $raw, true );
        unset( $raw );

        if ( ! is_array( $data ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
            wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'rma_backup_error' => 2 ], 'rma-yedekleme' ) );
            exit;
        }

        global $wpdb;
        $updated = 0;
        $created = 0;

        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );

        // PERF: terim sayacı güncellemeleri döngü boyunca ertelenir.
        wp_defer_term_counting( true );

        $allowed_allergens = array_keys( $this->get_allergen_definitions() );
        $cat_term_cache    = [];   // slug => term_id (satır başına sorgu yerine tek sorgu)

        // PERF: başlık -> ID haritası döngüden ÖNCE tek sorguda kurulur.
        //
        // Eskiden her satır için ayrı bir "WHERE post_title = %s" sorgusu
        // açılıyordu. WordPress çekirdeğinde `post_title` İNDEKSLİ DEĞİLDİR
        // (wp_posts indeksleri: post_name, type_status_date, post_parent,
        // post_author), yani her satır wp_posts'un menü kayıtlarını baştan
        // sona tarıyordu: 500 ürünlük bir içe aktarma 500 tarama demekti ve
        // tek bir istek veritabanını dakikalarca meşgul edebiliyordu.
        // Tek sorgu aynı taramayı bir kez yapar, gerisi bellekte çözülür.
        $baslik_haritasi = $this->import_title_map( $data['items'] );

        foreach ( $data['items'] as $item ) {
            $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
            if ( '' === $title ) continue;

            $pid = 0;

            // 1) export_id ile doğrudan eşleşme
            if ( ! empty( $item['export_id'] ) ) {
                $candidate = get_post( (int) $item['export_id'] );
                if ( $candidate && $candidate->post_type === 'rma_menu_item' ) {
                    $pid = $candidate->ID;
                }
            }

            // 2) Başlık ile eşleşme (aynı başlıkta ürün varsa üzerine yazılır)
            if ( ! $pid ) {
                $anahtar = $this->import_title_key( $title );

                if ( isset( $baslik_haritasi[ $anahtar ] ) ) {
                    $pid = (int) $baslik_haritasi[ $anahtar ];
                }
            }

            $postarr = [
                'post_title'   => $title,
                'post_content' => wp_kses_post( $item['content'] ?? '' ),
                'post_excerpt' => sanitize_textarea_field( $item['excerpt'] ?? '' ),
                'post_status'  => in_array( $item['status'] ?? 'publish', [ 'publish', 'draft', 'pending' ], true ) ? $item['status'] : 'publish',
                'post_type'    => 'rma_menu_item',
                'menu_order'   => isset( $item['menu_order'] ) ? (int) $item['menu_order'] : 0,
            ];

            if ( $pid ) {
                $postarr['ID'] = $pid;
                wp_update_post( $postarr );
                $updated++;
            } else {
                $pid = wp_insert_post( $postarr );
                if ( ! $pid || is_wp_error( $pid ) ) continue;
                $created++;

                // Yeni kayıt haritaya da girer: aynı dosyada aynı başlık ikinci
                // kez geçerse ikinci bir ürün açılmaz, az önce oluşturulanın
                // üzerine yazılır. (Satır başına sorgu yapan eski kod bunu
                // kendiliğinden sağlıyordu; harita da sağlamalı.)
                $baslik_haritasi[ $this->import_title_key( $title ) ] = (int) $pid;
            }

            // Meta alanları — sadece rma_ önekli olanlar, item içinde gelenlerle üzerine yazılır
            if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
                foreach ( $item['meta'] as $key => $val ) {
                    if ( strpos( $key, 'rma_' ) !== 0 ) continue;
                    update_post_meta( $pid, sanitize_key( $key ), is_scalar( $val ) ? sanitize_text_field( $val ) : $val );
                }
            }

            // Kategoriler — yoksa oluşturulur
            $cat_ids = [];
            foreach ( (array) ( $item['categories'] ?? [] ) as $slug ) {
                $slug = sanitize_title( $slug );
                if ( '' === $slug ) continue;

                if ( ! array_key_exists( $slug, $cat_term_cache ) ) {
                    $term = get_term_by( 'slug', $slug, 'rma_category' ) ?: wp_insert_term( $slug, 'rma_category', [ 'slug' => $slug ] );
                    $term_id = is_wp_error( $term ) ? 0 : ( is_object( $term ) ? $term->term_id : ( $term['term_id'] ?? 0 ) );
                    $cat_term_cache[ $slug ] = (int) $term_id;
                }
                if ( $cat_term_cache[ $slug ] ) $cat_ids[] = $cat_term_cache[ $slug ];
            }
            wp_set_object_terms( $pid, $cat_ids, 'rma_category' );

            // Alerjenler — sadece tanımlı slug'lar kabul edilir
            $allergen_slugs = array_values( array_intersect( array_map( 'sanitize_title', (array) ( $item['allergens'] ?? [] ) ), $allowed_allergens ) );
            wp_set_object_terms( $pid, $allergen_slugs, 'rma_allergen' );

            // Öne çıkan görsel — aynı URL ise tekrar indirilmez, kütüphanede varsa yeniden yüklenmez
            $image_url = esc_url_raw( $item['image_url'] ?? '' );
            if ( $image_url ) {
                $current_thumb_id = get_post_thumbnail_id( $pid );
                $current_url      = $current_thumb_id ? wp_get_attachment_url( $current_thumb_id ) : '';

                if ( $current_url !== $image_url ) {
                    $attach_id = attachment_url_to_postid( $image_url );

                    if ( ! $attach_id ) {
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        $sideloaded = media_sideload_image( $image_url, $pid, $title, 'id' );
                        if ( ! is_wp_error( $sideloaded ) ) $attach_id = $sideloaded;
                    }

                    if ( $attach_id && ! is_wp_error( $attach_id ) ) {
                        set_post_thumbnail( $pid, $attach_id );
                    }
                }
            }
        }

        wp_defer_term_counting( false );
        $this->force_bump_cache_version();

        wp_redirect(
            $this->admin_page_url(
                'qrms-rm-diger',
                [ 'rma_updated' => $updated, 'rma_created' => $created ],
                'rma-yedekleme'
            )
        );
        exit;
    }

    public function render_menu_backup_page() {
        $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=rma_export_menu' ), 'rma_export_menu_action' );

        if ( isset( $_GET['rma_updated'] ) || isset( $_GET['rma_created'] ) ) {
            printf(
                '<div class="updated"><p><strong>%d</strong> ürün güncellendi, <strong>%d</strong> yeni ürün eklendi.</p></div>',
                intval( $_GET['rma_updated'] ?? 0 ),
                intval( $_GET['rma_created'] ?? 0 )
            );
        }
        if ( isset( $_GET['rma_backup_error'] ) ) {
            echo '<div class="error"><p>Dosya okunamadı veya format geçersiz. Lütfen bu sayfadan dışa aktarılmış bir JSON dosyası yükleyin.</p></div>';
        }
        ?>
        <div class="rma-card" id="rma-yedekleme">
            <h2 class="rma-card-title">Yedekleme</h2>
            <p class="rma-card-desc">Menünüzün tamamını (fiyat, açıklama, alerjen, kategori ve görseller dahil) tek bir dosyaya indirip saklayın. Bir sorun olursa aynı dosyayı geri yükleyerek menünüzü eski hâline döndürebilirsiniz.</p>

            <div class="rma-section">
                <h3 class="rma-section-title">Yedek Al</h3>
                <p class="rma-desc">Dosyayı bilgisayarınıza indirir. Güvenli bir yerde saklayın.</p>
                <p class="rma-actions">
                    <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">Menü Yedeğini İndir</a>
                </p>
            </div>

            <div class="rma-section">
                <h3 class="rma-section-title">Yedeği Geri Yükle</h3>
                <p class="rma-desc">Daha önce bu sayfadan indirdiğiniz dosyayı yükleyin. Aynı ürün varsa <strong>üzerine yazılır</strong>, yoksa yeni eklenir — aynı dosyayı tekrar yüklemek ürünleri çoğaltmaz.</p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'rma_import_menu_action', 'rma_import_menu_nonce' ); ?>
                    <table class="form-table rma-form-table">
                        <tr>
                            <th><label for="rma_menu_json_file">Yedek Dosyası</label></th>
                            <td><input type="file" name="rma_menu_json_file" id="rma_menu_json_file" accept=".json,application/json" required></td>
                        </tr>
                    </table>
                    <?php submit_button( 'Yedeği Geri Yükle', 'primary', 'rma_submit_backup', false ); ?>
                </form>
            </div>
        </div>
    <?php }
}

<?php
/**
 * "Ürünüm Yok" — yönetim ekranları.
 *
 * `Restaurant_Menu_Automation` sınıfına `use` ile eklenir (bkz. qr-menu.php);
 * böylece `page_header()`, `admin_page_url()`, `import_title_key()` gibi
 * mevcut yardımcıları doğrudan kullanabilir, ama kendi mantığı tamamen bu
 * dosyada durur — hiçbir mevcut trait metodunun içeriği değişmez.
 *
 * @package QR_Menu_Suite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Urunum_Yok_Admin_Trait {

    /* -----------------------------------------------------------------
       1) "ÜRÜNÜM YOK" İŞARETLEME EKRANI
    ----------------------------------------------------------------- */

    public function render_urunum_yok_page() {
        $this->page_header(
            'Ürünüm Yok',
            'Bir malzeme tükendiğinde, o malzemeyi içeren ürünleri toplu olarak "Tükendi" işaretleyin. Belirlediğiniz saat gelince ürünler otomatik olarak tekrar aktif olur.'
        );

        if ( isset( $_GET['qmo_uy_isaretlendi'] ) ) {
            printf( '<div class="updated"><p><strong>%d</strong> ürün tükendi olarak işaretlendi.</p></div>', (int) $_GET['qmo_uy_isaretlendi'] );
        }
        if ( isset( $_GET['qmo_uy_hata'] ) ) {
            echo '<div class="error"><p>Lütfen bir malzeme, en az bir ürün ve geçerli (şu andan ileri) bir bitiş tarihi seçin.</p></div>';
        }
        if ( isset( $_GET['qmo_uy_aktiflestirildi'] ) ) {
            echo '<div class="updated"><p>Ürün tekrar aktif edildi.</p></div>';
        }

        $terms = get_terms( [ 'taxonomy' => 'rma_ingredient', 'hide_empty' => false, 'orderby' => 'name' ] );
        if ( is_wp_error( $terms ) ) $terms = [];

        $secili_id = isset( $_GET['malzeme_id'] ) ? (int) $_GET['malzeme_id'] : 0;

        echo '<div class="rma-card">';
        echo '<h2 class="rma-card-title">1. Malzeme Seç</h2>';

        if ( empty( $terms ) ) {
            printf(
                '<p class="rma-empty">Henüz malzeme eklenmemiş. Ürün ekleme ekranından veya <a href="%s">Malzemeler</a> ekranından malzeme ekleyin.</p>',
                esc_url( admin_url( 'edit-tags.php?taxonomy=rma_ingredient&post_type=rma_menu_item' ) )
            );
        } else {
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr( sanitize_key( wp_unslash( $_GET['page'] ?? 'qrms-rm-urunum-yok' ) ) ) . '">';
            echo '<p><input type="text" id="qmo-uy-arama" placeholder="Malzeme ara…" autocomplete="off" style="max-width:320px;"></p>';
            echo '<select name="malzeme_id" id="qmo-uy-select" style="min-width:280px;" onchange="this.form.submit()">';
            echo '<option value="">— Malzeme seçin —</option>';
            foreach ( $terms as $t ) {
                printf(
                    '<option value="%1$d" data-ad="%2$s" %3$s>%4$s (%5$d ürün)</option>',
                    (int) $t->term_id,
                    esc_attr( mb_strtolower( $t->name, 'UTF-8' ) ),
                    selected( $secili_id, $t->term_id, false ),
                    esc_html( $t->name ),
                    (int) $t->count
                );
            }
            echo '</select>';
            echo '<noscript><button type="submit" class="button">Seç</button></noscript>';
            echo '</form>';
            ?>
            <script>
            (function () {
                var arama = document.getElementById( 'qmo-uy-arama' );
                var secim = document.getElementById( 'qmo-uy-select' );
                if ( ! arama || ! secim ) return;
                var secenekler = Array.prototype.slice.call( secim.options );
                arama.addEventListener( 'input', function () {
                    var q = arama.value.toLocaleLowerCase( 'tr' );
                    secenekler.forEach( function ( o ) {
                        if ( ! o.value ) return;
                        o.hidden = '' !== q && -1 === o.getAttribute( 'data-ad' ).indexOf( q );
                    } );
                } );
            })();
            </script>
            <?php
        }
        echo '</div>';

        $secili_term = $secili_id ? get_term( $secili_id, 'rma_ingredient' ) : null;
        if ( $secili_term && ! is_wp_error( $secili_term ) ) {
            $this->render_urunum_yok_urun_listesi( $secili_term );
        }

        $this->render_urunum_yok_elle_liste();
        $this->render_urunum_yok_aktif_liste();

        $this->page_footer();
    }

    /**
     * Seçilen malzemeyi içeren ürünler + toplu işaretleme formu.
     *
     * @param WP_Term $term
     * @return void
     */
    private function render_urunum_yok_urun_listesi( $term ) {
        $urunler = get_posts( [
            'post_type'      => 'rma_menu_item',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => [ [
                'taxonomy' => 'rma_ingredient',
                'field'    => 'term_id',
                'terms'    => $term->term_id,
            ] ],
        ] );

        echo '<div class="rma-card" id="rma-uy-urunler">';
        printf( '<h2 class="rma-card-title">2. "%s" içeren ürünler (%d)</h2>', esc_html( $term->name ), count( $urunler ) );

        if ( empty( $urunler ) ) {
            echo '<p class="rma-empty">Bu malzemeyi içeren ürün bulunamadı. Ürün düzenleme ekranındaki "Malzemeler" kutusundan atama yapabilirsiniz.</p>';
            echo '</div>';
            return;
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'qmo_uy_mark_action', 'qmo_uy_mark_nonce' );
        echo '<input type="hidden" name="action" value="qmo_uy_isaretle">';
        echo '<input type="hidden" name="qmo_uy_malzeme_id" value="' . (int) $term->term_id . '">';

        echo '<table class="widefat striped" style="margin-bottom:14px;"><thead><tr>
                <th style="width:34px;"><input type="checkbox" id="qmo-uy-hepsi" checked></th>
                <th>Ürün</th><th>Kategori</th><th>Durum</th></tr></thead><tbody>';
        foreach ( $urunler as $u ) {
            $cats  = wp_get_object_terms( $u->ID, 'rma_category', [ 'fields' => 'names' ] );
            $cats  = is_wp_error( $cats ) ? [] : $cats;
            $durum = RMA_Tukendi::urun_tukendi( $u->ID )
                ? '<span style="color:#b32d2e;font-weight:600;">Tükendi</span>'
                : '<span style="color:#2e7d32;">Stokta</span>';

            printf(
                '<tr><td><input type="checkbox" class="qmo-uy-urun-cb" name="qmo_uy_urunler[]" value="%1$d" checked></td><td><a href="%2$s">%3$s</a></td><td>%4$s</td><td>%5$s</td></tr>',
                (int) $u->ID,
                esc_url( (string) get_edit_post_link( $u->ID ) ),
                esc_html( $u->post_title ),
                esc_html( implode( ', ', $cats ) ),
                $durum // phpcs:ignore WordPress.Security.EscapeOutput -- sabit, önceden esc_html uygulanmış HTML.
            );
        }
        echo '</tbody></table>';

        echo '<p><label for="qmo_uy_bitis"><strong>Bitiş Tarihi/Saati</strong> — bu saate kadar seçilen ürünler tükendi kalır, sonra otomatik aktif olur.</label><br>';
        echo '<input type="datetime-local" id="qmo_uy_bitis" name="qmo_uy_bitis" required style="margin-top:6px;"></p>';

        submit_button( 'Seçilenleri Tükendi Olarak İşaretle', 'primary', 'qmo_uy_submit', false );
        echo '</form>';

        echo '<script>(function(){var h=document.getElementById("qmo-uy-hepsi");if(!h)return;h.addEventListener("change",function(){document.querySelectorAll(".qmo-uy-urun-cb").forEach(function(cb){cb.checked=h.checked;});});})();</script>';

        echo '</div>';
    }

    /**
     * Elle (malzemeyle ilişkisiz) tükendi işaretlenmiş ürünler.
     *
     * Veri kaynağı hub bildirim şeridindeki "Elle işaretlenmiş" sayacıyla
     * AYNI sorgudur (qmo_urunum_yok_eksik_ozet); ikinci bir tarama yok.
     * "Tekrar Aktif Et" malzeme listesindeki "Şimdi Aktif Et" ile aynı
     * admin-post ucunu (qmo_uy_aktiflestir) kullanır.
     *
     * @return void
     */
    private function render_urunum_yok_elle_liste() {
        $ozet = function_exists( 'qmo_urunum_yok_eksik_ozet' )
            ? qmo_urunum_yok_eksik_ozet()
            : [ 'elle' => 0, 'elle_ids' => [] ];

        $ids   = isset( $ozet['elle_ids'] ) ? (array) $ozet['elle_ids'] : [];
        $limit = 50;

        echo '<div class="rma-card" id="rma-uy-elle">';
        echo '<h2 class="rma-card-title">Elle Kapatılan Ürünler</h2>';

        if ( empty( $ids ) ) {
            echo '<p class="rma-empty">Elle kapatılan ürün yok.</p></div>';
            return;
        }

        $goster = array_slice( $ids, 0, $limit );

        echo '<table class="widefat striped"><thead><tr><th>Ürün</th><th>Kategori</th><th></th></tr></thead><tbody>';
        foreach ( $goster as $id ) {
            $id    = (int) $id;
            $cats  = wp_get_object_terms( $id, 'rma_category', [ 'fields' => 'names' ] );
            $cats  = is_wp_error( $cats ) ? [] : $cats;
            $reactivate_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=qmo_uy_aktiflestir&urun_id=' . $id ),
                'qmo_uy_reactivate_' . $id
            );

            printf(
                '<tr><td><a href="%1$s">%2$s</a></td><td>%3$s</td><td><a class="button button-small" href="%4$s">Tekrar Aktif Et</a></td></tr>',
                esc_url( (string) get_edit_post_link( $id ) ),
                esc_html( get_the_title( $id ) ),
                esc_html( implode( ', ', $cats ) ),
                esc_url( $reactivate_url )
            );
        }
        echo '</tbody></table>';

        if ( count( $ids ) > $limit ) {
            printf(
                '<p class="rma-card-desc">İlk %d ürün listeleniyor; kalanını <a href="%s">Ürünlerim</a> sayfasından yönetebilirsiniz.</p>',
                $limit,
                esc_url( admin_url( 'edit.php?post_type=rma_menu_item' ) )
            );
        }

        echo '</div>';
    }

    /**
     * Şu an malzeme yüzünden tükendi işaretli ürünlerin listesi + tekil
     * "Şimdi Aktif Et" geri alma bağlantısı.
     *
     * @return void
     */
    private function render_urunum_yok_aktif_liste() {
        $ids = get_posts( [
            'post_type'      => 'rma_menu_item',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [ [ 'key' => '_qmo_tukendi_bitis', 'compare' => 'EXISTS' ] ],
        ] );

        echo '<div class="rma-card" id="rma-uy-aktif">';
        echo '<h2 class="rma-card-title">Malzeme Bazlı Tükendi Listesi</h2>';

        if ( empty( $ids ) ) {
            echo '<p class="rma-empty">Şu anda malzeme yüzünden tükendi işaretlenmiş ürün yok.</p></div>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>Ürün</th><th>Malzemeler</th><th>Aktif Olacağı Saat</th><th></th></tr></thead><tbody>';
        foreach ( $ids as $id ) {
            $kayitlar = RMA_Urunum_Yok_Stock::kayitlar( $id );
            $adlar    = [];
            foreach ( array_keys( $kayitlar ) as $tid ) {
                $t      = get_term( (int) $tid, 'rma_ingredient' );
                $adlar[] = ( $t && ! is_wp_error( $t ) ) ? $t->name : ( '#' . $tid );
            }

            $bitis          = (int) get_post_meta( $id, '_qmo_tukendi_bitis', true );
            $reactivate_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=qmo_uy_aktiflestir&urun_id=' . (int) $id ),
                'qmo_uy_reactivate_' . $id
            );

            printf(
                '<tr><td><a href="%1$s">%2$s</a></td><td>%3$s</td><td>%4$s</td><td><a class="button button-small" href="%5$s">Şimdi Aktif Et</a></td></tr>',
                esc_url( (string) get_edit_post_link( $id ) ),
                esc_html( get_the_title( $id ) ),
                esc_html( implode( ', ', $adlar ) ),
                esc_html( $bitis ? wp_date( 'd.m.Y H:i', $bitis ) : '—' ),
                esc_url( $reactivate_url )
            );
        }
        echo '</tbody></table></div>';
    }

    /**
     * "datetime-local" input değerini (saat dilimi bilgisiz) sitenin
     * zaman dilimine göre unix zaman damgasına çevirir.
     *
     * @param string $raw '2026-08-23T18:30' biçiminde.
     * @return int
     */
    private function uy_datetime_to_timestamp( $raw ) {
        $tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
        $dt = DateTime::createFromFormat( 'Y-m-d\TH:i', (string) $raw, $tz );
        return $dt ? $dt->getTimestamp() : 0;
    }

    public function handle_urunum_yok_mark() {
        if ( ! isset( $_POST['qmo_uy_mark_nonce'] ) || ! wp_verify_nonce( $_POST['qmo_uy_mark_nonce'], 'qmo_uy_mark_action' ) ) {
            wp_die( 'Güvenlik doğrulaması başarısız.' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Yetkiniz yok.' );

        $ingredient_id = isset( $_POST['qmo_uy_malzeme_id'] ) ? (int) $_POST['qmo_uy_malzeme_id'] : 0;
        $raw_datetime  = isset( $_POST['qmo_uy_bitis'] ) ? sanitize_text_field( $_POST['qmo_uy_bitis'] ) : '';
        $product_ids   = isset( $_POST['qmo_uy_urunler'] ) ? array_map( 'intval', (array) $_POST['qmo_uy_urunler'] ) : [];

        $term          = get_term( $ingredient_id, 'rma_ingredient' );
        $redirect_args = [ 'malzeme_id' => $ingredient_id ];

        if ( ! $term || is_wp_error( $term ) || '' === $raw_datetime || empty( $product_ids ) ) {
            $redirect_args['qmo_uy_hata'] = 1;
            wp_redirect( $this->admin_page_url( 'qrms-rm-urunum-yok', $redirect_args ) );
            exit;
        }

        $timestamp = $this->uy_datetime_to_timestamp( $raw_datetime );
        if ( $timestamp <= time() ) {
            $redirect_args['qmo_uy_hata'] = 1;
            wp_redirect( $this->admin_page_url( 'qrms-rm-urunum-yok', $redirect_args ) );
            exit;
        }

        $marked = 0;
        foreach ( $product_ids as $pid ) {
            $post = get_post( $pid );
            if ( ! $post || 'rma_menu_item' !== $post->post_type ) continue;
            if ( RMA_Urunum_Yok_Stock::isaretle( $pid, $ingredient_id, $timestamp ) ) $marked++;
        }

        $redirect_args['qmo_uy_isaretlendi'] = $marked;
        wp_redirect( $this->admin_page_url( 'qrms-rm-urunum-yok', $redirect_args ) );
        exit;
    }

    public function handle_urunum_yok_reactivate() {
        $urun_id = isset( $_GET['urun_id'] ) ? (int) $_GET['urun_id'] : 0;

        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'qmo_uy_reactivate_' . $urun_id ) ) {
            wp_die( 'Güvenlik doğrulaması başarısız.' );
        }
        if ( ! current_user_can( 'edit_post', $urun_id ) ) wp_die( 'Yetkiniz yok.' );

        RMA_Urunum_Yok_Stock::simdi_aktif_et( $urun_id );

        wp_redirect( $this->admin_page_url( 'qrms-rm-urunum-yok', [ 'qmo_uy_aktiflestirildi' => 1 ] ) );
        exit;
    }

    /* -----------------------------------------------------------------
       2) HUB EKRANINDAKİ "EKSİK ÜRÜN" BÖLÜMÜ
    ----------------------------------------------------------------- */

    /**
     * @param array{toplam:int,malzemeler:array<string,int>,elle:int,elle_ids?:int[]} $ozet
     * @return string HTML (boşsa '').
     */
    public function render_eksik_urun_notice( array $ozet ) {
        if ( empty( $ozet['malzemeler'] ) && empty( $ozet['elle'] ) ) {
            return '';
        }

        $li = '';
        foreach ( $ozet['malzemeler'] as $ad => $sayi ) {
            $li .= '<li>' . esc_html( $ad ) . ' — <strong>' . (int) $sayi . '</strong> ürün</li>';
        }
        if ( ! empty( $ozet['elle'] ) ) {
            $li .= '<li>Elle işaretlenmiş (malzemeyle ilişkisiz) — <strong>' . (int) $ozet['elle'] . '</strong> ürün</li>';
        }

        return '<div class="qrms-alert" style="margin-bottom:20px;">'
            . '<p><strong>Eksik Ürün</strong> — hangi malzemeden dolayı ürünlerin tükendi işaretlendiği:</p>'
            . '<ul style="margin:8px 0 0 20px;list-style:disc;">' . $li . '</ul>'
            . '</div>';
    }

    /* -----------------------------------------------------------------
       3) CSV EXPORT / IMPORT (malzeme atamaları dâhil, ID-then-title eşleşme)
    ----------------------------------------------------------------- */

    public function render_ingredient_csv_section() {
        $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=qmo_uy_csv_export' ), 'qmo_uy_csv_export' );

        echo '<div class="rma-card" id="rma-malzeme-aktar">';
        echo '<h2 class="rma-card-title">Malzeme Bazlı Toplu Aktarım (CSV)</h2>';
        echo '<p class="rma-card-desc">Ürünlerinizi ID, isim, kategori, fiyat ve atanmış malzemeleriyle CSV olarak dışa aktarın; aynı formatta düzenleyip geri yükleyerek mevcut ürünlerin malzeme atamalarını (ve varsa fiyat/kategorisini) toplu güncelleyin. İçe aktarma yeni ürün OLUŞTURMAZ, yalnızca eşleşen ürünleri günceller.</p>';

        echo '<p class="rma-actions"><a href="' . esc_url( $export_url ) . '" class="button button-primary">Ürünleri CSV Olarak Dışa Aktar</a></p>';

        if ( isset( $_GET['qmo_uy_csv_hata'] ) ) {
            echo '<div class="error"><p>Dosya okunamadı ya da önizleme süresi doldu. Lütfen tekrar deneyin.</p></div>';
        }
        if ( isset( $_GET['qmo_uy_csv_uygulandi'] ) ) {
            printf( '<div class="updated"><p><strong>%d</strong> ürün güncellendi.</p></div>', (int) $_GET['qmo_uy_csv_uygulandi'] );
        }

        $token   = isset( $_GET['qmo_uy_csv_token'] ) ? sanitize_text_field( wp_unslash( $_GET['qmo_uy_csv_token'] ) ) : '';
        $preview = $token ? get_transient( 'qmo_uy_csv_' . $token ) : false;

        if ( $preview && is_array( $preview ) ) {
            echo '<div class="notice notice-warning" style="padding:12px;margin:14px 0;">';
            echo '<p><strong>Önizleme</strong> — henüz hiçbir değişiklik uygulanmadı.</p>';
            printf( '<p>✅ <strong>%d</strong> ürün güncellenecek.</p>', (int) $preview['eslesen'] );

            if ( ! empty( $preview['eslesmeyen'] ) ) {
                printf(
                    '<p>⚠️ <strong>%d</strong> satır eşleşmedi (ürün bulunamadı): %s</p>',
                    count( $preview['eslesmeyen'] ),
                    esc_html( implode( ', ', array_slice( $preview['eslesmeyen'], 0, 20 ) ) )
                );
            }

            if ( ! empty( $preview['yeni_malzemeler'] ) ) {
                printf(
                    '<p>🆕 <strong>%d</strong> yeni malzeme oluşturulacak: %s</p>',
                    count( $preview['yeni_malzemeler'] ),
                    esc_html( implode( ', ', $preview['yeni_malzemeler'] ) )
                );
            } else {
                echo '<p>Yeni malzeme oluşturulmayacak.</p>';
            }

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;">';
            wp_nonce_field( 'qmo_uy_csv_confirm', 'qmo_uy_csv_confirm_nonce' );
            echo '<input type="hidden" name="action" value="qmo_uy_csv_import_confirm">';
            echo '<input type="hidden" name="qmo_uy_csv_token" value="' . esc_attr( $token ) . '">';
            submit_button( 'Onayla ve Uygula', 'primary', 'qmo_uy_csv_confirm_submit', false );
            echo '</form>';
            echo '<a class="button" href="' . esc_url( $this->admin_page_url( 'qrms-rm-diger', [], 'rma-malzeme-aktar' ) ) . '">Vazgeç</a>';
            echo '</div>';
        } else {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
            wp_nonce_field( 'qmo_uy_csv_import', 'qmo_uy_csv_nonce' );
            echo '<input type="hidden" name="action" value="qmo_uy_csv_import_preview">';
            echo '<table class="form-table rma-form-table"><tr><th><label for="qmo_uy_csv_file">CSV Dosyası</label></th>
                    <td><input type="file" name="qmo_uy_csv_file" id="qmo_uy_csv_file" accept=".csv,text/csv" required></td></tr></table>';
            submit_button( 'Dosyayı Yükle ve Önizle', 'secondary', 'qmo_uy_csv_preview_submit', false );
            echo '</form>';
        }

        echo '</div>';
    }

    public function handle_ingredient_csv_export() {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'qmo_uy_csv_export' ) ) {
            wp_die( 'Güvenlik doğrulaması başarısız.' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Yetkiniz yok.' );
        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="urunum-yok-malzemeler-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" ); // BOM — Excel'de Türkçe karakterler bozulmasın.
        fputcsv( $out, [ 'ID', 'Başlık', 'Kategori', 'Fiyat', 'Malzemeler' ], ';' );

        $batch_size = 200;
        $paged      = 1;

        do {
            $query = new WP_Query( [
                'post_type'              => 'rma_menu_item',
                'post_status'            => 'any',
                'posts_per_page'         => $batch_size,
                'paged'                  => $paged,
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => true,
                'orderby'                => 'title',
                'order'                  => 'ASC',
            ] );

            foreach ( $query->posts as $p ) {
                $cats = wp_get_object_terms( $p->ID, 'rma_category', [ 'fields' => 'names' ] );
                $ings = wp_get_object_terms( $p->ID, 'rma_ingredient', [ 'fields' => 'names' ] );

                fputcsv( $out, [
                    $p->ID,
                    $p->post_title,
                    is_wp_error( $cats ) ? '' : implode( ', ', $cats ),
                    get_post_meta( $p->ID, 'rma_price', true ),
                    is_wp_error( $ings ) ? '' : implode( '|', $ings ),
                ], ';' );
            }

            $count = count( $query->posts );
            unset( $query );
            $paged++;

            if ( ob_get_length() ) @ob_flush();
            flush();
        } while ( $count === $batch_size );

        fclose( $out );
        exit;
    }

    public function handle_ingredient_csv_import_preview() {
        if ( ! isset( $_POST['qmo_uy_csv_nonce'] ) || ! wp_verify_nonce( $_POST['qmo_uy_csv_nonce'], 'qmo_uy_csv_import' ) ) {
            wp_die( 'Güvenlik doğrulaması başarısız.' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Yetkiniz yok.' );

        if ( empty( $_FILES['qmo_uy_csv_file'] ) || $_FILES['qmo_uy_csv_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'qmo_uy_csv_hata' => 1 ], 'rma-malzeme-aktar' ) );
            exit;
        }

        $content = file_get_contents( $_FILES['qmo_uy_csv_file']['tmp_name'] );
        $content = preg_replace( "/^\xEF\xBB\xBF/", '', $content );
        $content = str_replace( [ "\r\n", "\r" ], "\n", $content );
        $lines   = array_values( array_filter( explode( "\n", $content ), fn( $l ) => '' !== trim( $l ) ) );

        if ( count( $lines ) < 2 ) {
            wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'qmo_uy_csv_hata' => 1 ], 'rma-malzeme-aktar' ) );
            exit;
        }

        $delimiter = ( strpos( $lines[0], ';' ) !== false ) ? ';' : ',';

        // 1. geçiş: satırları ayrıştır, başlık eşlemesi için gereken adları topla.
        $parsed         = [];
        $titles_needed  = [];
        foreach ( $lines as $i => $line ) {
            if ( 0 === $i ) continue; // başlık satırı
            $d     = str_getcsv( $line, $delimiter );
            $id    = isset( $d[0] ) ? (int) trim( (string) $d[0] ) : 0;
            $title = isset( $d[1] ) ? sanitize_text_field( $d[1] ) : '';

            if ( 0 === $id && '' === $title ) continue;
            if ( 0 === $id && '' !== $title ) $titles_needed[] = $title;

            $parsed[] = [
                'id'          => $id,
                'title'       => $title,
                'category'    => isset( $d[2] ) ? (string) $d[2] : '',
                'price'       => isset( $d[3] ) ? (string) $d[3] : '',
                'ingredients' => isset( $d[4] ) ? array_values( array_filter( array_map( 'trim', explode( '|', $d[4] ) ) ) ) : [],
            ];
        }

        // ID-then-title eşleştirmesi: repo genelindeki JSON import ile aynı
        // desen (bkz. RMA_Import_Export_Trait::import_title_map/import_title_key).
        $title_map = $this->import_title_map( array_map( fn( $t ) => [ 'title' => $t ], $titles_needed ) );

        $existing_ing       = get_terms( [ 'taxonomy' => 'rma_ingredient', 'hide_empty' => false ] );
        $existing_ing_lower = [];
        if ( ! is_wp_error( $existing_ing ) ) {
            foreach ( $existing_ing as $t ) {
                $existing_ing_lower[] = mb_strtolower( $t->name, 'UTF-8' );
            }
        }

        $rows            = [];
        $eslesen         = 0;
        $eslesmeyen      = [];
        $yeni_malzemeler = [];

        foreach ( $parsed as $row ) {
            $pid = 0;
            if ( $row['id'] > 0 ) {
                $post = get_post( $row['id'] );
                if ( $post && 'rma_menu_item' === $post->post_type ) $pid = $row['id'];
            }
            if ( ! $pid && '' !== $row['title'] ) {
                $key = $this->import_title_key( $row['title'] );
                if ( isset( $title_map[ $key ] ) ) $pid = (int) $title_map[ $key ];
            }

            foreach ( $row['ingredients'] as $ad ) {
                $lower = mb_strtolower( $ad, 'UTF-8' );
                if ( ! in_array( $lower, $existing_ing_lower, true ) && ! isset( $yeni_malzemeler[ $lower ] ) ) {
                    $yeni_malzemeler[ $lower ] = $ad;
                }
            }

            if ( $pid ) {
                $eslesen++;
                $rows[] = [
                    'pid'         => $pid,
                    'category'    => $row['category'],
                    'price'       => $row['price'],
                    'ingredients' => $row['ingredients'],
                ];
            } else {
                $eslesmeyen[] = '' !== $row['title'] ? $row['title'] : ( 'ID ' . $row['id'] );
            }
        }

        $token = wp_generate_password( 12, false, false );
        set_transient( 'qmo_uy_csv_' . $token, [
            'rows'            => $rows,
            'eslesen'         => $eslesen,
            'eslesmeyen'      => $eslesmeyen,
            'yeni_malzemeler' => array_values( $yeni_malzemeler ),
            'user'            => get_current_user_id(),
        ], 15 * MINUTE_IN_SECONDS );

        wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'qmo_uy_csv_token' => $token ], 'rma-malzeme-aktar' ) );
        exit;
    }

    public function handle_ingredient_csv_import_confirm() {
        if ( ! isset( $_POST['qmo_uy_csv_confirm_nonce'] ) || ! wp_verify_nonce( $_POST['qmo_uy_csv_confirm_nonce'], 'qmo_uy_csv_confirm' ) ) {
            wp_die( 'Güvenlik doğrulaması başarısız.' );
        }
        if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Yetkiniz yok.' );

        $token   = isset( $_POST['qmo_uy_csv_token'] ) ? sanitize_text_field( $_POST['qmo_uy_csv_token'] ) : '';
        $preview = $token ? get_transient( 'qmo_uy_csv_' . $token ) : false;

        if ( ! $preview || ! is_array( $preview ) ) {
            wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'qmo_uy_csv_hata' => 1 ], 'rma-malzeme-aktar' ) );
            exit;
        }

        delete_transient( 'qmo_uy_csv_' . $token );

        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
        wp_defer_term_counting( true );

        $ing_cache = [];
        $cat_cache = [];
        $updated   = 0;

        foreach ( $preview['rows'] as $row ) {
            $pid = (int) ( $row['pid'] ?? 0 );
            if ( ! $pid ) continue;

            if ( '' !== trim( (string) $row['price'] ) ) {
                update_post_meta( $pid, 'rma_price', sanitize_text_field( $row['price'] ) );
            }

            if ( '' !== trim( (string) $row['category'] ) ) {
                $cat_ids = [];
                foreach ( array_filter( array_map( 'trim', explode( ',', $row['category'] ) ) ) as $name ) {
                    if ( ! array_key_exists( $name, $cat_cache ) ) {
                        $term            = term_exists( $name, 'rma_category' ) ?: wp_insert_term( $name, 'rma_category' );
                        $cat_cache[ $name ] = ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) ? (int) $term['term_id'] : 0;
                    }
                    if ( $cat_cache[ $name ] ) $cat_ids[] = $cat_cache[ $name ];
                }
                if ( $cat_ids ) wp_set_object_terms( $pid, $cat_ids, 'rma_category' );
            }

            $ing_ids = [];
            foreach ( $row['ingredients'] as $name ) {
                if ( ! array_key_exists( $name, $ing_cache ) ) {
                    $term            = term_exists( $name, 'rma_ingredient' ) ?: wp_insert_term( $name, 'rma_ingredient' );
                    $ing_cache[ $name ] = ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) ? (int) $term['term_id'] : 0;
                }
                if ( $ing_cache[ $name ] ) $ing_ids[] = $ing_cache[ $name ];
            }
            wp_set_object_terms( $pid, $ing_ids, 'rma_ingredient', false );

            $updated++;
        }

        wp_defer_term_counting( false );
        $this->force_bump_cache_version();

        wp_redirect( $this->admin_page_url( 'qrms-rm-diger', [ 'qmo_uy_csv_uygulandi' => $updated ], 'rma-malzeme-aktar' ) );
        exit;
    }
}

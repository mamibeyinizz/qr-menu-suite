<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Ajax_Trait {

    public function ajax_toggle_status() {
        check_ajax_referer( 'rma_admin_nonce', 'security' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error();
        if ( isset( $_POST['id'] ) ) {
            // Whitelist: yalnızca '0' veya '1' kabul edilir
            $status = ( $_POST['status'] ?? '' ) === '1' ? '1' : '0';
            update_post_meta( intval( $_POST['id'] ), 'rma_active', $status );
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    /**
     * Ürün listesindeki Tükendi anahtarı.
     *
     * Göster/Gizle'den bağımsızdır: rma_active'e dokunulmaz, yalnızca
     * `_rma_tukendi` yazılır. Menü önbelleği meta kancasıyla tazelenir.
     */
    public function ajax_toggle_tukendi() {
        check_ajax_referer( 'rma_admin_nonce', 'security' );

        $post_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( $post_id < 1 || get_post_type( $post_id ) !== 'rma_menu_item' ) {
            wp_send_json_error();
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error();
        }

        RMA_Tukendi::kaydet( $post_id, ( $_POST['status'] ?? '' ) === '1' );
        $this->bump_cache_version();
        wp_send_json_success();
    }

    public function ajax_save_category_order() {
        check_ajax_referer( 'rma_admin_nonce', 'security' );
        if ( ! current_user_can( 'manage_categories' ) ) wp_send_json_error();
        if ( isset( $_POST['order'] ) ) {
            foreach ( array_map( 'intval', $_POST['order'] ) as $i => $tid ) {
                update_term_meta( $tid, 'rma_cat_order', $i );
            }
            // Kategori sırası menü çıktısını belirler — önbelleği tazele.
            $this->bump_cache_version();
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    /**
     * Menü listesi uç noktası.
     *
     * PERF: Sonuç (HTML + kategori listesi) sürüm damgalı bir transient'ta
     * saklanır. Aynı filtre/sıralama/arama kombinasyonunu isteyen sonraki
     * ziyaretçiler tek bir option okumasıyla yanıt alır; WP_Query, kart
     * render'ı ve çeviri köprüsü hiç çalışmaz. İçerik değiştiğinde
     * bump_cache_version() tüm kombinasyonları aynı anda geçersiz kılar.
     */
    public function ajax_load_items() {
        // Notice/warning'lerin JSON çıktısına sızıp yanıtı bozmasını engelle
        // (hatalar error_log'a yazılmaya devam eder). "Yükleme hatası oluştu"
        // belirtisinin en yaygın sebebi budur.
        @ini_set( 'display_errors', '0' );

        // Soft nonce check — public menu data, don't die on stale nonce (caching fix)
        check_ajax_referer( 'rma_ajax_nonce', 'security', false );

        $filters    = isset( $_POST['filters'] )  ? array_map( 'sanitize_text_field', (array) $_POST['filters'] ) : [];
        $sort_by    = sanitize_text_field( $_POST['sort_by']  ?? '' );
        $search     = sanitize_text_field( $_POST['search']   ?? '' );

        $suggest_cfg_raw = $_POST['suggest_cfg'] ?? [];
        if ( is_string( $suggest_cfg_raw ) ) {
            $suggest_cfg = json_decode( stripslashes( $suggest_cfg_raw ), true ) ?? [];
        } else {
            $suggest_cfg = (array) $suggest_cfg_raw;
        }
        $suggest_mode       = sanitize_text_field( $suggest_cfg['mode']       ?? 'system' );
        $suggest_slug       = sanitize_text_field( $suggest_cfg['slug']       ?? '' );
        $suggest_manual_ids = array_map( 'intval', (array) ( $suggest_cfg['manual_ids'] ?? [] ) );

        // Anahtar kararlılığı: aynı filtre kümesi farklı sırada gelse bile
        // tek bir önbellek girdisine düşsün.
        $filters_key = $filters;
        sort( $filters_key );
        $manual_key = $suggest_manual_ids;
        sort( $manual_key );

        $cache_key = $this->cache_key( 'menu', [
            'f'  => $filters_key,
            's'  => $sort_by,
            'q'  => $search,
            'sm' => $suggest_mode,
            'ss' => $suggest_slug,
            'si' => $manual_key,
        ] );

        $payload = $this->cache_get( $cache_key );

        if ( ! is_array( $payload ) ) {
            $payload = $this->build_menu_payload(
                $filters,
                $sort_by,
                $search,
                $suggest_mode,
                $suggest_slug,
                $suggest_manual_ids
            );

            // Arama sonuçları daha kısa süre saklanır; çok uzun (bot kaynaklı
            // olabilecek) aramalar hiç saklanmaz ki wp_options şişmesin.
            $ttl = ( '' === $search ) ? 5 * MINUTE_IN_SECONDS : 2 * MINUTE_IN_SECONDS;
            /**
             * Menü önbelleği ömrü (saniye).
             *
             * @param int    $ttl
             * @param string $search
             */
            $ttl = (int) apply_filters( 'rma_menu_cache_ttl', $ttl, $search );

            // Bayt uzunluğu üzerinden sınır (mbstring bağımlılığı olmadan).
            if ( strlen( $search ) <= 80 ) {
                $this->cache_set( $cache_key, $payload, $ttl );
            }
        }

        $this->flush_stray_output();
        wp_send_json_success( $payload );
    }

    /**
     * Menü yanıtını (html + kategoriler + öneri bayrağı) üretir.
     *
     * TEK SORGU MİMARİSİ
     * Tüm uygun ürünler TEK WP_Query ile çekilir; post meta, terim ve
     * öne çıkan görsel cache'leri toplu doldurulur, gruplama PHP'de
     * yapılır. Sıralama sorgu düzeyinde korunur.
     *
     * @return array{html:string,categories:array,has_suggestions:bool}
     */
    private function build_menu_payload( array $filters, $sort_by, $search, $suggest_mode, $suggest_slug, array $suggest_manual_ids ) {

        /**
         * Tek seferde çekilecek azami ürün sayısı. Eskiden -1 (sınırsız)
         * idi; çok büyük menülerde bellek/süre limitine takılma riski
         * taşıyordu. Gerçekçi menülerin tamamı bu sınırın altındadır.
         *
         * @param int $limit
         */
        $limit = (int) apply_filters( 'rma_max_menu_items', 800 );

        $base = [
            'post_type'              => 'rma_menu_item',
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
            'meta_query'             => [
                'relation' => 'AND',
                [ 'key' => 'rma_active', 'value' => '1', 'compare' => '=' ],
            ],
        ];

        $filter_map = [
            'vegan'       => 'rma_is_vegan',
            'vegetarian'  => 'rma_is_vegetarian',
            'gluten_free' => 'rma_is_gluten_free',
        ];
        $allowed_allergen_slugs = array_keys( $this->get_allergen_definitions() );
        $exclude_allergens      = [];
        foreach ( $filters as $f ) {
            if ( isset( $filter_map[ $f ] ) ) {
                $base['meta_query'][] = [ 'key' => $filter_map[ $f ], 'value' => '1', 'compare' => '=' ];
            } elseif ( strpos( $f, 'allergen_' ) === 0 ) {
                $slug = substr( $f, strlen( 'allergen_' ) );
                if ( in_array( $slug, $allowed_allergen_slugs, true ) ) $exclude_allergens[] = $slug;
            }
        }

        // Alerjen "hariç tut" tax_query klozu — indexli taxonomy sorgusu, meta_query'e göre çok daha performanslı.
        $allergen_clause = $exclude_allergens
            ? [ 'taxonomy' => 'rma_allergen', 'field' => 'slug', 'terms' => $exclude_allergens, 'operator' => 'NOT IN' ]
            : null;

        $search_filter = null;
        if ( ! empty( $search ) ) {
            $search_terms = preg_split( '/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY );
            $search_terms = array_map( 'sanitize_text_field', $search_terms );

            // Filtre YALNIZCA kendi sorgumuza uygulanır: aşağıdaki
            // 'rma_search' sorgu değişkeni imza görevi görür. Böylece bu
            // arada çalışabilecek başka bir WP_Query (tema, eklenti,
            // Elementor) yanlışlıkla etkilenmez.
            $base['rma_search'] = 1;

            $search_filter = function ( $where, $query ) use ( $search_terms ) {
                if ( ! $query instanceof WP_Query || ! $query->get( 'rma_search' ) ) return $where;

                global $wpdb;
                $clauses = [];
                foreach ( $search_terms as $term ) {
                    $like      = '%' . $wpdb->esc_like( $term ) . '%';
                    $clauses[] = $wpdb->prepare(
                        "( {$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s )",
                        $like, $like, $like
                    );
                }
                $where .= ' AND ( ' . implode( ' AND ', $clauses ) . ' )';
                return $where;
            };
            add_filter( 'posts_where', $search_filter, 10, 2 );
        }

        switch ( $sort_by ) {
            case 'az':         $base['orderby'] = 'title';         $base['order'] = 'ASC';  break;
            case 'price_asc':  $base['meta_key'] = 'rma_price';    $base['orderby'] = 'meta_value_num'; $base['order'] = 'ASC';  break;
            case 'price_desc': $base['meta_key'] = 'rma_price';    $base['orderby'] = 'meta_value_num'; $base['order'] = 'DESC'; break;
            case 'carbs':      $base['meta_key'] = 'rma_carbs';    $base['orderby'] = 'meta_value_num'; $base['order'] = 'DESC'; break;
            case 'protein':    $base['meta_key'] = 'rma_protein';  $base['orderby'] = 'meta_value_num'; $base['order'] = 'DESC'; break;
            default:           $base['orderby'] = 'menu_order';    $base['order'] = 'ASC';
        }

        $main_args = $base;
        if ( $allergen_clause ) $main_args['tax_query'] = [ $allergen_clause ];
        $main_q    = new WP_Query( $main_args );
        $all_posts = $main_q->posts;

        // posts_where arama filtresi tek sorguya uygulandı — hemen kaldır
        if ( $search_filter ) remove_filter( 'posts_where', $search_filter, 10 );

        // PERF (N+1 fix): kartlardaki get_the_post_thumbnail_url() her ürün
        // için ayrı attachment sorgusu doğuruyordu (100 ürün = 200+ sorgu).
        // Tüm öne çıkan görseller ve metaları burada TEK sorguda ısıtılır.
        if ( $all_posts && function_exists( 'update_post_thumbnail_cache' ) ) {
            update_post_thumbnail_cache( $main_q );
        }

        // Ürünleri kategorilere dağıt (get_the_terms primed cache kullanır — ek sorgu yok)
        $post_map = [];   // id => WP_Post (sorgu sırası korunur)
        $by_term  = [];   // term_id => [post_id, ...]
        $uncat    = [];
        foreach ( $all_posts as $p ) {
            $post_map[ $p->ID ] = $p;
            $pterms = get_the_terms( $p->ID, 'rma_category' );
            if ( empty( $pterms ) || is_wp_error( $pterms ) ) {
                $uncat[] = $p->ID;
                continue;
            }
            foreach ( $pterms as $pt ) {
                $by_term[ $pt->term_id ][] = $p->ID;
            }
        }

        $terms = get_terms( [ 'taxonomy' => 'rma_category', 'hide_empty' => true ] );
        if ( is_wp_error( $terms ) ) $terms = [];

        // PERF: sıra değerleri karşılaştırma fonksiyonu içinde değil, bir kez
        // okunur. usort karşılaştırma başına 2 get_term_meta çağırıyordu
        // (n·log n meta erişimi); artık terim başına 1 erişim yeterli.
        $term_order = [];
        foreach ( $terms as $term ) {
            $term_order[ $term->term_id ] = (int) get_term_meta( $term->term_id, 'rma_cat_order', true );
        }
        usort( $terms, function ( $a, $b ) use ( $term_order ) {
            return $term_order[ $a->term_id ] <=> $term_order[ $b->term_id ];
        } );

        $html            = '';
        $returned_cats   = [];
        $rendered_ids    = [];
        $has_suggestions = false;

        // Bir ID listesini karta çevirir. $mark=true ise kategori dedup'ına işaretler
        // (aynı ürün birden çok kategoride tek kez görünsün). Öneriler bölümü
        // $mark=false çağırır: böylece önerilen ürün KENDİ kategorisinde de listelenir.
        $render_ids = function ( array $ids, $mark = true ) use ( &$rendered_ids ) {
            $out = '';
            foreach ( $ids as $rid ) {
                if ( isset( $rendered_ids[ $rid ] ) ) continue;
                if ( $mark ) $rendered_ids[ $rid ] = true;
                $out .= $this->render_card( $rid );
            }
            return $out;
        };

        $suggestions_label = $this->t( 'Öneriler' );

        $wrap_suggestions = function ( $section ) use ( $suggestions_label ) {
            return '<div class="rma-section" data-cat-slug="__suggestions__">'
                 . '<h2 class="rma-section-title rma-suggestions-title">✦ ' . esc_html( $suggestions_label ) . '</h2>'
                 . '<div class="rma-grid">' . $section . '</div>'
                 . '</div>';
        };

        /* ---- Öneriler ---- */
        if ( $suggest_mode === 'manual' && ! empty( $suggest_manual_ids ) ) {
            // Ana sorgu aktif/yayınlanmış/arama/alerjen filtrelerini zaten
            // uyguladı — post_map'te olmayan manuel ID'ler otomatik elenir.
            $valid_ids = array_values( array_intersect( $suggest_manual_ids, array_keys( $post_map ) ) );
            $section   = $render_ids( $valid_ids, false );
            if ( '' !== $section ) {
                $has_suggestions = true;
                $html           .= $wrap_suggestions( $section );
            }
        } elseif ( $suggest_mode === 'system' && ! empty( $suggest_slug ) ) {
            $suggest_term = get_term_by( 'slug', $suggest_slug, 'rma_category' );
            if ( $suggest_term && ! is_wp_error( $suggest_term ) ) {
                $section = $render_ids( $by_term[ $suggest_term->term_id ] ?? [], false );
            } else {
                // Kategori yoksa "Önerilen" rozetliler (meta cache primed — ek sorgu yok)
                $rec_ids = [];
                foreach ( $post_map as $rid => $rp ) {
                    if ( get_post_meta( $rid, 'rma_badge_recommended', true ) === '1' ) $rec_ids[] = $rid;
                }
                $section = $render_ids( $rec_ids, false );
            }
            if ( '' !== $section ) {
                $has_suggestions = true;
                $html           .= $wrap_suggestions( $section );
            }
        }

        /* ---- Kategoriler ---- */
        foreach ( $terms as $term ) {
            $section = $render_ids( $by_term[ $term->term_id ] ?? [] );
            if ( '' === $section ) continue;

            // Kategori adı tek yerden çevrilir: hem bölüm başlığı hem de
            // JS'in nav butonlarını kurduğu 'categories' verisi bunu kullanır.
            // slug ÇEVRİLMEZ — data-slug eşleşmesi ve kaydırma ona bağlı.
            $cat_name = $this->t_term( $term );

            $returned_cats[] = [ 'name' => $cat_name, 'slug' => $term->slug ];
            $html .= '<div class="rma-section" data-cat-slug="' . esc_attr( $term->slug ) . '">'
                   . '<h2 class="rma-section-title">' . esc_html( $cat_name ) . '</h2>'
                   . '<div class="rma-grid">' . $section . '</div>'
                   . '</div>';
        }

        /* ---- Kategorisiz ---- */
        $section = $render_ids( $uncat );
        if ( '' !== $section ) {
            $other_name = $this->t( 'Diğer' );
            $returned_cats[] = [ 'name' => $other_name, 'slug' => 'diger' ];
            $html .= '<div class="rma-section" data-cat-slug="diger">'
                   . '<h2 class="rma-section-title">' . esc_html( $other_name ) . '</h2>'
                   . '<div class="rma-grid">' . $section . '</div>'
                   . '</div>';
        }

        if ( '' === $html ) {
            return [
                'html'            => '<div class="rma-empty">' . esc_html( $this->t( 'Ürün bulunamadı.' ) ) . '</div>',
                'categories'      => [],
                'has_suggestions' => false,
            ];
        }

        return [
            'html'            => $html,
            'categories'      => $returned_cats,
            'has_suggestions' => $has_suggestions,
        ];
    }

    /**
     * JSON göndermeden hemen önce, olası stray çıktıyı (PHP notice, tema/eklenti
     * whitespace'i) temizler. "Unexpected token < in JSON" / parsererror'un
     * klasik çözümü. Aktif bir output buffer varsa yalnızca onu boşaltır.
     */
    private function flush_stray_output() {
        if ( ob_get_length() ) {
            @ob_clean();
        }
    }

    /**
     * Ürün detay uç noktası (modal içeriği).
     *
     * PERF: Üretilen HTML, `the_content` filtre zincirini (kısa kod, oEmbed,
     * tema filtreleri) çalıştırdığı için pahalıdır. Sonuç ürün + dil bazında
     * önbelleklenir. Görüntülenme sayacı önbellekten bağımsız işler ve
     * yalnızca gerçek açılışlarda artar — komşu kartların sessiz ön yüklemesi
     * (prefetch) artık sayacı şişirmez ve gereksiz yazma sorgusu üretmez.
     */
    public function ajax_get_product_details() {
        @ini_set( 'display_errors', '0' );
        // Soft nonce check — public product data, don't die on stale nonce
        check_ajax_referer( 'rma_ajax_nonce', 'security', false );

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error(); die(); }

        // Güvenlik: yalnızca yayınlanmış menü ürünleri sunulur.
        // Aksi halde herhangi bir post ID'siyle taslak/özel içerik sızdırılabilir.
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'rma_menu_item' || $post->post_status !== 'publish' ) {
            wp_send_json_error();
            die();
        }
        if ( get_post_meta( $id, 'rma_active', true ) !== '1' ) {
            wp_send_json_error();
            die();
        }

        $is_prefetch = ! empty( $_POST['prefetch'] );
        if ( ! $is_prefetch ) {
            update_post_meta( $id, 'rma_views', (int) get_post_meta( $id, 'rma_views', true ) + 1 );
        }

        $cache_key = $this->cache_key( 'item', [ 'id' => $id ] );
        $html      = $this->cache_get( $cache_key );

        if ( ! is_string( $html ) || '' === $html ) {
            $html = $this->render_product_details( $post );
            /**
             * Ürün detay önbelleğinin ömrü (saniye).
             *
             * @param int $ttl
             * @param int $id
             */
            $ttl = (int) apply_filters( 'rma_item_cache_ttl', 10 * MINUTE_IN_SECONDS, $id );
            $this->cache_set( $cache_key, $html, $ttl );
        }

        $this->flush_stray_output();
        wp_send_json_success( $html );
        die();
    }

    /**
     * Ürün detay modalının HTML gövdesi.
     *
     * @param WP_Post $post
     * @return string
     */
    private function render_product_details( $post ) {
        $id = $post->ID;

        // Fiyat gösterimi menü kartıyla aynı kaynaktan (aktif kampanya +
        // kombin fiyatı dahil): bkz. class-kampanya.php.
        $price_html = RMA_Kampanya::fiyat_html( $id );

        $title = $this->t_field( $id, 'product', 'title', $post->post_title );
        $img   = $this->render_modal_image( $id, $title );
        $tukendi_banner = '';
        if ( RMA_Tukendi::urun_tukendi( $id ) ) {
            $tukendi_banner = '<p class="rma-modal-tukendi">' . esc_html( $this->t( RMA_Tukendi::MESAJ ) ) . '</p>';
        }

        // Çeviri, the_content filtresinden ÖNCE uygulanıyor: kısa kod ve oEmbed
        // işleme çevrilmiş metin üzerinde çalışsın.
        $desc = apply_filters( 'the_content', $this->t_field( $id, 'product', 'content', $post->post_content ) );
        if ( empty( trim( strip_tags( $desc ) ) ) ) {
            $desc = '<p>' . esc_html( $this->t_field( $id, 'product', 'excerpt', $post->post_excerpt ) ) . '</p>';
        }

        $attrs = '';
        if ( RMA_Tukendi::urun_tukendi( $id ) ) {
            $attrs .= '<span class="rma-attr rma-attr-tukendi">' . esc_html( $this->t( RMA_Tukendi::ETIKET ) ) . '</span>';
        }
        if ( get_post_meta( $id, 'rma_badge_recommended', true ) === '1' ) $attrs .= '<span class="rma-attr">⭐ ' . esc_html( $this->t( 'Önerilen' ) ) . '</span>';
        if ( get_post_meta( $id, 'rma_is_vegan',          true ) === '1' ) $attrs .= '<span class="rma-attr">🌿 ' . esc_html( $this->t( 'Vegan' ) ) . '</span>';
        if ( get_post_meta( $id, 'rma_is_vegetarian',     true ) === '1' ) $attrs .= '<span class="rma-attr">🥦 ' . esc_html( $this->t( 'Vejetaryen' ) ) . '</span>';
        if ( get_post_meta( $id, 'rma_is_gluten_free',    true ) === '1' ) $attrs .= '<span class="rma-attr">🌾 ' . esc_html( $this->t( 'Glütensiz' ) ) . '</span>';

        $spicy = get_post_meta( $id, 'rma_spicy_level', true );
        $cal   = get_post_meta( $id, 'rma_calories',    true );
        $grams = get_post_meta( $id, 'rma_grams',       true );
        $prot  = get_post_meta( $id, 'rma_protein',     true );
        $carbs = get_post_meta( $id, 'rma_carbs',       true );
        $fat   = get_post_meta( $id, 'rma_fat',         true );
        $prep  = get_post_meta( $id, 'rma_prep_time',   true );

        if ( $spicy ) $attrs .= '<span class="rma-attr">' . str_repeat( '🌶️', min( (int) $spicy, 3 ) ) . ' ' . esc_html( $this->t( 'Acı' ) ) . '</span>';
        if ( $prep  ) $attrs .= '<span class="rma-attr">⏱️ ' . esc_html( (int) $prep ) . ' ' . esc_html( $this->t( 'dk' ) ) . '</span>';
        if ( $cal   ) $attrs .= '<span class="rma-attr">🔥 ' . esc_html( $cal )   . ' ' . esc_html( $this->t( 'kcal' ) ) . '</span>';
        if ( $grams ) $attrs .= '<span class="rma-attr">⚖️ ' . esc_html( $grams ) . ' ' . esc_html( $this->t( 'g' ) ) . '</span>';
        if ( $prot  ) $attrs .= '<span class="rma-attr">💪 ' . esc_html( $prot )  . ' ' . esc_html( $this->t( 'g protein' ) ) . '</span>';
        if ( $carbs ) $attrs .= '<span class="rma-attr">🍞 ' . esc_html( $carbs ) . ' ' . esc_html( $this->t( 'g karb' ) ) . '</span>';
        if ( $fat   ) $attrs .= '<span class="rma-attr">🧈 ' . esc_html( $fat )   . ' ' . esc_html( $this->t( 'g yağ' ) ) . '</span>';

        /* ---- Şeffaf Menü: et menşei, alerjen, alkol/domuz uyarıları ---- */
        $meat_val = get_post_meta( $id, 'rma_meat_origin', true );
        $meat_opts = $this->get_meat_origin_options();
        $compliance = '';
        if ( $meat_val && ! empty( $meat_opts[ $meat_val ] ) ) {
            $compliance .= '<span class="rma-compliance-tag meat">🥩 ' . esc_html( $this->t( $meat_opts[ $meat_val ] ) ) . '</span>';
        }
        if ( get_post_meta( $id, 'rma_contains_alcohol', true ) === '1' ) {
            $compliance .= '<span class="rma-compliance-tag warn">🍷 ' . esc_html( $this->t( 'Alkol İçerir' ) ) . '</span>';
        }
        if ( get_post_meta( $id, 'rma_contains_pork', true ) === '1' ) {
            $compliance .= '<span class="rma-compliance-tag warn">🐖 ' . esc_html( $this->t( 'Domuz Türevi İçerir' ) ) . '</span>';
        }

        // get_the_terms — wp_get_object_terms'ün aksine terim cache'ini
        // kullanır, aynı ürün ikinci kez açıldığında ek sorgu doğmaz.
        $allergen_terms = get_the_terms( $id, 'rma_allergen' );
        $allergen_html  = '';
        if ( ! is_wp_error( $allergen_terms ) && ! empty( $allergen_terms ) ) {
            $defs = $this->get_allergen_definitions();
            $tags = '';
            foreach ( $allergen_terms as $t ) {
                $icon  = $defs[ $t->slug ]['icon'] ?? '⚠️';
                $tags .= '<span class="rma-allergen-tag">' . $icon . ' ' . esc_html( $this->t_term( $t, 'allergen' ) ) . '</span>';
            }
            $allergen_html = '<div class="rma-modal-allergens"><strong>' . esc_html( $this->t( 'Alerjen Uyarısı:' ) ) . '</strong><div class="rma-allergen-list">' . $tags . '</div></div>';
        }

        $compliance_html = $compliance ? '<div class="rma-attrs rma-compliance-row">' . $compliance . '</div>' : '';

        return sprintf(
            '<div class="rma-modal-img-wrap%s">
                %s
                %s
            </div>
            <div class="rma-modal-body">
                <h2 class="rma-modal-title">%s</h2>
                %s
                <p class="rma-modal-price">%s</p>
                <div class="rma-modal-desc">%s</div>
                %s
                %s
                %s
            </div>',
            RMA_Tukendi::urun_tukendi( $id ) ? ' is-tukendi' : '',
            $img,
            RMA_Tukendi::rozet_html( $id ),
            esc_html( $title ),
            $tukendi_banner,
            $price_html,
            $desc,
            $attrs ? '<div class="rma-attrs">' . $attrs . '</div>' : '',
            $compliance_html,
            $allergen_html
        );
    }

    /**
     * Modalın ana ürün görseli.
     *
     * PERF: Bu görsel modal açılır açılmaz ekranda olur; `loading="lazy"`
     * tarayıcının indirmeye başlamasını en az bir yerleşim turu
     * geciktiriyordu (görünürlük değerlendirmesi ancak yerleşimden sonra
     * yapılır). Bunun yerine açıkça `eager` + `fetchpriority="high"`
     * verilir: AJAX cevabı DOM'a girer girmez indirme yüksek öncelikle
     * başlar.
     *
     * `srcset`/`sizes` eklenmesiyle telefonlar artık her zaman 1024 px'lik
     * `large` dosyayı indirmez; modal kutusu en fazla 560 CSS px olduğu
     * için cihaz piksel oranına uyan en küçük dosya seçilir.
     *
     * Etiket elle kurulur (wp_get_attachment_image yerine): WP sürümleri
     * arasında `loading`/`fetchpriority` otomatik hesaplaması değiştiği
     * için, açıkça verdiğimiz değerlerin ezilmemesi garanti altına alınır.
     *
     * @param int    $id  Ürün (post) ID'si.
     * @param string $alt Görsel alternatif metni.
     * @return string <img> etiketi.
     */
    private function render_modal_image( $id, $alt ) {
        $thumb_id = get_post_thumbnail_id( $id );
        $src      = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'large' ) : false;

        if ( ! $src || empty( $src[0] ) ) {
            return sprintf(
                '<img src="%s" class="rma-modal-img" alt="%s" width="600" height="380" fetchpriority="high" decoding="async">',
                esc_url( 'https://placehold.co/600x380/111111/c9a84c?text=%E2%97%86' ),
                esc_attr( $alt )
            );
        }

        $srcset     = wp_get_attachment_image_srcset( $thumb_id, 'large' );
        $srcset_att = $srcset
            ? sprintf( ' srcset="%s" sizes="%s"', esc_attr( $srcset ), esc_attr( '(max-width: 560px) 100vw, 560px' ) )
            : '';

        return sprintf(
            '<img src="%s"%s class="rma-modal-img" alt="%s" width="%d" height="%d" loading="eager" fetchpriority="high" decoding="async">',
            esc_url( $src[0] ),
            $srcset_att,
            esc_attr( $alt ),
            (int) $src[1],
            (int) $src[2]
        );
    }

}

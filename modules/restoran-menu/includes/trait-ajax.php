<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Ajax_Trait {

    /**
     * Menü Görünümü canlı önizlemesi — rastgele başka bir ürün.
     *
     * Yalnızca ad / açıklama / fiyat / kategori / küçük görsel döner;
     * renk değişkenlerine dokunulmaz.
     */
    public function ajax_color_preview_item() {
        check_ajax_referer( 'rma_admin_nonce', 'security' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error();
        }

        $exclude = isset( $_POST['exclude'] ) ? (int) $_POST['exclude'] : 0;
        wp_send_json_success( $this->get_color_preview_item( $exclude ) );
    }

    public function ajax_toggle_status() {
        check_ajax_referer( 'rma_admin_nonce', 'security' );

        $post_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( $post_id < 1 || get_post_type( $post_id ) !== 'rma_menu_item' ) {
            wp_send_json_error();
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error();
        }

        // Whitelist: yalnızca '0' veya '1' kabul edilir
        $status = ( $_POST['status'] ?? '' ) === '1' ? '1' : '0';
        update_post_meta( $post_id, 'rma_active', $status );
        wp_send_json_success();
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

        // GÜVENLİK: uç kimliksizdir. Özellikle serbest metin 'search' alanı
        // cache anahtarını her seferinde değiştirebildiği için (bkz. aşağıdaki
        // 80 baytlık önbellek sınırı), rastgele/uzun arama dizileriyle önbellek
        // sürekli bypass edilip her istekte tam WP_Query çalıştırılabilir. IP
        // başına dakikalık tavan bu döngüyü keser; gerçek bir ziyaretçi filtre/
        // arama değiştirirken bu sınıra yaklaşmaz.
        $this->rma_ip_rate_limit( 'load', 'rma_load_items_ip_rate_limit', 60 );

        // BEYAZ LİSTE: filtre anahtarları RMA_Filtre kayıt defterinden
        // doğrulanır. Eskiden yalnızca sanitize_text_field uygulanıyordu;
        // tanınmayan bir anahtar sorguda sessizce yok sayılsa da önbellek
        // anahtarını kirletiyor ve her uydurma değer yeni bir transient
        // açıyordu (yukarıdaki IP tavanıyla aynı saldırı yüzeyi). Artık
        // tanınmayan değer daha okunmadan düşer.
        $filters    = RMA_Filtre::temizle_anahtarlar(
            $_POST['filters'] ?? [],
            $this->get_allergen_definitions()
        );
        $sort_by    = sanitize_text_field( $_POST['sort_by']  ?? '' );
        // Arama karakter sınırı: sunucuya işlenmek üzere gönderilen sorgu
        // uzunluğu baştan kısıtlanır (bkz. build_menu_payload — LIKE sorgusu
        // ne kadar uzun bir dizeyle çalışırsa çalışsın maliyeti aynıdır, ama
        // kısıt yine de anlamsız/bot kaynaklı dev payload'ları eler).
        $search     = mb_substr( sanitize_text_field( $_POST['search'] ?? '' ), 0, 60 );

        // Özel aralıklar: negatif / metin / tavanı aşan girdiler kelepçelenir,
        // ters verilen sınırlar takas edilir.
        $ranges = [
            'cal'   => RMA_Filtre::temizle_aralik( $_POST['cal_min'] ?? '',   $_POST['cal_max'] ?? '',   RMA_Filtre::KALORI_TAVAN ),
            'price' => RMA_Filtre::temizle_aralik( $_POST['price_min'] ?? '', $_POST['price_max'] ?? '', RMA_Filtre::FIYAT_TAVAN ),
        ];

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
        // tek bir önbellek girdisine düşsün. temizle_anahtarlar() zaten
        // sıralı döner; sort() geriye dönük güvenlik ağı olarak kalıyor.
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
            // Aralıklar da çıktıyı belirler; anahtara girmezse "0-300 kcal"
            // sonucu "0-700 kcal" isteyene servis edilirdi.
            'cr' => $ranges['cal'],
            'pr' => $ranges['price'],
        ] );

        $payload = $this->cache_get( $cache_key );

        if ( ! is_array( $payload ) ) {
            $payload = $this->build_menu_payload(
                $filters,
                $sort_by,
                $search,
                $suggest_mode,
                $suggest_slug,
                $suggest_manual_ids,
                $ranges
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
     * İKİ KATMANLI FİLTRELEME (bkz. class-filtre.php): "meta = 1" tipindeki
     * filtreler ve alerjen hariç tutma sorguya girer; meta yokluğunun
     * "geçer" anlamına geldiği ya da sayısal/kampanyalı fiyat karşılaştırması
     * gereken filtreler sorgudan SONRA, primed meta cache üzerinde uygulanır
     * (ek sorgu doğmaz).
     *
     * @param array $ranges ['cal' => [min,max], 'price' => [min,max]]. Yeni
     *                      parametre sonda ve varsayılanlı: eski çağrı imzası
     *                      bozulmaz.
     * @return array{html:string,categories:array,has_suggestions:bool}
     */
    private function build_menu_payload( array $filters, $sort_by, $search, $suggest_mode, $suggest_slug, array $suggest_manual_ids, array $ranges = [] ) {

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

        /* ---- A katmanı: sorguya giren filtreler ---- */
        $allowed_allergen_slugs = array_keys( $this->get_allergen_definitions() );

        foreach ( RMA_Filtre::meta_klozlari( $filters ) as $clause ) {
            $base['meta_query'][] = $clause;
        }

        // "Laktozsuz" ayrı bir meta değil, "süt" alerjenini taşımayan ürün
        // demektir: mevcut NOT IN klozuna katılır, ikinci sorgu doğmaz.
        $exclude_allergens = RMA_Filtre::haric_alerjenler( $filters, $allowed_allergen_slugs );

        /* ---- B katmanı bağlamı (sorgudan sonra uygulanır) ---- */
        $php_ctx = RMA_Filtre::php_baglami( $filters, $ranges );

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
            case 'spicy_desc': $base['meta_key'] = RMA_Filtre::META_ACI; $base['orderby'] = 'meta_value_num'; $base['order'] = 'DESC'; break;
            case 'spicy_asc':  $base['meta_key'] = RMA_Filtre::META_ACI; $base['orderby'] = 'meta_value_num'; $base['order'] = 'ASC';  break;
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

        // B katmanı: helal / acılık / kalori / fiyat / stok. Meta cache yukarıda
        // toplu ısıtıldığı için ürün başına ek sorgu YOKTUR. Bağlam boşsa
        // (filtre kullanılmıyor) döngü hiç kurulmaz — maliyet sıfır.
        if ( $php_ctx && $all_posts ) {
            $all_posts = array_values( array_filter( $all_posts, function ( $p ) use ( $php_ctx ) {
                return RMA_Filtre::satir_gecer( $this->build_filter_row( $p->ID, $php_ctx ), $php_ctx );
            } ) );
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
            // Filtre YOKKEN eski çıktı bire bir korunur (geriye dönük uyum:
            // özel temalar .rma-empty içeriğine göre stil veriyor olabilir).
            // Filtre varken kullanıcıya çıkış yolu gösterilir.
            $empty = ( $filters || $php_ctx )
                ? '<div class="rma-empty rma-empty-filtered">'
                  . '<p>' . esc_html( $this->t( 'Bu filtrelerle eşleşen ürün bulunamadı.' ) ) . '</p>'
                  . '<button type="button" class="rma-empty-reset">' . esc_html( $this->t( 'Filtreleri temizle' ) ) . '</button>'
                  . '</div>'
                : '<div class="rma-empty">' . esc_html( $this->t( 'Ürün bulunamadı.' ) ) . '</div>';

            return [
                'html'            => $empty,
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
     * B katmanı için tek ürünün karar satırı.
     *
     * Yalnızca primed meta cache'ten okur; ürün başına sorgu AÇMAZ.
     * Fiyat, kampanya/porsiyon sonrası MÜŞTERİYE GÖSTERİLEN fiyattır —
     * "50-100 ₺ arası" filtresi ekranda 80 ₺ yazan kampanyalı ürünü
     * elemesin diye ham rma_price değil, rma_get_effective_price() kullanılır.
     *
     * @param int   $id  Ürün ID'si.
     * @param array $ctx php_baglami() çıktısı — fiyat yalnızca gerçekten
     *                   gerekiyorsa hesaplanır (kampanya kuralı ürün başına
     *                   çalışır; fiyat filtresi yokken bedeli ödenmez).
     * @return array<string,mixed>
     */
    private function build_filter_row( $id, array $ctx = [] ) {
        $price = '';

        // TEK FİYAT KAYNAĞI: ham rma_price meta'sı burada da okunmaz.
        // rma_get_effective_price() kampanya yoksa zaten ham fiyata düşer;
        // ikinci bir okuma noktası açmak, bir gün kampanyalı ürünün
        // filtrede eski fiyatıyla değerlendirilmesi demek olurdu.
        if ( isset( $ctx['price'] ) && function_exists( 'rma_get_effective_price' ) ) {
            $price = rma_get_effective_price( $id );
        }

        return [
            'spicy'    => get_post_meta( $id, RMA_Filtre::META_ACI, true ),
            'calories' => get_post_meta( $id, 'rma_calories', true ),
            'price'    => $price,
            'alcohol'  => get_post_meta( $id, 'rma_contains_alcohol', true ),
            'pork'     => get_post_meta( $id, 'rma_contains_pork', true ),
            'tukendi'  => class_exists( 'RMA_Tukendi' ) ? RMA_Tukendi::urun_tukendi( $id ) : false,
        ];
    }

    /**
     * Genel kimliksiz menü uçları için IP başına dakikalık istek tavanı.
     *
     * Bu modül bilinçli olarak `_qmo-ortak`'a bağımlı değildir (bkz.
     * module.php başlığı), bu yüzden sayaç kendi transient'ıyla tutulur —
     * mevcut görüntülenme-sayacı kilidiyle aynı IP-hash deseni (aşağıdaki
     * ajax_get_product_details). Object cache varsa wp_cache_incr atomik
     * artışı, yoksa transient tabanlı artış kullanılır. Aşılırsa 429 ile
     * JSON hata döner ve die() eder.
     *
     * @param string $anahtar      Uç bazlı ayırt edici (ör. 'load', 'item').
     * @param string $filter_adi   Site sahibinin sınırı değiştirebileceği filtre.
     * @param int    $varsayilan   Filtre yoksa uygulanacak dakikalık tavan.
     */
    private function rma_ip_rate_limit( $anahtar, $filter_adi, $varsayilan ) {
        $limit = (int) apply_filters( $filter_adi, $varsayilan );
        if ( $limit < 1 ) return;

        $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $key = 'rma_rl_' . $anahtar . '_' . md5( $ip );

        if ( wp_using_ext_object_cache() ) {
            wp_cache_add( $key, 0, 'rma_rl', MINUTE_IN_SECONDS );
            $n = wp_cache_incr( $key, 1, 'rma_rl' );
        } else {
            $n = (int) get_transient( $key ) + 1;
            set_transient( $key, $n, MINUTE_IN_SECONDS );
        }

        if ( $n > $limit ) {
            $this->flush_stray_output();
            wp_send_json_error( [ 'kod' => 'limit' ], 429 );
            die();
        }
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

        // GÜVENLİK: modal her açılışta 1 gerçek istek + komşu kartlar için
        // sessiz prefetch üretir; normal gezinme bu sınırın çok altında
        // kalır, ama IP başına dakikada yüzlerce istek atan bir script artık
        // 429 ile durur.
        $this->rma_ip_rate_limit( 'item', 'rma_product_details_ip_rate_limit', 120 );

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
            // GÜVENLİK: uç kimliksizdir (nonce yumuşak kontrol edilir); sayaç
            // öncesinde hiçbir sınır yoktu, scriptle tekrarlanan istek her
            // seferinde ayrı bir postmeta UPDATE'i üretiyordu. IP+ürün başına
            // dakikada bir yazımla sınırlamak gerçek ziyaretçiyi etkilemez
            // (aynı ürünü dakikada bir kereden fazla "açmaz") ama script'li
            // tekrarların DB'ye yazma yükünü keser.
            $ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
            $kilit  = 'rma_view_' . $id . '_' . md5( $ip );

            if ( false === get_transient( $kilit ) ) {
                set_transient( $kilit, 1, MINUTE_IN_SECONDS );
                update_post_meta( $id, 'rma_views', (int) get_post_meta( $id, 'rma_views', true ) + 1 );
            }
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

        // Servis saati kısıtı: pencere dışındaysa uyarı, içindeyse yalnızca
        // bilgi satırı ("Servis saatleri: 07:00–11:00 · Hafta içi").
        $servis_disi  = RMA_Servis_Saati::servis_disi_mi( $id );
        $servis_notu  = '';
        $servis_metni = RMA_Servis_Saati::aciklama( $id );
        if ( $servis_disi ) {
            $servis_notu = '<p class="rma-modal-servis rma-modal-servis-disi">' . esc_html( RMA_Servis_Saati::mesaj( $id ) ) . '</p>';
        } elseif ( '' !== $servis_metni ) {
            $servis_notu = '<p class="rma-modal-servis">' . esc_html( $servis_metni ) . '</p>';
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
        if ( $servis_disi ) {
            $attrs .= '<span class="rma-attr rma-attr-servis">' . esc_html( RMA_Servis_Saati::etiket() ) . '</span>';
        }
        $attrs .= RMA_Ozel_Rozet::etiket_html( $id );
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

        // Acılık 0-4'e genişledi; rozette kademe adı da yazar ki 4 biber ile
        // 3 biber arasındaki fark okunabilir olsun.
        $spicy_level  = RMA_Filtre::seviye( $spicy );
        $spicy_labels = RMA_Filtre::aci_seviyeleri();
        if ( $spicy_level > 0 ) {
            $attrs .= '<span class="rma-attr">' . str_repeat( '🌶️', $spicy_level ) . ' '
                    . esc_html( $this->t( $spicy_labels[ $spicy_level ] ) ) . '</span>';
        }
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

        // Sepet betiği için sayısal taban fiyat: porsiyon farkı ve ekstralar
        // bunun üzerine eklenir. Metni ayrıştırmak (binlik ayracı, üstü
        // çizili eski fiyat, çeviri kalıbı) hataya açıktı.
        $fiyat_bilgi = RMA_Kampanya::fiyat_bilgisi( $id );
        $taban_fiyat = $fiyat_bilgi['aktif'] ? $fiyat_bilgi['yeni'] : $fiyat_bilgi['orijinal'];

        $tukendi  = RMA_Tukendi::urun_tukendi( $id );
        $rozet    = RMA_Tukendi::rozet_html( $id );
        if ( '' === $rozet ) {
            $rozet = RMA_Servis_Saati::rozet_html( $id );
        }

        return sprintf(
            '<div class="rma-modal-img-wrap%s">
                %s
                %s
            </div>
            <div class="rma-modal-body" data-id="%d"%s%s>
                <h2 class="rma-modal-title">%s</h2>
                %s
                %s
                <p class="rma-modal-price">%s</p>
                %s
                <div class="rma-modal-desc">%s</div>
                %s
                %s
                %s
                %s
            </div>',
            ( $tukendi || $servis_disi ) ? ' is-tukendi' : '',
            $img,
            $rozet,
            (int) $id,
            null !== $taban_fiyat ? ' data-fiyat="' . esc_attr( number_format( (float) $taban_fiyat, 2, '.', '' ) ) . '"' : '',
            ( $tukendi || $servis_disi ) ? ' data-siparis-kapali="1"' : '',
            esc_html( $title ),
            $tukendi_banner,
            $servis_notu,
            $price_html,
            RMA_Porsiyon::html( $id ),
            $desc,
            $attrs ? '<div class="rma-attrs">' . $attrs . '</div>' : '',
            $compliance_html,
            $allergen_html,
            RMA_Ekstra::html( $id )
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

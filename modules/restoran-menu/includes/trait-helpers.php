<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Helpers_Trait {

    /* -----------------------------------------------------------------
       İSTEK İÇİ (per-request) BELLEK ÖNBELLEKLERİ

       Aynı istek boyunca defalarca çağrılan get_option / get_terms
       sonuçları burada tutulur. Değerler yalnızca tek bir PHP isteği
       kadar yaşar, bu yüzden ayar kaydetme akışını etkilemez.
    ----------------------------------------------------------------- */
    private $rma_memo = [];

    /* -----------------------------------------------------------------
       ÇEVİRİ KÖPRÜSÜ

       Çeviri eklentisi (QRMenu Translator) kuruluysa metinler
       {prefix}rma_translations tablosundan gelir; kurulu değilse her
       sarmalayıcı orijinal Türkçe metni aynen döndürür. Bu yüzden RMA
       tek başına da eskisi gibi çalışır — function_exists guard'ları
       bilinçli, kaldırılmamalı.
    ----------------------------------------------------------------- */

    /**
     * Sabit arayüz metni (ui_string). Anahtar metinden türetilir,
     * çağıran taraf anahtar yönetmez.
     */
    public function t( $metin ) {
        return function_exists( 'rma_ceviri_ui' ) ? rma_ceviri_ui( $metin ) : $metin;
    }

    /**
     * ID'ye bağlı alan çevirisi (ürün başlığı, özeti, içeriği; terim adı).
     * Çeviri yoksa orijinal döner — hiçbir zaman boş göstermez.
     */
    public function t_field( $id, $tip, $alan, $orijinal ) {
        return function_exists( 'rma_translate_field' )
            ? rma_translate_field( $id, $tip, $alan, $orijinal )
            : $orijinal;
    }

    /**
     * Taksonomi terimi adı.
     *
     * @param WP_Term|null $terim Terim nesnesi.
     * @param string       $tip   'category' | 'allergen'.
     */
    public function t_term( $terim, $tip = 'category' ) {
        if ( ! $terim || is_wp_error( $terim ) ) return '';
        return $this->t_field( $terim->term_id, $tip, 'name', $terim->name );
    }

    /**
     * Alerjen etiketi — tanım dizisindeki label yerine, aynı metinle seed
     * edilmiş rma_allergen terimi üzerinden çevrilir. Böylece CSV'de
     * mükerrer satır oluşmaz (terim zaten allergen/name olarak çıkıyor).
     * Terim yoksa Türkçe etikete düşer.
     *
     * PERF: eskiden her slug için ayrı get_term_by() çağrılıyordu
     * (filtre panelinde 14 ayrı terim sorgusu). Artık tüm alerjen
     * terimleri tek get_terms() ile alınıp istek boyunca saklanır.
     */
    public function t_allergen_label( $slug, $label ) {
        $map   = $this->get_allergen_terms_by_slug();
        $terim = $map[ $slug ] ?? null;
        return $terim ? $this->t_term( $terim, 'allergen' ) : $this->t( $label );
    }

    /**
     * slug => WP_Term eşlemesi (rma_allergen). Tek sorgu, istek içi memo.
     *
     * @return array<string,WP_Term>
     */
    private function get_allergen_terms_by_slug() {
        if ( isset( $this->rma_memo['allergen_terms'] ) ) {
            return $this->rma_memo['allergen_terms'];
        }

        $terms = get_terms( [
            'taxonomy'   => 'rma_allergen',
            'hide_empty' => false,
        ] );

        $map = [];
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $map[ $term->slug ] = $term;
            }
        }

        $this->rma_memo['allergen_terms'] = $map;
        return $map;
    }

    /**
     * Görüntülenen dil kodu — JS köprüsü ve AJAX için.
     */
    public function current_lang() {
        return function_exists( 'rma_get_current_lang' ) ? rma_get_current_lang() : 'tr';
    }

    public function get_allergen_definitions() {
        return [
            'gluten'        => [ 'label' => 'Glüten',            'icon' => '🌾' ],
            'sut'           => [ 'label' => 'Süt / Laktoz',       'icon' => '🥛' ],
            'yumurta'       => [ 'label' => 'Yumurta',            'icon' => '🥚' ],
            'findik'        => [ 'label' => 'Fındık / Kuruyemiş', 'icon' => '🌰' ],
            'yer-fistigi'   => [ 'label' => 'Yer Fıstığı',        'icon' => '🥜' ],
            'soya'          => [ 'label' => 'Soya',               'icon' => '🌱' ],
            'balik'         => [ 'label' => 'Balık',              'icon' => '🐟' ],
            'kabuklu-deniz' => [ 'label' => 'Kabuklu Deniz Ürünü','icon' => '🦐' ],
            'susam'         => [ 'label' => 'Susam',              'icon' => '⚪' ],
            'kereviz'       => [ 'label' => 'Kereviz',            'icon' => '🥬' ],
            'hardal'        => [ 'label' => 'Hardal',             'icon' => '🟡' ],
            'lupin'         => [ 'label' => 'Lüpin',              'icon' => '🫘' ],
            'kukurt-dioksit'=> [ 'label' => 'Kükürt Dioksit/Sülfit','icon' => '🧪' ],
            'yumusakca'     => [ 'label' => 'Yumuşakça',          'icon' => '🐚' ],
        ];
    }

    public function register_default_allergen_terms() {
        if ( get_option( 'rma_allergen_terms_seeded' ) ) return;
        foreach ( $this->get_allergen_definitions() as $slug => $def ) {
            if ( ! term_exists( $slug, 'rma_allergen' ) ) {
                wp_insert_term( $def['label'], 'rma_allergen', [ 'slug' => $slug ] );
            }
        }
        update_option( 'rma_allergen_terms_seeded', '1' );
        // Seed sonrası alerjen terim listesi değişti — memo'yu düşür.
        unset( $this->rma_memo['allergen_terms'] );
    }

    public function get_meat_origin_options() {
        return [
            ''         => 'Belirtilmemiş / Uygulanmaz',
            'dana'     => 'Dana Eti',
            'kuzu'     => 'Kuzu / Koyun Eti',
            'tavuk'    => 'Tavuk Eti',
            'hindi'    => 'Hindi Eti',
            'balik'    => 'Balık',
            'deniz'    => 'Deniz Ürünü',
            'karisik'  => 'Karışık (Birden Fazla Et Türü)',
        ];
    }

    /* -----------------------------------------------------------------
       ÖNBELLEK ALTYAPISI (menü HTML'i + ürün detayları)

       Menü çıktısı sürüm damgalı bir anahtarla transient'a yazılır.
       İçerik değiştiğinde tek tek anahtar silmek yerine SÜRÜM ARTIRILIR
       (bump_cache_version); eski anahtarlar bir daha okunmaz ve TTL
       dolunca kendiliğinden düşer. Böylece invalidasyon O(1)'dir.

       Transient'lar süreli olduğu için wp_options'a autoload=no ile
       yazılır — sayfa yükünde autoload edilen veri büyümez.
    ----------------------------------------------------------------- */

    const RMA_CACHE_VERSION_OPTION = 'rma_cache_version';

    /**
     * Menü önbelleğinin geçerli sürüm damgası.
     */
    public function get_cache_version() {
        if ( isset( $this->rma_memo['cache_version'] ) ) {
            return $this->rma_memo['cache_version'];
        }

        $version = get_option( self::RMA_CACHE_VERSION_OPTION );
        if ( ! $version ) {
            $version = (string) time();
            // Frontend AJAX'ın her isteğinde okunur — autoload'da kalması
            // ek sorgu doğurmaz, aksine bir sorgu tasarruf ettirir.
            add_option( self::RMA_CACHE_VERSION_OPTION, $version, '', 'yes' );
        }

        $this->rma_memo['cache_version'] = (string) $version;
        return $this->rma_memo['cache_version'];
    }

    /**
     * Menü içeriği değiştiğinde çağrılır — tüm önbelleği tek hamlede
     * geçersiz kılar. Ürün kaydetme, meta güncelleme, terim değişikliği
     * ve ayar kaydetme kancalarına bağlıdır (bkz. qr-menu.php).
     */
    public function bump_cache_version() {
        // İstek başına TEK yazma. Toplu içe aktarımda yüzlerce ürün ×
        // onlarca meta güncellemesi bu kancayı binlerce kez tetikler;
        // damgayı bir kez tazelemek hepsini geçersiz kılmaya yeter.
        if ( ! empty( $this->rma_memo['cache_bumped'] ) ) return;

        $version = (string) time();
        // Aynı saniyede iki ayrı istek olursa damga aynı kalmasın.
        if ( isset( $this->rma_memo['cache_version'] ) && $this->rma_memo['cache_version'] === $version ) {
            $version .= '-' . wp_rand( 100, 999 );
        }
        update_option( self::RMA_CACHE_VERSION_OPTION, $version );
        $this->rma_memo['cache_version'] = $version;
        $this->rma_memo['cache_bumped']  = true;
    }

    /**
     * Damgayı istek içinde daha önce artırılmış olsa bile yeniden artırır.
     *
     * Toplu işlemlerin (CSV/JSON içe aktarma) SONUNDA çağrılır: döngünün
     * ilk satırında tetiklenen otomatik artırım, işlem sürerken gelen bir
     * ziyaretçinin YARIM menüyü yeni damgayla önbelleğe almasına yol
     * açabilirdi. İşin sonunda damganın bir kez daha tazelenmesi bu
     * ihtimali ortadan kaldırır.
     */
    public function force_bump_cache_version() {
        $this->rma_memo['cache_bumped'] = false;
        $this->bump_cache_version();
    }

    /**
     * Meta güncellemelerinde önbelleği tazeler. Yalnızca rma_ önekli ve
     * görünümü etkileyen anahtarlar dikkate alınır; rma_views sayacı
     * hariç tutulur (her ürün görüntülemesinde önbelleği düşürürdü).
     *
     * @param int    $meta_id
     * @param int    $object_id
     * @param string $meta_key
     */
    public function maybe_bump_cache_on_meta( $meta_id, $object_id, $meta_key ) {
        $key = (string) $meta_key;
        // `rma_active` gibi düz anahtarlar ve `_rma_tukendi` gibi gizli
        // (alt çizgili) anahtarlar görünümü etkiler. rma_views sayacı değil.
        if ( 0 !== strpos( $key, 'rma_' ) && 0 !== strpos( $key, '_rma_' ) ) return;
        if ( 'rma_views' === $key ) return;
        if ( get_post_type( $object_id ) !== 'rma_menu_item' ) return;
        $this->bump_cache_version();
    }

    /**
     * Terim ataması değişince (kategori / alerjen) önbelleği tazeler.
     *
     * @param int    $object_id
     * @param array  $terms
     * @param array  $tt_ids
     * @param string $taxonomy
     */
    public function maybe_bump_cache_on_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
        if ( ! in_array( $taxonomy, [ 'rma_category', 'rma_allergen' ], true ) ) return;
        $this->bump_cache_version();
    }

    /**
     * Post kaydedilince / çöpe atılınca / silinince önbelleği tazeler.
     *
     * @param int          $post_id
     * @param WP_Post|null $post    'deleted_post' kancasında gönderi artık
     *                              veritabanında olmadığı için tür bilgisi
     *                              buradan okunur.
     */
    public function maybe_bump_cache_on_post( $post_id, $post = null ) {
        if ( wp_is_post_revision( $post_id ) ) return;

        $post_type = ( $post instanceof WP_Post ) ? $post->post_type : get_post_type( $post_id );
        if ( 'rma_menu_item' !== $post_type ) return;

        $this->bump_cache_version();
    }

    /**
     * Sürüm damgalı önbellek anahtarı üretir.
     *
     * @param string $group Mantıksal grup ('menu', 'item' vb.).
     * @param array  $parts Anahtarı belirleyen tüm girdiler.
     */
    private function cache_key( $group, array $parts ) {
        $parts['__v']    = $this->get_cache_version();
        $parts['__lang'] = $this->current_lang();
        // Markup revizyonu: içerik değişmese bile üretilen HTML değiştiğinde
        // (örn. modal görselinin srcset/fetchpriority nitelikleri) eski
        // transient'lerin TTL'i dolmadan geçersizleşmesi için elle artırılır.
        $parts['__m']    = '2';
        // Aktif fiyat kampanyası anahtara girer: kampanya açıldığında,
        // kapandığında ya da kuralı değiştiğinde önbelleğe alınmış menü
        // HTML'i kendiliğinden geçersizleşir (bkz. RMA_Kampanya::imza).
        $parts['__kmp']  = class_exists( 'RMA_Kampanya' ) ? RMA_Kampanya::imza() : '0';
        return 'rma_' . $group . '_' . md5( wp_json_encode( $parts ) );
    }

    /**
     * Önbellekten okur. Önbellek kapalıysa (filtre) daima false döner.
     */
    private function cache_get( $key ) {
        if ( ! $this->cache_enabled() ) return false;
        return get_transient( $key );
    }

    /**
     * Önbelleğe yazar.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl Saniye.
     */
    private function cache_set( $key, $value, $ttl ) {
        if ( ! $this->cache_enabled() ) return;
        set_transient( $key, $value, max( 30, (int) $ttl ) );
    }

    /**
     * Önbellek açık mı? `add_filter( 'rma_cache_enabled', '__return_false' )`
     * ile hata ayıklama sırasında kapatılabilir. Giriş yapmış yöneticiler
     * her zaman taze içerik görür (ürünü düzenleyip anında kontrol etmek için).
     */
    private function cache_enabled() {
        if ( ! isset( $this->rma_memo['cache_enabled'] ) ) {
            $enabled = ! ( is_user_logged_in() && current_user_can( 'edit_posts' ) );
            /**
             * Menü önbelleğini aç/kapat.
             *
             * @param bool $enabled
             */
            $this->rma_memo['cache_enabled'] = (bool) apply_filters( 'rma_cache_enabled', $enabled );
        }
        return $this->rma_memo['cache_enabled'];
    }

    /**
     * Statik dosya sürümü — filemtime sonucu istek içinde saklanır,
     * böylece aynı dosya için tekrar tekrar disk erişimi olmaz.
     *
     * @param string $relative_path 'assets/css/rma-frontend.css' gibi.
     * @return int|null
     */
    private function asset_version( $relative_path ) {
        if ( isset( $this->rma_memo['asset_ver'][ $relative_path ] ) ) {
            return $this->rma_memo['asset_ver'][ $relative_path ];
        }
        $path = RMA_PLUGIN_DIR . $relative_path;
        $ver  = file_exists( $path ) ? filemtime( $path ) : null;
        $this->rma_memo['asset_ver'][ $relative_path ] = $ver;
        return $ver;
    }

    /**
     * Modülün bir yönetim sayfasının adresi.
     *
     * Sayfalar suite kuruluysa "QR Menü" üst menüsünün altında
     * (admin.php?page=…), suite yoksa eski tekil eklentideki gibi ürün
     * listesinin altında (edit.php?post_type=rma_menu_item&page=…) kayıtlıdır.
     * Yönlendirmeler ve sayfalar arası bağlantılar adresi tek yerden, buradan
     * alır.
     *
     * @param string $slug   Sayfa slug'ı.
     * @param array  $args   URL'ye eklenecek sorgu argümanları.
     * @param string $anchor Sayfa içi bölüm çapası (# olmadan).
     * @return string
     */
    public function admin_page_url( $slug, $args = [], $anchor = '' ) {
        $url = class_exists( 'QRMS_Admin' )
            ? admin_url( 'admin.php?page=' . $slug )
            : admin_url( 'edit.php?post_type=rma_menu_item&page=' . $slug );

        if ( ! empty( $args ) ) {
            $url = add_query_arg( $args, $url );
        }

        return '' !== $anchor ? $url . '#' . $anchor : $url;
    }

    /* -----------------------------------------------------------------
       NAV DESIGN HELPERS
    ----------------------------------------------------------------- */
    private function get_nav_design_defaults() {
        return [
            'preset'           => 'premium_gold',
            'bg'               => '#0a0a0a',
            'text'             => '#888888',
            'active'           => '#c9a84c',
            'border_color'     => '#2a2a2a',
            'padding_top'      => '12',
            'padding_bottom'   => '12',
            'btn_padding_h'    => '16',
            'btn_padding_v'    => '10',
            'btn_spacing'      => '4',
            'font_size'        => '0.83',
            'font_weight'      => '600',
            'sticky'           => '1',
            'blur'             => '1',
            'active_indicator' => 'background',
        ];
    }

    private function get_nav_design_settings() {
        if ( isset( $this->rma_memo['nav_design'] ) ) {
            return $this->rma_memo['nav_design'];
        }
        $saved    = get_option( 'rma_nav_design_settings', [] );
        $filtered = array_filter( (array) $saved, fn( $v ) => $v !== '' && $v !== null );
        $this->rma_memo['nav_design'] = array_merge( $this->get_nav_design_defaults(), $filtered );
        return $this->rma_memo['nav_design'];
    }

    /* -----------------------------------------------------------------
       RENK & TİPOGRAFİ VARSAYILANLARI — tek kaynak (admin + frontend)
       Not: nav_bg/nav_text/nav_active/badge_popular/nav_font_size gibi
       hiçbir yerde kullanılmayan eski anahtarlar kaldırıldı.
    ----------------------------------------------------------------- */
    private function get_color_defaults() {
        return [
            'bg'                => '#0a0a0a',
            'card'              => '#111111',
            'text'              => '#f5f0e8',
            'border'            => '#2a2a2a',
            'accent'            => '#c9a84c',
            'desc'              => '#888888',
            'toolbar_bg'        => '#0a0a0a',
            'modal_bg'          => '#0a0a0a',
            'section_title'     => '#f5f0e8',
            'filter_btn_border' => '#2a2a2a',
            'filter_btn_text'   => '#888888',
        ];
    }

    private function get_color_settings() {
        if ( isset( $this->rma_memo['colors'] ) ) {
            return $this->rma_memo['colors'];
        }
        $saved = get_option( 'rma_color_settings', [] );
        $this->rma_memo['colors'] = array_merge( $this->get_color_defaults(), array_filter( (array) $saved ) );
        return $this->rma_memo['colors'];
    }

    private function get_typo_defaults() {
        return [
            'heading_font'     => 'Plus Jakarta Sans',
            'heading_size'     => '1.42',
            'heading_weight'   => '700',
            'heading_style'    => 'normal',
            'heading_color'    => '#f5f0e8',
            'body_font'        => 'Plus Jakarta Sans',
            'body_size'        => '0.93',
            'body_weight'      => '700',
            'body_color'       => '#f5f0e8',
            'desc_size'        => '0.80',
            'desc_color'       => '#888888',
            'price_font'       => 'Plus Jakarta Sans',
            'price_size'       => '1.02',
            'price_color'      => '#c9a84c',
            'modal_title_size' => '1.55',
        ];
    }

    private function get_typo_settings() {
        if ( isset( $this->rma_memo['typo'] ) ) {
            return $this->rma_memo['typo'];
        }
        $saved = get_option( 'rma_typo_settings', [] );
        $this->rma_memo['typo'] = array_merge( $this->get_typo_defaults(), array_filter( (array) $saved ) );
        return $this->rma_memo['typo'];
    }

    /**
     * Aktif kategori göstergesinin CSS'i — frontend menüsü ve admin'deki
     * canlı önizleme için TEK KAYNAK. Önizleme bu çıktıyı kendi kapsamına
     * hapsederek kullanır, kopyalamaz.
     *
     * color / border-bottom beyanları !important taşır: temel .rma-nav-btn
     * kuralı tema stillerine karşı "color: … !important" ve "border: none
     * !important" kullanıyor, bu yüzden daha özgül olmalarına rağmen bu
     * beyanlar aksi hâlde eziliyordu (dot/none/bottom_line varyantlarında
     * yazı rengi değişmiyor, bottom_line'da alt çizgi hiç çizilmiyordu).
     */
    public function get_nav_indicator_css( $variant ) {
        $css = '';
        switch ( $variant ) {
            case 'background':
                $css = "
.rma-nav-btn.active {
    background: var(--rma-nav-active);
    color: #0a0a0a !important;
    border-radius: 20px;
    border-bottom: none !important;
    padding-left: calc(var(--rma-nav-btn-ph) + 4px);
    padding-right: calc(var(--rma-nav-btn-ph) + 4px);
}";
                break;
            case 'dot':
                $css = "
.rma-nav-btn.active {
    color: var(--rma-nav-active) !important;
    border-bottom: none !important;
}
.rma-nav-btn.active::after {
    content: '';
    display: block;
    width: 4px;
    height: 4px;
    background: var(--rma-nav-active);
    border-radius: 50%;
    margin: 3px auto 0;
}";
                break;
            case 'none':
                $css = "
.rma-nav-btn.active {
    color: var(--rma-nav-active) !important;
    border-bottom: none !important;
}";
                break;
            case 'bottom_line':
            default:
                $css = "
.rma-nav-btn.active {
    color: var(--rma-nav-active) !important;
    border-bottom: 3px solid var(--rma-nav-active) !important;
    text-shadow: 0 0 14px color-mix(in srgb, var(--rma-nav-active) 55%, transparent);
    font-weight: 600;
}";
                break;
        }
        return $css;
    }

    /**
     * Ayar dizilerini güvenli biçimde temizler (Settings API sanitize callback).
     */
    public function sanitize_settings_array( $input ) {
        if ( ! is_array( $input ) ) return [];
        $clean = [];
        foreach ( $input as $key => $val ) {
            $key = sanitize_key( $key );
            $clean[ $key ] = is_scalar( $val ) ? sanitize_text_field( (string) $val ) : '';
        }
        return $clean;
    }
}

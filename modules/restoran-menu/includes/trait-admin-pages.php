<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Admin_Pages_Trait {

    /**
     * Modülün kendi yönetim sayfaları — TEK KAYNAK.
     *
     * Her satır gerçek, ayrı bir WordPress sayfasıdır (add_submenu_page ile
     * kaydedilir); JS ile gizlenip gösterilen sahte sekme yoktur. Sayfalar sol
     * menüde görünmez — modülün hub ekranındaki kartlardan açılırlar; kayıt ve
     * kart sırası modülün menü kodunda (module.php) belirlenir.
     *
     * @return array<string,array{title:string,menu_title:string,render:string,desc:string,icon:string}>
     */
    public function get_subpages() {
        return [
            'qrms-rm-gorunum' => [
                'title'      => 'Menü Görünümü',
                'menu_title' => 'Görünüm',
                'hub_title'  => 'Menü Görünümü',
                'render'     => 'render_appearance_page',
                'desc'       => 'Menünüzün renkleri, yazı tipleri ve kategori çubuğu.',
                'icon'       => 'dashicons-art',
            ],
            'qrms-rm-one-cikanlar' => [
                'title'      => 'Öne Çıkanlar',
                'menu_title' => 'Öne Çıkanlar',
                'render'     => 'render_featured_page',
                'desc'       => 'Menünün üstünde öne çıkarılacak ürünler ve slider.',
                'icon'       => 'dashicons-star-filled',
            ],
            'qrms-rm-vitrin' => [
                'title'      => 'Ürün Vitrini',
                'menu_title' => 'Ürün Vitrini',
                'render'     => 'render_showcase_page',
                'desc'       => 'Seçtiğiniz ürünleri kayan bir vitrinde gösterin; kendi kısa kodu vardır.',
                'icon'       => 'dashicons-slides',
            ],
            'qrms-rm-kampanya' => [
                'title'      => 'Fiyat Kampanyaları',
                'menu_title' => 'Fiyat Kampanyaları',
                'render'     => 'render_campaign_page',
                'desc'       => 'Menüdeki fiyatları toplu zam/indirimle geçici olarak değiştirin; tek tıkla geri alın.',
                'icon'       => 'dashicons-tag',
            ],
            'qrms-rm-kampanya-banner' => [
                'title'      => 'Kampanya Banner',
                'menu_title' => 'Kampanya Banner',
                'hub_title'  => 'Kampanya Banner',
                'render'     => 'render_kampanya_banner_page',
                'desc'       => 'Sayfanın en üstünde tam genişlikte dönen kampanya görselleri: görseller, görünüm ayarları ve hazır şablonla görsel üretme.',
                'icon'       => 'dashicons-images-alt2',
            ],
            'qrms-rm-secenekler' => [
                'title'      => 'Ekstralar ve Rozetler',
                'menu_title' => 'Ekstralar ve Rozetler',
                'hub_title'  => 'Ekstralar ve Rozetler',
                'render'     => 'render_secenekler_page',
                'desc'       => 'Ürünlerde kullanacağınız ekstra gruplarını ve ürün rozetlerini tanımlayın.',
                'icon'       => 'dashicons-editor-ul',
            ],
            'qrms-rm-diger' => [
                'title'      => 'Diğer Ayarlar',
                'menu_title' => 'Diğer Ayarlar',
                'render'     => 'render_other_settings_page',
                'desc'       => 'Sepet ile sipariş, kategori sırası, toplu ürün aktarımı ve yedekleme.',
                'icon'       => 'dashicons-admin-tools',
            ],
            'qrms-rm-urunum-yok' => [
                'title'      => 'Ürünüm Yok',
                'menu_title' => 'Ürünüm Yok',
                'hub_title'  => 'Stok Durumu',
                'render'     => 'render_urunum_yok_page',
                'desc'       => 'Tükenen malzemeye göre ürünleri toplu "Tükendi" işaretleyin; belirlediğiniz saatte otomatik tekrar aktif olur.',
                'icon'       => 'dashicons-clock',
            ],
        ];
    }

    /**
     * "Restoran Menü" hub ekranındaki kartlar — üç mantıksal gruba ayrılmış.
     *
     * Ürün/kategori/alerjen ekranları WordPress'in kendi sayfalarıdır, bu
     * yüzden get_subpages() içinde değil burada tanımlanır.
     *
     * @return array<int,array{title:string,cards:array<int,array{url:string,title:string,desc:string,icon:string}>}>
     */
    private function get_hub_cards() {
        $sub = $this->get_subpages();

        $from_sub = static function ( $self, $slug ) use ( $sub ) {
            $page = $sub[ $slug ];
            return [
                'url'   => $self->admin_page_url( $slug ),
                'title' => isset( $page['hub_title'] ) ? $page['hub_title'] : $page['menu_title'],
                'desc'  => $page['desc'],
                'icon'  => $page['icon'],
            ];
        };

        return [
            [
                'title' => 'Ürünler',
                'cards' => [
                    [
                        'url'   => admin_url( 'edit.php?post_type=rma_menu_item' ),
                        'title' => 'Ürünlerim',
                        'desc'  => 'Menüdeki tüm ürünleri görün, düzenleyin, gösterip gizleyin veya tükendi işaretleyin.',
                        'icon'  => 'dashicons-list-view',
                    ],
                    [
                        'url'   => admin_url( 'post-new.php?post_type=rma_menu_item' ),
                        'title' => 'Ürün Ekle',
                        'desc'  => 'Menünüze yeni bir ürün ekleyin.',
                        'icon'  => 'dashicons-plus-alt',
                    ],
                    $from_sub( $this, 'qrms-rm-urunum-yok' ),
                    $from_sub( $this, 'qrms-rm-secenekler' ),
                    $from_sub( $this, 'qrms-rm-kampanya' ),
                    $from_sub( $this, 'qrms-rm-kampanya-banner' ),
                ],
            ],
            [
                'title' => 'Ürün Materyalleri',
                'cards' => [
                    [
                        'url'   => admin_url( 'edit-tags.php?taxonomy=rma_category&post_type=rma_menu_item' ),
                        'title' => 'Kategoriler',
                        'desc'  => 'Çorbalar, ana yemekler, içecekler… Menü bölümlerinizi yönetin.',
                        'icon'  => 'dashicons-category',
                    ],
                    [
                        'url'   => admin_url( 'edit-tags.php?taxonomy=rma_allergen&post_type=rma_menu_item' ),
                        'title' => 'Alerjenler',
                        'desc'  => 'Ürünlerde işaretlenebilecek alerjen listesini yönetin.',
                        'icon'  => 'dashicons-warning',
                    ],
                    [
                        'url'   => admin_url( 'edit-tags.php?taxonomy=rma_ingredient&post_type=rma_menu_item' ),
                        'title' => 'Malzemeler',
                        'desc'  => 'Ürünlerde kullanılan malzemeleri yönetin; "Ürünüm Yok" ekranı bu listeden beslenir.',
                        'icon'  => 'dashicons-food',
                    ],
                ],
            ],
            [
                'title' => 'Görünüm',
                'cards' => [
                    $from_sub( $this, 'qrms-rm-gorunum' ),
                    $from_sub( $this, 'qrms-rm-one-cikanlar' ),
                    $from_sub( $this, 'qrms-rm-vitrin' ),
                    $from_sub( $this, 'qrms-rm-diger' ),
                ],
            ],
        ];
    }

    /**
     * Genel Bakış kartındaki alt bağlantılar — hub kartlarıyla AYNI kaynak.
     *
     * get_hub_cards() sırası korunur; alt sayfa başlığı title'dır (hub
     * "Stok Durumu" / menü "Görünüm" derken Genel Bakış "Ürünüm Yok" /
     * "Menü Görünümü" der).
     *
     * @return array<int,array{url:string,title:string}>
     */
    public function get_overview_links() {
        $sub   = $this->get_subpages();
        $links = array();

        foreach ( $this->get_hub_cards() as $group ) {
            foreach ( $group['cards'] as $card ) {
                $title = $card['title'];

                foreach ( $sub as $slug => $page ) {
                    if ( ! preg_match( '/[?&]page=' . preg_quote( $slug, '/' ) . '(?:&|$|#)/', $card['url'] ) ) {
                        continue;
                    }
                    $title = isset( $page['title'] ) ? $page['title'] : $card['title'];
                    break;
                }

                $links[] = array(
                    'url'   => $card['url'],
                    'title' => $title,
                );
            }
        }

        return $links;
    }

    /**
     * Eski (sekmeli) sayfa slug'ları -> yeni sayfa + bölüm çapası.
     *
     * Yer imleri, dış bağlantılar ve eski yönlendirmeler çalışmaya devam
     * etsin diye her eski slug hâlâ kayıtlıdır; açıldığında içeriğin taşındığı
     * yeni sayfaya yönlendirir.
     *
     * Üçüncü eleman (opsiyonel) hedefe eklenecek query arg'larıdır: içerik
     * bir sayfanın belirli bir ADIMINA taşındıysa çapa tek başına yetmez.
     *
     * @return array<string,array{0:string,1:string,2?:array<string,string>}>
     */
    private function get_legacy_page_map() {
        return [
            'rma_settings'             => [ 'qrms-rm-gorunum',      '' ],
            'rma_color_settings'       => [ 'qrms-rm-gorunum',      '' ],
            'rma_nav_design'           => [ 'qrms-rm-gorunum',      'rma-kategori-cubugu' ],
            'rma_suggestions_settings' => [ 'qrms-rm-one-cikanlar', '' ],
            'rma_category_order'       => [ 'qrms-rm-diger',        'rma-kategori-sirasi' ],
            'rma_csv_import'           => [ 'qrms-rm-diger',        'rma-ice-disa-aktar' ],
            'rma_menu_backup'          => [ 'qrms-rm-diger',        'rma-yedekleme' ],

            // Eski "Banner Görünümü" sayfası (qrms-rm-banner-ayar) kaldırıldı;
            // içeriği "Kampanya Banner" sayfasındaki sihirbazın 2. adımıdır.
            // Slug kayıtlı kalır ve oraya yönlendirir — eski yer imleri ve
            // dış linkler 404 vermez.
            'qrms-rm-banner-ayar'      => [ 'qrms-rm-kampanya-banner', '', [ 'banner_adim' => 'kampanyalar' ] ],
        ];
    }

    public function add_admin_menus() {
        // Suite kuruluysa menü satırları "QR Menü" üst menüsünün altında,
        // module.php tarafından kaydedilir (WordPress menüsü iki seviyelidir,
        // "Restoran Menü" satırının altına üçüncü seviye eklenemez). Suite
        // yoksa eski tekil eklentideki gibi ürün listesinin altına eklenir.
        if ( ! class_exists( 'QRMS_Admin' ) ) {
            foreach ( $this->get_subpages() as $slug => $page ) {
                add_submenu_page(
                    'edit.php?post_type=rma_menu_item',
                    $page['title'],
                    $page['menu_title'],
                    'manage_options',
                    $slug,
                    [ $this, $page['render'] ]
                );
            }
        }

        // Eski slug'lar gizli sayfa olarak kayıtlı kalır; yer imleri ve
        // dış linkler içeriğin taşındığı yeni sayfaya yönlendirilir.
        // Üst menü olarak null yerine '' kullanılır: ikisi de aynı
        // (admin_page_<slug>) hook'unu üretir ama '' PHP 8.1+ üzerinde
        // plugin_basename() içindeki null deprecation uyarısını doğurmaz.
        foreach ( array_keys( $this->get_legacy_page_map() ) as $slug ) {
            add_submenu_page(
                '',
                'QR Menü',
                'QR Menü',
                'manage_options',
                $slug,
                [ $this, 'redirect_legacy_page' ]
            );
        }
    }

    /**
     * Eski submenu slug'ından içeriğin taşındığı yeni sayfaya yönlendirir.
     */
    public function redirect_legacy_page() {
        $slug = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
        $map  = $this->get_legacy_page_map();

        // Eski "tab" parametresi de dikkate alınır: rma_settings&tab=rma_csv_import
        // gibi adresler doğrudan ilgili bölüme gider.
        $tab = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );
        if ( isset( $map[ $tab ] ) ) {
            $slug = $tab;
        }

        $target = $map[ $slug ] ?? $map['rma_settings'];

        // Faz 9: hangi eski slug'ın hâlâ trafik aldığını ölç. Yönlendirme
        // zaten 302 üretecek; ekstra maliyet 1 okuma + 1 yazma.
        if ( class_exists( 'QRMS_Helpers' ) && method_exists( 'QRMS_Helpers', 'legacy_slug_hit' ) ) {
            QRMS_Helpers::legacy_slug_hit( $slug );
        }

        wp_safe_redirect( $this->admin_page_url( $target[0], $target[2] ?? [], $target[1] ) );
        exit;
    }

    /* -----------------------------------------------------------------
       ORTAK SAYFA İSKELETİ
    ----------------------------------------------------------------- */

    /**
     * Sayfa başlığı + açıklama + (varsa) geri bağlantısı.
     *
     * @param string $title Sayfa başlığı.
     * @param string $intro Kısa açıklama.
     * @return void
     */
    private function page_header( $title, $intro = '' ) {
        echo '<div class="wrap rma-admin">';

        // Suite kuruluysa "geri" bağlantısını QRMS_Admin her alt sayfanın
        // önüne kendisi basar (register_module_subpage); burada basmak onu
        // ikilerdi. Suite yoksa (eski tekil eklenti) tek kaynak burasıdır.
        if ( ! class_exists( 'QRMS_Admin' ) ) {
            echo '<a class="rma-back-link" href="' . esc_url( $this->hub_url() ) . '">&larr; Restoran Menü</a>';
        }

        echo '<h1>' . esc_html( $title ) . '</h1>';
        if ( '' !== $intro ) {
            echo '<p class="rma-admin-intro">' . esc_html( $intro ) . '</p>';
        }
    }

    private function page_footer() {
        echo '</div>';
    }

    /**
     * Başlangıç sayfasının adresi (suite modül sayfası ya da eski Ayarlar
     * sayfası). page_header() geri bağlantısı için kullanılır.
     *
     * @return string
     */
    private function hub_url() {
        if ( class_exists( 'QRMS_Admin' ) ) {
            return admin_url( 'admin.php?page=' . QRMS_Admin::get_module_page_slug( 'restoran-menu' ) );
        }
        return admin_url( 'edit.php?post_type=rma_menu_item' );
    }

    /* -----------------------------------------------------------------
       1) BAŞLANGIÇ SAYFASI — "Restoran Menü"
    ----------------------------------------------------------------- */

    /**
     * "Restoran Menü" satırının açtığı hub ekranı: modülün her işini gösteren
     * kart ızgarası.
     *
     * Sol admin menüsü tek seviyeye indirildiği için (yalnızca modül adları)
     * alt ekranlara buradan dallanılır. Sunum tüm modüllerde ortak olan
     * QRMS_Admin::render_hub() bileşenindedir; burada yalnızca kart listesi
     * verilir.
     */
    public function render_admin_page() {
        // "Ürünüm Yok" katmanının merkezi sayacı — sol menü rozeti ve Genel
        // Bakış kartıyla AYNI kaynak (bkz. urunum-yok/class-stock.php).
        $uy_ozet = function_exists( 'qmo_urunum_yok_eksik_ozet' )
            ? qmo_urunum_yok_eksik_ozet()
            : [ 'toplam' => 0, 'malzemeler' => [], 'elle' => 0, 'elle_ids' => [] ];

        echo '<div class="rma-hub">';
        QRMS_Admin::render_hub( [
            'title'       => 'Restoran Menü',
            'intro'       => 'Menünüzle ilgili her iş burada. Ne yapmak istiyorsanız kartına dokunun.',
            // Modülün marka vurgusu (frontend menü temasıyla aynı altın).
            'accent'      => '#c9a84c',
            'stats'       => $this->get_hub_stats( $uy_ozet ),
            'notice'      => method_exists( $this, 'render_eksik_urun_notice' ) ? $this->render_eksik_urun_notice( $uy_ozet ) : '',
            'card_groups' => $this->get_hub_cards(),
        ] );
        echo '</div>';
    }

    /**
     * Hub üstündeki özet kartları: tükendi, okunmayan yorum, bugünkü görüntüleme.
     *
     * @param array{toplam:int,malzemeler:array,elle:int,elle_ids?:int[]} $uy_ozet Ürünüm Yok özeti.
     * @return array<int,array{label:string,value:string|int,url:string,accent:string}>
     */
    private function get_hub_stats( $uy_ozet ) {
        $unread = $this->hub_unread_comment_count();
        $views  = $this->hub_today_view_count();

        $unread_url = function_exists( 'qrm_pro_admin_url' )
            ? qrm_pro_admin_url( 'qrms-yf-formlar', array( 'tab' => 'submissions' ) )
            : admin_url( 'admin.php?page=qrms-yf-formlar&tab=submissions' );

        $analiz_url = class_exists( 'QRMS_Admin' )
            ? QRMS_Admin::get_module_page_url( 'qr-analiz' )
            : admin_url( 'admin.php?page=qrms-module-qr-analiz' );

        return [
            [
                'label'  => 'Eksik Ürün (Tükendi)',
                'value'  => $uy_ozet['toplam'],
                'url'    => $uy_ozet['toplam'] > 0 ? $this->admin_page_url( 'qrms-rm-urunum-yok' ) : '',
                'accent' => $uy_ozet['toplam'] > 0 ? '#e11d48' : '#10b981',
            ],
            [
                'label'  => 'Okunmayan Yorum',
                'value'  => sprintf( '%d okunmayan yorum', $unread ),
                'url'    => $unread_url,
                'accent' => $unread > 0 ? '#f59e0b' : '#10b981',
            ],
            [
                'label'  => 'Bugün Görüntülenme',
                'value'  => sprintf( '%d görüntülenme (bugün)', $views ),
                'url'    => $analiz_url,
                'accent' => '#c9a84c',
            ],
        ];
    }

    /**
     * Yorum & Feedback formlarındaki okunmamış gönderim sayısı.
     *
     * Modülün kendi qrm_cf_unread_total() sayacını kullanır (transient +
     * istek içi memo); yoksa 0 döner. Başka modüle yazılmaz.
     *
     * @return int
     */
    private function hub_unread_comment_count() {
        if ( function_exists( 'qrm_cf_unread_total' ) ) {
            return (int) qrm_cf_unread_total();
        }
        return 0;
    }

    /**
     * QR Analiz tablosundaki bugünkü menü görüntüleme sayısı.
     *
     * İstatistikler Genel Bakış ile AYNI kova: genel_bakis()['mv_bugun'].
     * Ayrı COUNT + transient ikinci bir sayı üretirdi; kullanıcı iki ekranda
     * farklı rakam görürdü.
     *
     * @return int
     */
    private function hub_today_view_count() {
        if ( ! class_exists( 'QRMS_Analitik' ) || ! method_exists( 'QRMS_Analitik', 'genel_bakis' ) ) {
            return 0;
        }

        if ( method_exists( 'QRMS_Analitik', 'tablo_var_mi' ) && ! QRMS_Analitik::tablo_var_mi() ) {
            return 0;
        }

        $bakis = QRMS_Analitik::genel_bakis();

        return isset( $bakis['mv_bugun'] ) ? (int) $bakis['mv_bugun'] : 0;
    }

    public function register_settings() {
        $args = [ 'sanitize_callback' => [ $this, 'sanitize_settings_array' ] ];
        register_setting( 'rma_color_options_group', 'rma_color_settings',      $args );
        register_setting( 'rma_color_options_group', 'rma_typo_settings',       $args );
        register_setting( 'rma_nav_design_group',    'rma_nav_design_settings', $args );
    }

    /* -----------------------------------------------------------------
       2) GÖRÜNÜM SAYFASI
       Eski "Genel Ayarlar" sekmesinin üç iç sekmesi (Renkler / Tipografi /
       Hazır Paletler) ve "Kayar Başlık" sekmesi burada birleşti. Hiçbir
       alan düşmedi; az kullanılanlar açılır bölümlere alındı.
    ----------------------------------------------------------------- */

    /**
     * Menünün frontend'de kullandığı font seçenekleri.
     *
     * @return string[]
     */
    private function get_font_options() {
        return [
            'Plus Jakarta Sans',
            'Nunito',
            'Inter',
            'Nunito Sans',
            'Cormorant Garamond',
            'DM Sans',
            'Playfair Display',
            'Lato',
            'Montserrat',
            'Raleway',
            'Open Sans',
            'Roboto',
            'Merriweather',
            'Poppins',
            'Georgia',
            'serif',
            'sans-serif',
        ];
    }

    /**
     * Hazır tema paletleri. Anahtarlar renk ayarlarının anahtarlarıyla
     * birebir aynıdır — palete tıklayınca ilgili renk seçicilere yazılır.
     *
     * @return array
     */
    private function get_color_palettes() {
        return [
            [
                'name'   => 'Premium Altın',
                'sub'    => 'Koyu zemin, altın vurgu',
                'colors' => [ 'bg' => '#0a0a0a', 'card' => '#111111', 'text' => '#f5f0e8', 'border' => '#2a2a2a', 'accent' => '#c9a84c', 'desc' => '#888888', 'toolbar_bg' => '#0a0a0a', 'modal_bg' => '#0a0a0a', 'section_title' => '#f5f0e8' ],
            ],
            [
                'name'   => 'Klasik Turuncu',
                'sub'    => 'Beyaz zemin, turuncu vurgu',
                'colors' => [ 'bg' => '#ffffff', 'card' => '#ffffff', 'text' => '#333333', 'border' => '#eaeaea', 'accent' => '#ff6b00', 'desc' => '#777777', 'toolbar_bg' => '#f7f7f7', 'modal_bg' => '#ffffff', 'section_title' => '#333333' ],
            ],
            [
                'name'   => 'Koyu Tema',
                'sub'    => 'Siyah zemin, turuncu vurgu',
                'colors' => [ 'bg' => '#121212', 'card' => '#1e1e1e', 'text' => '#ffffff', 'border' => '#2a2a2a', 'accent' => '#ff8533', 'desc' => '#aaaaaa', 'toolbar_bg' => '#121212', 'modal_bg' => '#121212', 'section_title' => '#ffffff' ],
            ],
            [
                'name'   => 'Modern Mavi',
                'sub'    => 'Açık zemin, mavi vurgu',
                'colors' => [ 'bg' => '#f4f7f6', 'card' => '#ffffff', 'text' => '#2c3e50', 'border' => '#dcdde1', 'accent' => '#0984e3', 'desc' => '#7f8c8d', 'toolbar_bg' => '#f4f7f6', 'modal_bg' => '#ffffff', 'section_title' => '#2c3e50' ],
            ],
            [
                'name'   => 'Doğal Yeşil',
                'sub'    => 'Krem zemin, yeşil vurgu',
                'colors' => [ 'bg' => '#faf9f5', 'card' => '#ffffff', 'text' => '#3e3e3e', 'border' => '#e0e0e0', 'accent' => '#27ae60', 'desc' => '#8e8e8e', 'toolbar_bg' => '#faf9f5', 'modal_bg' => '#ffffff', 'section_title' => '#3e3e3e' ],
            ],
        ];
    }

    /**
     * Renk anahtarı → canlı önizleme CSS değişkeni.
     *
     * @return array<string,string>
     */
    private function color_preview_vars() {
        return [
            'accent'            => '--qrms-accent',
            'bg'                => '--qrms-bg',
            'card'              => '--qrms-card',
            'text'              => '--qrms-text',
            'section_title'     => '--qrms-section-title',
            'desc'              => '--qrms-desc',
            'border'            => '--qrms-border',
            'toolbar_bg'        => '--qrms-toolbar-bg',
            'filter_btn_border' => '--qrms-filter-btn-border',
            'filter_btn_text'   => '--qrms-filter-btn-text',
            'modal_bg'          => '--qrms-modal-bg',
        ];
    }

    /**
     * Renk formunun üstündeki minyatür menü önizlemesi.
     *
     * İlk boya kayıtlı renklerle yapılır; sonraki değişiklikler
     * admin-ui.js içindeki syncColorPreview() ile aynı --qrms-*
     * değişkenlerine yazılır.
     *
     * @return void
     */
    private function render_color_preview() {
        $c     = $this->get_color_settings();
        $style = '';
        foreach ( $this->color_preview_vars() as $key => $prop ) {
            if ( ! empty( $c[ $key ] ) ) {
                $style .= $prop . ':' . $c[ $key ] . ';';
            }
        }
        ?>
        <div class="rma-color-preview" style="<?php echo esc_attr( $style ); ?>">
            <p class="rma-color-preview-label"><?php esc_html_e( 'Canlı önizleme', 'qrms' ); ?></p>
            <div class="rma-color-preview-stage" data-qrms-swatch="bg">
                <div class="rma-color-preview-toolbar" data-qrms-swatch="toolbar_bg">
                    <span class="rma-color-preview-search" data-qrms-swatch="border">
                        <span class="rma-color-preview-search-ph"><?php esc_html_e( 'Ürün ara…', 'qrms' ); ?></span>
                    </span>
                    <span class="rma-color-preview-filter" data-qrms-swatch="filter_btn_border filter_btn_text">
                        <?php esc_html_e( 'Filtrele', 'qrms' ); ?>
                    </span>
                </div>
                <div class="rma-color-preview-heading" data-qrms-swatch="section_title"><?php esc_html_e( 'Çorbalar', 'qrms' ); ?></div>
                <div class="rma-color-preview-card" data-qrms-swatch="card border">
                    <div class="rma-color-preview-name" data-qrms-swatch="text"><?php esc_html_e( 'Mercimek Çorbası', 'qrms' ); ?></div>
                    <div class="rma-color-preview-desc" data-qrms-swatch="desc"><?php esc_html_e( 'Limonla servis edilir', 'qrms' ); ?></div>
                    <div class="rma-color-preview-price" data-qrms-swatch="accent">₺95</div>
                </div>
                <div class="rma-color-preview-modal" data-qrms-swatch="modal_bg"><?php esc_html_e( 'Ürün penceresi', 'qrms' ); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Tek bir renk seçici satırı.
     *
     * @param string $key   Ayar anahtarı (rma_color_settings[$key]).
     * @param string $label Görünen etiket.
     * @param string $desc  Açıklama.
     * @return void
     */
    private function render_color_row( $key, $label, $desc ) {
        $c     = $this->get_color_settings();
        $def_c = $this->get_color_defaults();
        ?>
        <tr>
            <th><label for="rma_c_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="text"
                       id="rma_c_<?php echo esc_attr( $key ); ?>"
                       name="rma_color_settings[<?php echo esc_attr( $key ); ?>]"
                       value="<?php echo esc_attr( $c[ $key ] ?? '#000000' ); ?>"
                       class="rma-color-picker"
                       data-default-color="<?php echo esc_attr( $def_c[ $key ] ?? '#000000' ); ?>">
                <p class="description rma-desc"><?php echo esc_html( $desc ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Tek bir tipografi alanı satırı.
     *
     * @param array $field Alan tanımı.
     * @return void
     */
    private function render_typo_row( array $field ) {
        $t     = $this->get_typo_settings();
        $def_t = $this->get_typo_defaults();
        $name  = $field['name'];
        $val   = $t[ $name ] ?? '';
        ?>
        <tr>
            <th><label for="rma_t_<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
            <td>
                <?php if ( 'font' === $field['type'] ) : ?>
                    <select id="rma_t_<?php echo esc_attr( $name ); ?>" name="rma_typo_settings[<?php echo esc_attr( $name ); ?>]" class="rma-select-wide">
                        <?php foreach ( $this->get_font_options() as $fo ) : ?>
                            <option value="<?php echo esc_attr( $fo ); ?>" <?php selected( $val, $fo ); ?>><?php echo esc_html( $fo ); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ( 'color' === $field['type'] ) : ?>
                    <input type="text"
                           id="rma_t_<?php echo esc_attr( $name ); ?>"
                           name="rma_typo_settings[<?php echo esc_attr( $name ); ?>]"
                           value="<?php echo esc_attr( $val ); ?>"
                           class="rma-color-picker"
                           data-default-color="<?php echo esc_attr( $def_t[ $name ] ?? '#ffffff' ); ?>">
                <?php elseif ( 'select' === $field['type'] ) : ?>
                    <select id="rma_t_<?php echo esc_attr( $name ); ?>" name="rma_typo_settings[<?php echo esc_attr( $name ); ?>]" class="rma-select-narrow">
                        <?php foreach ( $field['options'] as $opt_val => $opt_label ) : ?>
                            <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $val, (string) $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ( 'range' === $field['type'] ) : ?>
                    <div class="rma-range-row">
                        <input type="range"
                               id="rma_t_<?php echo esc_attr( $name ); ?>"
                               name="rma_typo_settings[<?php echo esc_attr( $name ); ?>]"
                               value="<?php echo esc_attr( $val ); ?>"
                               min="<?php echo esc_attr( $field['min'] ); ?>"
                               max="<?php echo esc_attr( $field['max'] ); ?>"
                               step="<?php echo esc_attr( $field['step'] ); ?>"
                               oninput="this.nextElementSibling.textContent=this.value+'rem'">
                        <span class="rma-range-val"><?php echo esc_html( $val ); ?>rem</span>
                    </div>
                <?php endif; ?>
                <?php if ( ! empty( $field['desc'] ) ) : ?>
                    <p class="description rma-desc"><?php echo esc_html( $field['desc'] ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Görünüm sayfası: hazır temalar, renkler, yazı tipleri ve kategori
     * çubuğu tasarımı.
     */
    public function render_appearance_page() {
        $this->page_header(
            'Menü Görünümü',
            'Önce hazır bir tema seçin, isterseniz altındaki bölümlerden tek tek değiştirin. Değişiklikler kaydettikten sonra menünüze yansır.'
        );
        ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'rma_color_options_group' ); ?>

            <div class="rma-card">
                <h2 class="rma-card-title">1. Hazır Tema Seç</h2>
                <p class="rma-card-desc">Bir temaya dokunun — renkler otomatik dolar. Sonra sayfanın altındaki <strong>Görünümü Kaydet</strong> butonuna basmayı unutmayın.</p>

                <div class="rma-theme-grid">
                    <?php
                    $current_colors = $this->get_color_settings();
                    foreach ( $this->get_color_palettes() as $pal ) :
                        // Kayıtlı renkler bir paletle birebir aynıysa o kart
                        // "seçili" görünür — kullanıcı hangi temada olduğunu
                        // görebilsin diye. Palet adı ayrıca saklanmaz.
                        $is_current = true;
                        foreach ( $pal['colors'] as $key => $value ) {
                            if ( strtolower( (string) ( $current_colors[ $key ] ?? '' ) ) !== strtolower( $value ) ) {
                                $is_current = false;
                                break;
                            }
                        }
                        ?>
                        <button type="button" class="rma-theme-card<?php echo $is_current ? ' is-selected' : ''; ?>" data-palette='<?php echo esc_attr( wp_json_encode( $pal['colors'] ) ); ?>'>
                            <span class="rma-theme-preview" style="background:<?php echo esc_attr( $pal['colors']['bg'] ); ?>;">
                                <span class="rma-theme-chip" style="background:<?php echo esc_attr( $pal['colors']['card'] ); ?>;border-color:<?php echo esc_attr( $pal['colors']['border'] ); ?>;">
                                    <span class="rma-theme-line" style="background:<?php echo esc_attr( $pal['colors']['text'] ); ?>;"></span>
                                    <span class="rma-theme-line rma-theme-line-sm" style="background:<?php echo esc_attr( $pal['colors']['desc'] ); ?>;"></span>
                                    <span class="rma-theme-price" style="color:<?php echo esc_attr( $pal['colors']['accent'] ); ?>;">₺</span>
                                </span>
                            </span>
                            <span class="rma-theme-name"><?php echo esc_html( $pal['name'] ); ?></span>
                            <span class="rma-theme-sub"><?php echo esc_html( $pal['sub'] ); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php $this->render_color_preview(); ?>

            <div class="rma-card">
                <h2 class="rma-card-title">2. Renkleri Özelleştir</h2>
                <p class="rma-card-desc">En çok fark yaratan dört renk burada. Renk kutusuna dokunup istediğiniz tonu seçin.</p>

                <table class="form-table rma-form-table">
                    <?php
                    $primary_colors = [
                        'accent' => [ 'Vurgu Rengi', 'Fiyatlar, seçili kategori ve butonlar bu renkte görünür.' ],
                        'bg'     => [ 'Menü Arka Planı', 'Menünün genel arka plan rengi.' ],
                        'card'   => [ 'Ürün Kutusu Arka Planı', 'Her ürünün durduğu kutunun rengi.' ],
                        'text'   => [ 'Ana Yazı Rengi', 'Ürün adları ve genel yazılar.' ],
                    ];
                    foreach ( $primary_colors as $key => $row ) {
                        $this->render_color_row( $key, $row[0], $row[1] );
                    }
                    ?>
                </table>

                <details class="rma-details">
                    <summary>Diğer renk ayarları</summary>
                    <table class="form-table rma-form-table">
                        <?php
                        $other_colors = [
                            'section_title'     => [ 'Kategori Başlığı Rengi', 'Menüdeki "Çorbalar", "Tatlılar" gibi başlıkların rengi.' ],
                            'desc'              => [ 'Açıklama Yazısı Rengi', 'Ürün kutusundaki kısa açıklamalar.' ],
                            'border'            => [ 'Çerçeve Rengi', 'Kutu çerçeveleri ve ayırıcı çizgiler.' ],
                            'toolbar_bg'        => [ 'Arama Şeridi Arka Planı', 'Arama ve filtre butonlarının bulunduğu şerit.' ],
                            'filter_btn_border' => [ 'Filtre Butonu Çerçevesi', '"Filtrele" ve "Sıfırla" butonlarının çerçeve rengi.' ],
                            'filter_btn_text'   => [ 'Filtre Butonu Yazısı', '"Filtrele" ve "Sıfırla" butonlarının yazı rengi.' ],
                            'modal_bg'          => [ 'Ürün Penceresi Arka Planı', 'Bir ürüne tıklanınca açılan detay penceresi.' ],
                        ];
                        foreach ( $other_colors as $key => $row ) {
                            $this->render_color_row( $key, $row[0], $row[1] );
                        }
                        ?>
                    </table>
                </details>
            </div>

            <div class="rma-card">
                <h2 class="rma-card-title">3. Yazı Tipleri</h2>
                <p class="rma-card-desc">Menüde kullanılacak yazı tiplerini seçin.</p>

                <table class="form-table rma-form-table">
                    <?php
                    $primary_typo = [
                        [ 'label' => 'Kategori Başlıkları', 'name' => 'heading_font', 'type' => 'font', 'desc' => 'Menüdeki bölüm başlıklarının yazı tipi.' ],
                        [ 'label' => 'Ürün Adları',         'name' => 'body_font',    'type' => 'font', 'desc' => 'Ürün isimlerinin yazı tipi.' ],
                        [ 'label' => 'Fiyatlar',            'name' => 'price_font',   'type' => 'font', 'desc' => 'Fiyat yazılarının yazı tipi.' ],
                    ];
                    foreach ( $primary_typo as $field ) {
                        $this->render_typo_row( $field );
                    }
                    ?>
                </table>

                <details class="rma-details">
                    <summary>Yazı boyutu, kalınlık ve renk ayarları</summary>
                    <?php
                    $weights = [ '300' => '300 — İnce', '400' => '400 — Normal', '500' => '500 — Orta', '600' => '600 — Yarı kalın', '700' => '700 — Kalın', '800' => '800 — Çok kalın' ];

                    $typo_groups = [
                        'Kategori Başlıkları' => [
                            [ 'label' => 'Boyut',    'name' => 'heading_size',   'type' => 'range',  'min' => '0.8', 'max' => '3', 'step' => '0.05', 'desc' => 'Varsayılan: 1.42' ],
                            [ 'label' => 'Kalınlık', 'name' => 'heading_weight', 'type' => 'select', 'options' => $weights, 'desc' => '' ],
                            [ 'label' => 'Stil',     'name' => 'heading_style',  'type' => 'select', 'options' => [ 'normal' => 'Normal', 'italic' => 'İtalik', 'oblique' => 'Eğik' ], 'desc' => '' ],
                            [ 'label' => 'Renk',     'name' => 'heading_color',  'type' => 'color',  'desc' => 'Kategori başlıklarının yazı rengi.' ],
                        ],
                        'Ürün Adları' => [
                            [ 'label' => 'Boyut',    'name' => 'body_size',   'type' => 'range',  'min' => '0.7', 'max' => '1.6', 'step' => '0.01', 'desc' => 'Varsayılan: 0.93' ],
                            [ 'label' => 'Kalınlık', 'name' => 'body_weight', 'type' => 'select', 'options' => array_slice( $weights, 0, 5, true ), 'desc' => '' ],
                            [ 'label' => 'Renk',     'name' => 'body_color',  'type' => 'color',  'desc' => 'Ürün adlarının yazı rengi.' ],
                        ],
                        'Ürün Açıklamaları' => [
                            [ 'label' => 'Boyut', 'name' => 'desc_size',  'type' => 'range', 'min' => '0.6', 'max' => '1.2', 'step' => '0.01', 'desc' => 'Varsayılan: 0.80' ],
                            [ 'label' => 'Renk',  'name' => 'desc_color', 'type' => 'color', 'desc' => 'Kısa açıklamaların yazı rengi.' ],
                        ],
                        'Fiyatlar' => [
                            [ 'label' => 'Boyut', 'name' => 'price_size',  'type' => 'range', 'min' => '0.8', 'max' => '2', 'step' => '0.01', 'desc' => 'Varsayılan: 1.02' ],
                            [ 'label' => 'Renk',  'name' => 'price_color', 'type' => 'color', 'desc' => 'Fiyat yazılarının rengi.' ],
                        ],
                        'Ürün Detay Penceresi' => [
                            [ 'label' => 'Başlık Boyutu', 'name' => 'modal_title_size', 'type' => 'range', 'min' => '1', 'max' => '3', 'step' => '0.05', 'desc' => 'Varsayılan: 1.55' ],
                        ],
                    ];

                    foreach ( $typo_groups as $group_label => $fields ) : ?>
                        <div class="rma-section">
                            <h3 class="rma-section-title"><?php echo esc_html( $group_label ); ?></h3>
                            <table class="form-table rma-form-table">
                                <?php foreach ( $fields as $field ) {
                                    $this->render_typo_row( $field );
                                } ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </details>
            </div>

            <p class="rma-sticky-save">
                <?php submit_button( 'Görünümü Kaydet', 'primary', 'rma_submit_settings', false ); ?>
            </p>
        </form>

        <?php $this->render_nav_design_page(); ?>

        <?php
        $this->page_footer();
    }

    /* -----------------------------------------------------------------
       3) ÖNE ÇIKANLAR SAYFASI
       Eski "Öneriler" sekmesi + menüden erişilemeyen "Öne Çıkan Slider"
       ekranı burada toplandı.
    ----------------------------------------------------------------- */

    public function render_featured_page() {
        $this->page_header(
            'Öne Çıkanlar',
            'Menünün üst bölümünde müşterilerinize hangi ürünleri göstereceğinizi buradan belirlersiniz.'
        );

        $this->render_suggestions_page();
        $this->render_slider_section();
        $this->render_slider_appearance_form();

        $this->page_footer();
    }

    /**
     * "Öne Çıkan Slider" bölümü — slide listesi ve düzenleme bağlantıları.
     *
     * Slider ekranı `qmo_slide` içerik türünde durur; menü kaydı üst menü
     * olmayan bir slug'a bağlı olduğu için sol menüde hiç görünmüyordu.
     * Bu bölüm ona erişimi geri kazandırır.
     */
    private function render_slider_section() {
        if ( ! post_type_exists( 'qmo_slide' ) ) {
            return;
        }

        $slides = class_exists( 'QMO_Slide_CPT' ) ? QMO_Slide_CPT::get_published_slides() : [];
        ?>
        <div class="rma-card" id="rma-slider">
            <h2 class="rma-card-title">Öne Çıkan Slider</h2>
            <p class="rma-card-desc">Menünün en üstünde kayan görsel şeritte gösterilecek ürün grupları. Her grupta en fazla 4 ürün gösterilir.</p>

            <?php if ( empty( $slides ) ) : ?>
                <p class="rma-empty">Henüz slider grubu eklenmemiş.</p>
            <?php else : ?>
                <ul class="rma-simple-list">
                    <?php foreach ( $slides as $slide ) :
                        $urunler = [];
                        for ( $i = 1; $i <= 4; $i++ ) {
                            $pid = (int) get_post_meta( $slide->ID, '_qmo_slide_urun_' . $i, true );
                            if ( $pid ) {
                                $urunler[] = get_the_title( $pid );
                            }
                        }
                        ?>
                        <?php $edit_link = get_edit_post_link( $slide->ID ); ?>
                        <li class="rma-simple-item">
                            <span class="rma-simple-main">
                                <?php if ( $edit_link ) : ?>
                                    <a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $slide->post_title ?: 'Başlıksız grup' ); ?></a>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $slide->post_title ?: 'Başlıksız grup' ); ?></strong>
                                <?php endif; ?>
                                <span class="rma-simple-sub"><?php echo esc_html( $urunler ? implode( ', ', $urunler ) : 'Ürün seçilmemiş' ); ?></span>
                            </span>
                            <span class="rma-simple-meta">Sıra: <?php echo (int) $slide->menu_order; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="rma-actions">
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=qmo_slide' ) ); ?>">Yeni Slider Grubu Ekle</a>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=qmo_slide' ) ); ?>">Tüm Grupları Yönet</a>
                <a class="button" href="#qmo-slider-gorunum">Görünümü Ayarla</a>
            </p>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------
       4) DİĞER AYARLAR SAYFASI
       Eski "Kategori Sıralaması", "İçe/Dışa Aktar" ve "Yedekleme"
       sekmeleri burada üç bölüm hâlinde alt alta duruyor.
    ----------------------------------------------------------------- */

    public function render_other_settings_page() {
        $this->page_header(
            'Diğer Ayarlar',
            'Sık kullanılmayan işlemler burada. İhtiyacınız olan bölüme aşağıdan geçebilirsiniz.'
        );
        ?>
        <nav class="rma-anchor-nav" aria-label="Sayfa bölümleri">
            <a href="#rma-sepet-siparis">Sepet ile Sipariş</a>
            <a href="#rma-kategori-sirasi">Kategori Sırası</a>
            <a href="#rma-ice-disa-aktar">Toplu Ürün Aktarımı</a>
            <a href="#rma-malzeme-aktar">Malzeme Aktarımı</a>
            <a href="#rma-yedekleme">Yedekleme</a>
        </nav>
        <?php

        $this->render_sepet_siparis_section();
        $this->render_category_order_page();
        $this->render_csv_import_page();
        if ( method_exists( $this, 'render_ingredient_csv_section' ) ) {
            $this->render_ingredient_csv_section();
        }
        $this->render_menu_backup_page();

        $this->page_footer();
    }

    /**
     * `admin_post_qmo_sepet_ayar_kaydet` — sepet anahtarını kaydeder.
     *
     * @return void
     */
    public function handle_sepet_ayar_save() {
        check_admin_referer( 'qmo_sepet_ayar_kaydet' );

        $yetki = class_exists( 'QRMS_Admin' ) ? QRMS_Admin::CAPABILITY : 'manage_options';

        if ( ! current_user_can( $yetki ) ) {
            wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qrms' ), '', array( 'response' => 403 ) );
        }

        // Gizli 0 + checkbox 1: işaretliyse son değer 1, değilse 0 gelir.
        $aktif = ( isset( $_POST['qmo_sepet_aktif'] ) && (int) $_POST['qmo_sepet_aktif'] === 1 ) ? 1 : 0;
        update_option( 'qmo_sepet_aktif', $aktif );

        wp_safe_redirect(
            $this->admin_page_url( 'qrms-rm-diger', array( 'sepet_msg' => 'kaydedildi' ), 'rma-sepet-siparis' )
        );
        exit;
    }

    /**
     * Sepet ile sipariş anahtarı — Diğer Ayarlar sayfasının ilk bölümü.
     *
     * @return void
     */
    private function render_sepet_siparis_section() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $durum = isset( $_GET['sepet_msg'] ) ? sanitize_key( wp_unslash( $_GET['sepet_msg'] ) ) : '';
        $aktif = (int) get_option( 'qmo_sepet_aktif', 0 );

        if ( 'kaydedildi' === $durum ) {
            echo '<div class="notice notice-success is-dismissible"><p>Sepet ile sipariş ayarı kaydedildi.</p></div>';
        }
        ?>
        <div class="rma-card" id="rma-sepet-siparis">
            <h2 class="rma-card-title">Sepet ile Sipariş</h2>
            <p class="rma-card-desc">Masadan QR ile giren müşterilerin menüden direkt sipariş verebilmesini sağlar. Kapalıyken sepet menünün altında hiç gösterilmez. Varsayılan: kapalı.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'qmo_sepet_ayar_kaydet' ); ?>
                <input type="hidden" name="action" value="qmo_sepet_ayar_kaydet">
                <input type="hidden" name="qmo_sepet_aktif" value="0">

                <label class="rma-check-row" for="qmo-sepet-aktif">
                    <span class="rma-switch">
                        <input type="checkbox" name="qmo_sepet_aktif" id="qmo-sepet-aktif" value="1" <?php checked( 1, $aktif ); ?>>
                        <span class="rma-slider"></span>
                    </span>
                    <span>Sepet ile siparişi etkinleştir</span>
                </label>
                <p class="description rma-desc">Açıkken <code>[restaurant_menu]</code> çıktısının altına sepet otomatik eklenir. Elle <code>[qmo_sepet]</code> kısa kodu hâlâ herhangi bir sayfaya yazılabilir; aynı sayfada iki kez basılmaz.</p>

                <p class="rma-actions">
                    <?php submit_button( 'Kaydet', 'primary', 'submit', false ); ?>
                </p>
            </form>
        </div>
        <?php
    }

    public function render_category_order_page() {
        $terms = get_terms( [ 'taxonomy' => 'rma_category', 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) ) $terms = [];

        // PERF: sıra değerleri karşılaştırma fonksiyonunun içinde değil,
        // terim başına bir kez okunur (usort n·log n kez çağırır).
        $term_order = [];
        foreach ( $terms as $t ) {
            $term_order[ $t->term_id ] = (int) get_term_meta( $t->term_id, 'rma_cat_order', true );
        }
        usort( $terms, function ( $a, $b ) use ( $term_order ) {
            return $term_order[ $a->term_id ] <=> $term_order[ $b->term_id ];
        } ); ?>
        <div class="rma-card" id="rma-kategori-sirasi">
            <h2 class="rma-card-title">Kategori Sırası</h2>
            <p class="rma-card-desc">Kategorilerin menüde hangi sırayla görüneceğini belirleyin. Sürükleyip bırakın — değişiklik anında kaydedilir.</p>
            <div id="rma-category-sorter-msg" class="rma-toast">Kaydedildi!</div>
            <ul id="rma-category-list" class="rma-sortable">
                <?php if ( empty( $terms ) ) {
                    echo '<li class="rma-empty">Henüz kategori yok.</li>';
                } else {
                    foreach ( $terms as $t ) {
                        echo "<li data-id='" . (int) $t->term_id . "' class='rma-sortable-item'>
                                <span class='dashicons dashicons-menu' aria-hidden='true'></span>" . esc_html( $t->name ) . "
                              </li>";
                    }
                } ?>
            </ul>
        </div>
    <?php }

    public function admin_scripts( $hook ) {
        // Yalnızca eklentinin kendi ekranlarında yükle — diğer tüm admin
        // sayfalarına gereksiz script/stil enjeksiyonu engellenir.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== 'rma_menu_item' ) return;

        $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );

        // Modülün kendi ayar sayfaları (suite yokken ürün listesinin altında
        // kayıtlıdır). Renk seçici ve sürükle-bırak yalnızca bu ekranlarda
        // gerekir.
        $is_settings = array_key_exists( $page, $this->get_subpages() );

        // PERF: admin-ui.js/css yalnızca gerçekten kullanıldığı ekranlarda
        // gerekir — ayar sayfaları ve ürün listesi (Göster/Gizle anahtarı).
        // Ürün ekleme/düzenleme ve taksonomi ekranlarında hiçbir işlevi yok,
        // oralarda artık yüklenmiyor.
        $is_list = ( 'edit' === $screen->base );

        // Porsiyon/ekstra/servis saati arayüzü ürün DÜZENLEME ekranında ve
        // kategori formunda da gerekir; oralarda admin-ui.js yüklenmez.
        if ( in_array( $screen->base, [ 'post', 'edit-tags', 'term' ], true ) || 'qrms-rm-secenekler' === $page ) {
            $this->enqueue_secenek_assets( RMA_PLUGIN_URL, [ $this, 'asset_version' ] );
        }

        if ( ! $is_settings && ! $is_list ) return;

        $deps = [ 'jquery' ];

        if ( $is_settings ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            $deps[] = 'wp-color-picker';
            $deps[] = 'jquery-ui-sortable';

            wp_enqueue_style(
                'rma-admin-ui',
                RMA_PLUGIN_URL . 'assets/css/admin-ui.css',
                [],
                $this->asset_version( 'assets/css/admin-ui.css' )
            );
        } elseif ( $is_list ) {
            // Native WP liste ekranında yalnızca Göster/Gizle anahtarı stili
            // gerekir; tam admin-ui.css burada kasıtlı olarak yüklenmez.
            wp_enqueue_style(
                'rma-admin-list',
                RMA_PLUGIN_URL . 'assets/css/rma-admin-list.css',
                [],
                $this->asset_version( 'assets/css/rma-admin-list.css' )
            );

            // Hızlı Düzenle görsel seçici (wp.media) + satır açma kancası.
            wp_enqueue_media();
            $deps[] = 'inline-edit-post';
        }

        wp_enqueue_script(
            'rma-admin-ui',
            RMA_PLUGIN_URL . 'assets/js/admin-ui.js',
            $deps,
            $this->asset_version( 'assets/js/admin-ui.js' ),
            true
        );

        // Görünüm sayfasındaki canlı önizleme, frontend'in gerçek nav
        // stylesheet'ini kullanır — ayrı bir taklit değil.
        if ( 'qrms-rm-gorunum' === $page ) {
            wp_enqueue_style(
                'rma-nav',
                RMA_PLUGIN_URL . 'assets/css/rma-nav.css',
                [ 'rma-admin-ui' ],
                $this->asset_version( 'assets/css/rma-nav.css' )
            );

            // Aktif gösterge CSS'inin dört varyantı da frontend ile aynı
            // kaynaktan (get_nav_indicator_css) gelir; her biri önizleme
            // kabına hapsedilir ki admin sayfasının kalanına sızmasın.
            wp_add_inline_style( 'rma-nav', $this->get_nav_preview_css() );
        }

        // Kampanya Banner sayfasının canlı önizlemesi ön yüzün GERÇEK
        // stylesheet'ini kullanır; 3. adımdaki görsel üretme aracı ise kendi
        // bağımsız betiğiyle gelir. (Suite kuruluysa aynı varlıklar
        // module.php'den kuyruğa girer.)
        if ( 'qrms-rm-kampanya-banner' === $page ) {
            wp_enqueue_style(
                'qmo-banner-slider',
                RMA_PLUGIN_URL . 'includes/frontend-banner-slider.css',
                [ 'rma-admin-ui' ],
                $this->asset_version( 'includes/frontend-banner-slider.css' )
            );
            wp_enqueue_style(
                'qmo-slider-fonts',
                'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&display=swap',
                [],
                null
            );
            wp_enqueue_script(
                'qmo-banner-olustur',
                RMA_PLUGIN_URL . 'assets/js/banner-olustur.js',
                [],
                $this->asset_version( 'assets/js/banner-olustur.js' ),
                true
            );
        }

        $nonce = wp_create_nonce( 'rma_admin_nonce' );

        wp_add_inline_script(
            'rma-admin-ui',
            'var RMA_ADMIN = ' . wp_json_encode( [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => $nonce,
            ] ) . ';',
            'before'
        );
    }

    /**
     * Canlı önizlemedeki aktif gösterge CSS'i — dört varyant da frontend ile
     * aynı kaynaktan gelir, her biri önizleme kabına hapsedilir.
     *
     * Suite'in kendi varlık kuyruğu (module.php) da bu metodu kullanır;
     * kural üretimi tek yerde durur.
     *
     * @return string
     */
    public function get_nav_preview_css() {
        $css = '';
        foreach ( [ 'background', 'dot', 'none', 'bottom_line' ] as $variant ) {
            $css .= str_replace(
                '.rma-nav-btn.active',
                '.rma-nav-preview[data-rma-ind="' . $variant . '"] .rma-nav-btn.active',
                $this->get_nav_indicator_css( $variant )
            );
        }
        return $css;
    }

}

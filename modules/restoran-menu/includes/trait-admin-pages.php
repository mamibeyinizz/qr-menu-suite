<?php

if ( ! defined( 'ABSPATH' ) ) exit;

trait RMA_Admin_Pages_Trait {

    /**
     * Ayarlar sayfasının sekme tanımı — tek kaynak.
     * Anahtarlar eski submenu slug'larıdır; sekme id'si olarak korunur
     * (eski linkler ve import redirect'leri bu id'lerle çalışmaya devam eder).
     */
    private function get_admin_tabs() {
        return [
            'rma_color_settings'       => [ 'label' => 'Genel Ayarlar',        'render' => 'render_settings_page' ],
            'rma_nav_design'           => [ 'label' => 'Kayar Başlık',         'render' => 'render_nav_design_page' ],
            'rma_category_order'       => [ 'label' => 'Kategori Sıralaması',  'render' => 'render_category_order_page' ],
            'rma_suggestions_settings' => [ 'label' => 'Öneriler',             'render' => 'render_suggestions_page' ],
            'rma_csv_import'           => [ 'label' => 'İçe/Dışa Aktar',       'render' => 'render_csv_import_page' ],
            'rma_menu_backup'          => [ 'label' => 'Yedekleme',            'render' => 'render_menu_backup_page' ],
        ];
    }

    public function add_admin_menus() {
        add_submenu_page(
            'edit.php?post_type=rma_menu_item',
            'QR Menü Ayarları',
            'Ayarlar',
            'manage_options',
            'rma_settings',
            [ $this, 'render_admin_page' ]
        );

        // Eski slug'lar gizli sayfa olarak kayıtlı kalır; yer imleri ve
        // dış linkler yeni sayfanın ilgili sekmesine yönlendirilir.
        // Üst menü olarak null yerine '' kullanılır: ikisi de aynı
        // (admin_page_<slug>) hook'unu üretir ama '' PHP 8.1+ üzerinde
        // plugin_basename() içindeki null deprecation uyarısını doğurmaz.
        foreach ( $this->get_admin_tabs() as $slug => $tab ) {
            add_submenu_page(
                '',
                $tab['label'],
                $tab['label'],
                'manage_options',
                $slug,
                [ $this, 'redirect_legacy_page' ]
            );
        }
    }

    /**
     * Eski submenu slug'ından yeni birleşik sayfaya yönlendirir.
     */
    public function redirect_legacy_page() {
        $slug = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
        $tabs = $this->get_admin_tabs();
        $tab  = isset( $tabs[ $slug ] ) ? $slug : 'rma_color_settings';

        wp_safe_redirect( admin_url( 'edit.php?post_type=rma_menu_item&page=rma_settings&tab=' . $tab ) );
        exit;
    }

    /**
     * Tek admin sayfasının ortak iskeleti: başlık + sekme çubuğu +
     * her sekmenin gövdesini basan panel döngüsü. Altı render_*_page()
     * metodu artık yalnızca kendi gövdesini üretir.
     */
    public function render_admin_page() {
        $tabs   = $this->get_admin_tabs();
        $active = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) );
        if ( ! isset( $tabs[ $active ] ) ) {
            $active = 'rma_color_settings';
        }
        ?>
        <div class="wrap rma-admin">
            <h1>QR Menü Ayarları</h1>
            <p class="rma-admin-intro">Menünün görünümü, sıralaması, önerileri ve yedekleme işlemleri tek yerden yönetilir.</p>

            <div class="rma-tabs" role="tablist">
                <?php foreach ( $tabs as $slug => $tab ) : ?>
                    <button type="button"
                            class="rma-tab<?php echo $slug === $active ? ' is-active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $slug === $active ? 'true' : 'false'; ?>"
                            aria-controls="rma-panel-<?php echo esc_attr( $slug ); ?>"
                            data-rma-tab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $tab['label'] ); ?></button>
                <?php endforeach; ?>
            </div>

            <?php foreach ( $tabs as $slug => $tab ) : ?>
                <div class="rma-tabpanel"
                     id="rma-panel-<?php echo esc_attr( $slug ); ?>"
                     role="tabpanel"
                     data-rma-panel="<?php echo esc_attr( $slug ); ?>"
                     <?php echo $slug === $active ? '' : 'hidden'; ?>>
                    <?php $this->{ $tab['render'] }(); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function register_settings() {
        $args = [ 'sanitize_callback' => [ $this, 'sanitize_settings_array' ] ];
        register_setting( 'rma_color_options_group', 'rma_color_settings',      $args );
        register_setting( 'rma_color_options_group', 'rma_typo_settings',       $args );
        register_setting( 'rma_nav_design_group',    'rma_nav_design_settings', $args );
    }

    public function render_settings_page() {
        $def_c = $this->get_color_defaults();
        $c     = $this->get_color_settings();
        $def_t = $this->get_typo_defaults();
        $t     = $this->get_typo_settings();

        $font_options = [
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
        ?>
        <form method="post" action="options.php">
                <?php settings_fields( 'rma_color_options_group' ); ?>

                <div>
                    <div class="rma-subtabs" role="tablist">
                        <button type="button" class="rma-subtab is-active" role="tab" data-rma-subtab="colors">Renkler</button>
                        <button type="button" class="rma-subtab"           role="tab" data-rma-subtab="typography">Tipografi</button>
                        <button type="button" class="rma-subtab"           role="tab" data-rma-subtab="palettes">Hazır Paletler</button>
                    </div>

                    <div class="rma-subtabpanel" role="tabpanel" data-rma-subpanel="colors">
                        <?php
                        $color_fields = [
                            'Genel Arka Plan & Yapı' => [
                                'bg'   => [ 'Ana Arka Plan', 'Menünün tüm arka planı.' ],
                                'card' => [ 'Kart Arka Planı', 'Her ürün kartının arka plan rengi.' ],
                                'border' => [ 'Kenarlık Rengi', 'Kart çerçeveleri ve genel borderlar.' ],
                            ],
                            'Metin Renkleri' => [
                                'text'          => [ 'Ana Metin Rengi', 'Ürün başlıkları ve genel metin.' ],
                                'desc'          => [ 'Açıklama Rengi', 'Kısa açıklama metinleri.' ],
                                'section_title' => [ 'Kategori Başlık Rengi', 'Menüdeki kategori başlıklarının rengi.' ],
                            ],
                            'Vurgu & Fiyat' => [
                                'accent' => [ 'Ana Vurgu Rengi ⭐', 'Fiyatlar, aktif nav butonu, hover efektleri.' ],
                            ],
                            'Araç Çubuğu' => [
    'toolbar_bg' => [ 'Toolbar Arka Plan', 'Arama ve filtre butonlarının bulunduğu şerit.' ],
    // BURAYA EKLE:
    'filter_btn_border' => [ 'Filtrele Butonu Kenarlık', 'Filtrele ve Sıfırla butonlarının kenarlık rengi.' ],
    'filter_btn_text'   => [ 'Filtrele Butonu Yazı', 'Filtrele ve Sıfırla butonlarının yazı rengi.' ],
],
                            'Modal' => [
                                'modal_bg' => [ 'Modal Arka Plan', 'Ürüne tıklandığında açılan detay penceresi.' ],
                            ],
                        ];
                        foreach ( $color_fields as $group_label => $fields ) : ?>
                            <div class="rma-section">
                                <h3 class="rma-section-title"><?php echo $group_label; ?></h3>
                                <table class="form-table rma-form-table">
                                    <?php foreach ( $fields as $key => [$label, $desc] ) : ?>
                                    <tr>
                                        <th><label for="rma_c_<?php echo $key; ?>"><?php echo $label; ?></label></th>
                                        <td>
                                            <input type="text" id="rma_c_<?php echo $key; ?>" name="rma_color_settings[<?php echo $key; ?>]" value="<?php echo esc_attr( $c[$key] ?? '#000000' ); ?>" class="rma-color-picker" data-default-color="<?php echo esc_attr( $def_c[$key] ?? '#000000' ); ?>"/>
                                            <p class="description rma-desc"><?php echo $desc; ?></p>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="rma-subtabpanel" role="tabpanel" data-rma-subpanel="typography" hidden>
                        <?php
                        $typo_groups = [
                            'Kategori Başlıkları' => [
                                'description' => 'Menüde her kategorinin üst başlığı.',
                                'fields' => [
                                    [ 'label' => 'Font Ailesi',  'name' => 'heading_font',   'type' => 'font',   'key' => 'heading_font',   'desc' => 'Kategori başlık fontu.' ],
                                    [ 'label' => 'Boyut (rem)',  'name' => 'heading_size',   'type' => 'range',  'min' => '0.8', 'max' => '3', 'step' => '0.05', 'key' => 'heading_size', 'desc' => 'Varsayılan: 1.42rem' ],
                                    [ 'label' => 'Kalınlık',    'name' => 'heading_weight', 'type' => 'select', 'options' => [ '300','400','500','600','700','800' ], 'key' => 'heading_weight', 'desc' => '400 = normal, 700 = bold.' ],
                                    [ 'label' => 'Stil',        'name' => 'heading_style',  'type' => 'select', 'options' => [ 'normal','italic','oblique' ], 'key' => 'heading_style', 'desc' => 'Normal mi, italik mi?' ],
                                    [ 'label' => 'Renk',        'name' => 'heading_color',  'type' => 'color',  'key' => 'heading_color',  'desc' => 'Kategori başlık yazı rengi.' ],
                                ],
                            ],
                            'Ürün Adları' => [
                                'description' => 'Her ürün kartındaki ürün adı.',
                                'fields' => [
                                    [ 'label' => 'Font Ailesi', 'name' => 'body_font',   'type' => 'font',  'key' => 'body_font',  'desc' => 'Ürün adı fontu.' ],
                                    [ 'label' => 'Boyut (rem)', 'name' => 'body_size',   'type' => 'range', 'min' => '0.7', 'max' => '1.6', 'step' => '0.01', 'key' => 'body_size', 'desc' => 'Varsayılan: 0.93rem' ],
                                    [ 'label' => 'Kalınlık',   'name' => 'body_weight', 'type' => 'select', 'options' => [ '300','400','500','600','700' ], 'key' => 'body_weight', 'desc' => 'Ürün adı kalınlığı.' ],
                                    [ 'label' => 'Renk',       'name' => 'body_color',  'type' => 'color', 'key' => 'body_color', 'desc' => 'Ürün adı yazı rengi.' ],
                                ],
                            ],
                            'Ürün Açıklamaları' => [
                                'description' => 'Karttaki kısa açıklama metni.',
                                'fields' => [
                                    [ 'label' => 'Boyut (rem)', 'name' => 'desc_size',  'type' => 'range', 'min' => '0.6', 'max' => '1.2', 'step' => '0.01', 'key' => 'desc_size', 'desc' => 'Varsayılan: 0.80rem' ],
                                    [ 'label' => 'Renk',        'name' => 'desc_color', 'type' => 'color', 'key' => 'desc_color', 'desc' => 'Açıklama yazı rengi.' ],
                                ],
                            ],
                            'Fiyat Yazısı' => [
                                'description' => 'Kart altındaki fiyat gösterimi.',
                                'fields' => [
                                    [ 'label' => 'Font Ailesi', 'name' => 'price_font',  'type' => 'font', 'key' => 'price_font', 'desc' => 'Fiyat fontu.' ],
                                    [ 'label' => 'Boyut (rem)', 'name' => 'price_size',  'type' => 'range', 'min' => '0.8', 'max' => '2', 'step' => '0.01', 'key' => 'price_size', 'desc' => 'Varsayılan: 1.02rem' ],
                                    [ 'label' => 'Renk',        'name' => 'price_color', 'type' => 'color', 'key' => 'price_color', 'desc' => 'Fiyat yazı rengi.' ],
                                ],
                            ],
                            'Modal Başlığı' => [
                                'description' => 'Ürün detay penceresindeki büyük başlık.',
                                'fields' => [
                                    [ 'label' => 'Boyut (rem)', 'name' => 'modal_title_size', 'type' => 'range', 'min' => '1', 'max' => '3', 'step' => '0.05', 'key' => 'modal_title_size', 'desc' => 'Varsayılan: 1.55rem' ],
                                ],
                            ],
                        ];
                        foreach ( $typo_groups as $group_label => $group_data ) :
                            $group_desc = $group_data['description'] ?? '';
                        ?>
                            <div class="rma-section">
                                <h3 class="rma-section-title"><?php echo $group_label; ?></h3>
                                <?php if ( $group_desc ) : ?><p class="rma-section-desc"><?php echo $group_desc; ?></p><?php endif; ?>
                                <table class="form-table rma-form-table">
                                    <?php foreach ( $group_data['fields'] as $field ) :
                                        $val = $t[ $field['key'] ] ?? '';
                                    ?>
                                    <tr>
                                        <th><label><?php echo $field['label']; ?></label></th>
                                        <td>
                                            <?php if ( $field['type'] === 'font' ) : ?>
                                                <select name="rma_typo_settings[<?php echo $field['name']; ?>]" class="rma-select-wide">
                                                    <?php foreach ( $font_options as $fo ) : ?>
                                                        <option value="<?php echo esc_attr( $fo ); ?>" <?php selected( $val, $fo ); ?>><?php echo $fo; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ( $field['type'] === 'color' ) : ?>
                                                <input type="text" name="rma_typo_settings[<?php echo $field['name']; ?>]" value="<?php echo esc_attr( $val ); ?>" class="rma-color-picker" data-default-color="<?php echo esc_attr( $def_t[ $field['key'] ] ?? '#ffffff' ); ?>">
                                            <?php elseif ( $field['type'] === 'select' ) : ?>
                                                <select name="rma_typo_settings[<?php echo $field['name']; ?>]" class="rma-select-narrow">
                                                    <?php foreach ( $field['options'] as $opt ) : ?>
                                                        <option value="<?php echo $opt; ?>" <?php selected( $val, $opt ); ?>><?php echo $opt; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ( $field['type'] === 'range' ) : ?>
                                                <div class="rma-range-row">
                                                    <input type="range" name="rma_typo_settings[<?php echo $field['name']; ?>]" value="<?php echo esc_attr( $val ); ?>" min="<?php echo $field['min']; ?>" max="<?php echo $field['max']; ?>" step="<?php echo $field['step']; ?>" oninput="this.nextElementSibling.textContent=this.value+'rem'">
                                                    <span class="rma-range-val"><?php echo $val; ?>rem</span>
                                                </div>
                                            <?php endif; ?>
                                            <p class="description rma-desc"><?php echo $field['desc']; ?></p>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="rma-subtabpanel" role="tabpanel" data-rma-subpanel="palettes" hidden>
                        <p class="rma-card-desc">Bir palete tıklayın, ardından <strong>Ayarları Kaydet</strong>.</p>
                        <div class="rma-palette-row">
                            <?php
                            $palettes = [
                                [ 'name' => '◆ Premium Altın', 'colors' => [ 'bg' => '#0a0a0a', 'card' => '#111111', 'text' => '#f5f0e8', 'border' => '#2a2a2a', 'accent' => '#c9a84c', 'desc' => '#888888', 'toolbar_bg' => '#0a0a0a', 'modal_bg' => '#0a0a0a', 'section_title' => '#f5f0e8' ] ],
                                [ 'name' => 'Klasik Turuncu',  'colors' => [ 'bg' => '#ffffff', 'card' => '#ffffff', 'text' => '#333333', 'border' => '#eaeaea', 'accent' => '#ff6b00', 'desc' => '#777777', 'toolbar_bg' => '#f7f7f7', 'modal_bg' => '#ffffff', 'section_title' => '#333333' ] ],
                                [ 'name' => 'Koyu Tema',       'colors' => [ 'bg' => '#121212', 'card' => '#1e1e1e', 'text' => '#ffffff', 'border' => '#2a2a2a', 'accent' => '#ff8533', 'desc' => '#aaaaaa', 'toolbar_bg' => '#121212', 'modal_bg' => '#121212', 'section_title' => '#ffffff' ] ],
                                [ 'name' => 'Modern Mavi',     'colors' => [ 'bg' => '#f4f7f6', 'card' => '#ffffff', 'text' => '#2c3e50', 'border' => '#dcdde1', 'accent' => '#0984e3', 'desc' => '#7f8c8d', 'toolbar_bg' => '#f4f7f6', 'modal_bg' => '#ffffff', 'section_title' => '#2c3e50' ] ],
                                [ 'name' => 'Doğal Yeşil',     'colors' => [ 'bg' => '#faf9f5', 'card' => '#ffffff', 'text' => '#3e3e3e', 'border' => '#e0e0e0', 'accent' => '#27ae60', 'desc' => '#8e8e8e', 'toolbar_bg' => '#faf9f5', 'modal_bg' => '#ffffff', 'section_title' => '#3e3e3e' ] ],
                            ];
                            foreach ( $palettes as $pal ) : ?>
                                <button type="button" class="button rma-palette-btn" data-palette='<?php echo json_encode( $pal['colors'] ); ?>'>
                                    <?php echo $pal['name']; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <p>
                    <?php submit_button( 'Ayarları Kaydet', 'primary', 'rma_submit_settings', false ); ?>
                </p>
            </form>
    <?php }

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
        <div class="rma-card">
            <h2 class="rma-card-title">Kategori Sıralaması</h2>
            <p class="rma-card-desc">Kategorileri sürükleyerek menüdeki sırasını değiştirin — değişiklik anında kaydedilir.</p>
            <div id="rma-category-sorter-msg" class="rma-toast">Kaydedildi!</div>
            <ul id="rma-category-list" class="rma-sortable">
                <?php if ( empty( $terms ) || is_wp_error( $terms ) ) {
                    echo '<li>Henüz kategori yok.</li>';
                } else {
                    foreach ( $terms as $t ) {
                        echo "<li data-id='{$t->term_id}' class='rma-sortable-item'>
                                <span class='dashicons dashicons-menu'></span>" . esc_html( $t->name ) . "
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

        // Tüm sekmeler tek sayfada render edildiği için sortable ve renk
        // seçici birlikte, yalnızca ayarlar sayfasında yüklenir.
        $is_settings = ( $page === 'rma_settings' );

        // PERF: admin-ui.js/css yalnızca gerçekten kullanıldığı iki ekranda
        // gerekir — Ayarlar sayfası ve ürün listesi (Göster/Gizle anahtarı).
        // Ürün ekleme/düzenleme ve taksonomi ekranlarında hiçbir işlevi yok,
        // oralarda artık yüklenmiyor.
        $is_list = ( 'edit' === $screen->base );
        if ( ! $is_settings && ! $is_list ) return;

        $deps = [ 'jquery' ];

        if ( $is_settings ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
            $deps[] = 'wp-color-picker';
            $deps[] = 'jquery-ui-sortable';
        }

        // Ortak admin stili ürün listesi ekranında da gerekir
        // (Göster/Gizle anahtarı orada kullanılıyor).
        wp_enqueue_style(
            'rma-admin-ui',
            RMA_PLUGIN_URL . 'assets/css/admin-ui.css',
            [],
            $this->asset_version( 'assets/css/admin-ui.css' )
        );

        wp_enqueue_script(
            'rma-admin-ui',
            RMA_PLUGIN_URL . 'assets/js/admin-ui.js',
            $deps,
            $this->asset_version( 'assets/js/admin-ui.js' ),
            true
        );

        // Kayar Başlık sekmesindeki canlı önizleme, frontend'in gerçek nav
        // stylesheet'ini kullanır — ayrı bir taklit değil.
        if ( $is_settings ) {
            wp_enqueue_style(
                'rma-nav',
                RMA_PLUGIN_URL . 'assets/css/rma-nav.css',
                [ 'rma-admin-ui' ],
                $this->asset_version( 'assets/css/rma-nav.css' )
            );

            // Aktif gösterge CSS'inin dört varyantı da frontend ile aynı
            // kaynaktan (get_nav_indicator_css) gelir; her biri önizleme
            // kabına hapsedilir ki admin sayfasının kalanına sızmasın.
            $indicator_css = '';
            foreach ( [ 'background', 'dot', 'none', 'bottom_line' ] as $variant ) {
                $indicator_css .= str_replace(
                    '.rma-nav-btn.active',
                    '.rma-nav-preview[data-rma-ind="' . $variant . '"] .rma-nav-btn.active',
                    $this->get_nav_indicator_css( $variant )
                );
            }
            wp_add_inline_style( 'rma-nav', $indicator_css );
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

}
